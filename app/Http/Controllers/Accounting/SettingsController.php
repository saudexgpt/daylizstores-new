<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingSettings;
use App\Services\Accounting\SalesPoster;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show()
    {
        return response()->json($this->payload());
    }

    public function update(Request $request, SalesPoster $poster)
    {
        $data = $request->validate([
            'books_start_date' => ['sometimes', 'date_format:Y-m-d', 'after:2000-01-01', 'before_or_equal:today'],
            // empty string / null = nothing closed
            'books_closed_through' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sales_deposit_account' => ['sometimes', 'string', 'exists:accounts,code'],
        ]);

        if (isset($data['sales_deposit_account'])) {
            $account = Account::where('code', $data['sales_deposit_account'])->first();
            if ($account->type !== 'asset' || !$account->is_active) {
                return response()->json(['message' => 'Sales must be deposited to an active asset account (a bank or cash account).'], 422);
            }
        }

        $before = $this->payload();
        foreach (['books_start_date' => AccountingSettings::START, 'books_closed_through' => AccountingSettings::CLOSED, 'sales_deposit_account' => AccountingSettings::DEPOSIT] as $field => $key) {
            if (array_key_exists($field, $data)) {
                AccountingSettings::set($key, $data[$field] ?? '');
            }
        }

        $this->logUserActivity('Accounting settings changed', 'Books settings were changed by ' . $this->getUser()->name
            . ' (start ' . $before['books_start_date'] . ' → ' . AccountingSettings::booksStart()
            . ', closed through ' . ($before['books_closed_through'] ?: 'none') . ' → ' . (AccountingSettings::closedThrough() ?: 'none') . ')');

        // an earlier start date brings older orders into the books
        $sync = null;
        if (isset($data['books_start_date']) && $data['books_start_date'] < $before['books_start_date']) {
            $sync = $poster->sync();
        }

        return response()->json($this->payload() + ['sync' => $sync]);
    }

    /** post any sales that are missing from / changed since the last sync */
    public function syncSales(SalesPoster $poster)
    {
        $result = $poster->sync();
        if ($result['skipped']) {
            return response()->json(['message' => 'A sync is already running. Try again in a moment.'], 409);
        }
        $this->logUserActivity('Sales synced to the books', 'Sales were re-synced to the ledger by ' . $this->getUser()->name . " ({$result['entries']} entries posted)");

        return response()->json(['sync' => $result] + $this->payload());
    }

    private function payload(): array
    {
        return [
            'books_start_date' => AccountingSettings::booksStart(),
            'books_closed_through' => AccountingSettings::closedThrough(),
            'sales_deposit_account' => AccountingSettings::depositAccountCode(),
            'last_sales_sync' => AccountingSettings::get(AccountingSettings::LAST_SYNC),
            'entries' => JournalEntry::count(),
            'deposit_accounts' => Account::where('type', 'asset')->where('is_active', true)->whereIn('subtype', ['bank', 'cash', 'clearing'])->orderBy('code')->get(['id', 'code', 'name']),
            'today' => Carbon::now()->toDateString(),
        ];
    }
}
