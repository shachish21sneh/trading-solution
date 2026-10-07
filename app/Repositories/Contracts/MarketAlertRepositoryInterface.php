<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface MarketAlertRepositoryInterface
{
    public function getRecentAlerts(string $symbol, int $limit = 50): Collection;
    public function getCriticalAlerts(string $symbol, int $limit = 20): Collection;
}
