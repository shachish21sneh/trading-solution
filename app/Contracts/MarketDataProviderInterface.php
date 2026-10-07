<?php

namespace App\Contracts;

interface MarketDataProviderInterface
{
    /**
     * Get underlying spot quote: ['spot_price' => float, 'change' => float, 'timestamp' => Carbon]
     */
    public function getUnderlyingQuote(string $symbol): array;

    /**
     * Fetch complete live option chain snapshot from market data provider.
     * Return normalized structure:
     * [
     *   'spot_price' => float,
     *   'timestamp' => string,
     *   'strikes' => [
     *      '24500.00' => [
     *          'CE' => ['oi' => int, 'change_oi' => int, 'volume' => int, 'iv' => float, 'ltp' => float, 'change' => float],
     *          'PE' => ['oi' => int, 'change_oi' => int, 'volume' => int, 'iv' => float, 'ltp' => float, 'change' => float],
     *      ]
     *   ]
     * ]
     */
    public function getOptionChain(string $symbol, string $expiryDate): array;

    /**
     * Get available expiry dates for underlying.
     */
    public function getExpiryDates(string $symbol): array;

    /**
     * Identify provider name (e.g. Zerodha, Upstox, AngelOne, Fyers, Simulation).
     */
    public function getProviderName(): string;
}
