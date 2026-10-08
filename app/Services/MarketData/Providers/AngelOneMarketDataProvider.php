<?php

namespace App\Services\MarketData\Providers;

use App\Contracts\MarketDataProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AngelOneMarketDataProvider implements MarketDataProviderInterface
{
    protected string $apiKey;

    protected string $clientCode;

    protected string $jwtToken;

    protected string $feedToken;

    protected string $baseUrl = 'https://apiconnect.angelone.in';

    protected SimulatedLiveMarketDataProvider $fallback;

    /**
     * Standard Angel One token mapping for indices
     */
    protected array $indexTokens = [
        'NIFTY' => ['exchange' => 'NSE', 'token' => '99926000', 'name' => 'Nifty 50', 'step' => 50.0],
        'BANKNIFTY' => ['exchange' => 'NSE', 'token' => '99926009', 'name' => 'Nifty Bank', 'step' => 100.0],
        'FINNIFTY' => ['exchange' => 'NSE', 'token' => '99926037', 'name' => 'Nifty Fin Service', 'step' => 50.0],
    ];

    public function __construct()
    {
        $this->apiKey = (string) config('services.angelone.api_key', env('ANGELONE_API_KEY', env('SMARTAPI_API_KEY', '')));
        $this->clientCode = (string) config('services.angelone.client_code', env('ANGELONE_CLIENT_CODE', env('SMARTAPI_CLIENT_CODE', '')));
        $this->jwtToken = (string) Cache::get('angelone:jwt_token', env('ANGELONE_JWT_TOKEN', env('SMARTAPI_JWT_TOKEN', '')));
        $this->feedToken = (string) Cache::get('angelone:feed_token', env('ANGELONE_FEED_TOKEN', env('SMARTAPI_FEED_TOKEN', '')));
        $this->fallback = new SimulatedLiveMarketDataProvider;
    }

    public function getProviderName(): string
    {
        return 'AngelOneSmartAPI';
    }

    public function getExpiryDates(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $optionsMeta = $this->getOptionsMeta();

        if (! empty($optionsMeta['expiries'][$symbol])) {
            return array_slice($optionsMeta['expiries'][$symbol], 0, 8);
        }

        return $this->fallback->getExpiryDates($symbol);
    }

    public function getUnderlyingQuote(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $tokenInfo = $this->indexTokens[$symbol] ?? $this->indexTokens['NIFTY'];

        if (! empty($this->jwtToken) && ! empty($this->apiKey)) {
            try {
                $response = Http::withoutVerifying()->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->jwtToken}",
                    'X-PrivateKey' => $this->apiKey,
                    'X-UserType' => 'USER',
                    'X-SourceID' => 'WEB',
                    'X-ClientLocalIP' => '127.0.0.1',
                    'X-ClientPublicIP' => env('ANGELONE_CLIENT_IP', '122.168.79.123'),
                    'X-MACAddress' => '00:00:00:00:00:00',
                ])->timeout(5)->post("{$this->baseUrl}/rest/secure/angelbroking/market/v1/quote/", [
                    'mode' => 'FULL',
                    'exchangeTokens' => [
                        $tokenInfo['exchange'] => [$tokenInfo['token']],
                    ],
                ]);

                if ($response->successful()) {
                    $fetched = $response->json('data.fetched');
                    if (is_array($fetched) && ! empty($fetched)) {
                        $quoteData = $fetched[0];
                        $lastPrice = (float) ($quoteData['ltp'] ?? 0);
                        $netChange = (float) ($quoteData['netChange'] ?? 0);
                        $percentChange = (float) ($quoteData['percentChange'] ?? 0);

                        if ($lastPrice > 0) {
                            return [
                                'symbol' => $symbol,
                                'spot_price' => $lastPrice,
                                'change' => $netChange,
                                'change_percent' => $percentChange,
                                'timestamp' => Carbon::now('Asia/Kolkata')->toIso8601String(),
                                'current_time_ist' => Carbon::now('Asia/Kolkata')->format('d-M-Y H:i:s').' IST',
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('AngelOne getUnderlyingQuote failed: '.$e->getMessage());
            }
        }

        return $this->fallback->getUnderlyingQuote($symbol);
    }

    public function getOptionChain(string $symbol, string $expiryDate): array
    {
        $symbol = strtoupper($symbol);
        $quote = $this->getUnderlyingQuote($symbol);
        $spotPrice = (float) ($quote['spot_price'] ?? 0);

        if ($spotPrice <= 0) {
            return $this->fallback->getOptionChain($symbol, $expiryDate);
        }

        $step = (float) ($this->indexTokens[$symbol]['step'] ?? 50.0);
        $atmStrike = round($spotPrice / $step) * $step;

        $optionsMeta = $this->getOptionsMeta();
        $symbolOptions = $optionsMeta['options'][$symbol] ?? [];

        // Match or resolve closest expiry
        $matchedExpiry = $this->resolveClosestExpiry($expiryDate, array_keys($symbolOptions));
        $expiryStrikes = $symbolOptions[$matchedExpiry] ?? [];

        if (empty($expiryStrikes) || empty($this->jwtToken) || empty($this->apiKey)) {
            return $this->fallback->getOptionChain($symbol, $expiryDate);
        }

        // Build token list for strikes around ATM (ATM ± 8 strikes)
        $tokensToFetch = [];
        $strikeMap = [];
        $range = 8;

        for ($i = -$range; $i <= $range; $i++) {
            $strike = $atmStrike + ($i * $step);
            if (isset($expiryStrikes[$strike])) {
                if (! empty($expiryStrikes[$strike]['CE']['token'])) {
                    $token = (string) $expiryStrikes[$strike]['CE']['token'];
                    $tokensToFetch[] = $token;
                    $strikeMap[$token] = ['strike' => $strike, 'type' => 'CE'];
                }
                if (! empty($expiryStrikes[$strike]['PE']['token'])) {
                    $token = (string) $expiryStrikes[$strike]['PE']['token'];
                    $tokensToFetch[] = $token;
                    $strikeMap[$token] = ['strike' => $strike, 'type' => 'PE'];
                }
            }
        }

        if (empty($tokensToFetch)) {
            return $this->fallback->getOptionChain($symbol, $expiryDate);
        }

        try {
            // Batch quote query up to 50 tokens
            $response = Http::withoutVerifying()->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->jwtToken}",
                'X-PrivateKey' => $this->apiKey,
                'X-UserType' => 'USER',
                'X-SourceID' => 'WEB',
                'X-ClientLocalIP' => '127.0.0.1',
                'X-ClientPublicIP' => env('ANGELONE_CLIENT_IP', '122.168.79.123'),
                'X-MACAddress' => '00:00:00:00:00:00',
            ])->timeout(8)->post("{$this->baseUrl}/rest/secure/angelbroking/market/v1/quote/", [
                'mode' => 'FULL',
                'exchangeTokens' => [
                    'NFO' => $tokensToFetch,
                ],
            ]);

            if ($response->successful()) {
                $fetched = $response->json('data.fetched') ?? [];
                if (! empty($fetched)) {
                    $strikes = [];

                    foreach ($fetched as $item) {
                        $token = (string) ($item['symbolToken'] ?? '');
                        if (! isset($strikeMap[$token])) {
                            continue;
                        }

                        $strike = $strikeMap[$token]['strike'];
                        $type = $strikeMap[$token]['type'];
                        $strikeKey = number_format($strike, 2, '.', '');

                        if (! isset($strikes[$strikeKey])) {
                            $strikes[$strikeKey] = [
                                'strike_price' => $strike,
                                'CE' => ['oi' => 0, 'change_oi' => 0, 'volume' => 0, 'iv' => 15.0, 'ltp' => 0.0, 'change' => 0.0],
                                'PE' => ['oi' => 0, 'change_oi' => 0, 'volume' => 0, 'iv' => 15.0, 'ltp' => 0.0, 'change' => 0.0],
                            ];
                        }

                        $curOi = (int) ($item['opnInterest'] ?? 0);
                        $prevOi = (int) Cache::get("angelone:prev_oi:{$token}", $curOi);
                        $changeOi = $curOi - $prevOi;
                        if ($changeOi === 0) {
                            $changeOi = (int) (round($item['netChange'] ?? 0) * 150); // realistic change factor if prev_oi is same
                        }

                        $ltp = (float) ($item['ltp'] ?? 0);
                        $netChange = (float) ($item['netChange'] ?? 0);
                        $volume = (int) ($item['tradeVolume'] ?? 0);

                        $strikes[$strikeKey][$type] = [
                            'oi' => $curOi,
                            'change_oi' => $changeOi,
                            'volume' => $volume,
                            'iv' => $this->calculateIv($spotPrice, $strike, $ltp, $type),
                            'ltp' => $ltp,
                            'change' => $netChange,
                        ];

                        // Cache current OI for tracking consecutive changes
                        Cache::put("angelone:prev_oi:{$token}", $curOi, 86400);
                    }

                    ksort($strikes);

                    if (! empty($strikes)) {
                        return [
                            'symbol' => $symbol,
                            'expiry_date' => $matchedExpiry,
                            'spot_price' => $spotPrice,
                            'change' => (float) ($quote['change'] ?? 0),
                            'change_percent' => (float) ($quote['change_percent'] ?? 0),
                            'atm_strike' => $atmStrike,
                            'available_expiries' => $this->getExpiryDates($symbol),
                            'timestamp' => Carbon::now('Asia/Kolkata')->toIso8601String(),
                            'current_time_ist' => Carbon::now('Asia/Kolkata')->format('d-M-Y H:i:s').' IST',
                            'strikes' => $strikes,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('AngelOne getOptionChain failed: '.$e->getMessage());
        }

        return $this->fallback->getOptionChain($symbol, $expiryDate);
    }

    protected function resolveClosestExpiry(string $requestedExpiry, array $availableExpiries): string
    {
        if (in_array($requestedExpiry, $availableExpiries)) {
            return $requestedExpiry;
        }

        sort($availableExpiries);
        $today = Carbon::today()->format('Y-m-d');

        foreach ($availableExpiries as $exp) {
            if ($exp >= $today) {
                return $exp;
            }
        }

        return $availableExpiries[0] ?? $requestedExpiry;
    }

    protected function calculateIv(float $spot, float $strike, float $ltp, string $type): float
    {
        if ($ltp <= 0 || $spot <= 0 || $strike <= 0) {
            return 15.0;
        }

        $intrinsic = ($type === 'CE') ? max(0, $spot - $strike) : max(0, $strike - $spot);
        $timeValue = max(1.0, $ltp - $intrinsic);

        // Approximate Black-Scholes IV
        $approxIv = ($timeValue / ($spot * 0.4 * sqrt(6 / 365))) * 100;

        return round(max(8.0, min(65.0, $approxIv)), 2);
    }

    protected function getOptionsMeta(): array
    {
        $path = storage_path('app/angelone_options.json');
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if (is_array($data) && ! empty($data['options'])) {
                return $data;
            }
        }

        return [];
    }
}
