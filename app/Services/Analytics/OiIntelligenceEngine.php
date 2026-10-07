<?php

namespace App\Services\Analytics;

use App\Contracts\OiIntelligenceEngineInterface;
use App\Models\OptionSnapshot;
use App\Models\Underlying;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class OiIntelligenceEngine implements OiIntelligenceEngineInterface
{
    public function __construct(
        protected PeakBottomDetectorService $peakBottomDetector,
        protected TrendDetectionService $trendDetector,
        protected HistoricalSparklineService $sparklineService
    ) {}

    public function processChain(Underlying $underlying, array $rawChainData, string $expiryDate): array
    {
        $spotPrice = (float) ($rawChainData['spot_price'] ?? $underlying->spot_price);
        $underlying->spot_price = $spotPrice;
        $underlying->save();

        $atmStrike = $underlying->getAtmStrikeAttribute();
        $visibleStrikes = $underlying->getVisibleStrikes(5); // 11 strikes: ATM-5 to ATM+5
        $allStrikesData = $rawChainData['strikes'] ?? [];
        $now = Carbon::now();

        $processedStrikes = [];
        $snapshotsToInsert = [];

        // Accumulators for Totals
        $callTotalOi = 0;
        $callTotalChangeOi = 0;
        $callTotalVolume = 0;
        $callTotalIvSum = 0;
        $callTotalLtpSum = 0;

        $putTotalOi = 0;
        $putTotalChangeOi = 0;
        $putTotalVolume = 0;
        $putTotalIvSum = 0;
        $putTotalLtpSum = 0;

        $visibleCount = count($visibleStrikes);

        foreach ($visibleStrikes as $strike) {
            $strikeKey = number_format($strike, 2, '.', '');
            $strikeRaw = $allStrikesData[$strikeKey] ?? [
                'strike_price' => $strike,
                'CE' => ['oi' => 0, 'change_oi' => 0, 'volume' => 0, 'iv' => 0.0, 'ltp' => 0.0, 'change' => 0.0],
                'PE' => ['oi' => 0, 'change_oi' => 0, 'volume' => 0, 'iv' => 0.0, 'ltp' => 0.0, 'change' => 0.0],
            ];

            $ceRaw = $strikeRaw['CE'];
            $peRaw = $strikeRaw['PE'];

            // 1. Process CE Analytics & Reversal Detection
            $ceAnalytic = $this->peakBottomDetector->processStrikeOi(
                $underlying->id,
                $underlying->symbol,
                $strike,
                'CE',
                (int) $ceRaw['oi'],
                (int) $ceRaw['change_oi']
            );

            // 2. Process PE Analytics & Reversal Detection
            $peAnalytic = $this->peakBottomDetector->processStrikeOi(
                $underlying->id,
                $underlying->symbol,
                $strike,
                'PE',
                (int) $peRaw['oi'],
                (int) $peRaw['change_oi']
            );

            // 3. Detect Action Trends
            $ceTrend = $this->trendDetector->detectStrikeTrend(
                'CE',
                (float) $ceRaw['change'],
                (int) $ceRaw['change_oi'],
                (int) $ceRaw['oi'],
                $ceAnalytic->prev_oi
            );

            $peTrend = $this->trendDetector->detectStrikeTrend(
                'PE',
                (float) $peRaw['change'],
                (int) $peRaw['change_oi'],
                (int) $peRaw['oi'],
                $peAnalytic->prev_oi
            );

            // If analytic detected peak unwinding or fresh bottom build-up, elevate it
            if ($ceAnalytic->current_trend === 'OI Unwinding' || $ceAnalytic->current_trend === 'Fresh OI Build-up') {
                $ceTrend = $ceAnalytic->current_trend;
            }
            if ($peAnalytic->current_trend === 'OI Unwinding' || $peAnalytic->current_trend === 'Fresh OI Build-up') {
                $peTrend = $peAnalytic->current_trend;
            }

            // Accumulate visible strikes totals
            $callTotalOi += (int) $ceRaw['oi'];
            $callTotalChangeOi += (int) $ceRaw['change_oi'];
            $callTotalVolume += (int) $ceRaw['volume'];
            $callTotalIvSum += (float) $ceRaw['iv'];
            $callTotalLtpSum += (float) $ceRaw['ltp'];

            $putTotalOi += (int) $peRaw['oi'];
            $putTotalChangeOi += (int) $peRaw['change_oi'];
            $putTotalVolume += (int) $peRaw['volume'];
            $putTotalIvSum += (float) $peRaw['iv'];
            $putTotalLtpSum += (float) $peRaw['ltp'];

            // Prepare Snapshot rows for DB (Immutable historical storage)
            $snapshotsToInsert[] = [
                'underlying_id' => $underlying->id,
                'symbol' => $underlying->symbol,
                'expiry_date' => $expiryDate,
                'spot_price' => $spotPrice,
                'strike_price' => $strike,
                'option_type' => 'CE',
                'oi' => (int) $ceRaw['oi'],
                'change_oi' => (int) $ceRaw['change_oi'],
                'volume' => (int) $ceRaw['volume'],
                'iv' => (float) $ceRaw['iv'],
                'ltp' => (float) $ceRaw['ltp'],
                'price_change' => (float) $ceRaw['change'],
                'snapshot_time' => $now,
            ];

            $snapshotsToInsert[] = [
                'underlying_id' => $underlying->id,
                'symbol' => $underlying->symbol,
                'expiry_date' => $expiryDate,
                'spot_price' => $spotPrice,
                'strike_price' => $strike,
                'option_type' => 'PE',
                'oi' => (int) $peRaw['oi'],
                'change_oi' => (int) $peRaw['change_oi'],
                'volume' => (int) $peRaw['volume'],
                'iv' => (float) $peRaw['iv'],
                'ltp' => (float) $peRaw['ltp'],
                'price_change' => (float) $peRaw['change'],
                'snapshot_time' => $now,
            ];

            // Calculate Difference % from previous snapshot
            $ceDiffPct = $ceAnalytic->prev_oi > 0 
                ? round((((int) $ceRaw['oi'] - $ceAnalytic->prev_oi) / $ceAnalytic->prev_oi) * 100, 2)
                : 0.0;
            $peDiffPct = $peAnalytic->prev_oi > 0 
                ? round((((int) $peRaw['oi'] - $peAnalytic->prev_oi) / $peAnalytic->prev_oi) * 100, 2)
                : 0.0;

            // Assemble Full Row Structure
            $processedStrikes[] = [
                'strike_price' => $strike,
                'is_atm' => ($strike == $atmStrike),
                'atm_relation' => $this->getAtmLabel($strike, $atmStrike, $underlying->strike_step),

                // CALL SIDE
                'call' => [
                    'oi' => (int) $ceRaw['oi'],
                    'change_oi' => (int) $ceRaw['change_oi'],
                    'volume' => (int) $ceRaw['volume'],
                    'iv' => (float) $ceRaw['iv'],
                    'ltp' => (float) $ceRaw['ltp'],
                    'change' => (float) $ceRaw['change'],
                    
                    // Indicators
                    'current_trend' => $ceTrend,
                    'prev_snapshot' => $ceAnalytic->prev_oi,
                    'current_snapshot' => (int) $ceRaw['oi'],
                    'difference' => (int) $ceRaw['oi'] - $ceAnalytic->prev_oi,
                    'diff_percent' => $ceDiffPct,
                    'peak_oi' => $ceAnalytic->peak_oi,
                    'peak_time' => $ceAnalytic->peak_time?->format('H:i:s'),
                    'current_vs_peak' => $ceAnalytic->peak_oi - (int) $ceRaw['oi'],
                    'drop_percent' => $ceAnalytic->drop_percentage,
                    'trend_since' => $ceAnalytic->trend_started_at?->diffForHumans() ?: 'Just now',
                    'trend_history' => [
                        'started_increasing' => $ceAnalytic->started_increasing_at?->format('H:i:s'),
                        'peak_time' => $ceAnalytic->peak_time?->format('H:i:s'),
                        'started_decreasing' => $ceAnalytic->started_decreasing_at?->format('H:i:s'),
                        'lowest_point' => $ceAnalytic->lowest_point,
                        'started_recovering' => $ceAnalytic->recovered_at?->format('H:i:s'),
                    ],
                    'sparkline' => $this->sparklineService->getSparkline($underlying->symbol, $strike, 'CE', 30),
                ],

                // PUT SIDE
                'put' => [
                    'oi' => (int) $peRaw['oi'],
                    'change_oi' => (int) $peRaw['change_oi'],
                    'volume' => (int) $peRaw['volume'],
                    'iv' => (float) $peRaw['iv'],
                    'ltp' => (float) $peRaw['ltp'],
                    'change' => (float) $peRaw['change'],

                    // Indicators
                    'current_trend' => $peTrend,
                    'prev_snapshot' => $peAnalytic->prev_oi,
                    'current_snapshot' => (int) $peRaw['oi'],
                    'difference' => (int) $peRaw['oi'] - $peAnalytic->prev_oi,
                    'diff_percent' => $peDiffPct,
                    'peak_oi' => $peAnalytic->peak_oi,
                    'peak_time' => $peAnalytic->peak_time?->format('H:i:s'),
                    'current_vs_peak' => $peAnalytic->peak_oi - (int) $peRaw['oi'],
                    'drop_percent' => $peAnalytic->drop_percentage,
                    'trend_since' => $peAnalytic->trend_started_at?->diffForHumans() ?: 'Just now',
                    'trend_history' => [
                        'started_increasing' => $peAnalytic->started_increasing_at?->format('H:i:s'),
                        'peak_time' => $peAnalytic->peak_time?->format('H:i:s'),
                        'started_decreasing' => $peAnalytic->started_decreasing_at?->format('H:i:s'),
                        'lowest_point' => $peAnalytic->lowest_point,
                        'started_recovering' => $peAnalytic->recovered_at?->format('H:i:s'),
                    ],
                    'sparkline' => $this->sparklineService->getSparkline($underlying->symbol, $strike, 'PE', 30),
                ],
            ];
        }

        // Save snapshots batch to database (Never overwrite history)
        if (!empty($snapshotsToInsert)) {
            OptionSnapshot::insert($snapshotsToInsert);
        }

        // Calculate Totals & Averages for visible strikes
        $pcr = ($callTotalOi > 0) ? round($putTotalOi / $callTotalOi, 2) : 1.0;
        $oiDifference = $putTotalOi - $callTotalOi; // Positive = Put bias, Negative = Call bias

        $totals = [
            'call_total' => [
                'oi' => $callTotalOi,
                'change_oi' => $callTotalChangeOi,
                'volume' => $callTotalVolume,
                'avg_iv' => $visibleCount > 0 ? round($callTotalIvSum / $visibleCount, 2) : 0,
                'avg_ltp' => $visibleCount > 0 ? round($callTotalLtpSum / $visibleCount, 2) : 0,
            ],
            'put_total' => [
                'oi' => $putTotalOi,
                'change_oi' => $putTotalChangeOi,
                'volume' => $putTotalVolume,
                'avg_iv' => $visibleCount > 0 ? round($putTotalIvSum / $visibleCount, 2) : 0,
                'avg_ltp' => $visibleCount > 0 ? round($putTotalLtpSum / $visibleCount, 2) : 0,
            ],
            'pcr' => $pcr,
            'total_call_oi' => $callTotalOi,
            'total_put_oi' => $putTotalOi,
            'difference' => $oiDifference,
            'market_sentiment' => $this->trendDetector->detectMarketSentiment($pcr, $callTotalChangeOi, $putTotalChangeOi),
        ];

        // Detect Support & Resistance Levels
        $levels = $this->detectSupportResistance($allStrikesData ?: $processedStrikes);
        $maxPain = $this->computeMaxPain($allStrikesData ?: $processedStrikes);

        // Track Support/Resistance shift compared to previous cached levels
        $shiftKey = "options:levels:{$underlying->symbol}";
        $prevLevels = Cache::get($shiftKey);
        $supportShift = null;
        $resistanceShift = null;

        if ($prevLevels) {
            if ($levels['support_1'] > $prevLevels['support_1']) {
                $supportShift = 'Upward Shift (Bullish)';
            } elseif ($levels['support_1'] < $prevLevels['support_1']) {
                $supportShift = 'Downward Shift (Bearish)';
            }

            if ($levels['resistance_1'] > $prevLevels['resistance_1']) {
                $resistanceShift = 'Upward Shift (Bullish)';
            } elseif ($levels['resistance_1'] < $prevLevels['resistance_1']) {
                $resistanceShift = 'Downward Shift (Bearish)';
            }
        }
        Cache::put($shiftKey, $levels, 3600);

        return [
            'symbol' => $underlying->symbol,
            'name' => $underlying->name,
            'spot_price' => $spotPrice,
            'atm_strike' => $atmStrike,
            'expiry_date' => $expiryDate,
            'timestamp' => $now->toIso8601String(),
            'strikes' => $processedStrikes,
            'totals' => $totals,
            'levels' => array_merge($levels, [
                'max_pain' => $maxPain,
                'support_shift' => $supportShift,
                'resistance_shift' => $resistanceShift,
            ]),
        ];
    }

    public function detectSupportResistance(array $strikesData): array
    {
        $callOiByStrike = [];
        $putOiByStrike = [];

        foreach ($strikesData as $key => $strike) {
            $strikePrice = is_array($strike) ? ($strike['strike_price'] ?? (float) $key) : (float) $key;
            $ceOi = is_array($strike) ? ($strike['CE']['oi'] ?? $strike['call']['oi'] ?? 0) : 0;
            $peOi = is_array($strike) ? ($strike['PE']['oi'] ?? $strike['put']['oi'] ?? 0) : 0;

            $callOiByStrike[$strikePrice] = $ceOi;
            $putOiByStrike[$strikePrice] = $peOi;
        }

        arsort($callOiByStrike);
        arsort($putOiByStrike);

        $callKeys = array_keys($callOiByStrike);
        $putKeys = array_keys($putOiByStrike);

        return [
            'resistance_1' => $callKeys[0] ?? 0.0,
            'resistance_2' => $callKeys[1] ?? 0.0,
            'support_1' => $putKeys[0] ?? 0.0,
            'support_2' => $putKeys[1] ?? 0.0,
        ];
    }

    public function computeMaxPain(array $strikesData): float
    {
        $strikes = [];
        foreach ($strikesData as $key => $item) {
            $strikes[] = is_array($item) ? ($item['strike_price'] ?? (float) $key) : (float) $key;
        }

        if (empty($strikes)) {
            return 0.0;
        }

        $minLoss = PHP_FLOAT_MAX;
        $maxPainStrike = $strikes[0];

        foreach ($strikes as $expiryCandidate) {
            $totalLoss = 0.0;

            foreach ($strikesData as $k => $item) {
                $strike = is_array($item) ? ($item['strike_price'] ?? (float) $k) : (float) $k;
                $ceOi = is_array($item) ? ($item['CE']['oi'] ?? $item['call']['oi'] ?? 0) : 0;
                $peOi = is_array($item) ? ($item['PE']['oi'] ?? $item['put']['oi'] ?? 0) : 0;

                // CE Writer payout if candidate > strike
                if ($expiryCandidate > $strike) {
                    $totalLoss += ($expiryCandidate - $strike) * $ceOi;
                }

                // PE Writer payout if candidate < strike
                if ($expiryCandidate < $strike) {
                    $totalLoss += ($strike - $expiryCandidate) * $peOi;
                }
            }

            if ($totalLoss < $minLoss) {
                $minLoss = $totalLoss;
                $maxPainStrike = $expiryCandidate;
            }
        }

        return (float) $maxPainStrike;
    }

    protected function getAtmLabel(float $strike, float $atmStrike, float $step): string
    {
        $diff = round(($strike - $atmStrike) / ($step ?: 50));
        if ($diff == 0) return 'ATM';
        return $diff > 0 ? "ATM +{$diff}" : "ATM {$diff}";
    }
}
