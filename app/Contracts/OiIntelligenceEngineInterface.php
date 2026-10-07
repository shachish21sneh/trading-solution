<?php

namespace App\Contracts;

use App\Models\Underlying;

interface OiIntelligenceEngineInterface
{
    /**
     * Process live option chain through the intelligence engine:
     * - Filters ATM ± 5 strikes
     * - Runs Peak and Bottom detection
     * - Runs Strike-level trend classification (Fresh OI, Unwinding, Long/Short build-up, etc.)
     * - Computes Support & Resistance levels + Shift detection
     * - Computes Totals, Averages, PCR, Net Difference, Max Pain
     */
    public function processChain(Underlying $underlying, array $rawChainData, string $expiryDate): array;

    /**
     * Detect primary & secondary support and resistance strikes
     */
    public function detectSupportResistance(array $strikesData): array;

    /**
     * Compute Max Pain level across all strikes
     */
    public function computeMaxPain(array $strikesData): float;
}
