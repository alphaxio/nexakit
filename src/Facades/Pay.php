<?php

namespace Alphaxio\Nexakit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Alphaxio\Nexakit\Pay\Builders\ChargeBuilder charge()
 * @method static \Alphaxio\Nexakit\Pay\Contracts\PaymentGateway driver(string|null $driver = null)
 *
 * @see \Alphaxio\Nexakit\Pay\PayManager
 */
class Pay extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'nexakit.pay';
    }
}
