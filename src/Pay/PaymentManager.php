<?php

namespace Alphaxio\Nexakit\Pay;

use Illuminate\Support\Manager;
use Alphaxio\Nexakit\Pay\Contracts\PaymentGateway;
use Alphaxio\Nexakit\Pay\Drivers\PaystackDriver;
use Alphaxio\Nexakit\Pay\Drivers\FlutterwaveDriver;
use Alphaxio\Nexakit\Pay\Drivers\SandboxPaymentDriver;

class PaymentManager extends Manager
{
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
        $config = $this->container['config']['nexakit.pay.drivers.paystack'] ?? [];

        return new PaystackDriver($config);
    }

    /**
     * Create an instance of the Flutterwave payment driver.
     */
    public function createFlutterwaveDriver(): PaymentGateway
    {
        $config = $this->container['config']['nexakit.pay.drivers.flutterwave'] ?? [];

        return new FlutterwaveDriver($config);
    }

    /**
     * Get the default payment driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->container['config']['nexakit.pay.default'] ?? 'sandbox';
    }
}
