<?php

namespace App\Http\Requests\Order;

/**
 * Online-payment checkout: identical to the bank-transfer checkout except
 * there is no receipt image (payment is verified with the gateway instead).
 */
class InitializePaystackPaymentRequest extends StoreOrderRequest
{
    /**
     * @return array
     */
    protected function receiptRules()
    {
        return [];
    }
}
