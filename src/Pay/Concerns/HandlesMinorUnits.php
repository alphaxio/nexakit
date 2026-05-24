<?php

namespace Alphaxio\Nexakit\Pay\Concerns;

trait HandlesMinorUnits
{
    protected array $zeroDecimalCurrencies = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'SLL', 'UGX', 'VUV', 'XAF', 'XOF', 'XPF'
    ];

    /**
     * Check if a currency is zero-decimal.
     */
    protected function isZeroDecimal(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->zeroDecimalCurrencies);
    }

    /**
     * Convert currency units to minor units.
     */
    protected function convertToMinorUnits(float|int $amount, string $currency): int
    {
        if ($this->isZeroDecimal($currency)) {
            return (int) $amount;
        }

        if (function_exists('bcmul')) {
            return (int) bcmul((string) $amount, '100', 0);
        }

        return (int) round($amount * 100);
    }

    /**
     * Convert minor currency units to major units.
     */
    protected function convertToMajorUnits(float|int $amount, string $currency): float|int
    {
        if ($this->isZeroDecimal($currency)) {
            return $amount;
        }

        return $amount / 100;
    }
}
