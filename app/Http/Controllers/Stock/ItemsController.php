<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\SaveItemRequest;
use App\Http\Requests\Stock\StockItemRequest;
use App\Models\ItemDiscount;
use App\Models\ItemReview;
use App\Models\Order\OrderItem;
use App\Models\Stock\Item;
use App\Models\Stock\ItemMedia;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemSizePrice;
use App\Models\Stock\ItemStock;
use App\Models\Stock\ItemTax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Costing\CostingSettings;
use Illuminate\Support\Facades\DB;

class ItemsController extends Controller
{
    protected $balance = 'quantity_stocked - reserved - sold';
    public function fetchAllItems()
    {
        $items = Item::with(['category'])->where('enabled', 1)->get();
        return response()->json(compact('items'));
    }

    public function fetchLatestProducts()
    {
        $stocks = ItemStock::whereHas('item', function ($q) {
            $q->where('enabled', 1);
        })->with(['item' => function ($q) {
            $q->where('enabled', 1);
        }, 'item.media', 'item.price'])->whereRaw($this->balance . ' > 0')->orderBy('updated_at', 'DESC')->groupBy('item_id')->paginate(10);
        return response()->json(compact('stocks'));
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $itemQuery = Item::query();
        $exclude_item_id = NULL;
        $relationship = ['media', 'itemStocks' => function ($q) {
            $q->whereRaw($this->balance . ' > 0');
        }, 'discounts', 'category', 'price', 'sizePrices'];

        if (isset($request->exclude_item_id) && $request->exclude_item_id !== '' && $request->exclude_item_id !== null) {
            $exclude_item_id = $request->exclude_item_id;
        }
        if ($user !== null) {
            if ($user->role !== 'staff') {
                $itemQuery->where('enabled', 1);
            }
        } else {

            $itemQuery->where('enabled', 1);
        }

        $itemQuery->with($relationship)
            ->withCount(['reviews as reviews_count' => function ($q) {
                $q->where('is_published', 1);
            }])
            ->withAvg(['reviews as reviews_avg_star' => function ($q) {
                $q->where('is_published', 1);
            }], 'star')
            ->where('id', '!=', $exclude_item_id);
        if (isset($request->category_id) && $request->category_id !== '' && $request->category_id !== null) {
            $itemQuery->where('category_id', $request->category_id);
        }

        if (isset($request->item_name) && $request->item_name !== '') {

            $itemQuery->where('name', 'LIKE', '%' . $request->item_name . '%');;
        }
        if (filter_var($request->discounted, FILTER_VALIDATE_BOOLEAN)) {
            $itemQuery->whereHas('discounts');
        }
        if (isset($request->sort) && $request->sort === 'newest') {
            $itemQuery->orderBy('created_at', 'DESC');
        } else {
            $itemQuery->inRandomOrder();
        }
        $items = $itemQuery->paginate($request->limit);

        return response()->json(compact('items'));
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Stock\Item  $item
     * @return \Illuminate\Http\Response
     */
    /**
     * Disabled products are hidden from the storefront listing already; this
     * keeps them from being opened (and so added to a cart) directly by slug
     * or id, too. Signed-in staff/admins can still see them.
     */
    private function viewerCanSeeDisabledItems()
    {
        $user = Auth::guard('api')->user();
        return $user && ($user->role === 'staff' || $user->isAdmin());
    }
    public function show(Item $item)
    {
        if (!$item->enabled && !$this->viewerCanSeeDisabledItems()) {
            return response()->json(['item' => null], 200);
        }

        $item = $item->with(['media', 'itemStocks' => function ($q) {
            $q->whereRaw($this->balance . ' > 0');
        }, 'discounts', 'category', 'price', 'sizePrices'])
            ->withCount(['reviews as reviews_count' => function ($q) {
                $q->where('is_published', 1);
            }])
            ->withAvg(['reviews as reviews_avg_star' => function ($q) {
                $q->where('is_published', 1);
            }], 'star')
            ->find($item->id);
        // $item->currency_id = $item->price->currency_id;
        // $item->purchase_price = $item->price->purchase_price;
        // $item->amount = $item->price->amount;
        return response()->json(compact('item'), 200);
    }
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Stock\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function itemDetails(Request $request)
    {
        $slug = str_replace(' ', '-', strtolower($request->slug));
        $item = Item::with(['media', 'itemStocks' => function ($q) {
            $q->whereRaw($this->balance . ' > 0');
        }, 'discounts', 'category', 'price', 'sizePrices'])
            ->withCount(['reviews as reviews_count' => function ($q) {
                $q->where('is_published', 1);
            }])
            ->withAvg(['reviews as reviews_avg_star' => function ($q) {
                $q->where('is_published', 1);
            }], 'star')
            ->where('slug', $slug)
            ->when(!$this->viewerCanSeeDisabledItems(), function ($q) {
                $q->where('enabled', 1);
            })
            ->first();
        // $item->currency_id = $item->price->currency_id;
        // $item->purchase_price = $item->price->purchase_price;
        // $item->amount = $item->price->amount;
        return response()->json(compact('item'), 200);
    }
    public function searchProduct(Request $request)
    {
        $itemQuery = Item::query();
        $relationship = ['media', 'itemStocks' => function ($q) {
            $q->whereRaw($this->balance . ' > 0');
        }, 'discounts', 'category', 'price', 'sizePrices'];
        $keyword = $request->slug;

        $itemQuery->where('enabled', 1)
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', '%' . $keyword . '%');
                $q->orWhere(function ($p) use ($keyword) {
                    $p->whereHas('category', function ($p)  use ($keyword) {
                        $p->where('name', 'LIKE', '%' . $keyword . '%');
                    });
                });
            });
        $items = $itemQuery->with($relationship)
            ->withCount(['reviews as reviews_count' => function ($q) {
                $q->where('is_published', 1);
            }])
            ->withAvg(['reviews as reviews_avg_star' => function ($q) {
                $q->where('is_published', 1);
            }], 'star')
            ->paginate($request->limit);
        return response()->json(compact('items'));
    }
    public function itemReviews(Request $request)
    {

        $reviews = ItemReview::with('user')->where(['item_id' => $request->item_id, 'is_published' => 1])->paginate($request->limit);
        $average = ItemReview::where(['item_id' => $request->item_id, 'is_published' => 1])->selectRaw('AVG(star) as overall')->first();
        return response()->json(compact('reviews', 'average'), 200);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(SaveItemRequest $request, Item $item)
    {
        $user = $this->getUser();
        $name = $request->name;
        $category_id = $request->category_id;
        $description = $request->description;
        $existing = Item::where(['category_id' => $category_id, 'name' => $name])->first();
        $item = Item::updateOrCreate(
            ['category_id' => $category_id, 'name' => $name],
            ['slug' => $this->uniqueSlug($name, $existing ? $existing->id : null), 'description' => $description]
        );

        $this->attachItemToMedia($item->id, $request->input('images', []));
        $this->removeDeletedMedia($item->id, $request->input('deletedImages', []));
        $discounts = $request->input('discounts', []);
        if (count($discounts) > 0) {

            $this->createItemDiscounts($discounts, $item->id);
        }
        ItemPrice::updateOrCreate(
            ['item_id' => $item->id],
            ['amount' => $request->amount]
        );
        // log this action
        $title = "Product Added";
        $description = $name . " added to list of products by " . $user->name;;
        $roles = [];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($item);

        // return response()->json(['message' => 'Duplicate SKU'], 500);
    }

    /**
     * Only images that are not yet attached to a product (fresh uploads) or
     * that already belong to this product can be attached. The ids come from
     * the client, so without this scope a request could steal another
     * product's images.
     */
    private function attachItemToMedia($item_id, $mediaIds)
    {
        if (count($mediaIds) > 0) {
            $media = ItemMedia::whereIn('id', $mediaIds)
                ->where(function ($q) use ($item_id) {
                    $q->whereNull('item_id')->orWhere('item_id', $item_id);
                })
                ->get();
            foreach ($media as $med) {
                $med->item_id = $item_id;
                $med->save();
            }
        }
    }
    /**
     * Only this product's own images can be removed (same reason as above —
     * the ids are client-supplied), and a file that is already gone from disk
     * no longer turns the request into a 500.
     */
    private function removeDeletedMedia($item_id, $deletedMediaIds)
    {
        if (count($deletedMediaIds) > 0) {
            $media = ItemMedia::whereIn('id', $deletedMediaIds)->where('item_id', $item_id)->get();
            foreach ($media as $med) {
                foreach ([$med->thumbnail, $med->link] as $relative) {
                    $path = $relative ? portalPulicPath($relative) : null;
                    if ($path && is_file($path)) {
                        unlink($path);
                    }
                }
                $med->delete();
            }
        }
    }
    /**
     * URL slug for a product name, made unique: two products with the same
     * name in different categories used to share one slug, so the product page
     * for one of them showed the other.
     */
    private function uniqueSlug($name, $ignoreItemId = null)
    {
        $base = str_replace(' ', '-', strtolower($name));
        $slug = $base;
        $n = 2;
        while (Item::withTrashed()->where('slug', $slug)->when($ignoreItemId, fn ($q) => $q->where('id', '!=', $ignoreItemId))->exists()) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }
    /**
     * Matching on item_id alone would collapse every tier an admin defines
     * for the same item into a single row (each loop iteration overwriting
     * the last) — a discount row's own id (present only once it's been
     * persisted, same convention the deletedDiscounts removal flow already
     * relies on) is what distinguishes which specific tier to update,
     * letting an item genuinely carry multiple MOQ tiers.
     */
    private function createItemDiscounts($discounts, $item_id)
    {
        foreach ($discounts as $discount) {
            if ($discount['amount'] != null && $discount['minimum_order_quantity'] != null) {
                $minimum_order_quantity = (int) $discount['minimum_order_quantity'];
                $amount = (int) $discount['amount'];
                if (!empty($discount['id'])) {
                    // scoped to this product: the id is client-supplied
                    ItemDiscount::where('id', $discount['id'])->where('item_id', $item_id)->update([
                        'minimum_order_quantity' => $minimum_order_quantity,
                        'amount' => $amount,
                    ]);
                } else {
                    ItemDiscount::create([
                        'item_id' => $item_id,
                        'minimum_order_quantity' => $minimum_order_quantity,
                        'amount' => $amount,
                    ]);
                }
            }
        }
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Stock\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function update(SaveItemRequest $request, Item $item)
    {
        $user = $this->getUser();
        $item->name = $request->name;
        $item->slug = $this->uniqueSlug($request->name, $item->id);
        $item->category_id = $request->category_id;
        $item->description = $request->description;
        $item->save();

        $this->attachItemToMedia($item->id, $request->input('images', []));
        $this->removeDeletedMedia($item->id, $request->input('deletedImages', []));
        $discounts = $request->input('discounts', []);
        if (count($discounts) > 0) {

            $this->createItemDiscounts($discounts, $item->id);
        }
        $this->removeDeletedDiscounts($item->id, $request->input('deletedDiscounts', []));
        ItemPrice::updateOrCreate(
            ['item_id' => $item->id],
            ['amount' => $request->amount]
        );
        $title = "Product details modified";
        $description = "Product information for $item->name  was modified by " . $user->name;;
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($item);
    }
    private function removeDeletedDiscounts($item_id, $deletedDiscounts)
    {
        //
        if (count($deletedDiscounts) > 0) {
            $discounts = ItemDiscount::whereIn('id', $deletedDiscounts)->where('item_id', $item_id)->get();
            foreach ($discounts as $discount) {
                $discount->delete();
            }
        }
    }
    /**
     * Stock Items.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Stock\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function stock(StockItemRequest $request, Item $item)
    {
        // once costing is live, stock must arrive with a cost (and the layers must match the shelf)
        if (CostingSettings::enabled()) {
            return response()->json(['message' => 'Product costing is switched on, so stock now comes in through Receive stock, which records what it cost.'], 422);
        }
        $user = $this->getUser();
        $stocks = json_decode(json_encode($request->sub_batches));
        $total_quantity = 0;
        // Row-locked: two people stocking the same colour/size at once used to
        // overwrite each other's `quantity_stocked += n`.
        DB::transaction(function () use ($stocks, $item, &$total_quantity) {
            foreach ($stocks as $stock) {
                $quantity = (int) $stock->quantity;
                $other_color = $stock->other_color ?? null;
                $color = (($stock->color ?? null) != 'others') ? ($stock->color ?? null) : $other_color;
                $size = $stock->size ?? null;
                $item_stock = ItemStock::where(['color' => $color, 'size' => $size, 'item_id' => $item->id])->lockForUpdate()->first();
                if (!$item_stock) {
                    $item_stock = new ItemStock();
                    $item_stock->quantity_stocked = $quantity;
                } else {

                    $item_stock->quantity_stocked += $quantity;
                }
                $item_stock->item_id = $item->id;
                $item_stock->color = $color;
                $item_stock->size = $size;
                $item_stock->save();

                $total_quantity += $quantity;
            }
        });
        $this->upsertSizePrices($request->size_prices, $item->id);
        $title = "New Product Stock";
        $description = "$total_quantity quantity of $item->name  was stocked by " . $user->name;;
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($item);
    }
    /**
     * Set/update the price for each distinct size submitted alongside a
     * stock-up. Sizes are the only pricing dimension — color rows sharing
     * the same size always resolve to the same price via this table, rather
     * than duplicating an amount on every color row of that size.
     */
    private function upsertSizePrices($sizePrices, $item_id)
    {
        if (!$sizePrices) {
            return;
        }
        foreach ($sizePrices as $sizePrice) {
            $size = $sizePrice['size'] ?? null;
            $amount = $sizePrice['amount'] ?? null;
            if ($size === null || $size === '' || $amount === null || $amount === '') {
                continue;
            }
            ItemSizePrice::updateOrCreate(
                ['item_id' => $item_id, 'size' => $size],
                ['amount' => $amount]
            );
        }
    }
    /**
     * A customer rating / comment on a product they received. This endpoint is
     * public (guests review through the order-tracking flow), so:
     *  - `field` is a closed set. It used to be written straight onto the model
     *    (`$review->$field = ...`), which let anyone set `is_published` and skip
     *    moderation, or overwrite `user_id`;
     *  - the reviewer must have a delivered order containing the product;
     *  - it is rate-limited in routes/api.php.
     */
    public function giveReview(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'field' => ['required', 'in:star,comment'],
            'value' => $request->field === 'star'
                ? ['required', 'integer', 'between:1,5']
                : ['nullable', 'string', 'max:1000'],
        ]);

        $received = OrderItem::where('item_id', $data['item_id'])
            ->whereHas('order', function ($q) use ($data) {
                $q->where('user_id', $data['user_id'])->where('order_status', 'Delivered');
            })
            ->exists();
        if (!$received) {
            return response()->json(['message' => 'Only customers who received this product can review it'], 403);
        }

        $item_review = ItemReview::where(['user_id' => $data['user_id'], 'item_id' => $data['item_id']])->first();
        if (!$item_review) {
            $item_review = new ItemReview();
            $item_review->user_id = $data['user_id'];
            $item_review->item_id = $data['item_id'];
        }
        $field = $data['field'];
        $item_review->$field = $data['value'];
        $item_review->save();

        return response()->json(compact('item_review'), 200);
    }
    public function approveReview(Request $request, ItemReview $review)
    {
        $request->validate(['value' => ['required', 'boolean']]);
        $review->is_published = $request->boolean('value');
        $review->save();

        // (This used to return compact('item_review') — a variable that doesn't
        // exist here — so approving a review answered 500 after saving it.)
        return response()->json(['item_review' => $review], 200);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Item\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function destroy(Item $item)
    {
        // first log this event
        $user = $this->getUser();
        $title = "Product deleted";
        $description = $item->name . " was removed from list of products by " . $user->name;
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);

        // $item->taxes()->detach(); //use detach for pivoted relationship (hasManyThrough)
        $item->price()->delete();
        $item->delete();
        return response()->json(null, 204);
    }
    public function toggleStatus(Request $request, Item $item)
    {
        $request->validate([
            'value' => ['required', 'boolean'],
            'action' => ['nullable', 'string', 'in:enabled,disabled,enable,disable'],
        ]);
        // first log this event
        $user = $this->getUser();
        $action = $request->input('action', $request->boolean('value') ? 'enabled' : 'disabled');
        $title = "Product $action";
        $description = $item->name . " was $action by " . $user->name;
        $this->logUserActivity($title, $description);

        // $item->taxes()->detach(); //use detach for pivoted relationship (hasManyThrough)
        $item->enabled = $request->boolean('value');
        $item->save();
        return response()->json(null, 204);
    }
}
