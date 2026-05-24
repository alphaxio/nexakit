<?php

namespace Alphaxio\Nexakit\Pay\Builders;

use Illuminate\Support\Str;
use Alphaxio\Nexakit\Pay\PayManager;
use Alphaxio\Nexakit\Pay\DTOs\ChargeRequest;
use Alphaxio\Nexakit\Pay\DTOs\PaymentResponse;

class ChargeBuilder
{
    protected float|int|null $amount = null;
    protected ?string $currency = null;
    protected ?string $email = null;
    protected ?string $reference = null;
    protected ?string $callbackUrl = null;
    protected ?string $cancelUrl = null;
    protected ?string $driver = null;
    protected array $options = [];

    /**
     * Create a new ChargeBuilder instance.
     */
    public function __construct(protected PayManager $manager) {}

    /**
     * Set the transaction amount in major units (e.g. Naira, Dollars).
     */
    public function amount(float|int $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Set the transaction currency.
     */
    public function currency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Set the customer's email.
     */
    public function email(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Set the customer recipient (automatically resolves email).
     */
    public function to(mixed $customer): self
    {
        if (is_string($customer)) {
            $this->email = $customer;
        } elseif (is_object($customer)) {
            if (method_exists($customer, 'routeNotificationFor')) {
                $this->email = $customer->routeNotificationFor('mail');
            } elseif (isset($customer->email)) {
                $this->email = $customer->email;
            }
        }

        return $this;
    }

    /**
     * Set a custom transaction reference.
     */
    public function reference(string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * Set the return/callback URL.
     */
    public function callbackUrl(string $url): self
    {
        $this->callbackUrl = $url;
        return $this;
    }

    /**
     * Set the return/cancel URL.
     */
    public function cancelUrl(string $url): self
    {
        $this->cancelUrl = $url;
        return $this;
    }

    /**
     * Explicitly choose the payment driver for this charge.
     */
    public function via(string $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Pass driver-specific options.
     */
    public function with(array $options): self
    {
        $this->options = array_merge_recursive($this->options, $options);
        return $this;
    }

    /**
     * Convert the builder state into a ChargeRequest DTO.
     */
    public function toDto(): ChargeRequest
    {
        if ($this->amount === null) {
            throw new \InvalidArgumentException("Payment amount is required.");
        }

        return new ChargeRequest(
            amount: $this->amount,
            currency: $this->currency ?? config('nexakit.pay.currency', 'NGN'),
            email: $this->email ?? '',
            reference: $this->reference ?? 'nk_' . Str::random(16),
            callbackUrl: $this->callbackUrl,
            cancelUrl: $this->cancelUrl,
            options: $this->options
        );
    }

    /**
     * Initialize the payment via the selected gateway driver.
     */
    public function initialize(): PaymentResponse
    {
        $driver = $this->driver ?? config('nexakit.pay.default', 'sandbox');

        return $this->manager->driver($driver)->initiate($this->toDto());
    }
}
