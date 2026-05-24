<?php

namespace Alphaxio\Nexakit\Pay\Drivers;

use Illuminate\Support\Facades\Http;
use Alphaxio\Nexakit\Pay\Contracts\PaymentGateway;
use Alphaxio\Nexakit\Pay\DTOs\ChargeRequest;
use Alphaxio\Nexakit\Pay\DTOs\PaymentResponse;
use RuntimeException;

class PaystackDriver implements PaymentGateway
{
    protected string $baseUrl = 'https://api.paystack.co';

    /**
     * Create a new PaystackDriver instance.
     */
    public function __construct(protected array $config)
    {
        if (empty($this->config['secret_key'])) {
            throw new RuntimeException('Paystack secret key is missing from configuration.');
        }
    }

    /**
     * Initiate a charge (initialize Paystack checkout).
     */
    public function initiate(ChargeRequest $request): PaymentResponse
    {
        // Build base Paystack payload with minor units
        $minorAmount = $this->convertToMinorUnits($request->amount, $request->currency);

        $payload = [
            'amount' => $minorAmount,
            'email' => $request->email,
            'reference' => $request->reference,
            'currency' => $request->currency,
        ];

        if ($request->callbackUrl) {
            $payload['callback_url'] = $request->callbackUrl;
        }

        // Merge any custom Paystack driver options (e.g., metadata, split_code)
        $paystackOptions = $request->options['paystack'] ?? [];
        $payload = array_merge($payload, $paystackOptions);

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        if ($response->failed() || !$response->json('status')) {
            throw new RuntimeException('Paystack initialization failed: ' . ($response->json('message') ?? 'Error'));
        }

        return new PaymentResponse(
            status: 'pending',
            reference: $request->reference,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: $response->json('data.authorization_url'),
            gateway: 'paystack',
            meta: $response->json(),
            metadata: $request->options['paystack']['metadata'] ?? []
        );
    }

    /**
     * Verify a transaction via its reference.
     */
    public function verify(string $reference): PaymentResponse
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/transaction/verify/" . rawurlencode($reference));

        if ($response->failed() || !$response->json('status')) {
            throw new RuntimeException('Paystack verification failed: ' . ($response->json('message') ?? 'Error'));
        }

        $data = $response->json('data');
        $currency = $data['currency'] ?? 'NGN';
        $majorAmount = $this->convertToMajorUnits($data['amount'] ?? 0, $currency);
        $rawMetadata = $data['metadata'] ?? [];
        $metadata = is_string($rawMetadata) ? (json_decode($rawMetadata, true) ?? []) : $rawMetadata;

        return new PaymentResponse(
            status: $this->normalizeStatus($data['status'] ?? 'failed'),
            reference: $data['reference'],
            amount: $majorAmount,
            currency: $currency,
            gateway: 'paystack',
            meta: $response->json(),
            metadata: $metadata
        );
    }

    /**
     * Refund a completed transaction.
     */
    public function refund(string $reference, float|int $amount, ?string $reason = null): PaymentResponse
    {
        $currency = config('nexakit.pay.currency', 'NGN');
        $minorAmount = $this->convertToMinorUnits($amount, $currency);

        $payload = [
            'transaction' => $reference,
            'amount' => $minorAmount,
        ];

        if ($reason) {
            $payload['customer_note'] = $reason;
        }

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/refund", $payload);

        if ($response->failed() || !$response->json('status')) {
            throw new RuntimeException('Paystack refund failed: ' . ($response->json('message') ?? 'Error'));
        }

        $rawMetadata = $response->json('data.metadata') ?? [];
        $metadata = is_string($rawMetadata) ? (json_decode($rawMetadata, true) ?? []) : $rawMetadata;

        return new PaymentResponse(
            status: 'success',
            reference: $reference,
            amount: $amount,
            currency: $response->json('data.currency') ?? $currency,
            gateway: 'paystack',
            meta: $response->json(),
            metadata: $metadata
        );
    }

    /**
     * Convert major currency units to minor units (e.g., Naira to Kobo).
     */
    protected function convertToMinorUnits(float|int $amount, string $currency): int
    {
        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'SLL', 'UGX', 'VUV', 'XAF', 'XOF', 'XPF'
        ];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return (int) $amount;
        }

        return (int) round($amount * 100);
    }

    /**
     * Convert minor currency units to major units (e.g., Kobo to Naira).
     */
    protected function convertToMajorUnits(float|int $amount, string $currency): float|int
    {
        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'SLL', 'UGX', 'VUV', 'XAF', 'XOF', 'XPF'
        ];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return $amount;
        }

        return $amount / 100;
    }

    /**
     * Get default authorization headers for Paystack API.
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
     * Map Paystack status string to unified status.
     */
    protected function normalizeStatus(string $status): string
    {
        return match ($status) {
            'success' => 'success',
            'ongoing', 'pending' => 'pending',
            default => 'failed',
        };
    }
}
