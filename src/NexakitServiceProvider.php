<?php

namespace Alphaxio\Nexakit;

use Illuminate\Support\ServiceProvider;
use Alphaxio\Nexakit\Pay\PaymentManager;
// use Alphaxio\Nexakit\Sms\SmsManager;
// use Alphaxio\Nexakit\Kyc\KycManager;

class NexakitServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package configuration into the application's config
        $this->mergeConfigFrom(
            __DIR__.'/../config/nexakit.php', 'nexakit'
        );

        // Register Payment Manager singleton
        $this->app->singleton('nexakit.pay', function ($app) {
            return new PaymentManager($app);
        });
        $this->app->alias('nexakit.pay', PaymentManager::class);

        // Register SMS Manager singleton (Disabled until implemented)
        // $this->app->singleton('nexakit.sms', function ($app) {
        //     return new SmsManager($app);
        // });
        // $this->app->alias('nexakit.sms', SmsManager::class);

        // Register KYC Manager singleton (Disabled until implemented)
        // $this->app->singleton('nexakit.kyc', function ($app) {
        //     return new KycManager($app);
        // });
        // $this->app->alias('nexakit.kyc', KycManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration if running in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nexakit.php' => config_path('nexakit.php'),
            ], 'nexakit-config');
        }
    }
}
