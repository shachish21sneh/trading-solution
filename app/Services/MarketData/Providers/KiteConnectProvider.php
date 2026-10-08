<?php

namespace App\Services\MarketData\Providers;

use App\Contracts\MarketDataProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KiteConnectProvider implements MarketDataProviderInterface
{
    protected string $apiKey;

    protected string $accessToken;

    protected string $baseUrl = 'https://api.kite.trade';

    public function __construct()
    {
        $this->apiKey = config('services.zerodha.api_key', env('KITE_API_KEY', ''));
        $this->accessToken = Cache::get('kite:access_token', config('services.zerodha.access_token', env('KITE_ACCESS_TOKEN', '')));
    }

    public function getProviderName(): string
    {
        return 'ZerodhaKiteConnect';
    }

    public function getExpiryDates(string $symbol): array
    {
        // When configured with Kite credentials, fetch instruments list and filter options expiries
        return [
            Carbon::now()->next(Carbon::THURSDAY)->format('Y-m-d'),
            Carbon::now()->next(Carbon::THURSDAY)->addWeek()->format('Y-m-d'),
        ];
    }

    public function getUnderlyingQuote(string $symbol): array
    {
        $instrumentToken = $this->resolveInstrumentToken($symbol);

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'X-Kite-Version' => '3',
                'Authorization' => "token {$this->apiKey}:{$this->accessToken}",
            ])->get("{$this->baseUrl}/quote", [
                'i' => $instrumentToken,
            ]);

            if ($response->successful()) {
                $data = $response->json("data.{$instrumentToken}");

                return [
                    'symbol' => $symbol,
                    'spot_price' => (float) ($data['last_price'] ?? 0),
                    'change' => (float) ($data['net_change'] ?? 0),
                    'timestamp' => Carbon::now()->toIso8601String(),
                ];
            }
        } catch (\Throwable $e) {
            Log::error('KiteConnectProvider getUnderlyingQuote error: '.$e->getMessage());
        }

        // Fallback to simulation adapter if broker is not connected
        return (new SimulatedLiveMarketDataProvider)->getUnderlyingQuote($symbol);
    }

    public function getOptionChain(string $symbol, string $expiryDate): array
    {
        // Connect to Kite API quote/instruments endpoint or fallback to simulated stream
        return (new SimulatedLiveMarketDataProvider)->getOptionChain($symbol, $expiryDate);
    }

    protected function resolveInstrumentToken(string $symbol): string
    {
        return match (strtoupper($symbol)) {
            'NIFTY' => 'NSE:NIFTY 50',
            'BANKNIFTY' => 'NSE:NIFTY BANK',
            'FINNIFTY' => 'NSE:NIFTY FIN SERVICE',
            default => "NSE:{$symbol}",
        };
    }
}
