<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Services\Costing\CostingSettings;
use App\Services\Costing\CostingSetup;
use Illuminate\Http\Request;

/**
 * Switching costing on (cost sheet -> check -> go live) and the "do the books match the shelf?" check.
 * Cut-over is an accounting decision, so it sits behind "manage accounting".
 */
class CostingController extends Controller
{
    public function __construct(private CostingSetup $setup)
    {
    }

    /** enough for any screen to know which stock flow to show */
    public function state()
    {
        return response()->json(['enabled' => CostingSettings::enabled(), 'start_date' => CostingSettings::startDate()]);
    }

    public function reconcile()
    {
        return response()->json($this->setup->reconcile());
    }

    /** what the system says is on the shelf — the starting point of the cost sheet */
    public function shelf()
    {
        $shelf = $this->setup->shelf();

        return response()->json(['enabled' => CostingSettings::enabled(), 'rows' => $shelf->values(), 'units' => $shelf->sum('qty')]);
    }

    public function check(Request $request)
    {
        $data = $this->validated($request);

        return response()->json($this->setup->check($data['rows']));
    }

    public function goLive(Request $request)
    {
        $data = $this->validated($request) + $request->validate(['start_date' => ['required', 'date_format:Y-m-d']]);
        $result = $this->setup->goLive($data['rows'], $data['start_date'], \Auth::id());
        $this->logUserActivity('Product costing switched on', 'Product costing went live on ' . $result['start_date'] . ' with ' . $result['layers'] . ' product sizes valued at ' . number_format($result['value'], 2) . ' — by ' . $this->getUser()->name);

        return response()->json($result + ['state' => ['enabled' => true, 'start_date' => $result['start_date']]]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'rows' => ['required', 'array', 'max:60000'],
            'rows.*.item_id' => ['required', 'integer'],
            'rows.*.size' => ['nullable', 'string', 'max:60'],
            'rows.*.unit_cost' => ['nullable'],
        ]);
    }
}
