<?php

namespace App\Services\MarketData\Providers;

use App\Contracts\MarketDataProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SimulatedLiveMarketDataProvider implements MarketDataProviderInterface
{
    protected array $baseConfig = [
        'NIFTY' => [
            'base_spot' => 24500.0,
            'step' => 50.0,
            'lot_size' => 50,
            'base_oi' => 1500000,
        ],
        'BANKNIFTY' => [
            'base_spot' => 52200.0,
            'step' => 100.0,
            'lot_size' => 15,
            'base_oi' => 850000,
        ],
        'FINNIFTY' => [
            'base_spot' => 23800.0,
            'step' => 50.0,
            'lot_size' => 40,
            'base_oi' => 600000,
        ],
    ];

    public function getProviderName(): string
    {
        return 'SimulatedLiveStream';
    }

    public function getExpiryDates(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $today = Carbon::now();
        
        // Next 4 Thursdays (typical NSE weekly expiry)
        $expiries = [];
        $current = $today->copy();
        
        for ($i = 0; $i < 4; $i++) {
            if ($current->dayOfWeek !== Carbon::THURSDAY || $current->isPast()) {
                $current->next(Carbon::THURSDAY);
            }
            $expiries[] = $current->format('Y-m-d');
            $current->addWeek();
        }

        return $expiries;
    }

    public function getUnderlyingQuote(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $cacheKey = "sim:spot:{$symbol}";
        
        $config = $this->baseConfig[$symbol] ?? $this->baseConfig['NIFTY'];
        $prevSpot = Cache::get($cacheKey, $config['base_spot']);

        // Micro drift (-0.08% to +0.08%)
        $driftPct = (mt_rand(-80, 85) / 100000.0);
        $newSpot = round($prevSpot * (1 + $driftPct), 2);
        Cache::put($cacheKey, $newSpot, 3600);

        $change = round($newSpot - $config['base_spot'], 2);
        $changePct = round(($change / $config['base_spot']) * 100, 2);

        return [
            'symbol' => $symbol,
            'spot_price' => $newSpot,
            'change' => $change,
            'change_percent' => $changePct,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }

    public function getOptionChain(string $symbol, string $expiryDate): array
    {
        $symbol = strtoupper($symbol);
        $quote = $this->getUnderlyingQuote($symbol);
        $spotPrice = $quote['spot_price'];
        $config = $this->baseConfig[$symbol] ?? $this->baseConfig['NIFTY'];
        $step = $config['step'];

        $atmStrike = round($spotPrice / $step) * $step;

        // Generate full strikes range (e.g. ATM ± 15 strikes to allow dynamic ATM shifting)
        $strikes = [];
        $range = 15;

        for ($i = -$range; $i <= $range; $i++) {
            $strike = round($atmStrike + ($i * $step), 2);
            $strikeKey = number_format($strike, 2, '.', '');

            $ceData = $this->generateSimulatedOptionData($symbol, $strike, 'CE', $spotPrice, $atmStrike, $config);
            $peData = $this->generateSimulatedOptionData($symbol, $strike, 'PE', $spotPrice, $atmStrike, $config);

            $strikes[$strikeKey] = [
                'strike_price' => $strike,
                'CE' => $ceData,
                'PE' => $peData,
            ];
        }

        return [
            'symbol' => $symbol,
            'expiry_date' => $expiryDate,
            'spot_price' => $spotPrice,
            'atm_strike' => $atmStrike,
            'timestamp' => Carbon::now()->toIso8601String(),
            'strikes' => $strikes,
        ];
    }

    protected function generateSimulatedOptionData(
        string $symbol,
        float $strike,
        string $type,
        float $spotPrice,
        float $atmStrike,
        array $config
    ): array {
        $cacheKey = "sim:strike:{$symbol}:{$strike}:{$type}";
        $existing = Cache::get($cacheKey);

        $distFromAtm = abs($strike - $atmStrike) / $config['step'];
        $moneynessFactor = max(0.2, 1 - ($distFromAtm * 0.07));

        if (!$existing) {
            // Initial seed
            $baseOi = (int) ($config['base_oi'] * $moneynessFactor * (0.8 + (mt_rand(0, 40) / 100)));
            $baseVolume = (int) ($baseOi * (0.3 + (mt_rand(0, 30) / 100)));
            
            // Intrinsic + Time value for LTP
            $intrinsic = ($type === 'CE') ? max(0, $spotPrice - $strike) : max(0, $strike - $spotPrice);
            $timeValue = max(10, ($config['step'] * 1.8) * exp(-0.15 * $distFromAtm));
            $ltp = round($intrinsic + $timeValue, 2);
            $iv = round(13.5 + ($distFromAtm * 0.4) + (mt_rand(-50, 50) / 100), 2);

            $data = [
                'oi' => $baseOi,
                'change_oi' => 0,
                'volume' => $baseVolume,
                'iv' => $iv,
                'ltp' => $ltp,
                'change' => 0.0,
            ];
        } else {
            // Live evolution
            $oiDelta = (int) (mt_rand(-12000, 15000));
            $newOi = max(5000, $existing['oi'] + $oiDelta);
            $changeOi = $newOi - $existing['oi'];

            $newVolume = $existing['volume'] + mt_rand(500, 8000);

            // Recompute LTP based on spot drift
            $intrinsic = ($type === 'CE') ? max(0, $spotPrice - $strike) : max(0, $strike - $spotPrice);
            $timeValue = max(5, ($config['step'] * 1.8) * exp(-0.15 * $distFromAtm) + (mt_rand(-20, 20) / 10));
            $newLtp = round(max(0.5, $intrinsic + $timeValue), 2);
            $priceChange = round($newLtp - $existing['ltp'], 2);

            $newIv = round(max(8.0, min(35.0, $existing['iv'] + (mt_rand(-20, 20) / 100))), 2);

            $data = [
                'oi' => $newOi,
                'change_oi' => $changeOi,
                'volume' => $newVolume,
                'iv' => $newIv,
                'ltp' => $newLtp,
                'change' => $priceChange,
            ];
        }

        Cache::put($cacheKey, $data, 3600);
        return $data;
    }
}
