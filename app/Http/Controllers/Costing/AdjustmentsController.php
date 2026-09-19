<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Models\Costing\StockAdjustment;
use App\Services\Costing\Costing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Damage, loss and count differences — the only other way stock leaves the shelf besides a sale. */
class AdjustmentsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'q' => ['nullable', 'string', 'max:100']]);
        $canSee = $this->getUser()->can('view cost');

        $page = DB::table('stock_adjustments as a')
            ->join('items as i', 'i.id', '=', 'a.item_id')
            ->leftJoin('item_stocks as s', 's.id', '=', 'a.item_stock_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.created_by')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('i.name', 'like', '%' . $request->q . '%')->orWhere('a.reference', 'like', '%' . $request->q . '%')))
            ->orderByDesc('a.adjusted_on')->orderByDesc('a.id')
            ->select('a.id', 'a.reference', 'a.adjusted_on', 'a.quantity', 'a.reason', 'a.note', 'a.cost', 'i.name as product', 'a.size', 's.color', 'u.name as by')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'adjustments' => collect($page->items())->map(fn ($a) => [
                'id' => $a->id, 'reference' => $a->reference, 'date' => $a->adjusted_on, 'product' => $a->product, 'size' => $a->size ?: null, 'color' => $a->color,
                'quantity' => (int) $a->quantity, 'reason' => $a->reason, 'note' => $a->note, 'by' => $a->by,
                'cost' => $canSee ? (float) $a->cost : null,
            ])->values(),
            'pagination' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function store(Request $request, Costing $costing)
    {
        $data = $request->validate([
            'item_stock_id' => ['required', 'integer', 'exists:item_stocks,id'],
            'quantity' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', 'in:damage,loss,count_difference,other'],
            'note' => ['nullable', 'string', 'max:255'],
            'adjusted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:2000-01-01'],
            // what each unit FOUND is worth (needed only when adding stock once costing is live)
            'unit_cost' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
        ]);

        $adjustment = $costing->adjust($data, \Auth::id());
        $this->logUserActivity('Stock adjusted', "{$adjustment->reference}: {$adjustment->quantity} unit(s), " . str_replace('_', ' ', $adjustment->reason) . ' — by ' . $this->getUser()->name);

        return response()->json(['adjustment' => ['id' => $adjustment->id, 'reference' => $adjustment->reference, 'quantity' => $adjustment->quantity]], 201);
    }
}
