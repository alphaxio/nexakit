<?php

namespace Alphaxio\Nexakit\Pay\DTOs;

readonly class PaymentResponse
{
    /**
     * Create a new PaymentResponse instance.
     *
     * @param string $status The standardized status: 'success', 'failed', or 'pending'
     * @param string $reference Unique transaction reference
     * @param int $amount Amount in minor units (e.g., kobo for NGN, cents for USD)
     * @param string $currency Three-letter currency code (e.g., NGN, GHS, KES)
     * @param string|null $redirectUrl Optional checkout URL (if status is pending and requires redirect)
     * @param string $gateway The driver name that handled this transaction (e.g., 'paystack')
     * @param array $meta Raw response payload from the gateway provider
     */
    public function __construct(
        public string $status,
        public string $reference,
        public int $amount,
        public string $currency,
        public ?string $redirectUrl = null,
        public string $gateway = '',
        public array $meta = []
    ) {}

    /**
     * Check if the transaction was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the transaction is pending (e.g., waiting for user to input OTP on redirect page).
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
