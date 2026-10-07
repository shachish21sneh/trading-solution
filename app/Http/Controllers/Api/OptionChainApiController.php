<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AlertEngineInterface;
use App\Contracts\OiIntelligenceEngineInterface;
use App\Http\Controllers\Controller;
use App\Models\MarketAlert;
use App\Models\OptionSnapshot;
use App\Models\Underlying;
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
        protected MarketSessionService $sessionService
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
     * Get list of historical timestamps available today for timeline scrubbing
     */
    public function replayTimeline(string $symbol): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $today = Carbon::today();

        $rows = OptionSnapshot::query()
            ->where('symbol', $symbol)
            ->where('snapshot_time', '>=', $today)
            ->select('snapshot_time', 'spot_price')
            ->orderBy('snapshot_time', 'asc')
            ->get()
            ->unique(fn ($item) => $item->snapshot_time?->format('Y-m-d H:i:s'))
            ->map(fn ($item) => [
                'time' => $item->snapshot_time?->format('Y-m-d H:i:s'),
                'spot' => (float) $item->spot_price,
            ])
            ->values();

        // If no records today yet, provide recent distinct snapshot times
        if ($rows->isEmpty()) {
            $rows = OptionSnapshot::query()
                ->where('symbol', $symbol)
                ->select('snapshot_time', 'spot_price')
                ->orderBy('snapshot_time', 'asc')
                ->limit(200)
                ->get()
                ->unique(fn ($item) => $item->snapshot_time?->format('Y-m-d H:i:s'))
                ->map(fn ($item) => [
                    'time' => $item->snapshot_time?->format('Y-m-d H:i:s'),
                    'spot' => (float) $item->spot_price,
                ])
                ->values();
        }

        return response()->json([
            'symbol' => $symbol,
            'timeline' => $rows,
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

        // Find snapshots within 30-second window of target
        $snapshots = OptionSnapshot::query()
            ->where('symbol', $symbol)
            ->whereBetween('snapshot_time', [
                $targetTime->copy()->subSeconds(25),
                $targetTime->copy()->addSeconds(25),
            ])
            ->orderBy('strike_price', 'asc')
            ->get();

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
            if (!isset($strikesRaw[$strikeKey])) {
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
        $analysis['is_replay'] = true;
        $analysis['replay_time'] = $targetTime->format('H:i:s d-M-Y');

        return response()->json([
            'status' => 'success',
            'data' => $analysis,
            'replay_time' => $targetTime->toIso8601String(),
        ]);
    }
}
