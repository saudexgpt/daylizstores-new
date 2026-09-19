<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Setting\Tax;
use Illuminate\Http\Request;

class TaxesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $taxes = Tax::get();
        return response()->json(compact('taxes'));
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request, Tax $tax)
    {
        //
        $name = $request->name;
        $tax = Tax::where('name', $name)->first();

        if (!$tax) {
            $tax = new Tax();
            $tax->name = $name;
            $tax->rate = $request->rate;
            $tax->enabled = $request->enabled;
            $tax->save();

            return response()->json(compact('tax'), 200);
        }
        return response()->json(['message' => 'Duplicate name'], 200);
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Tax\Tax  $tax
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Tax $tax)
    {
        //
        $tax->name = $request->name;
        $tax->rate = $request->rate;
        $tax->enabled = $request->enabled;
        $tax->save();

        return response()->json(compact('tax'), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Tax\Tax  $tax
     * @return \Illuminate\Http\Response
     */
    public function destroy(Tax $tax)
    {
        //
        $tax->delete();
        return response()->json(null, 204);
    }
}
