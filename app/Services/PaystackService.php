<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    private function secretKey()
    {
        return config('services.paystack.secret_key');
    }

    private function baseUrl()
    {
        return config('services.paystack.payment_url', 'https://api.paystack.co');
    }

    /**
     * Starts a Paystack transaction. Amount must be in kobo (Naira x 100) —
     * Paystack's API always works in the currency's smallest unit.
     *
     * @return array Paystack's decoded JSON response
     */
    public function initializeTransaction($email, $amountInKobo, $reference, $callbackUrl)
    {
        $response = Http::withToken($this->secretKey())
            ->post($this->baseUrl() . '/transaction/initialize', [
                'email' => $email,
                'amount' => $amountInKobo,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
            ]);
        return $response->json();
    }

    /**
     * Server-side transaction verification — the only thing an order should
     * ever be marked "paid" on. Never trust a client-supplied success flag.
     *
     * @return array Paystack's decoded JSON response
     */
    public function verifyTransaction($reference)
    {
        $response = Http::withToken($this->secretKey())
            ->get($this->baseUrl() . '/transaction/verify/' . rawurlencode($reference));
        return $response->json();
    }

    /**
     * Paystack signs webhook payloads with an HMAC SHA512 of the raw
     * request body using the secret key — verifying this is the only thing
     * that makes an inbound webhook call trustworthy, since the URL itself
     * is publicly reachable and anyone could otherwise POST a fake payload.
     */
    public function verifyWebhookSignature($rawPayload, $signatureHeader)
    {
        if (!$signatureHeader) {
            return false;
        }
        $expected = hash_hmac('sha512', $rawPayload, (string) $this->secretKey());
        return hash_equals($expected, (string) $signatureHeader);
    }
}
