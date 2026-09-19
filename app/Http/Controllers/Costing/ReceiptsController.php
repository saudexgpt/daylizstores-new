<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Costing\SaveReceiptRequest;
use App\Models\Accounting\Account;
use App\Models\Costing\CostLayer;
use App\Models\Costing\StockReceipt;
use App\Models\Stock\Item;
use App\Services\Costing\Costing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receive stock: a supplier delivery with its invoice, extra costs and what each size cost.
 * Recording one is for whoever manages products ("create menu"); reading the costs back needs
 * "view cost"; voiding a mistaken delivery needs "manage accounting".
 */
class ReceiptsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:posted,void'], 'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = StockReceipt::query()
            ->when($request->filled('from'), fn ($q) => $q->where('received_on', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('received_on', '<=', $request->to))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('supplier', 'like', $like)->orWhere('invoice_number', 'like', $like)->orWhere('reference', 'like', $like));
            })
            ->withCount('lines')
            ->orderByDesc('received_on')->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'receipts' => collect($page->items())->map(fn ($r) => $this->present($r))->values(),
            'pagination' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(StockReceipt $receipt)
    {
        $receipt->load('lines.item:id,name');

        return response()->json(['receipt' => $this->present($receipt) + [
            'lines' => $receipt->lines->map(fn ($l) => [
                'item_id' => $l->item_id, 'product' => optional($l->item)->name, 'size' => $l->size, 'color' => $l->color,
                'quantity' => $l->quantity, 'unit_cost' => (float) $l->unit_cost, 'extra_cost' => (float) $l->extra_cost,
                'landed_unit_cost' => round(((float) $l->unit_cost * $l->quantity + (float) $l->extra_cost) / $l->quantity, 2),
            ])->values(),
            'void_reason' => $receipt->void_reason,
        ]]);
    }

    public function store(SaveReceiptRequest $request, Costing $costing)
    {
        $receipt = $costing->receive($request->validated(), $this->userId());
        $this->logUserActivity('Stock received', "{$receipt->reference}: stock from {$receipt->supplier} was recorded by " . $this->getUser()->name);

        return response()->json(['receipt' => $this->present($receipt)], 201);
    }

    public function void(Request $request, StockReceipt $receipt, Costing $costing)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);
        $costing->voidReceipt($receipt, $data['reason'], $this->userId());
        $this->logUserActivity('Stock delivery voided', "{$receipt->reference} ({$receipt->supplier}) was voided by " . $this->getUser()->name . ': ' . $data['reason']);

        return response()->json(['receipt' => $this->present($receipt->fresh())]);
    }

    /**
     * Find products to receive: their sizes and colours (including ones currently out of stock) and, for
     * people who may see costs, what each size cost last time.
     */
    public function products(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $items = Item::query()->where('name', 'like', '%' . $request->q . '%')->orderBy('name')->limit(20)->get(['id', 'name', 'enabled']);
        $ids = $items->pluck('id');

        $stocks = DB::table('item_stocks')->whereNull('deleted_at')->whereIn('item_id', $ids)
            ->get(['id', 'item_id', 'color', 'size', 'quantity_stocked', 'reserved', 'sold'])->groupBy('item_id');
        $prices = DB::table('item_size_prices')->whereNull('deleted_at')->whereIn('item_id', $ids)->get(['item_id', 'size'])->groupBy('item_id');
        $canSee = $this->getUser()->can('view cost');
        $last = $canSee
            ? CostLayer::whereIn('item_id', $ids)->orderBy('id')->get(['item_id', 'size', 'qty_received', 'cost_total'])
                ->mapWithKeys(fn ($l) => [$l->item_id . '|' . $l->size => $l->qty_received ? round((float) $l->cost_total / $l->qty_received, 2) : null])
            : collect();

        return response()->json(['products' => $items->map(function ($item) use ($stocks, $prices, $last, $canSee) {
            $rows = $stocks->get($item->id, collect());
            $sizes = $rows->pluck('size')->merge($prices->get($item->id, collect())->pluck('size'))->map(fn ($s) => Costing::sizeKey($s))->filter()->unique()->sort()->values();

            return [
                'id' => $item->id, 'name' => $item->name, 'enabled' => (bool) $item->enabled,
                'sizes' => $sizes->all(),
                'colors' => $rows->pluck('color')->filter()->unique()->sort()->values()->all(),
                // each shelf row (colour + size) with what is free to take off it — for stock adjustments
                'stocks' => $rows->map(fn ($s) => ['id' => $s->id, 'color' => $s->color, 'size' => Costing::sizeKey($s->size), 'available' => (int) ($s->quantity_stocked - $s->reserved - $s->sold)])->values()->all(),
                'last_cost' => $canSee ? $sizes->mapWithKeys(fn ($s) => [$s => $last->get($item->id . '|' . $s)])->all() + ['' => $last->get($item->id . '|')] : null,
            ];
        })->values()]);
    }

    /** the bank / cash accounts a delivery can be paid from (people who receive stock may not have accounting access) */
    public function paymentAccounts()
    {
        return response()->json(['accounts' => Account::where('type', 'asset')->where('is_active', true)
            ->whereIn('subtype', ['cash', 'bank', 'clearing'])->orderBy('code')->get(['id', 'code', 'name'])]);
    }

    private function present(StockReceipt $r): array
    {
        return [
            'id' => $r->id, 'reference' => $r->reference, 'received_on' => $r->received_on->toDateString(), 'supplier' => $r->supplier,
            'invoice_number' => $r->invoice_number, 'status' => $r->status, 'lines_count' => $r->lines_count ?? null,
            'items_total' => (float) $r->items_total, 'extra_costs' => (float) $r->extra_costs, 'extra_costs_note' => $r->extra_costs_note,
            'landed_total' => (float) $r->landed_total, 'paid' => $r->payment_account_id !== null,
        ];
    }

    private function userId(): ?int
    {
        return \Auth::id();
    }
}
