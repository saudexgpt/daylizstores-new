<?php

namespace App\Services\Reports;

use App\Services\Reports\Definitions\BalanceSheet;
use App\Services\Reports\Definitions\CategoryBreakdown;
use App\Services\Reports\Definitions\GrossMargin;
use App\Services\Reports\Definitions\InventoryValuation;
use App\Services\Reports\Definitions\GeneralLedger;
use App\Services\Reports\Definitions\IncomeExpenses;
use App\Services\Reports\Definitions\NewCustomers;
use App\Services\Reports\Definitions\OrdersList;
use App\Services\Reports\Definitions\OutOfStock;
use App\Services\Reports\Definitions\ProfitLoss;
use App\Services\Reports\Definitions\SalesByCategory;
use App\Services\Reports\Definitions\SalesByCustomer;
use App\Services\Reports\Definitions\SalesByProduct;
use App\Services\Reports\Definitions\SalesSummary;
use App\Services\Reports\Definitions\StockLevels;
use App\Services\Reports\Definitions\TrialBalance;
use Illuminate\Support\Collection;

/** The catalogue. Adding a report = write the class, add it here. */
class ReportRegistry
{
    private const REPORTS = [
        SalesSummary::class, SalesByProduct::class, SalesByCategory::class, SalesByCustomer::class, OrdersList::class, GrossMargin::class,
        StockLevels::class, OutOfStock::class, InventoryValuation::class,
        NewCustomers::class,
        IncomeExpenses::class, CategoryBreakdown::class, ProfitLoss::class, BalanceSheet::class, TrialBalance::class, GeneralLedger::class,
    ];

    /** @return Collection<string,Report> keyed by report key, in catalogue order */
    public function all(): Collection
    {
        return collect(self::REPORTS)->map(fn ($class) => app($class))->keyBy(fn (Report $r) => $r->key());
    }

    public function find(string $key): ?Report
    {
        return $this->all()->get($key);
    }

    public function visibleTo($user): Collection
    {
        return $this->all()->filter(fn (Report $r) => $r->allowedFor($user));
    }
}
