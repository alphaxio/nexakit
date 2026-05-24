# Nexakit

Nexakit is a unified integration toolkit for Laravel, designed to simplify building digital services for African applications.

It normalizes differences across various third-party APIs, providing unified interfaces, standardizing responses, and streamlining configurations.

## Requirements

* **PHP**: `^8.2` or higher
* **Laravel**: `10.x` | `11.x` | `12.x` | `13.x`

## Installation

Install the package via Composer:

```bash
composer require alphaxio/nexakit
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="nexakit-config"
```

## Configuration

Define your credentials in your `.env` file:

```env
# Default Payment Driver (paystack, flutterwave, sandbox)
# Defaults to "sandbox" if not defined here.
NEXAKIT_PAY_DRIVER=paystack

# Paystack Configuration
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...

# Flutterwave Configuration
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST-...
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-...
```

---

## Payments Usage

Nexakit uses a fluent interface and a standardized DTO (`PaymentResponse`) for all payment operations.

### 1. Initialize a Payment

Pass the transaction amount directly in major units (e.g., `2500` for ₦2,500). Nexakit converts it to minor units internally if required by the gateway.

You can pass an email address string directly, or pass a user model to `to()` to automatically resolve the email address.

```php
use Alphaxio\Nexakit\Facades\Pay;

$response = Pay::charge()
    ->amount(18000)
    ->currency('NGN')
    ->to($user) // Resolves email from $user model automatically, or pass "seun.adebayo@gmail.com"
    ->reference('pay_1779617591_STn35W')
    ->callbackUrl('https://pienexa.com/payments/callback')
    ->via('paystack') // Defaults to NEXAKIT_PAY_DRIVER if omitted
    ->with([
        'paystack' => [
            'metadata' => [
                'environment' => 'production',
                'invoice_no' => 'INV-2026-0042',
            ]
        ]
    ])
    ->initialize();

// Redirect user to the checkout page
return redirect($response->redirectUrl);
```

### 2. Verify a Payment

Trigger verification inside your callback or webhook handler. Normalized statuses always map to `'success'`, `'pending'`, or `'failed'`.

```php
use Alphaxio\Nexakit\Facades\Pay;

$response = Pay::driver('paystack')->verify('pay_1779617591_STn35W');

if ($response->isSuccessful()) {
    $amount = $response->amount;       // 18000 (float/int)
    $metadata = $response->metadata;   // ['environment' => 'production', 'invoice_no' => 'INV-2026-0042']
    
    // Access the raw payload returned by the gateway API
    $rawResponse = $response->meta;
}
```

### 3. Refund a Payment

Perform complete or partial refunds. Specify the refund amount in major units.

```php
use Alphaxio\Nexakit\Facades\Pay;

$response = Pay::driver('paystack')->refund(
    reference: 'pay_1779617591_STn35W',
    amount: 10000, // NGN 10,000 partial refund
    reason: 'Customer request'
);

if ($response->isSuccessful()) {
    // Refund processed successfully
}
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
