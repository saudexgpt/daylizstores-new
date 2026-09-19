<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / update a product. The routes are already behind the
 * `permission:create menu` middleware; this class only validates the payload
 * (none of these endpoints validated anything before, so a missing
 * `discounts` array crashed with a TypeError and any string was accepted as
 * a price).
 */
class SaveItemRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // `items.name` has a database-wide unique index, so a duplicate used to
            // surface as a 500. (Includes trashed products: their name stays reserved.)
            'name' => ['required', 'string', 'max:190', Rule::unique('items', 'name')->ignore(optional($this->route('item'))->id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:10000'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],

            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['integer'],
            'deletedImages' => ['nullable', 'array', 'max:20'],
            'deletedImages.*' => ['integer'],

            'discounts' => ['nullable', 'array', 'max:20'],
            'discounts.*.id' => ['nullable', 'integer'],
            'discounts.*.amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'discounts.*.minimum_order_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'deletedDiscounts' => ['nullable', 'array', 'max:20'],
            'deletedDiscounts.*' => ['integer'],
        ];
    }
}
