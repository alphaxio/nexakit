<?php

namespace Alphaxio\Nexakit\Pay;

use Illuminate\Support\Manager;
use Alphaxio\Nexakit\Pay\Contracts\PaymentGateway;
use Alphaxio\Nexakit\Pay\Builders\ChargeBuilder;
use Alphaxio\Nexakit\Pay\Drivers\PaystackDriver;
use Alphaxio\Nexakit\Pay\Drivers\FlutterwaveDriver;
use Alphaxio\Nexakit\Pay\Drivers\StripeDriver;
use Alphaxio\Nexakit\Pay\Drivers\SandboxPaymentDriver;
use InvalidArgumentException;

class PayManager extends Manager
{
    /**
     * Get a driver instance.
     *
     * @param  string|null  $driver
     * @return \Alphaxio\Nexakit\Pay\Contracts\PaymentGateway
     */
    public function driver($driver = null): PaymentGateway
    {
        return parent::driver($driver);
    }

    /**
     * Start a fluent charge builder.
     */
    public function charge(): ChargeBuilder
    {
        return new ChargeBuilder($this);
    }

    /**
     * Create an instance of the Sandbox payment driver.
     */
    public function createSandboxDriver(): PaymentGateway
    {
        return new SandboxPaymentDriver();
    }

    /**
     * Create an instance of the Paystack payment driver.
     */
    public function createPaystackDriver(): PaymentGateway
    {
        return new PaystackDriver($this->resolveConfig('paystack'));
    }

    /**
     * Create an instance of the Flutterwave payment driver.
     */
    public function createFlutterwaveDriver(): PaymentGateway
    {
        return new FlutterwaveDriver($this->resolveConfig('flutterwave'));
    }

    /**
     * Create an instance of the Stripe payment driver.
     */
    public function createStripeDriver(): PaymentGateway
    {
        return new StripeDriver($this->resolveConfig('stripe'));
    }

    /**
     * Get the default payment driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->container['config']['nexakit.pay.default'] ?? 'sandbox';
    }

    /**
     * Resolve the configuration for the given driver.
     *
     * @throws \InvalidArgumentException
     */
    protected function resolveConfig(string $driver): array
    {
        $config = $this->container['config']["nexakit.pay.drivers.{$driver}"] ?? null;

        if (is_null($config)) {
            throw new InvalidArgumentException("Nexakit Pay driver [{$driver}] configuration is missing.");
        }

        return $config;
    }
}
