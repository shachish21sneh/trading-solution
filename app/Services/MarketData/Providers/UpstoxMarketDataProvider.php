<?php

namespace App\Services\MarketData\Providers;

use App\Contracts\MarketDataProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpstoxMarketDataProvider implements MarketDataProviderInterface
{
    protected string $apiKey;
    protected string $accessToken;
    protected string $baseUrl = 'https://api.upstox.com/v2';
    protected SimulatedLiveMarketDataProvider $fallback;

    protected array $instrumentKeys = [
        'NIFTY' => 'NSE_INDEX|Nifty 50',
        'BANKNIFTY' => 'NSE_INDEX|Nifty Bank',
        'FINNIFTY' => 'NSE_INDEX|Nifty Fin Service',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.upstox.api_key', env('UPSTOX_API_KEY', ''));
        $this->accessToken = Cache::get('upstox:access_token', env('UPSTOX_ACCESS_TOKEN', ''));
        $this->fallback = new SimulatedLiveMarketDataProvider();
    }

    public function getProviderName(): string
    {
        return 'UpstoxMarketDataFeed';
    }

    public function getExpiryDates(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $instrumentKey = $this->instrumentKeys[$symbol] ?? 'NSE_INDEX|Nifty 50';

        if (!empty($this->accessToken)) {
            try {
                $response = Http::withoutVerifying()->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$this->accessToken}",
                ])->timeout(5)->get("{$this->baseUrl}/option/contract", [
                    'instrument_key' => $instrumentKey,
                ]);

                if ($response->successful() && is_array($response->json('data'))) {
                    $expiries = collect($response->json('data'))
                        ->pluck('expiry')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                        ->take(5)
                        ->toArray();

                    if (!empty($expiries)) {
                        return $expiries;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Upstox getExpiryDates failed: " . $e->getMessage());
            }
        }

        return $this->fallback->getExpiryDates($symbol);
    }

    public function getUnderlyingQuote(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $instrumentKey = $this->instrumentKeys[$symbol] ?? 'NSE_INDEX|Nifty 50';

        if (!empty($this->accessToken)) {
            try {
                $response = Http::withoutVerifying()->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$this->accessToken}",
                ])->timeout(5)->get("{$this->baseUrl}/market-quote/quotes", [
                    'instrument_key' => $instrumentKey,
                ]);

                if ($response->successful()) {
                    $quoteData = $response->json("data.{$instrumentKey}");
                    if ($quoteData) {
                        $lastPrice = (float) ($quoteData['last_price'] ?? 0);
                        $netChange = (float) ($quoteData['net_change'] ?? 0);
                        $closePrice = (float) ($quoteData['ohlc']['close'] ?? ($lastPrice - $netChange));
                        $changePct = $closePrice > 0 ? round(($netChange / $closePrice) * 100, 2) : 0;

                        return [
                            'symbol' => $symbol,
                            'spot_price' => $lastPrice,
                            'change' => $netChange,
                            'change_percent' => $changePct,
                            'timestamp' => Carbon::now()->toIso8601String(),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Upstox getUnderlyingQuote failed: " . $e->getMessage());
            }
        }

        return $this->fallback->getUnderlyingQuote($symbol);
    }

    public function getOptionChain(string $symbol, string $expiryDate): array
    {
        $symbol = strtoupper($symbol);
        $instrumentKey = $this->instrumentKeys[$symbol] ?? 'NSE_INDEX|Nifty 50';

        if (!empty($this->accessToken)) {
            try {
                $response = Http::withoutVerifying()->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$this->accessToken}",
                ])->timeout(8)->get("{$this->baseUrl}/option/chain", [
                    'instrument_key' => $instrumentKey,
                    'expiry_date' => $expiryDate,
                ]);

                if ($response->successful() && is_array($response->json('data'))) {
                    $rawStrikes = $response->json('data');
                    $spotPrice = 0.0;
                    $strikes = [];

                    foreach ($rawStrikes as $row) {
                        $strike = (float) ($row['strike_price'] ?? 0);
                        if ($strike <= 0) continue;

                        if ($spotPrice === 0.0 && !empty($row['underlying_spot_price'])) {
                            $spotPrice = (float) $row['underlying_spot_price'];
                        }

                        $ceRaw = $row['call_options'] ?? [];
                        $peRaw = $row['put_options'] ?? [];

                        $ceLtp = (float) ($ceRaw['market_data']['ltp'] ?? 0);
                        $ceClose = (float) ($ceRaw['market_data']['close_price'] ?? $ceLtp);
                        $ceOi = (int) ($ceRaw['market_data']['oi'] ?? 0);
                        $cePrevOi = (int) ($ceRaw['market_data']['prev_oi'] ?? $ceOi);

                        $peLtp = (float) ($peRaw['market_data']['ltp'] ?? 0);
                        $peClose = (float) ($peRaw['market_data']['close_price'] ?? $peLtp);
                        $peOi = (int) ($peRaw['market_data']['oi'] ?? 0);
                        $pePrevOi = (int) ($peRaw['market_data']['prev_oi'] ?? $peOi);

                        $strikeKey = number_format($strike, 2, '.', '');
                        $strikes[$strikeKey] = [
                            'strike_price' => $strike,
                            'CE' => [
                                'oi' => $ceOi,
                                'change_oi' => $ceOi - $cePrevOi,
                                'volume' => (int) ($ceRaw['market_data']['volume'] ?? 0),
                                'iv' => (float) ($ceRaw['option_greeks']['iv'] ?? 15.0),
                                'ltp' => $ceLtp,
                                'change' => round($ceLtp - $ceClose, 2),
                            ],
                            'PE' => [
                                'oi' => $peOi,
                                'change_oi' => $peOi - $pePrevOi,
                                'volume' => (int) ($peRaw['market_data']['volume'] ?? 0),
                                'iv' => (float) ($peRaw['option_greeks']['iv'] ?? 15.0),
                                'ltp' => $peLtp,
                                'change' => round($peLtp - $peClose, 2),
                            ],
                        ];
                    }

                    if (!empty($strikes)) {
                        $step = ($symbol === 'BANKNIFTY') ? 100 : 50;
                        $atmStrike = round($spotPrice / $step) * $step;

                        return [
                            'symbol' => $symbol,
                            'expiry_date' => $expiryDate,
                            'spot_price' => $spotPrice,
                            'atm_strike' => $atmStrike,
                            'timestamp' => Carbon::now()->toIso8601String(),
                            'strikes' => $strikes,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Upstox getOptionChain failed: " . $e->getMessage());
            }
        }

        return $this->fallback->getOptionChain($symbol, $expiryDate);
    }
}
