<?php

namespace App\Services\MarketData;

use App\Contracts\MarketDataProviderInterface;
use App\Services\MarketData\Providers\AngelOneMarketDataProvider;
use App\Services\MarketData\Providers\KiteConnectProvider;
use App\Services\MarketData\Providers\SimulatedLiveMarketDataProvider;
use App\Services\MarketData\Providers\UpstoxMarketDataProvider;
use Illuminate\Support\Facades\Cache;

class MarketDataService
{
    protected MarketDataProviderInterface $provider;

    public function __construct()
    {
        $driver = Cache::get('market_data_provider', config('marketdata.provider', env('MARKET_DATA_PROVIDER', 'simulation')));

        $this->provider = match (strtolower((string) $driver)) {
            'zerodha', 'kite' => new KiteConnectProvider,
            'upstox' => new UpstoxMarketDataProvider,
            'angelone', 'angel' => new AngelOneMarketDataProvider,
            default => new SimulatedLiveMarketDataProvider,
        };
    }

    public function getActiveProvider(): MarketDataProviderInterface
    {
        return $this->provider;
    }

    public function setProvider(MarketDataProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    public function getSpotQuote(string $symbol): array
    {
        $quote = $this->provider->getUnderlyingQuote($symbol);

        // Cache live spot in Redis/Cache
        try {
            Cache::put("options:live:spot:{$symbol}", $quote, 60);
        } catch (\Throwable $e) {
            // Safe fallback if redis connection fails
        }

        return $quote;
    }

    public function getOptionChain(string $symbol, string $expiryDate): array
    {
        $chain = $this->provider->getOptionChain($symbol, $expiryDate);

        // Cache live chain snapshot in Redis/Cache
        try {
            Cache::put("options:live:chain:{$symbol}:{$expiryDate}", $chain, 30);
        } catch (\Throwable $e) {
            // Safe fallback
        }

        return $chain;
    }

    public function getAvailableExpiries(string $symbol): array
    {
        return $this->provider->getExpiryDates($symbol);
    }

    public function getExpiryDates(string $symbol): array
    {
        return $this->provider->getExpiryDates($symbol);
    }
}
