<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\SaveTransactionRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Services\Accounting\Ledger;
use App\Services\Accounting\LedgerException;
use Illuminate\Http\Request;

/**
 * Income, expenses, transfers and journal entries.
 *
 * The everyday forms are one-amount, two-account transactions; this turns them into balanced
 * double-entry lines:
 *
 *   expense   Dr  <expense account>            Cr  <paid from: bank / cash / payable>
 *   income    Dr  <received into: bank / cash>  Cr  <income account>
 *   transfer  Dr  <to account>                  Cr  <from account>   (bank→cash, owner capital, drawings, loans …)
 *   journal   the lines exactly as entered      (for an accountant)
 */
class TransactionsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->filtered($request);
        $entries = (clone $query)->with('lines.account:id,code,name,type')->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'transactions' => collect($entries->items())->map(fn ($e) => $this->present($e))->values(),
            'summary' => $this->summary($query),
            'pagination' => ['total' => $entries->total(), 'page' => $entries->currentPage(), 'per_page' => $entries->perPage(), 'last_page' => $entries->lastPage()],
        ]);
    }

    public function show(JournalEntry $entry)
    {
        return response()->json(['transaction' => $this->present($entry->load('lines.account:id,code,name,type', 'creator:id,name'), true)]);
    }

    public function store(SaveTransactionRequest $request, Ledger $ledger)
    {
        $entry = $ledger->post($this->payload($request));

        return response()->json(['transaction' => $this->present($entry, true)], 201);
    }

    /** Correct a transaction: the old one is voided (reversed) and the corrected one posted. */
    public function update(SaveTransactionRequest $request, JournalEntry $entry, Ledger $ledger)
    {
        $this->assertCorrectable($entry);
        $new = $ledger->replace($entry, $this->payload($request), 'Corrected');
        $this->logUserActivity('Transaction corrected', "{$entry->reference} was corrected (now {$new->reference}) by " . $this->getUser()->name);

        return response()->json(['transaction' => $this->present($new, true)]);
    }

    public function void(Request $request, JournalEntry $entry, Ledger $ledger)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);
        $reversal = $ledger->void($entry, $data['reason'], $this->userId());
        $this->logUserActivity('Transaction voided', "{$entry->reference} ({$entry->description}) was voided by " . $this->getUser()->name . ': ' . $data['reason']);

        return response()->json(['transaction' => $this->present($entry->fresh('lines.account:id,code,name,type'), true), 'reversal' => $reversal->reference]);
    }

    // ------------------------------------------------------------------ building the entry

    private function payload(SaveTransactionRequest $request): array
    {
        $type = $request->type;
        $base = [
            'date' => $request->date,
            'type' => $type,
            'description' => $request->description,
            'party' => $request->party,
            'payment_reference' => $request->payment_reference,
            'created_by' => $this->userId(),
        ];

        if (in_array($type, ['journal', 'opening'], true)) {
            return $base + ['lines' => collect($request->lines)->map(fn ($l) => [
                'account_id' => $l['account_id'], 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0, 'memo' => $l['memo'] ?? null,
            ])->all()];
        }

        $accounts = Account::whereIn('id', array_filter([$request->account_id, $request->payment_account_id, $request->from_account_id, $request->to_account_id]))->get()->keyBy('id');
        $amount = (float) $request->amount;

        if ($type === 'transfer') {
            foreach (['from_account_id', 'to_account_id'] as $field) {
                if (!in_array($accounts[$request->$field]->type, ['asset', 'liability', 'equity'], true)) {
                    throw new LedgerException('Transfers move money between balance-sheet accounts (bank, cash, loans, capital, drawings). Income and expense accounts cannot be used here.');
                }
            }

            return $base + ['lines' => [
                ['account_id' => (int) $request->to_account_id, 'debit' => $amount],
                ['account_id' => (int) $request->from_account_id, 'credit' => $amount],
            ]];
        }

        $category = $accounts[$request->account_id];
        $payment = $accounts[$request->payment_account_id];
        // once costing is live, cost of goods is posted from sales and deliveries; typing stock purchases in here as well would count them twice
        if ($type === 'expense' && $category->code === '5000' && \App\Services\Costing\CostingSettings::enabled()) {
            throw new LedgerException('Cost of Goods Sold is posted automatically now that product costing is live. Record stock you buy with Receive stock instead (Manage Products → Receive Stock).');
        }
        if ($category->type !== $type) {
            throw new LedgerException($type === 'expense'
                ? 'An expense must be recorded against an expense category.'
                : 'Income must be recorded against an income category.');
        }
        if (!in_array($payment->type, ['asset', 'liability'], true)) {
            throw new LedgerException('Choose where the money ' . ($type === 'expense' ? 'was paid from' : 'was received') . ' (a bank or cash account, or Accounts Payable for something bought on credit).');
        }

        return $base + ['lines' => $type === 'expense'
            ? [['account_id' => $category->id, 'debit' => $amount], ['account_id' => $payment->id, 'credit' => $amount]]
            : [['account_id' => $payment->id, 'debit' => $amount], ['account_id' => $category->id, 'credit' => $amount]]];
    }

    private function assertCorrectable(JournalEntry $entry): void
    {
        if ($entry->status === 'void') {
            throw new LedgerException('A voided transaction cannot be edited.');
        }
        if ($entry->isReversal()) {
            throw new LedgerException('A reversing entry cannot be edited.');
        }
        if (in_array($entry->source, Ledger::SYSTEM_SOURCES, true)) {
            throw new LedgerException('This entry was generated by the system (sales, stock or costing) and cannot be edited. Change what it came from instead.');
        }
    }

    // ------------------------------------------------------------------ listing

    private function filtered(Request $request)
    {
        return JournalEntry::query()
            ->when($request->filled('from'), fn ($q) => $q->where('entry_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('entry_date', '<=', $request->to))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('account_id'), fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $request->account_id)))
            ->when($request->filled('min_amount'), fn ($q) => $q->where('total', '>=', $request->min_amount))
            ->when($request->filled('max_amount'), fn ($q) => $q->where('total', '<=', $request->max_amount))
            ->when($request->filled('q'), function ($q) use ($request) {
                $needle = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('description', 'like', $needle)->orWhere('party', 'like', $needle)
                    ->orWhere('reference', 'like', $needle)->orWhere('payment_reference', 'like', $needle));
            })
            // reversing entries are bookkeeping noise on the list (the voided original shows them)
            ->whereNull('reverses_entry_id')
            // "manual" = typed in by a person; the daily sales summaries are system entries
            ->when($request->input('source', 'manual') === 'manual', fn ($q) => $q->whereNull('source'))
            ->when($request->input('source') === 'sales', fn ($q) => $q->whereIn('source', ['sales', 'cogs']))
            ->when($request->input('source') === 'stock', fn ($q) => $q->whereIn('source', ['receipt', 'adjustment', 'opening_stock']));
    }

    /** income, expense and net for exactly what the filters match (voided entries excluded) */
    private function summary($query): array
    {
        $ids = (clone $query)->where('status', 'posted')->select('journal_entries.id');
        $rows = JournalLine::query()
            ->join('accounts as a', 'a.id', '=', 'journal_lines.account_id')
            ->whereIn('journal_lines.journal_entry_id', $ids)
            ->whereIn('a.type', ['income', 'expense'])
            ->groupBy('a.type')
            ->get(['a.type', \DB::raw('SUM(journal_lines.debit) as debit'), \DB::raw('SUM(journal_lines.credit) as credit')])
            ->keyBy('type');

        $income = Ledger::toKobo(($rows['income']->credit ?? 0)) - Ledger::toKobo(($rows['income']->debit ?? 0));
        $expense = Ledger::toKobo(($rows['expense']->debit ?? 0)) - Ledger::toKobo(($rows['expense']->credit ?? 0));

        return ['income' => Ledger::fromKobo($income), 'expense' => Ledger::fromKobo($expense), 'net' => Ledger::fromKobo($income - $expense)];
    }

    private function present(JournalEntry $e, bool $withLines = false): array
    {
        $pl = $e->lines->first(fn ($l) => in_array($l->account->type, ['income', 'expense'], true));
        $other = $e->lines->first(fn ($l) => !in_array($l->account->type, ['income', 'expense'], true));
        $debit = $e->lines->first(fn ($l) => (float) $l->debit > 0);
        $credit = $e->lines->first(fn ($l) => (float) $l->credit > 0);
        $acc = fn ($l) => $l ? ['id' => $l->account->id, 'code' => $l->account->code, 'name' => $l->account->name] : null;

        $row = [
            'id' => $e->id,
            'reference' => $e->reference,
            'date' => $e->entry_date->toDateString(),
            'type' => $e->type,
            'description' => $e->description,
            'party' => $e->party,
            'payment_reference' => $e->payment_reference,
            'amount' => (float) $e->total,
            'status' => $e->status,
            'source' => $e->source,
            'is_reversal' => $e->isReversal(),
            'void_reason' => $e->void_reason,
            'voided_at' => optional($e->voided_at)->toDateTimeString(),
            'replaced_by_entry_id' => $e->replaced_by_entry_id,
            'replaces_entry_id' => $e->replaces_entry_id,
            // income/expense: the P&L category and the bank/cash side; transfer: from -> to
            'category' => $acc($pl),
            'account' => $acc($other),
            'from' => $e->type === 'transfer' ? $acc($credit) : null,
            'to' => $e->type === 'transfer' ? $acc($debit) : null,
            'editable' => $e->status === 'posted' && !$e->isReversal() && !in_array($e->source, Ledger::SYSTEM_SOURCES, true),
        ];
        if ($withLines) {
            $row['created_by'] = optional($e->creator)->name;
            $row['created_at'] = optional($e->created_at)->toDateTimeString();
            $row['lines'] = $e->lines->map(fn ($l) => [
                'account_id' => $l->account_id, 'code' => $l->account->code, 'name' => $l->account->name,
                'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'memo' => $l->memo,
            ])->values();
        }

        return $row;
    }

    private function userId(): ?int
    {
        return \Auth::id();
    }
}
