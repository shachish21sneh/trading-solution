<?php

namespace App\Http\Controllers;

use App\Contracts\OiIntelligenceEngineInterface;
use App\Models\MarketAlert;
use App\Models\Underlying;
use App\Services\MarketData\MarketDataService;
use App\Services\MarketSession\MarketSessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        MarketDataService $marketService,
        OiIntelligenceEngineInterface $intelligenceEngine,
        MarketSessionService $sessionService
    ): Response {
        $underlyings = Underlying::where('is_active', true)->get();
        $selectedSymbol = strtoupper($request->query('symbol', 'NIFTY'));
        
        $activeUnderlying = $underlyings->firstWhere('symbol', $selectedSymbol) 
            ?: $underlyings->first();

        $selectedExpiry = $request->query('expiry', $activeUnderlying->selected_expiry ?: ($activeUnderlying->available_expiries[0] ?? now()->format('Y-m-d')));

        // Fetch live option chain & run intelligence engine
        $rawChain = $marketService->getOptionChain($activeUnderlying->symbol, $selectedExpiry);
        $analysis = $intelligenceEngine->processChain($activeUnderlying, $rawChain, $selectedExpiry);

        $sessionStatus = $sessionService->getSessionStatus();
        $analysis['market_session'] = $sessionStatus;

        $recentAlerts = MarketAlert::where('symbol', $activeUnderlying->symbol)
            ->latest('id')
            ->limit(20)
            ->get();

        return Inertia::render('OptionChainTerminal', [
            'underlyings' => $underlyings,
            'activeUnderlying' => $activeUnderlying,
            'selectedExpiry' => $selectedExpiry,
            'initialAnalysis' => $analysis,
            'initialAlerts' => $recentAlerts,
            'providerName' => $marketService->getActiveProvider()->getProviderName(),
            'marketSession' => $sessionStatus,
        ]);
    }
}
