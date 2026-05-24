<?php

namespace Alphaxio\Nexakit\Pay\Drivers;

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
        return new PaymentResponse(
            status: 'success',
            reference: $reference,
            amount: 5000, // Simulated standard amount
            currency: 'NGN',
            gateway: 'sandbox',
            meta: ['message' => 'Sandbox simulation verified successfully.'],
            metadata: []
        );
    }

    /**
     * Refund a completed transaction (simulate automatic success).
     */
    public function refund(string $reference, float|int $amount, ?string $reason = null): PaymentResponse
    {
        return new PaymentResponse(
            status: 'success',
            reference: $reference,
            amount: $amount,
            currency: 'NGN',
            gateway: 'sandbox',
            meta: [
                'message' => 'Sandbox simulation refunded successfully.',
                'reason' => $reason
            ],
            metadata: []
        );
    }
}
