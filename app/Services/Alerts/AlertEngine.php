<?php

namespace App\Services\Alerts;

use App\Contracts\AlertEngineInterface;
use App\Events\MarketAlertCreated;
use App\Models\MarketAlert;
use App\Models\Underlying;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AlertEngine implements AlertEngineInterface
{
    public function evaluateAndDispatch(Underlying $underlying, array $currentAnalysis, ?array $previousAnalysis = null): array
    {
        $alertsGenerated = [];
        $symbol = $underlying->symbol;
        $spotPrice = (float) $currentAnalysis['spot_price'];
        $levels = $currentAnalysis['levels'];
        $strikes = $currentAnalysis['strikes'];
        $totals = $currentAnalysis['totals'];

        // 1. Check Support and Resistance Shift
        if (! empty($levels['support_shift'])) {
            $alertsGenerated[] = $this->recordAlert(
                $symbol,
                'Support Shifted',
                'warning',
                "{$symbol} Support Shifted: {$levels['support_shift']}",
                "Major Put OI shifted to strike {$levels['support_1']}. Prev Support: ".($levels['support_2'] ?? 'N/A'),
                $levels['support_1'],
                'PE',
                ['levels' => $levels]
            );
        }

        if (! empty($levels['resistance_shift'])) {
            $alertsGenerated[] = $this->recordAlert(
                $symbol,
                'Resistance Shifted',
                'warning',
                "{$symbol} Resistance Shifted: {$levels['resistance_shift']}",
                "Major Call OI shifted to strike {$levels['resistance_1']}. Prev Resistance: ".($levels['resistance_2'] ?? 'N/A'),
                $levels['resistance_1'],
                'CE',
                ['levels' => $levels]
            );
        }

        // 2. Breakout and Breakdown Detection
        $res1 = (float) ($levels['resistance_1'] ?? 0);
        $sup1 = (float) ($levels['support_1'] ?? 0);

        if ($res1 > 0 && $spotPrice >= ($res1 - 5) && $totals['pcr'] > 1.1) {
            $alertsGenerated[] = $this->recordAlert(
                $symbol,
                'Possible Breakout',
                'critical',
                "🚀 Potential Bullish Breakout above {$res1}",
                "Spot ({$spotPrice}) is testing Call Resistance at {$res1} with high PCR ({$totals['pcr']}) indicating put writer aggression.",
                $res1,
                'CE',
                ['spot' => $spotPrice, 'resistance' => $res1, 'pcr' => $totals['pcr']]
            );
        }

        if ($sup1 > 0 && $spotPrice <= ($sup1 + 5) && $totals['pcr'] < 0.85) {
            $alertsGenerated[] = $this->recordAlert(
                $symbol,
                'Possible Breakdown',
                'critical',
                "⚠️ Potential Bearish Breakdown below {$sup1}",
                "Spot ({$spotPrice}) is breaking Put Support at {$sup1} with low PCR ({$totals['pcr']}) indicating call writer domination.",
                $sup1,
                'PE',
                ['spot' => $spotPrice, 'support' => $sup1, 'pcr' => $totals['pcr']]
            );
        }

        // 3. Scan strike-level heavy build-up / unwinding & fresh writing
        foreach ($strikes as $row) {
            $strike = $row['strike_price'];
            $call = $row['call'];
            $put = $row['put'];

            // Heavy CE Build-up
            if ($call['change_oi'] >= 15000) {
                $alertsGenerated[] = $this->recordAlert(
                    $symbol,
                    'Heavy OI Build-up',
                    'info',
                    "Heavy Call Addition at {$strike} CE (+{$call['change_oi']} OI)",
                    "Aggressive call positioning at strike {$strike}. Total Call OI: {$call['oi']}",
                    $strike,
                    'CE',
                    $call
                );
            }

            // Heavy PE Build-up
            if ($put['change_oi'] >= 15000) {
                $alertsGenerated[] = $this->recordAlert(
                    $symbol,
                    'Heavy OI Build-up',
                    'info',
                    "Heavy Put Addition at {$strike} PE (+{$put['change_oi']} OI)",
                    "Aggressive put positioning at strike {$strike}. Total Put OI: {$put['oi']}",
                    $strike,
                    'PE',
                    $put
                );
            }

            // Heavy CE Unwinding
            if ($call['change_oi'] <= -10000 || $call['current_trend'] === 'OI Unwinding') {
                if ($call['drop_percent'] >= 10) {
                    $alertsGenerated[] = $this->recordAlert(
                        $symbol,
                        'Heavy OI Unwinding',
                        'warning',
                        "🔴 Heavy Call Unwinding at {$strike} CE",
                        "Call writers covering! OI dropped {$call['drop_percent']}% from peak {$call['peak_oi']}.",
                        $strike,
                        'CE',
                        $call
                    );
                }
            }

            // Heavy PE Unwinding
            if ($put['change_oi'] <= -10000 || $put['current_trend'] === 'OI Unwinding') {
                if ($put['drop_percent'] >= 10) {
                    $alertsGenerated[] = $this->recordAlert(
                        $symbol,
                        'Heavy OI Unwinding',
                        'warning',
                        "🔴 Heavy Put Unwinding at {$strike} PE",
                        "Put writers retreating! OI dropped {$put['drop_percent']}% from peak {$put['peak_oi']}.",
                        $strike,
                        'PE',
                        $put
                    );
                }
            }

            // Fresh Call Writing
            if ($call['current_trend'] === 'Call Writing' && $call['change_oi'] > 8000) {
                $alertsGenerated[] = $this->recordAlert(
                    $symbol,
                    'Fresh Call Writing',
                    'info',
                    "Fresh Call Writing at {$strike} CE",
                    "Call writers active as price declines. Resistance solidifying at {$strike}.",
                    $strike,
                    'CE',
                    $call
                );
            }

            // Fresh Put Writing
            if ($put['current_trend'] === 'Put Writing' && $put['change_oi'] > 8000) {
                $alertsGenerated[] = $this->recordAlert(
                    $symbol,
                    'Fresh Put Writing',
                    'info',
                    "Fresh Put Writing at {$strike} PE",
                    "Put writers creating support floor at {$strike}.",
                    $strike,
                    'PE',
                    $put
                );
            }
        }

        return $alertsGenerated;
    }

    public function recordAlert(
        string $symbol,
        string $alertType,
        string $severity,
        string $title,
        string $message,
        ?float $strike = null,
        ?string $optionType = null,
        array $metadata = []
    ): MarketAlert {
        // Throttling key to avoid spamming the exact same strike alert within 30 seconds
        $throttleKey = "alert:throttle:{$symbol}:{$alertType}:{$strike}:{$optionType}";
        if (Cache::has($throttleKey)) {
            // Return existing or skip redundant insert
            return MarketAlert::query()->where('symbol', $symbol)->where('alert_type', $alertType)->latest('id')->first()
                ?? new MarketAlert;
        }

        Cache::put($throttleKey, true, 30);

        $underlying = Underlying::where('symbol', $symbol)->first();

        $alert = MarketAlert::create([
            'underlying_id' => $underlying?->id,
            'symbol' => $symbol,
            'alert_type' => $alertType,
            'severity' => $severity,
            'strike_price' => $strike,
            'option_type' => $optionType,
            'title' => $title,
            'message' => $message,
            'metadata' => $metadata,
            'created_at' => Carbon::now(),
        ]);

        // Broadcast event for WebSockets
        try {
            event(new MarketAlertCreated($alert));
        } catch (\Throwable $e) {
            // Safe logging if reverb/pusher socket is offline
        }

        return $alert;
    }
}
