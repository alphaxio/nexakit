<?php

namespace Alphaxio\Nexakit\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Alphaxio\Nexakit\NexakitServiceProvider;
use Alphaxio\Nexakit\Facades\Pay;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            NexakitServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Pay' => Pay::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Set up default configs for testing
        $app['config']->set('nexakit.pay.default', 'sandbox');
        $app['config']->set('nexakit.pay.currency', 'NGN');

        $app['config']->set('nexakit.pay.drivers.paystack', [
            'public_key' => 'pstk_pb_a0d8e8749e7b2a6f8b90c12d3e4f5a6b',
            'secret_key' => 'pstk_sc_f7a8e8749e7b2a6f8b90c12d3e4f5a6b',
        ]);

        $app['config']->set('nexakit.pay.drivers.flutterwave', [
            'public_key' => 'flwv_pb_a0d8e8749e7b2a6f8b90c12d3e4f5a6b',
            'secret_key' => 'flwv_sc_f7a8e8749e7b2a6f8b90c12d3e4f5a6b',
        ]);
    }
}
