<?php

namespace App\Repositories\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface OptionSnapshotRepositoryInterface
{
    /**
     * Store a batch of snapshots without overwriting history
     */
    public function storeBatch(array $records): bool;

    /**
     * Get historical snapshots for strike and symbol over time window
     */
    public function getHistoryForStrike(string $symbol, float $strikePrice, string $optionType, Carbon $from): Collection;

    /**
     * Get list of historical dates available for symbol
     */
    public function getAvailableHistoricalDates(string $symbol): Collection;

    /**
     * Get timeline points for a specific date or latest date
     */
    public function getTimelineForDate(string $symbol, ?string $date = null): array;

    /**
     * Get snapshots across all strikes at a specific historical point in time (Time-Travel / Replay)
     */
    public function getSnapshotsAtTimestamp(string $symbol, Carbon $timestamp): Collection;
}
