<?php

namespace Alphaxio\Nexakit\Pay\Contracts;

use Alphaxio\Nexakit\Pay\DTOs\ChargeRequest;
use Alphaxio\Nexakit\Pay\DTOs\PaymentResponse;

interface PaymentGateway
{
    /**
     * Initiate a charge (redirect/inline payment).
     *
     * @param ChargeRequest $request Standardized charge request object
     * @return PaymentResponse Standardized payment response
     */
    public function initiate(ChargeRequest $request): PaymentResponse;

    /**
     * Verify a transaction's status.
     *
     * @param string $reference Unique transaction reference
     * @return PaymentResponse Standardized payment response
     */
    public function verify(string $reference): PaymentResponse;

    /**
     * Refund a completed transaction.
     *
     * @param string $reference Unique transaction reference
     * @param float|int $amount Amount to refund in major units (e.g. Naira, Dollars)
     * @param string|null $reason Optional description of the refund reason
     * @return PaymentResponse Standardized payment response
     */
    public function refund(string $reference, float|int $amount, ?string $reason = null): PaymentResponse;
}
