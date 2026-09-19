<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Setting\Currency;
use Illuminate\Http\Request;

class CurrenciesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $currencies = Currency::get();
        return response()->json(compact('currencies'));
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request, Currency $currency)
    {
        //
        $name = $request->name;
        $currency = Currency::where('name', $name)->first();

        if (!$currency) {
            $currency = new Currency();
            $currency->name = $name;
            $currency->code = $request->code;
            $currency->rate = $request->rate;
            $currency->enabled = $request->enabled;
            $currency->save();

            return response()->json(compact('currency'), 200);
        }
        return response()->json(['message' => 'Duplicate currency'], 200);
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Currency\Currency  $currency
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Currency $currency)
    {
        //
        $currency->name = $request->name;
        $currency->code = $request->code;
        $currency->rate = $request->rate;
        $currency->enabled = $request->enabled;
        $currency->save();

        return response()->json(compact('currency'), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Currency\Currency  $currency
     * @return \Illuminate\Http\Response
     */
    public function destroy(Currency $currency)
    {
        //
        $currency->delete();
        return response()->json(null, 204);
    }
}
