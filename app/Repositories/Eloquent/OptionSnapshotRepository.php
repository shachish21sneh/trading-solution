<?php

namespace App\Repositories\Eloquent;

use App\Models\OptionSnapshot;
use App\Repositories\Contracts\OptionSnapshotRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OptionSnapshotRepository implements OptionSnapshotRepositoryInterface
{
    public function storeBatch(array $records): bool
    {
        return OptionSnapshot::insert($records);
    }

    public function getHistoryForStrike(string $symbol, float $strikePrice, string $optionType, Carbon $from): Collection
    {
        return OptionSnapshot::query()
            ->where('symbol', $symbol)
            ->where('strike_price', $strikePrice)
            ->where('option_type', $optionType)
            ->where('snapshot_time', '>=', $from)
            ->orderBy('snapshot_time', 'asc')
            ->get();
    }

    public function getSnapshotsAtTimestamp(string $symbol, Carbon $timestamp): Collection
    {
        // Find closest snapshot time within 30 seconds
        $timeWindowStart = $timestamp->copy()->subSeconds(15);
        $timeWindowEnd = $timestamp->copy()->addSeconds(15);

        return OptionSnapshot::query()
            ->where('symbol', $symbol)
            ->whereBetween('snapshot_time', [$timeWindowStart, $timeWindowEnd])
            ->orderBy('strike_price', 'asc')
            ->get();
    }
}
