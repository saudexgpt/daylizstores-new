<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\SaveAccountRequest;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingReports;
use Illuminate\Http\Request;

/**
 * The chart of accounts. Reading needs "view accounting"; changing it needs "manage accounting".
 */
class AccountsController extends Controller
{
    public function index(Request $request, AccountingReports $reports)
    {
        $accounts = Account::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->has('active') && $request->active !== '', fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->get();

        // current balance on each account's normal side, and whether it has ever been used
        $balances = collect();
        if ($request->boolean('with_balance')) {
            $balances = $reports->totals(null, now()->toDateString())->keyBy('id');
        }

        return response()->json(['accounts' => $accounts->map(function (Account $a) use ($balances, $request) {
            $row = $a->only(['id', 'code', 'name', 'type', 'subtype', 'description', 'is_system', 'is_active']);
            if ($request->boolean('with_balance')) {
                $t = $balances->get($a->id);
                $row['balance'] = $t ? Account::normalBalance($a->type, $t->debit_kobo / 100, $t->credit_kobo / 100) : 0;
                $row['in_use'] = (bool) $t;
            }

            return $row;
        })->values()]);
    }

    public function store(SaveAccountRequest $request)
    {
        $account = Account::create($request->only(['code', 'name', 'type', 'subtype', 'description']) + ['is_system' => false, 'is_active' => true]);
        $this->logUserActivity('Account added', "Ledger account {$account->code} {$account->name} was added by " . $this->getUser()->name);

        return response()->json(['account' => $account], 201);
    }

    public function update(SaveAccountRequest $request, Account $account)
    {
        $inUse = $account->lines()->exists();

        // accounts the system posts to by code can only be renamed
        if ($account->is_system && ($request->filled('type') && $request->type !== $account->type || $request->has('is_active') && !$request->boolean('is_active'))) {
            return response()->json(['message' => 'This is a system account (used by automatic postings). It can be renamed but not retyped or deactivated.'], 422);
        }
        // changing what kind of account it is would silently change every past statement
        if ($inUse && $request->filled('type') && $request->type !== $account->type) {
            return response()->json(['message' => 'This account already has transactions, so its type cannot be changed.'], 422);
        }
        if ($inUse && $request->filled('code') && $request->code !== $account->code) {
            return response()->json(['message' => 'This account already has transactions, so its code cannot be changed.'], 422);
        }

        $account->fill($request->only(['code', 'name', 'type', 'subtype', 'description']));
        if ($request->has('is_active')) {
            $account->is_active = $request->boolean('is_active');
        }
        if ($account->is_system) {
            $account->code = $account->getOriginal('code');
            $account->type = $account->getOriginal('type');
        }
        $account->save();
        $this->logUserActivity('Account updated', "Ledger account {$account->code} {$account->name} was updated by " . $this->getUser()->name);

        return response()->json(['account' => $account]);
    }

    public function destroy(Account $account)
    {
        if ($account->is_system) {
            return response()->json(['message' => 'System accounts cannot be deleted.'], 422);
        }
        if ($account->lines()->exists()) {
            return response()->json(['message' => 'This account has transactions, so it cannot be deleted. Deactivate it instead.'], 422);
        }
        $account->delete();

        return response()->json(null, 204);
    }
}
