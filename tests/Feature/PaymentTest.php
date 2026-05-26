<?php

namespace Alphaxio\Nexakit\Tests\Feature;

use Alphaxio\Nexakit\Tests\TestCase;
use Alphaxio\Nexakit\Facades\Pay;
use Alphaxio\Nexakit\Pay\Builders\ChargeBuilder;
use Alphaxio\Nexakit\Pay\DTOs\PaymentResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_charge_builder()
    {
        $builder = Pay::charge();
        $this->assertInstanceOf(ChargeBuilder::class, $builder);
    }

    /** @test */
    public function it_validates_required_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount is required.');

        Pay::charge()
            ->email('alphaxio47@gmail.com')
            ->initialize();
    }

    /** @test */
    public function it_validates_email_format()
    {
        $this->expectException(ValidationException::class);

        Pay::charge()
            ->amount(1000)
            ->email('invalid-email-format')
            ->initialize();
    }

    /** @test */
    public function it_works_with_sandbox_driver_by_default()
    {
        $response = Pay::charge()
            ->amount(5000)
            ->email('alphaxio47@gmail.com')
            ->initialize();

        $this->assertInstanceOf(PaymentResponse::class, $response);
        $this->assertTrue($response->isPending());
        $this->assertEquals('sandbox', $response->gateway);
        $this->assertStringContainsString('sandbox/checkout', $response->redirectUrl);

        $verify = Pay::driver('sandbox')->verify($response->reference);
        $this->assertTrue($verify->isSuccessful());

        $refund = Pay::driver('sandbox')->refund($response->reference, 5000);
        $this->assertTrue($refund->isSuccessful());
    }

    /** @test */
    public function it_can_initialize_paystack_payment()
    {
        $reference = $this->generateReference();

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Initialization successful',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/paystack-checkout-url',
                    'access_code' => 'sandbox-access-code',
                    'reference' => $reference
                ]
            ], 200)
        ]);

        $response = Pay::charge()
            ->via('paystack')
            ->amount(5000)
            ->email('alphaxio47@gmail.com')
            ->reference($reference)
            ->initialize();

        $this->assertTrue($response->isPending());
        $this->assertEquals('https://checkout.paystack.com/paystack-checkout-url', $response->redirectUrl);
        $this->assertEquals('paystack', $response->gateway);

        Http::assertSent(function ($request) use ($reference) {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request['amount'] === 500000
                && $request['email'] === 'alphaxio47@gmail.com'
                && $request['reference'] === $reference;
        });
    }

    /** @test */
    public function it_can_verify_paystack_payment()
    {
        $reference = $this->generateReference();

        Http::fake([
            "https://api.paystack.co/transaction/verify/{$reference}" => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 500000,
                    'currency' => 'NGN'
                ]
            ], 200)
        ]);

        $response = Pay::driver('paystack')->verify($reference);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals($reference, $response->reference);
        $this->assertEquals(5000, $response->amount);
        $this->assertEquals('NGN', $response->currency);
    }

    /** @test */
    public function it_can_initialize_flutterwave_payment()
    {
        $reference = $this->generateReference();

        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'message' => 'Hosted Link Created',
                'data' => [
                    'link' => 'https://checkout.flutterwave.com/flutterwave-checkout-url'
                ]
            ], 200)
        ]);

        $response = Pay::charge()
            ->via('flutterwave')
            ->amount(5000)
            ->email('alphaxio47@gmail.com')
            ->reference($reference)
            ->initialize();

        $this->assertTrue($response->isPending());
        $this->assertEquals('https://checkout.flutterwave.com/flutterwave-checkout-url', $response->redirectUrl);
        $this->assertEquals('flutterwave', $response->gateway);

        Http::assertSent(function ($request) use ($reference) {
            return $request->url() === 'https://api.flutterwave.com/v3/payments'
                && $request['amount'] === 5000
                && $request['customer']['email'] === 'alphaxio47@gmail.com'
                && $request['tx_ref'] === $reference;
        });
    }

    /** @test */
    public function it_can_verify_flutterwave_payment()
    {
        $reference = $this->generateReference();

        Http::fake([
            "https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref={$reference}" => Http::response([
                'status' => 'success',
                'data' => [
                    'status' => 'successful',
                    'tx_ref' => $reference,
                    'amount' => 5000,
                    'currency' => 'NGN'
                ]
            ], 200)
        ]);

        $response = Pay::driver('flutterwave')->verify($reference);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals($reference, $response->reference);
        $this->assertEquals(5000, $response->amount);
    }

    /** @test */
    public function it_can_refund_flutterwave_payment_via_two_step_lookup()
    {
        $reference = $this->generateReference();

        Http::fake([
            // Step 1: Verify call to look up FLW Transaction ID from reference
            "https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref={$reference}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 999111,
                    'status' => 'successful',
                    'tx_ref' => $reference,
                    'amount' => 5000,
                    'currency' => 'NGN'
                ]
            ], 200),
            // Step 2: Refund call using resolved ID 999111
            'https://api.flutterwave.com/v3/transactions/999111/refund' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 888222,
                    'amount' => 5000,
                    'currency' => 'NGN',
                    'status' => 'refunded'
                ]
            ], 200)
        ]);

        $response = Pay::driver('flutterwave')->refund($reference, 5000, 'Customer request');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(5000, $response->amount);
    }

    /** @test */
    public function it_can_initialize_stripe_payment()
    {
        $reference = $this->generateReference();

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_session_id',
                'url' => 'https://checkout.stripe.com/pay/cs_test_session_id',
                'payment_status' => 'unpaid',
                'status' => 'open',
                'client_reference_id' => $reference,
                'amount_total' => 500000,
                'currency' => 'usd'
            ], 200)
        ]);

        $response = Pay::charge()
            ->via('stripe')
            ->amount(5000)
            ->currency('USD')
            ->email('alphaxio47@gmail.com')
            ->reference($reference)
            ->initialize();

        $this->assertTrue($response->isPending());
        $this->assertEquals('https://checkout.stripe.com/pay/cs_test_session_id', $response->redirectUrl);
        $this->assertEquals('stripe', $response->gateway);

        Http::assertSent(function ($request) use ($reference) {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && (int) $request['line_items[0][price_data][unit_amount]'] === 500000
                && $request['customer_email'] === 'alphaxio47@gmail.com'
                && $request['client_reference_id'] === $reference;
        });
    }

    /** @test */
    public function it_can_verify_stripe_payment_by_session_id()
    {
        $sessionId = 'cs_test_session_id';

        Http::fake([
            "https://api.stripe.com/v1/checkout/sessions/{$sessionId}" => Http::response([
                'id' => $sessionId,
                'payment_status' => 'paid',
                'status' => 'complete',
                'client_reference_id' => 'tx_1234567890',
                'amount_total' => 500000,
                'currency' => 'usd',
                'metadata' => [
                    'invoice_no' => 'INV-001'
                ]
            ], 200)
        ]);

        $response = Pay::driver('stripe')->verify($sessionId);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('tx_1234567890', $response->reference);
        $this->assertEquals(5000, $response->amount);
        $this->assertEquals('USD', $response->currency);
        $this->assertEquals(['invoice_no' => 'INV-001'], $response->metadata);
    }

    /** @test */
    public function it_can_verify_stripe_payment_by_client_reference_id()
    {
        $reference = 'tx_1234567890';

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions*' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'cs_test_session_id_other',
                        'payment_status' => 'unpaid',
                        'status' => 'open',
                        'client_reference_id' => 'tx_9999999999',
                        'amount_total' => 300000,
                        'currency' => 'usd',
                        'metadata' => []
                    ],
                    [
                        'id' => 'cs_test_session_id',
                        'payment_status' => 'paid',
                        'status' => 'complete',
                        'client_reference_id' => $reference,
                        'amount_total' => 500000,
                        'currency' => 'usd',
                        'metadata' => [
                            'invoice_no' => 'INV-001'
                        ]
                    ]
                ],
                'has_more' => false
            ], 200)
        ]);

        $response = Pay::driver('stripe')->verify($reference);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals($reference, $response->reference);
        $this->assertEquals(5000, $response->amount);
        $this->assertEquals('USD', $response->currency);
        $this->assertEquals(['invoice_no' => 'INV-001'], $response->metadata);
    }

    /** @test */
    public function it_can_refund_stripe_payment()
    {
        $sessionId = 'cs_test_session_id';

        Http::fake([
            "https://api.stripe.com/v1/checkout/sessions/{$sessionId}" => Http::response([
                'id' => $sessionId,
                'payment_intent' => 'pi_test_intent_id',
                'payment_status' => 'paid',
                'status' => 'complete',
                'client_reference_id' => 'tx_1234567890',
                'amount_total' => 500000,
                'currency' => 'usd'
            ], 200),
            'https://api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_refund_id',
                'amount' => 500000,
                'currency' => 'usd',
                'status' => 'succeeded'
            ], 200)
        ]);

        $response = Pay::driver('stripe')->refund($sessionId, 5000, 'Customer request');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(5000, $response->amount);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.stripe.com/v1/refunds'
                && $request['payment_intent'] === 'pi_test_intent_id'
                && (int) $request['amount'] === 500000;
        });
    }

    /** @test */
    public function it_maps_pending_stripe_refund_status_correctly()
    {
        $sessionId = 'cs_test_session_id';

        Http::fake([
            "https://api.stripe.com/v1/checkout/sessions/{$sessionId}" => Http::response([
                'id' => $sessionId,
                'payment_intent' => 'pi_test_intent_id',
                'payment_status' => 'paid',
                'status' => 'complete',
                'client_reference_id' => 'tx_1234567890',
                'amount_total' => 500000,
                'currency' => 'usd'
            ], 200),
            'https://api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_refund_id',
                'amount' => 500000,
                'currency' => 'usd',
                'status' => 'pending'
            ], 200)
        ]);

        $response = Pay::driver('stripe')->refund($sessionId, 5000, 'Customer request');

        $this->assertTrue($response->isPending());
    }

    /** @test */
    public function it_can_refund_paystack_payment()
    {
        $reference = $this->generateReference();

        Http::fake([
            // Step 1: Verify transaction to resolve currency and check status
            "https://api.paystack.co/transaction/verify/{$reference}" => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 500000,
                    'currency' => 'USD'
                ]
            ], 200),
            // Step 2: Refund transaction using verified details
            'https://api.paystack.co/refund' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'processed',
                    'transaction' => [
                        'reference' => $reference
                    ],
                    'amount' => 500000,
                    'currency' => 'USD'
                ]
            ], 200)
        ]);

        $response = Pay::driver('paystack')->refund($reference, 5000, 'requested_by_customer');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(5000, $response->amount);
        $this->assertEquals('USD', $response->currency);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.paystack.co/refund'
                && (int) $request['amount'] === 500000;
        });
    }

    /** @test */
    public function it_throws_on_failed_paystack_initialization()
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => false,
                'message' => 'Invalid key'
            ], 401)
        ]);

        $this->expectException(\RuntimeException::class);

        Pay::charge()
            ->via('paystack')
            ->amount(5000)
            ->email('test@example.com')
            ->initialize();
    }

    /** @test */
    public function it_throws_on_failed_flutterwave_initialization()
    {
        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'error',
                'message' => 'Invalid key'
            ], 401)
        ]);

        $this->expectException(\RuntimeException::class);

        Pay::charge()
            ->via('flutterwave')
            ->amount(5000)
            ->email('test@example.com')
            ->initialize();
    }

    /** @test */
    public function it_throws_on_failed_stripe_initialization()
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'error' => [
                    'message' => 'Invalid API key'
                ]
            ], 401)
        ]);

        $this->expectException(\RuntimeException::class);

        Pay::charge()
            ->via('stripe')
            ->amount(5000)
            ->email('test@example.com')
            ->initialize();
    }
}
