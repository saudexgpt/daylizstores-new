<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add stock to a product. Quantities must be positive: this endpoint only
 * ever adds, and a negative number used to silently remove stock.
 */
class StockItemRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'sub_batches' => ['required', 'array', 'min:1', 'max:100'],
            'sub_batches.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            // colour and size are optional ("if it applies") — a plain product has neither
            'sub_batches.*.color' => ['nullable', 'string', 'max:60'],
            'sub_batches.*.other_color' => ['nullable', 'string', 'max:60', 'required_if:sub_batches.*.color,others'],
            'sub_batches.*.size' => ['nullable', 'string', 'max:60'],

            'size_prices' => ['nullable', 'array', 'max:100'],
            'size_prices.*.size' => ['nullable', 'string', 'max:60'],
            'size_prices.*.amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ];
    }
}
