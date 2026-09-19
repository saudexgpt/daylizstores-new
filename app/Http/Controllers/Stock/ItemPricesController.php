<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use Illuminate\Http\Request;

class ItemPricesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $item_prices = ItemPrice::with('item')->get();
        return response()->json(compact('item_prices'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);
        $item_price = new ItemPrice();
        $item_price->item_id = $request->item_id;
        $item_price->amount = $request->amount;
        $item_price->save();

        return $this->show($item_price);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Stock\ItemPrice  $itemPrice
     * @return \Illuminate\Http\Response
     */
    public function show(ItemPrice $item_price)
    {
        //
        $item_price = $item_price->with('item')->find($item_price->id);
        return response()->json(compact('item_price'), 200);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Stock\ItemPrice  $item_price
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ItemPrice $item_price)
    {
        $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);
        $item_price->item_id = $request->item_id;
        $item_price->amount = $request->amount;
        $item_price->save();

        return $this->show($item_price);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Stock\ItemPrice  $item_price
     * @return \Illuminate\Http\Response
     */
    public function destroy(ItemPrice $item_price)
    {
        //
        $item_price->delete();
        return response()->json(null, 204);
    }
}
