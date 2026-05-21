<?php

namespace Alphaxio\Nexakit\Tests\Feature;

use Alphaxio\Nexakit\Tests\TestCase;
use Alphaxio\Nexakit\Facades\Pay;
use Alphaxio\Nexakit\Pay\ChargeBuilder;
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
        $reference = 'tx_' . Str::random(12);

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
        $reference = 'tx_' . Str::random(12);

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
        $reference = 'tx_' . Str::random(12);

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
        $reference = 'tx_' . Str::random(12);

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
        $reference = 'tx_' . Str::random(12);

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
}
