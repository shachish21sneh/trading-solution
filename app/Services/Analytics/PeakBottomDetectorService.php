<?php

namespace App\Services\Analytics;

use App\Models\StrikeAnalytic;
use Carbon\Carbon;

class PeakBottomDetectorService
{
    /**
     * Update strike peak and bottom metrics and return analytic record.
     */
    public function processStrikeOi(
        int $underlyingId,
        string $symbol,
        float $strikePrice,
        string $optionType,
        int $currentOi,
        int $changeOi
    ): StrikeAnalytic {
        $now = Carbon::now();

        $analytic = StrikeAnalytic::firstOrNew([
            'underlying_id' => $underlyingId,
            'strike_price' => $strikePrice,
            'option_type' => $optionType,
        ], [
            'symbol' => $symbol,
            'peak_oi' => $currentOi,
            'peak_time' => $now,
            'lowest_point' => $currentOi,
            'bottom_time' => $now,
            'current_oi' => $currentOi,
            'prev_oi' => $currentOi,
            'current_trend' => 'Neutral',
            'trend_started_at' => $now,
        ]);

        $prevOi = $analytic->current_oi ?: $currentOi;
        $analytic->prev_oi = $prevOi;
        $analytic->current_oi = $currentOi;

        // Peak Formation Logic
        if ($currentOi > $analytic->peak_oi) {
            $analytic->peak_oi = $currentOi;
            $analytic->peak_time = $now;
        }

        // Bottom Formation Logic
        if ($analytic->lowest_point == 0 || $currentOi < $analytic->lowest_point) {
            $analytic->lowest_point = $currentOi;
            $analytic->bottom_time = $now;
        }

        // Calculate Drop % from Peak
        if ($analytic->peak_oi > 0) {
            $dropPct = round((($analytic->peak_oi - $currentOi) / $analytic->peak_oi) * 100, 2);
            if ($dropPct > $analytic->highest_drop_pct) {
                $analytic->highest_drop_pct = $dropPct;
            }
        }

        // Trend Reversal & Formation Detection
        // 1. Peak Reversal: If OI starts falling after reaching highest value -> 🔴 OI Unwinding
        if ($analytic->peak_oi > 0 && $currentOi < $prevOi && ($analytic->peak_oi - $currentOi) > 2000) {
            if ($analytic->current_trend !== 'OI Unwinding') {
                $analytic->current_trend = 'OI Unwinding';
                $analytic->started_decreasing_at = $now;
                $analytic->trend_started_at = $now;
            }
        }
        // 2. Bottom Reversal: If OI continuously decreased and starts increasing -> 🟢 Fresh OI Build-up
        elseif ($currentOi > $prevOi && $analytic->lowest_point > 0 && ($currentOi - $analytic->lowest_point) > 2000) {
            if ($analytic->current_trend !== 'Fresh OI Build-up') {
                $analytic->current_trend = 'Fresh OI Build-up';
                $analytic->recovered_at = $now;
                $analytic->started_increasing_at = $now;
                $analytic->trend_started_at = $now;
            }
        }
        // 3. Normal progression
        elseif ($currentOi > $prevOi) {
            if (!$analytic->started_increasing_at) {
                $analytic->started_increasing_at = $now;
            }
        } elseif ($currentOi < $prevOi) {
            if (!$analytic->started_decreasing_at) {
                $analytic->started_decreasing_at = $now;
            }
        }

        $analytic->save();

        return $analytic;
    }
}
