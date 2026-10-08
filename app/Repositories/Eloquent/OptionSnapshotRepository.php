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

    public function getAvailableHistoricalDates(string $symbol): Collection
    {
        $symbol = strtoupper($symbol);

        return OptionSnapshot::query()
            ->where('symbol', $symbol)
            ->selectRaw('
                date(snapshot_time) as snapshot_date,
                count(*) as total_snapshots,
                count(distinct snapshot_time) as distinct_timestamps,
                min(snapshot_time) as min_time,
                max(snapshot_time) as max_time,
                min(spot_price) as min_spot,
                max(spot_price) as max_spot
            ')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date', 'desc')
            ->get()
            ->map(function ($row) {
                $minCarbon = Carbon::parse($row->min_time);
                $maxCarbon = Carbon::parse($row->max_time);
                $dateCarbon = Carbon::parse($row->snapshot_date);
                $isToday = $dateCarbon->isToday();

                return [
                    'date' => $row->snapshot_date,
                    'formatted_date' => $dateCarbon->format('d-M-Y'),
                    'is_today' => $isToday,
                    'total_snapshots' => (int) $row->total_snapshots,
                    'distinct_timestamps' => (int) $row->distinct_timestamps,
                    'min_time' => $row->min_time,
                    'max_time' => $row->max_time,
                    'start_time' => $minCarbon->format('H:i:s'),
                    'end_time' => $maxCarbon->format('H:i:s'),
                    'start_time_formatted' => $minCarbon->format('h:i:s A'),
                    'end_time_formatted' => $maxCarbon->format('h:i:s A'),
                    'min_spot' => (float) $row->min_spot,
                    'max_spot' => (float) $row->max_spot,
                    'display_label' => $dateCarbon->format('d-M-Y').($isToday ? ' (Today)' : '')." • {$minCarbon->format('H:i')} - {$maxCarbon->format('H:i')} IST ({$row->distinct_timestamps} ticks)",
                ];
            });
    }

    public function getTimelineForDate(string $symbol, ?string $date = null): array
    {
        $symbol = strtoupper($symbol);
        $query = OptionSnapshot::query()->where('symbol', $symbol);

        if ($date) {
            $targetDate = Carbon::parse($date);
            $query->whereBetween('snapshot_time', [
                $targetDate->copy()->startOfDay(),
                $targetDate->copy()->endOfDay(),
            ]);
            $dateStr = $targetDate->format('Y-m-d');
        } else {
            // Find latest available date for this symbol
            $latestSnapshot = OptionSnapshot::where('symbol', $symbol)->latest('snapshot_time')->first();
            if ($latestSnapshot) {
                $latestDate = Carbon::parse($latestSnapshot->snapshot_time);
                $query->whereBetween('snapshot_time', [
                    $latestDate->copy()->startOfDay(),
                    $latestDate->copy()->endOfDay(),
                ]);
                $dateStr = $latestDate->format('Y-m-d');
            } else {
                $query->where('snapshot_time', '>=', Carbon::today());
                $dateStr = Carbon::today()->format('Y-m-d');
            }
        }

        $rows = $query
            ->select('snapshot_time', 'spot_price')
            ->orderBy('snapshot_time', 'asc')
            ->get()
            ->unique(fn ($item) => $item->snapshot_time?->format('Y-m-d H:i:s'))
            ->map(fn ($item) => [
                'time' => $item->snapshot_time?->format('Y-m-d H:i:s'),
                'time_formatted' => $item->snapshot_time?->format('H:i:s'),
                'time_label' => $item->snapshot_time?->format('h:i:s A'),
                'date' => $item->snapshot_time?->format('Y-m-d'),
                'date_formatted' => $item->snapshot_time?->format('d-M-Y'),
                'spot' => (float) $item->spot_price,
            ])
            ->values();

        $minTime = $rows->first()['time_label'] ?? '09:15 AM';
        $maxTime = $rows->last()['time_label'] ?? '03:30 PM';
        $minTime24 = $rows->first()['time_formatted'] ?? '09:15:00';
        $maxTime24 = $rows->last()['time_formatted'] ?? '15:30:00';

        return [
            'date' => $dateStr,
            'date_formatted' => Carbon::parse($dateStr)->format('d-M-Y'),
            'min_time' => $minTime,
            'max_time' => $maxTime,
            'min_time_24' => $minTime24,
            'max_time_24' => $maxTime24,
            'timeline' => $rows->all(),
        ];
    }

    public function getSnapshotsAtTimestamp(string $symbol, Carbon $timestamp): Collection
    {
        $symbol = strtoupper($symbol);
        // Find closest snapshot time within 25 seconds
        $timeWindowStart = $timestamp->copy()->subSeconds(25);
        $timeWindowEnd = $timestamp->copy()->addSeconds(25);

        $snapshots = OptionSnapshot::query()
            ->where('symbol', $symbol)
            ->whereBetween('snapshot_time', [$timeWindowStart, $timeWindowEnd])
            ->orderBy('strike_price', 'asc')
            ->get();

        if ($snapshots->isEmpty()) {
            // Find closest snapshot on that date
            $closest = OptionSnapshot::query()
                ->where('symbol', $symbol)
                ->whereDate('snapshot_time', $timestamp->toDateString())
                ->orderByRaw('ABS(strftime("%s", snapshot_time) - ?)', [$timestamp->timestamp])
                ->first();

            if (! $closest) {
                // Try closest overall
                $closest = OptionSnapshot::query()
                    ->where('symbol', $symbol)
                    ->orderByRaw('ABS(strftime("%s", snapshot_time) - ?)', [$timestamp->timestamp])
                    ->first();
            }

            if ($closest) {
                $snapshots = OptionSnapshot::query()
                    ->where('symbol', $symbol)
                    ->where('snapshot_time', $closest->snapshot_time)
                    ->orderBy('strike_price', 'asc')
                    ->get();
            }
        }

        return $snapshots;
    }
}
