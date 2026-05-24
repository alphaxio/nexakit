<?php

namespace Alphaxio\Nexakit\Pay\Drivers;

use Illuminate\Support\Facades\Http;
use Alphaxio\Nexakit\Pay\Contracts\PaymentGateway;
use Alphaxio\Nexakit\Pay\DTOs\ChargeRequest;
use Alphaxio\Nexakit\Pay\DTOs\PaymentResponse;
use Alphaxio\Nexakit\Pay\Concerns\HandlesMinorUnits;
use RuntimeException;

class FlutterwaveDriver implements PaymentGateway
{
    use HandlesMinorUnits;

    protected string $baseUrl = 'https://api.flutterwave.com/v3';

    /**
     * Create a new FlutterwaveDriver instance.
     */
    public function __construct(protected array $config)
    {
        if (empty($this->config['secret_key'])) {
            throw new RuntimeException('Flutterwave secret key is missing from configuration.');
        }
    }

    /**
     * Initiate a charge (initialize Flutterwave standard checkout).
     */
    public function initiate(ChargeRequest $request): PaymentResponse
    {
        $payload = [
            'tx_ref' => $request->reference,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'redirect_url' => $request->callbackUrl ?? url('/'),
            'customer' => [
                'email' => $request->email,
            ],
        ];

        // Merge any custom Flutterwave options (e.g., customizations, payment_options)
        $flutterwaveOptions = $request->options['flutterwave'] ?? [];
        
        if (isset($flutterwaveOptions['customer'])) {
            $payload['customer'] = array_merge(
                $payload['customer'],
                $flutterwaveOptions['customer']
            );
            unset($flutterwaveOptions['customer']);
        }
        
        $payload = array_merge($payload, $flutterwaveOptions);

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/payments", $payload);

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new RuntimeException('Flutterwave initialization failed: ' . ($response->json('message') ?? 'Error'));
        }

        return new PaymentResponse(
            status: 'pending',
            reference: $request->reference,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: $response->json('data.link'),
            gateway: 'flutterwave',
            meta: $response->json(),
            metadata: $request->options['flutterwave']['meta'] ?? []
        );
    }

    /**
     * Verify a transaction via its reference.
     */
    public function verify(string $reference): PaymentResponse
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/transactions/verify_by_reference", [
                'tx_ref' => $reference,
            ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new RuntimeException('Flutterwave verification failed: ' . ($response->json('message') ?? 'Error'));
        }

        $data = $response->json('data');
        $rawMetadata = $data['meta'] ?? [];
        $metadata = is_string($rawMetadata) ? (json_decode($rawMetadata, true) ?? []) : $rawMetadata;

        return new PaymentResponse(
            status: $this->normalizeStatus($data['status'] ?? 'failed'),
            reference: $data['tx_ref'] ?? $reference,
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'NGN',
            gateway: 'flutterwave',
            meta: $response->json(),
            metadata: $metadata
        );
    }

    /**
     * Refund a completed transaction.
     */
    public function refund(string $reference, float|int $amount, ?string $reason = null): PaymentResponse
    {
        // 1. Get the Flutterwave transaction ID by calling the API directly
        $verifyResponse = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/transactions/verify_by_reference", [
                'tx_ref' => $reference,
            ]);

        if ($verifyResponse->failed() || $verifyResponse->json('status') !== 'success') {
            throw new RuntimeException('Flutterwave refund lookup failed: Cannot verify transaction details.');
        }

        $transactionId = $verifyResponse->json('data.id') ?? null;
        if (!$transactionId) {
            throw new RuntimeException('Flutterwave refund failed: Could not resolve transaction ID for reference.');
        }

        // 2. Execute the refund request
        $payload = [
            'amount' => $amount,
        ];

        if ($reason) {
            $payload['comments'] = $reason;
        }

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/transactions/{$transactionId}/refund", $payload);

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new RuntimeException('Flutterwave refund failed: ' . ($response->json('message') ?? 'Error'));
        }

        $rawMetadata = $response->json('data.meta') ?? [];
        $metadata = is_string($rawMetadata) ? (json_decode($rawMetadata, true) ?? []) : $rawMetadata;
        $refundStatus = $response->json('data.status') ?? 'pending';

        return new PaymentResponse(
            status: $this->normalizeStatus($refundStatus),
            reference: $reference,
            amount: $amount,
            currency: $response->json('data.currency') ?? 'NGN',
            gateway: 'flutterwave',
            meta: $response->json(),
            metadata: $metadata
        );
    }

    /**
     * Get authorization headers for Flutterwave API.
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->config['secret_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Map Flutterwave status to unified status.
     */
    protected function normalizeStatus(string $status): string
    {
        return match ($status) {
            'successful', 'completed', 'refunded'       => 'success',
            'pending', 'processing'                     => 'pending',
            'failed', 'cancelled', 'reversed', 'error'  => 'failed',
            default                                     => 'failed',
        };
    }
}
