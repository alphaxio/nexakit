<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the default payment gateway driver for your
    | application. The payment manager handles transactions, checkouts,
    | and verification.
    |
    | Supported drivers: "paystack", "flutterwave", "monnify", "opay", "sandbox"
    |
    */

    'pay' => [
        'default' => env('NEXAKIT_PAY_DRIVER', 'sandbox'),

        'drivers' => [
            'sandbox' => [
                // No credentials needed for sandbox simulation
            ],

            'paystack' => [
                'public_key' => env('PAYSTACK_PUBLIC_KEY'),
                'secret_key' => env('PAYSTACK_SECRET_KEY'),
                'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
            ],

            'flutterwave' => [
                'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
                'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
                'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
                'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
            ],

            'monnify' => [
                'api_key' => env('MONNIFY_API_KEY'),
                'secret_key' => env('MONNIFY_SECRET_KEY'),
                'contract_code' => env('MONNIFY_CONTRACT_CODE'),
                'webhook_secret' => env('MONNIFY_WEBHOOK_SECRET'),
                'sandbox' => env('MONNIFY_SANDBOX', true),
            ],

            'opay' => [
                'public_key' => env('OPAY_PUBLIC_KEY'),
                'merchant_id' => env('OPAY_MERCHANT_ID'),
                'secret_key' => env('OPAY_SECRET_KEY'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default SMS gateway driver. The SMS manager manages
    | sending notifications, alerts, and transactional messages.
    |
    | Supported drivers: "termii", "log"
    |
    */

    'sms' => [
        'default' => env('NEXAKIT_SMS_DRIVER', 'log'),

        'drivers' => [
            'log' => [
                // Writes SMS payloads to storage/logs/laravel.log
            ],

            'termii' => [
                'api_key' => env('TERMII_API_KEY'),
                'sender_id' => env('TERMII_SENDER_ID'),
                'channel' => env('TERMII_CHANNEL', 'generic'), // generic, dnd, whatsapp
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | KYC Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default KYC / Identity verification driver.
    |
    | Supported drivers: "dojah", "sandbox"
    |
    */

    'kyc' => [
        'default' => env('NEXAKIT_KYC_DRIVER', 'sandbox'),

        'drivers' => [
            'sandbox' => [
                // No credentials needed for sandbox simulation
            ],

            'dojah' => [
                'app_id' => env('DOJAH_APP_ID'),
                'secret_key' => env('DOJAH_SECRET_KEY'),
                'sandbox' => env('DOJAH_SANDBOX', true),
            ],
        ],
    ],

];
