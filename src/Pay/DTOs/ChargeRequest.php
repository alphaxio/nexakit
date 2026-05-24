<?php

namespace Alphaxio\Nexakit\Pay\DTOs;

use Illuminate\Support\Facades\Validator;

readonly class ChargeRequest
{
    /**
     * Create a new ChargeRequest instance.
     *
     * @param int $amount Amount in minor units (e.g., kobo for NGN, cents for USD)
     * @param string $currency Three-letter currency code (e.g., NGN, GHS, KES)
     * @param string $email Customer email address
     * @param string $reference Unique transaction reference
     * @param string|null $callbackUrl Optional URL to redirect to after payment completion
     * @param string|null $cancelUrl Optional URL to redirect to if payment is cancelled
     * @param array $options Optional driver-specific configuration overrides
     */
    public function __construct(
        public float|int $amount,
        public string $currency,
        public string $email,
        public string $reference,
        public ?string $callbackUrl = null,
        public ?string $cancelUrl = null,
        public array $options = []
    ) {
        Validator::make([
            'amount' => $amount,
            'currency' => $currency,
            'email' => $email,
            'reference' => $reference,
        ], [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'email' => 'required|email',
            'reference' => 'required|string',
        ])->validate();
    }

    /**
     * Get a driver-specific option.
     *
     * @param string $driver The driver name (e.g., 'paystack')
     * @param string $key The option key
     * @param mixed $default The default value if the option is not set
     */
    public function getOption(string $driver, string $key, mixed $default = null): mixed
    {
        return $this->options[$driver][$key] ?? $default;
    }
}
