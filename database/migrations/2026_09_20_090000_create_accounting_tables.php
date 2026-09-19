<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry general ledger.
 *
 *  accounts          the chart of accounts (what a transaction can be posted to)
 *  journal_entries   one row per transaction: a date, a description and a status
 *  journal_lines     the debits and credits; for every entry, SUM(debit) = SUM(credit)
 *                    (enforced by App\Services\Accounting\Ledger — the only writer)
 *
 * Money is DECIMAL(14,2): exact, unlike the FLOAT/DOUBLE the order tables used before
 * 2026_07_09_150002. Posted entries are never edited or deleted, only voided by a
 * reversing entry, so the history is a complete audit trail.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 10)->unique();
            $table->string('name', 120);
            // asset|liability|equity|income|expense decide which side is "normal" and which
            // statement the account appears on
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense'])->index();
            // finer grouping: bank, cash, inventory, payable, capital, sales, cogs, operating …
            $table->string('subtype', 30)->nullable()->index();
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false); // referenced by code (sales posting): can't be deleted / retyped
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference', 30)->nullable()->unique(); // EXP-000123, set right after insert
            $table->date('entry_date')->index();
            $table->enum('type', ['income', 'expense', 'transfer', 'journal', 'sales', 'opening'])->index();
            $table->string('description', 255);
            $table->string('party', 150)->nullable();               // who was paid / who paid
            $table->string('payment_reference', 100)->nullable();   // receipt / invoice / transfer number
            $table->decimal('total', 14, 2);                        // = total debits = total credits
            $table->enum('status', ['posted', 'void'])->default('posted')->index();
            // system-generated entries (the daily sales summary) identify what they came from
            $table->string('source', 30)->nullable();
            $table->string('source_key', 60)->nullable();
            $table->unsignedBigInteger('reverses_entry_id')->nullable()->index();      // set on a reversal
            $table->unsignedBigInteger('reversed_by_entry_id')->nullable();            // set on a voided original
            $table->unsignedBigInteger('replaces_entry_id')->nullable();               // set on a correction
            $table->unsignedBigInteger('replaced_by_entry_id')->nullable();            // set on the superseded original
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['source', 'source_key']);
            $table->foreign('created_by')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('SET NULL');
            $table->foreign('voided_by')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('SET NULL');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo', 255)->nullable();

            $table->index('journal_entry_id');
            $table->index('account_id');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('account_id')->references('id')->on('accounts')->onUpdate('CASCADE')->onDelete('RESTRICT');
        });

        $this->seedChartOfAccounts();
        $this->seedSettings();
    }

    public function down()
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        DB::table('settings')->whereIn('key', ['books_start_date', 'books_closed_through', 'sales_deposit_account', 'last_sales_sync'])->delete();
    }

    /** [code, name, type, subtype, system?, description] */
    private function chart(): array
    {
        return [
            // ---- assets
            ['1000', 'Cash on Hand', 'asset', 'cash', false, 'Physical cash kept in the business'],
            ['1010', 'Bank – Sterling', 'asset', 'bank', true, 'Default account customer bank transfers are received into'],
            ['1020', 'Bank – FCMB', 'asset', 'bank', false, null],
            ['1030', 'Online Payments Clearing (Paystack)', 'asset', 'clearing', false, 'Card payments taken but not yet settled to the bank'],
            ['1100', 'Inventory (Stock on Hand)', 'asset', 'inventory', false, 'Value of stock on the shelf, adjusted at period end'],
            ['1200', 'Accounts Receivable', 'asset', 'receivable', false, 'Money customers owe the business'],
            ['1300', 'Prepayments & Deposits', 'asset', 'prepayment', false, 'Rent or supplier deposits paid in advance'],
            ['1500', 'Equipment & Fixtures', 'asset', 'fixed', false, 'Shelves, computers, generators and other long-life assets'],
            ['1590', 'Accumulated Depreciation', 'asset', 'contra', false, 'Total depreciation charged on fixed assets (reduces their value)'],
            // ---- liabilities
            ['2000', 'Accounts Payable', 'liability', 'payable', false, 'Money owed to suppliers for goods or services received'],
            ['2100', 'Loans Payable', 'liability', 'loan', false, 'Bank loans and money borrowed'],
            ['2200', 'Taxes Payable', 'liability', 'tax', false, 'VAT, PAYE and company income tax owed'],
            ['2900', 'Accrued Expenses', 'liability', 'accrued', false, 'Expenses incurred but not yet paid'],
            // ---- equity
            ['3000', "Owner's Capital", 'equity', 'capital', false, 'Money the owner has put into the business'],
            ['3100', "Owner's Drawings", 'equity', 'drawings', false, 'Money the owner has taken out for personal use'],
            ['3200', 'Retained Earnings', 'equity', 'retained', false, 'Profit kept in the business from earlier years'],
            ['3900', 'Opening Balance Equity', 'equity', 'opening', false, 'The other side of opening balances when starting the books'],
            // ---- income
            ['4000', 'Sales Revenue', 'income', 'sales', true, 'Product sales — posted automatically from paid orders'],
            ['4100', 'Delivery Income', 'income', 'sales', true, 'Delivery charged to customers — posted automatically'],
            ['4200', 'Sales Returns & Refunds', 'income', 'contra', false, 'Refunds and returns (reduces sales)'],
            ['4900', 'Other Income', 'income', 'other_income', false, 'Any income that is not a product sale'],
            ['4910', 'Interest Income', 'income', 'other_income', false, null],
            // ---- cost of sales
            ['5000', 'Cost of Goods Sold (Stock Purchases)', 'expense', 'cogs', false, 'Cost of the goods bought for resale'],
            ['5100', 'Freight & Import Charges', 'expense', 'cogs', false, 'Getting goods to the shop: shipping, clearing, transport in'],
            ['5200', 'Packaging Materials', 'expense', 'cogs', false, 'Bags, boxes and wrapping used on orders'],
            // ---- operating expenses
            ['6000', 'Salaries & Wages', 'expense', 'operating', false, null],
            ['6100', 'Rent', 'expense', 'operating', false, null],
            ['6200', 'Electricity & Water', 'expense', 'operating', false, null],
            ['6210', 'Internet & Phone', 'expense', 'operating', false, null],
            ['6300', 'Transport & Delivery', 'expense', 'operating', false, 'Getting orders to customers (dispatch riders, fuel)'],
            ['6400', 'Marketing & Advertising', 'expense', 'operating', false, null],
            ['6500', 'Bank & Payment Gateway Charges', 'expense', 'operating', false, 'Transfer fees, card fees, account maintenance'],
            ['6600', 'Repairs & Maintenance', 'expense', 'operating', false, null],
            ['6700', 'Professional Fees', 'expense', 'operating', false, 'Accountant, lawyer, consultants'],
            ['6800', 'Office & Admin Supplies', 'expense', 'operating', false, null],
            ['6850', 'Insurance', 'expense', 'operating', false, null],
            ['6900', 'Taxes, Licences & Levies', 'expense', 'operating', false, null],
            ['6950', 'Depreciation', 'expense', 'operating', false, 'Wear and tear on fixed assets'],
            ['6960', 'Stock Loss & Damages', 'expense', 'operating', false, 'Damaged, expired or missing stock written off'],
            ['7900', 'Miscellaneous Expenses', 'expense', 'operating', false, 'Anything that fits nowhere else'],
        ];
    }

    private function seedChartOfAccounts(): void
    {
        $now = now();
        foreach ($this->chart() as [$code, $name, $type, $subtype, $system, $description]) {
            if (DB::table('accounts')->where('code', $code)->exists()) {
                continue;
            }
            DB::table('accounts')->insert([
                'code' => $code, 'name' => $name, 'type' => $type, 'subtype' => $subtype,
                'description' => $description, 'is_system' => $system, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function seedSettings(): void
    {
        $defaults = [
            // Sales are posted to the ledger from this date on. Earlier orders stay in the
            // sales reports but are not part of the books. Default: the start of this year.
            'books_start_date' => now()->startOfYear()->toDateString(),
            // entries dated on or before this day can't be created, voided or changed
            'books_closed_through' => '',
            // the account customer transfers are received into (a code from the chart)
            'sales_deposit_account' => '1010',
            'last_sales_sync' => '',
        ];
        foreach ($defaults as $key => $value) {
            if (!DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value]);
            }
        }
    }
};
