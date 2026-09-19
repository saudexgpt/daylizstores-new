<?php

namespace App\Http\Requests\Order;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Shared base for the public (guest-accessible) cart/order endpoints.
 */
abstract class OrderFormRequest extends FormRequest
{
    /**
     * Cart, checkout and order placement are open to guests; authorization
     * is handled at the route level (these sit outside the auth:api group),
     * and abuse is limited by per-route throttling in routes/api.php.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Cart line rules shared by every endpoint that receives a cart. Only
     * what the server actually acts on is accepted: which stock row, and how
     * many. Prices, names and item ids are always re-derived server-side.
     *
     * @return array
     */
    protected function cartLineRules()
    {
        return [
            // distinct: one line per stock row, so a crafted request can't
            // split an order across duplicate lines to dodge the stock check.
            'cart_items.*.stock_id' => ['required', 'integer', 'min:1', 'distinct'],
            'cart_items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'cart_items.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Returns a 422 carrying the first validation message, so the customer
     * is told what to fix (the frontend surfaces `message` as-is) instead of
     * an opaque server error.
     *
     * @param Validator $validator
     * @return void
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Please provide valid order details: ' . $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
