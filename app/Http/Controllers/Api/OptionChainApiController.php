<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AlertEngineInterface;
use App\Contracts\OiIntelligenceEngineInterface;
use App\Http\Controllers\Controller;
use App\Models\MarketAlert;
use App\Models\OptionSnapshot;
use App\Models\Underlying;
use App\Repositories\Contracts\OptionSnapshotRepositoryInterface;
use App\Services\MarketData\MarketDataService;
use App\Services\MarketSession\MarketSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OptionChainApiController extends Controller
{
    public function __construct(
        protected MarketDataService $marketService,
        protected OiIntelligenceEngineInterface $intelligenceEngine,
        protected AlertEngineInterface $alertEngine,
        protected MarketSessionService $sessionService,
        protected OptionSnapshotRepositoryInterface $snapshotRepo
    ) {}

    public function live(string $symbol, Request $request): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $underlying = Underlying::where('symbol', $symbol)->firstOrFail();
        $expiry = $request->query('expiry', $underlying->selected_expiry ?: ($underlying->available_expiries[0] ?? now()->format('Y-m-d')));

        $previousAnalysis = Cache::get("options:live:analysis:{$symbol}");
        $session = $this->sessionService->getSessionStatus();

        $rawChain = $this->marketService->getOptionChain($symbol, $expiry);
        $analysis = $this->intelligenceEngine->processChain($underlying, $rawChain, $expiry);
        $analysis['market_session'] = $session;
        $analysis['available_expiries'] = $this->marketService->getExpiryDates($symbol);

        Cache::put("options:live:analysis:{$symbol}", $analysis, 30);

        // Run alert engine on live poll/socket tick
        $this->alertEngine->evaluateAndDispatch($underlying, $analysis, $previousAnalysis);

        return response()->json([
            'status' => 'success',
            'data' => $analysis,
            'provider' => $this->marketService->getActiveProvider()->getProviderName(),
            'market_session' => $session,
        ]);
    }

    public function strikeHistory(string $symbol, float $strike, Request $request): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $minutes = (int) $request->query('minutes', 60);
        $since = Carbon::now()->subMinutes($minutes);

        $ceHistory = OptionSnapshot::query()
            ->where('symbol', $symbol)
            ->where('strike_price', $strike)
            ->where('option_type', 'CE')
            ->where('snapshot_time', '>=', $since)
            ->orderBy('snapshot_time', 'asc')
            ->get();

        $peHistory = OptionSnapshot::query()
            ->where('symbol', $symbol)
            ->where('strike_price', $strike)
            ->where('option_type', 'PE')
            ->where('snapshot_time', '>=', $since)
            ->orderBy('snapshot_time', 'asc')
            ->get();

        return response()->json([
            'symbol' => $symbol,
            'strike' => $strike,
            'ce' => $ceHistory,
            'pe' => $peHistory,
        ]);
    }

    public function alerts(string $symbol): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $alerts = MarketAlert::where('symbol', $symbol)
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'symbol' => $symbol,
            'alerts' => $alerts,
        ]);
    }

    /**
     * Get available historical dates with snapshot counts and time spans for a symbol
     */
    public function historicalDates(string $symbol): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $dates = $this->snapshotRepo->getAvailableHistoricalDates($symbol);

        return response()->json([
            'status' => 'success',
            'symbol' => $symbol,
            'dates' => $dates,
        ]);
    }

    /**
     * Get list of historical timestamps available for timeline scrubbing (filtered by date)
     */
    public function replayTimeline(string $symbol, Request $request): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $dateParam = $request->query('date');

        $result = $this->snapshotRepo->getTimelineForDate($symbol, $dateParam);

        return response()->json([
            'status' => 'success',
            'symbol' => $symbol,
            'selected_date' => $result['date'],
            'selected_date_formatted' => $result['date_formatted'],
            'min_time' => $result['min_time'],
            'max_time' => $result['max_time'],
            'min_time_24' => $result['min_time_24'],
            'max_time_24' => $result['max_time_24'],
            'total_points' => count($result['timeline']),
            'timeline' => $result['timeline'],
        ]);
    }

    /**
     * Replay exact option chain at historical timestamp
     */
    public function replay(string $symbol, Request $request): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $timeStr = $request->query('timestamp');
        $targetTime = $timeStr ? Carbon::parse($timeStr) : Carbon::now()->subMinutes(15);
        $underlying = Underlying::where('symbol', $symbol)->firstOrFail();

        $snapshots = $this->snapshotRepo->getSnapshotsAtTimestamp($symbol, $targetTime);

        if ($snapshots->isEmpty()) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No historical snapshot found for requested timestamp',
                'data' => null,
            ]);
        }

        // Reconstruct raw chain from snapshots and run through intelligence engine
        $spotPrice = $snapshots->first()->spot_price;
        $strikesRaw = [];

        foreach ($snapshots as $snap) {
            $strikeKey = number_format($snap->strike_price, 2, '.', '');
            if (! isset($strikesRaw[$strikeKey])) {
                $strikesRaw[$strikeKey] = [
                    'strike_price' => $snap->strike_price,
                    'CE' => ['oi' => 0, 'change_oi' => 0, 'volume' => 0, 'iv' => 0, 'ltp' => 0, 'change' => 0],
                    'PE' => ['oi' => 0, 'change_oi' => 0, 'volume' => 0, 'iv' => 0, 'ltp' => 0, 'change' => 0],
                ];
            }

            $type = $snap->option_type;
            $strikesRaw[$strikeKey][$type] = [
                'oi' => $snap->oi,
                'change_oi' => $snap->change_oi,
                'volume' => $snap->volume,
                'iv' => $snap->iv,
                'ltp' => $snap->ltp,
                'change' => $snap->price_change,
            ];
        }

        $rawChain = [
            'spot_price' => $spotPrice,
            'timestamp' => $targetTime->toIso8601String(),
            'strikes' => $strikesRaw,
        ];

        $expiry = $snapshots->first()->expiry_date ?: $underlying->selected_expiry;
        $analysis = $this->intelligenceEngine->processChain($underlying, $rawChain, $expiry);

        $actualSnapshotTime = Carbon::parse($snapshots->first()->snapshot_time);
        $analysis['is_replay'] = true;
        $analysis['replay_time'] = $actualSnapshotTime->format('H:i:s d-M-Y');
        $analysis['replay_time_only'] = $actualSnapshotTime->format('H:i:s');
        $analysis['replay_date_only'] = $actualSnapshotTime->format('d-M-Y');
        $analysis['replay_date_ymd'] = $actualSnapshotTime->format('Y-m-d');
        $analysis['spot_price'] = $spotPrice;

        return response()->json([
            'status' => 'success',
            'data' => $analysis,
            'replay_time' => $actualSnapshotTime->toIso8601String(),
            'replay_time_only' => $actualSnapshotTime->format('H:i:s'),
            'replay_date_only' => $actualSnapshotTime->format('d-M-Y'),
            'replay_date_ymd' => $actualSnapshotTime->format('Y-m-d'),
        ]);
    }
}
