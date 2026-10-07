<?php

namespace App\Repositories\Eloquent;

use App\Models\MarketAlert;
use App\Repositories\Contracts\MarketAlertRepositoryInterface;
use Illuminate\Support\Collection;

class MarketAlertRepository implements MarketAlertRepositoryInterface
{
    public function getRecentAlerts(string $symbol, int $limit = 50): Collection
    {
        return MarketAlert::query()
            ->where('symbol', $symbol)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getCriticalAlerts(string $symbol, int $limit = 20): Collection
    {
        return MarketAlert::query()
            ->where('symbol', $symbol)
            ->whereIn('severity', ['warning', 'critical'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
