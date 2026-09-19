<?php

namespace App\Http\Requests\Costing;

use Illuminate\Foundation\Http\FormRequest;

/** A delivery from a supplier: who, which invoice, what arrived and what each unit cost. */
class SaveReceiptRequest extends FormRequest
{
    public function authorize()
    {
        return true; // route middleware: permission:create menu
    }

    public function rules()
    {
        return [
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:2000-01-01'],
            'supplier' => ['required', 'string', 'max:150'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            // freight, customs, handling — shared across the lines in proportion to their value
            'extra_costs' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'extra_costs_note' => ['nullable', 'string', 'max:255'],
            // where the money came from; empty = bought on credit
            'payment_account_id' => ['nullable', 'integer', 'exists:accounts,id'],

            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.size' => ['nullable', 'string', 'max:60'],
            'lines.*.color' => ['nullable', 'string', 'max:60'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
        ];
    }

    public function messages()
    {
        return [
            'lines.*.unit_cost.gt' => 'Every line needs a unit cost above zero.',
            'lines.*.unit_cost.required' => 'Every line needs a unit cost.',
            'received_on.before_or_equal' => 'The delivery date cannot be in the future.',
        ];
    }
}
