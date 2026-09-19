<?php

namespace App\Http\Requests\Order;

/**
 * Read-only cart re-check. Deliberately lenient about *which* stock rows
 * exist — a stale cart pointing at a since-deleted stock row must come back
 * as "no longer available", not as a validation error.
 */
class ValidateCartRequest extends OrderFormRequest
{
    /**
     * @return array
     */
    public function rules()
    {
        return array_merge([
            'cart_items' => ['required', 'array', 'min:1', 'max:100'],
        ], $this->cartLineRules());
    }
}
