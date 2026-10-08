<?php

namespace App\Services\Analytics;

class TrendDetectionService
{
    /**
     * Detect strike price-action & OI trend
     * Returns:
     * - 'Long Build-up' (Price Up, OI Up)
     * - 'Short Build-up' (Price Down, OI Up)
     * - 'Short Covering' (Price Up, OI Down)
     * - 'Long Unwinding' (Price Down, OI Down)
     * - 'Call Writing' (CE OI Up, Price Down/Neutral)
     * - 'Put Writing' (PE OI Up, Price Down/Neutral)
     * - 'OI Increasing'
     * - 'OI Decreasing'
     */
    public function detectStrikeTrend(
        string $optionType,
        float $priceChange,
        int $oiChange,
        int $currentOi,
        int $prevOi
    ): string {
        $priceUp = $priceChange > 0.05;
        $priceDown = $priceChange < -0.05;
        $oiUp = $oiChange > 500;
        $oiDown = $oiChange < -500;

        if ($priceUp && $oiUp) {
            return 'Long Build-up';
        }

        if ($priceDown && $oiUp) {
            if ($optionType === 'CE') {
                return 'Call Writing';
            }

            return 'Short Build-up';
        }

        if ($priceUp && $oiDown) {
            return 'Short Covering';
        }

        if ($priceDown && $oiDown) {
            return 'Long Unwinding';
        }

        if ($oiUp) {
            return ($optionType === 'PE') ? 'Put Writing' : 'OI Increasing';
        }

        if ($oiDown) {
            return 'OI Decreasing';
        }

        return 'Neutral';
    }

    /**
     * Detect market sentiment based on PCR and visible strike totals
     */
    public function detectMarketSentiment(float $pcr, int $callOiChange, int $putOiChange): string
    {
        if ($pcr >= 1.3 && $putOiChange > $callOiChange) {
            return 'Strongly Bullish (Heavy Put Writing)';
        }

        if ($pcr >= 1.0) {
            return 'Mildly Bullish (Put Bias)';
        }

        if ($pcr <= 0.7 && $callOiChange > $putOiChange) {
            return 'Strongly Bearish (Heavy Call Writing)';
        }

        if ($pcr < 1.0) {
            return 'Mildly Bearish (Call Bias)';
        }

        return 'Rangebound / Neutral';
    }
}
