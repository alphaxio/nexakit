<?php

namespace Alphaxio\Nexakit\Pay\Drivers;

use Illuminate\Support\Facades\Http;
use Alphaxio\Nexakit\Pay\Contracts\PaymentGateway;
use Alphaxio\Nexakit\Pay\DTOs\ChargeRequest;
use Alphaxio\Nexakit\Pay\DTOs\PaymentResponse;
use Alphaxio\Nexakit\Pay\Concerns\HandlesMinorUnits;
use RuntimeException;

class StripeDriver implements PaymentGateway
{
    use HandlesMinorUnits;

    protected string $baseUrl = 'https://api.stripe.com/v1';

    /**
     * Create a new StripeDriver instance.
     */
    public function __construct(protected array $config)
    {
        if (empty($this->config['secret_key'])) {
            throw new RuntimeException('Stripe secret key is missing from configuration.');
        }
    }

    /**
     * Initiate a charge (initialize Stripe checkout session).
     */
    public function initiate(ChargeRequest $request): PaymentResponse
    {
        $minorAmount = $this->convertToMinorUnits($request->amount, $request->currency);

        $cancelUrl = $request->cancelUrl ?? $request->getOption('stripe', 'cancel_url') ?? $request->callbackUrl ?? config('app.url');

        $payload = [
            'mode' => 'payment',
            'success_url' => $request->callbackUrl ?? config('app.url'),
            'cancel_url' => $cancelUrl,
            'customer_email' => $request->email,
            'client_reference_id' => $request->reference,
            'line_items[0][price_data][currency]' => strtolower($request->currency),
            'line_items[0][price_data][unit_amount]' => $minorAmount,
            'line_items[0][price_data][product_data][name]' => 'Payment Ref: ' . $request->reference,
            'line_items[0][quantity]' => 1,
        ];

        // Merge custom Stripe options
        $stripeOptions = $request->options['stripe'] ?? [];
        if (!empty($stripeOptions)) {
            // Remove cancel_url if it was set inside stripe options since we already handled it above
            unset($stripeOptions['cancel_url']);
            $flattenedOptions = $this->flattenPayload($stripeOptions);
            $payload = array_merge($payload, $flattenedOptions);
        }

        $response = Http::asForm()
            ->withBasicAuth($this->config['secret_key'], '')
            ->withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/checkout/sessions", $payload);

        if ($response->failed() || empty($response->json('id'))) {
            throw new RuntimeException('Stripe initialization failed: ' . ($response->json('error.message') ?? 'Error'));
        }

        return new PaymentResponse(
            status: 'pending',
            reference: $request->reference,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: $response->json('url'),
            gateway: 'stripe',
            meta: $response->json(),
            metadata: $stripeOptions['metadata'] ?? []
        );
    }

    /**
     * Verify a transaction via its session ID or client reference.
     */
    public function verify(string $reference): PaymentResponse
    {
        $session = $this->resolveCheckoutSession($reference);

        $paymentStatus = $session['payment_status'] ?? 'unpaid';
        $sessionStatus = $session['status'] ?? 'open';

        $status = match(true) {
            $paymentStatus === 'paid'        => 'success',
            $sessionStatus === 'open'        => 'pending',
            $sessionStatus === 'expired'     => 'failed',
            default                          => 'failed',
        };

        $currency = strtoupper($session['currency'] ?? 'USD');
        $majorAmount = $this->convertToMajorUnits($session['amount_total'] ?? 0, $currency);
        $metadata = $session['metadata'] ?? [];

        return new PaymentResponse(
            status: $status,
            reference: $session['client_reference_id'] ?? $reference,
            amount: $majorAmount,
            currency: $currency,
            gateway: 'stripe',
            meta: $session,
            metadata: $metadata
        );
    }

    /**
     * Refund a transaction.
     */
    public function refund(string $reference, float|int $amount, ?string $reason = null): PaymentResponse
    {
        $session = $this->resolveCheckoutSession($reference);

        $paymentIntent = $session['payment_intent'] ?? null;
        if (!$paymentIntent) {
            throw new RuntimeException('Cannot refund: Checkout session is unpaid or payment intent is missing.');
        }

        $currency = strtoupper($session['currency'] ?? 'USD');
        $minorAmount = $this->convertToMinorUnits($amount, $currency);

        $payload = [
            'payment_intent' => $paymentIntent,
            'amount' => $minorAmount,
        ];

        if ($reason) {
            $allowedReasons = ['duplicate', 'fraudulent', 'requested_by_customer'];
            $payload['reason'] = in_array($reason, $allowedReasons) ? $reason : 'requested_by_customer';
            $payload['metadata[reason]'] = $reason;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->config['secret_key'], '')
            ->withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/refunds", $payload);

        if ($response->failed() || empty($response->json('id'))) {
            throw new RuntimeException('Stripe refund failed: ' . ($response->json('error.message') ?? 'Error'));
        }

        $refundStatus = $response->json('status');

        $status = match($refundStatus) {
            'succeeded' => 'success',
            'pending'   => 'pending',
            default     => 'failed',
        };

        return new PaymentResponse(
            status: $status,
            reference: $reference,
            amount: $amount,
            currency: $currency,
            gateway: 'stripe',
            meta: $response->json(),
            metadata: $response->json('metadata') ?? []
        );
    }

    /**
     * Resolve checkout session by session ID or client reference ID.
     */
    protected function resolveCheckoutSession(string $reference): array
    {
        $reference = trim($reference);
        if (empty($reference)) {
            throw new \InvalidArgumentException('Transaction reference cannot be empty.');
        }

        // If reference is a Stripe checkout session ID directly
        if (str_starts_with($reference, 'cs_')) {
            $response = Http::withBasicAuth($this->config['secret_key'], '')
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/checkout/sessions/{$reference}");

            if ($response->successful()) {
                return $response->json();
            }
        }

        // Otherwise search for session by listing and filtering by client_reference_id
        // Since Stripe API does not support direct filtering by client_reference_id
        $startingAfter = null;
        $pagesLimit = 3; // Look up to 3 pages (300 sessions) to avoid performance issues

        for ($i = 0; $i < $pagesLimit; $i++) {
            $params = ['limit' => 100];
            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $response = Http::withBasicAuth($this->config['secret_key'], '')
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/checkout/sessions", $params);

            if ($response->failed()) {
                break;
            }

            $sessions = $response->json('data') ?? [];
            if (empty($sessions)) {
                break;
            }

            foreach ($sessions as $session) {
                if (($session['client_reference_id'] ?? null) === $reference) {
                    return $session;
                }
            }

            if (!$response->json('has_more')) {
                break;
            }

            $lastSession = $sessions[count($sessions) - 1];
            $startingAfter = $lastSession['id'] ?? null;
            if (!$startingAfter) {
                break;
            }
        }

        throw new RuntimeException("Stripe checkout session not found for reference: {$reference}");
    }

    /**
     * Get the default headers for the Stripe API.
     */
    protected function getHeaders(): array
    {
        $version = $this->config['api_version'] ?? '2024-06-20';

        return [
            'Stripe-Version' => $version,
            'Accept'         => 'application/json',
        ];
    }

    /**
     * Recursively flatten a nested array into Stripe-compatible form parameters.
     */
    protected function flattenPayload(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenPayload($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }
}
