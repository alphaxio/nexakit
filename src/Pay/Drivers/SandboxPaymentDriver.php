<?php

namespace Alphaxio\Nexakit\Pay\Drivers;

use Illuminate\Support\Facades\Cache;
use Alphaxio\Nexakit\Pay\Contracts\PaymentGateway;
use Alphaxio\Nexakit\Pay\DTOs\ChargeRequest;
use Alphaxio\Nexakit\Pay\DTOs\PaymentResponse;

class SandboxPaymentDriver implements PaymentGateway
{
    /**
     * Initiate a charge (simulate redirect checkout).
     */
    public function initiate(ChargeRequest $request): PaymentResponse
    {
        // Cache transaction details to simulate realistic status checks
        Cache::put("nexakit_sandbox_{$request->reference}", [
            'amount' => $request->amount,
            'currency' => $request->currency,
            'metadata' => $request->options['sandbox']['metadata'] ?? [],
        ], now()->addHour());

        return new PaymentResponse(
            status: 'pending',
            reference: $request->reference,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: config('app.url') . '/nexakit/sandbox/checkout?reference=' . $request->reference . '&callback_url=' . urlencode($request->callbackUrl ?? ''),
            gateway: 'sandbox',
            meta: ['message' => 'Sandbox simulation initialized successfully.'],
            metadata: $request->options['sandbox']['metadata'] ?? []
        );
    }

    /**
     * Verify a transaction status (simulate automatic success).
     */
    public function verify(string $reference): PaymentResponse
    {
        $cached = Cache::get("nexakit_sandbox_{$reference}") ?? [
            'amount' => 5000,
            'currency' => 'NGN',
            'metadata' => [],
        ];

        return new PaymentResponse(
            status: 'success',
            reference: $reference,
            amount: $cached['amount'],
            currency: $cached['currency'],
            gateway: 'sandbox',
            meta: ['message' => 'Sandbox simulation verified successfully.'],
            metadata: $cached['metadata']
        );
    }

    /**
     * Refund a completed transaction (simulate automatic success).
     */
    public function refund(string $reference, float|int $amount, ?string $reason = null): PaymentResponse
    {
        $cached = Cache::get("nexakit_sandbox_{$reference}") ?? [
            'amount' => $amount,
            'currency' => 'NGN',
            'metadata' => [],
        ];

        return new PaymentResponse(
            status: 'success',
            reference: $reference,
            amount: $amount,
            currency: $cached['currency'],
            gateway: 'sandbox',
            meta: [
                'message' => 'Sandbox simulation refunded successfully.',
                'reason' => $reason
            ],
            metadata: []
        );
    }
}
