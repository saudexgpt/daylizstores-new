<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingReports;
use App\Services\Accounting\SalesPoster;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Read-only financial statements. Sales are brought up to date first (a no-op when they were
 * synced in the last few minutes), so the numbers always include the latest paid orders.
 */
class StatementsController extends Controller
{
    public function __construct(private AccountingReports $reports, private SalesPoster $poster)
    {
    }

    public function overview(Request $request)
    {
        [$from, $to] = $this->period($request);
        $this->poster->syncIfStale();

        return response()->json($this->reports->overview($from, $to));
    }

    public function profitLoss(Request $request)
    {
        [$from, $to] = $this->period($request);
        $this->poster->syncIfStale();

        return response()->json($this->reports->profitAndLoss($from, $to, $request->boolean('compare', true)));
    }

    public function balanceSheet(Request $request)
    {
        $this->poster->syncIfStale();

        return response()->json($this->reports->balanceSheet($this->asAt($request)));
    }

    public function trialBalance(Request $request)
    {
        $this->poster->syncIfStale();

        return response()->json($this->reports->trialBalance($this->asAt($request)));
    }

    public function ledger(Request $request)
    {
        $request->validate(['account_id' => ['required', 'integer', 'exists:accounts,id']]);
        [$from, $to] = $this->period($request);
        $this->poster->syncIfStale();

        return response()->json($this->reports->generalLedger((int) $request->account_id, $from, $to));
    }

    /** from/to, defaulting to the current month; validated so a bad date is a 422, not a 500 */
    private function period(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $to = $request->input('to', now()->toDateString());
        $from = $request->input('from', Carbon::parse($to)->startOfMonth()->toDateString());

        return [$from, $to];
    }

    private function asAt(Request $request): string
    {
        $request->validate(['as_at' => ['nullable', 'date_format:Y-m-d']]);

        return $request->input('as_at', now()->toDateString());
    }
}
