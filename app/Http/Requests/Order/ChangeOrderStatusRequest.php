<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff changing an order's delivery / payment status. Both values are a
 * closed set — they used to be saved verbatim, so any string could be written
 * into an order's status.
 */
class ChangeOrderStatusRequest extends FormRequest
{
    public const ORDER_STATUSES = ['Pending', 'CARP', 'On Transit', 'Delivered', 'Cancelled'];
    public const PAYMENT_STATUSES = ['pending', 'paid', 'cancelled', 'carp'];

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => ['required', 'string', Rule::in(self::ORDER_STATUSES)],
            'payment_status' => ['sometimes', 'nullable', 'string', Rule::in(self::PAYMENT_STATUSES)],
        ];
    }
}
