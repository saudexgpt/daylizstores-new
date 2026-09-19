<?php

namespace App\Http\Requests\Order;

/**
 * Bank-transfer checkout: contact + delivery details, a non-empty cart, and
 * the payment-evidence image.
 */
class StoreOrderRequest extends OrderFormRequest
{
    /**
     * @return array
     */
    public function rules()
    {
        return array_merge([
            'order_uniq_id' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'max:190', 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'address' => ['required', 'string', 'max:500'],
            'nearest_bustop' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'location' => ['required', 'array', 'min:1', 'max:5'],
            'location.*' => ['required', 'string', 'max:120'],
            // Accepted for backwards compatibility with older clients but
            // never trusted: order amount/total are always recomputed
            // server-side from the cart.
            'delivery_cost' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'cart_items' => ['required', 'array', 'min:1', 'max:50'],
        ], $this->cartLineRules(), $this->receiptRules());
    }

    /**
     * Payment evidence must be a real jpg/png (checked by content, not by the
     * client-supplied filename/extension) and reasonably small. It's stored
     * inside the web root, so this is what keeps executable uploads out.
     *
     * @return array
     */
    protected function receiptRules()
    {
        return [
            'receipt_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
