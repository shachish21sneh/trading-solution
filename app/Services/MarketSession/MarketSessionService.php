<?php

namespace App\Services\MarketSession;

use Carbon\Carbon;

class MarketSessionService
{
    // NSE Standard Market Timings in IST (Asia/Kolkata)
    const MARKET_OPEN_HOUR = 9;

    const MARKET_OPEN_MINUTE = 15;

    const MARKET_CLOSE_HOUR = 15;

    const MARKET_CLOSE_MINUTE = 30;

    /**
     * Get comprehensive market session status
     */
    public function getSessionStatus(): array
    {
        $now = Carbon::now('Asia/Kolkata');
        $isWeekday = in_array($now->dayOfWeek, [
            Carbon::MONDAY,
            Carbon::TUESDAY,
            Carbon::WEDNESDAY,
            Carbon::THURSDAY,
            Carbon::FRIDAY,
        ]);

        $marketOpenToday = $now->copy()->setTime(self::MARKET_OPEN_HOUR, self::MARKET_OPEN_MINUTE, 0);
        $marketCloseToday = $now->copy()->setTime(self::MARKET_CLOSE_HOUR, self::MARKET_CLOSE_MINUTE, 0);

        $isOpen = $isWeekday && $now->between($marketOpenToday, $marketCloseToday);
        $isPreOpen = $isWeekday && $now->between(
            $now->copy()->setTime(9, 0, 0),
            $marketOpenToday
        );

        // Calculate next market open time
        $nextOpen = $this->calculateNextMarketOpen($now);

        $diffMinutes = $now->diffInMinutes($nextOpen, false);
        $hours = intdiv(abs($diffMinutes), 60);
        $mins = abs($diffMinutes) % 60;
        $timeToOpen = "{$hours}h {$mins}m";

        return [
            'is_open' => $isOpen,
            'is_pre_open' => $isPreOpen,
            'status' => $isOpen ? 'OPEN' : ($isPreOpen ? 'PRE_OPEN' : 'CLOSED'),
            'current_time_ist' => $now->format('d-M-Y H:i:s').' IST',
            'date_ist' => $now->format('d-M-Y'),
            'time_ist' => $now->format('H:i:s').' IST',
            'timestamp' => $now->toIso8601String(),
            'market_open_time' => '09:15:00 IST',
            'market_close_time' => '15:30:00 IST',
            'next_open' => $nextOpen->format('Y-m-d H:i:s'),
            'time_to_open' => $timeToOpen,
            'message' => $isOpen
                ? 'Market is currently ACTIVE. Real-time broker data streaming.'
                : ($isPreOpen
                    ? 'Market is in PRE-OPEN session (09:00 - 09:15 IST).'
                    : "Market is CLOSED. Displaying End-Of-Day (EOD) Closing Snapshot. Next session opens in {$timeToOpen}."),
        ];
    }

    /**
     * Calculate next market opening datetime
     */
    public function calculateNextMarketOpen(Carbon $now): Carbon
    {
        $next = $now->copy();

        // If today is weekday and before 09:15 AM
        if (in_array($now->dayOfWeek, [Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY])) {
            $openToday = $now->copy()->setTime(self::MARKET_OPEN_HOUR, self::MARKET_OPEN_MINUTE, 0);
            if ($now->lt($openToday)) {
                return $openToday;
            }
        }

        // Otherwise advance to next weekday 09:15 AM
        $next->addDay();
        while (in_array($next->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
            $next->addDay();
        }

        return $next->setTime(self::MARKET_OPEN_HOUR, self::MARKET_OPEN_MINUTE, 0);
    }
}
