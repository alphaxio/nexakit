<?php

namespace Alphaxio\Nexakit\Pay\Drivers;

use Illuminate\Support\Facades\Http;
use Alphaxio\Nexakit\Pay\Contracts\PaymentGateway;
use Alphaxio\Nexakit\Pay\DTOs\ChargeRequest;
use Alphaxio\Nexakit\Pay\DTOs\PaymentResponse;
use Alphaxio\Nexakit\Pay\Concerns\HandlesMinorUnits;
use RuntimeException;

class PaystackDriver implements PaymentGateway
{
    use HandlesMinorUnits;

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

        $payload = $response->json();
        if (isset($payload['data']['metadata']) && is_string($payload['data']['metadata'])) {
            $payload['data']['metadata'] = json_decode($payload['data']['metadata'], true) ?? $payload['data']['metadata'];
        }

        $data = $payload['data'] ?? [];
        $currency = $data['currency'] ?? 'NGN';
        $majorAmount = $this->convertToMajorUnits($data['amount'] ?? 0, $currency);
        $metadata = $data['metadata'] ?? [];

        return new PaymentResponse(
            status: $this->normalizeStatus($data['status'] ?? 'failed'),
            reference: $data['reference'] ?? $reference,
            amount: $majorAmount,
            currency: $currency,
            gateway: 'paystack',
            meta: $payload,
            metadata: $metadata
        );
    }

    /**
     * Refund a completed transaction.
     */
    public function refund(string $reference, float|int $amount, ?string $reason = null): PaymentResponse
    {
        // Fetch the real transaction currency first
        $verifyResponse = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/transaction/verify/" . rawurlencode($reference));

        if ($verifyResponse->failed() || !$verifyResponse->json('status')) {
            throw new RuntimeException('Paystack refund lookup failed: Cannot verify transaction details.');
        }

        $currency = $verifyResponse->json('data.currency') ?? config('nexakit.pay.currency', 'NGN');
        $minorAmount = $this->convertToMinorUnits($amount, $currency);

        $payload = [
            'transaction' => $reference,
            'amount' => $minorAmount,
        ];

        if ($reason) {
            $payload['customer_note'] = $reason;
            $payload['merchant_note'] = $reason;
        }

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/refund", $payload);

        if ($response->failed() || !$response->json('status')) {
            throw new RuntimeException('Paystack refund failed: ' . ($response->json('message') ?? 'Error'));
        }

        $payloadResponse = $response->json();
        // Decode nested transaction metadata if present
        if (isset($payloadResponse['data']['transaction']['metadata']) && is_string($payloadResponse['data']['transaction']['metadata'])) {
            $payloadResponse['data']['transaction']['metadata'] = json_decode($payloadResponse['data']['transaction']['metadata'], true) ?? $payloadResponse['data']['transaction']['metadata'];
        }

        $rawMetadata = $payloadResponse['data']['metadata'] ?? [];
        $metadata = is_string($rawMetadata) ? (json_decode($rawMetadata, true) ?? []) : $rawMetadata;
        $refundStatus = $payloadResponse['data']['status'] ?? 'pending';

        return new PaymentResponse(
            status: $this->normalizeStatus($refundStatus),
            reference: $reference,
            amount: $amount,
            currency: $payloadResponse['data']['currency'] ?? $currency,
            gateway: 'paystack',
            meta: $payloadResponse,
            metadata: $metadata
        );
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
            'success', 'processed'  => 'success',
            'ongoing', 'pending',
            'queued', 'processing'  => 'pending',
            'abandoned', 'reversed',
            'failed'                => 'failed',
            default                 => 'failed',
        };
    }
}
