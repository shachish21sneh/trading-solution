<?php

namespace App\Console\Commands;

use App\Contracts\AlertEngineInterface;
use App\Contracts\OiIntelligenceEngineInterface;
use App\Events\OptionChainUpdated;
use App\Models\Underlying;
use App\Services\MarketData\MarketDataService;
use App\Services\MarketSession\MarketSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class StreamOptionChainCommand extends Command
{
    protected $signature = 'option-chain:stream 
                            {--symbol= : Specific symbol to stream (default all active)} 
                            {--interval=5 : Polling/streaming interval in seconds} 
                            {--force : Force streaming even when live market is closed (uses simulation or cached quote)}
                            {--once : Run once and exit}';

    protected $description = 'Ingest real-time option chain data, compute OI intelligence, store snapshots, and broadcast live';

    public function handle(
        MarketDataService $marketService,
        OiIntelligenceEngineInterface $intelligenceEngine,
        AlertEngineInterface $alertEngine,
        MarketSessionService $sessionService
    ): int {
        $symbolFilter = $this->option('symbol');
        $interval = (int) $this->option('interval') ?: 5;
        $runOnce = $this->option('once');
        $force = (bool) $this->option('force');

        $providerName = $marketService->getActiveProvider()->getProviderName();
        $isSimulation = str_contains(strtolower($providerName), 'simulat');

        $this->info("⚡ OI Intelligence Engine Stream initialized [Provider: {$providerName}, Cadence: {$interval}s]");

        do {
            $session = $sessionService->getSessionStatus();
            $isMarketOpen = $session['is_open'];

            // Handle Market Closed Hours (Outside 09:15 AM - 03:30 PM IST)
            if (!$isMarketOpen && !$force && !$isSimulation) {
                $this->warn(sprintf(
                    "[%s] 🛑 MARKET IS CLOSED (%s). Real-time broker feed stopped.",
                    now('Asia/Kolkata')->format('H:i:s'),
                    $session['status']
                ));
                $this->line("Next live market session opens at: " . $session['next_open'] . " (" . $session['time_to_open'] . ")");
                $this->line("Tip: Pass --force to simulate live stream during offline hours.");

                // If runOnce requested, exit gracefully
                if ($runOnce) break;

                // Sleep for 30 seconds before re-checking market hours
                sleep(30);
                continue;
            }

            $query = Underlying::where('is_active', true);
            if ($symbolFilter) {
                $query->where('symbol', strtoupper($symbolFilter));
            }
            $underlyings = $query->get();

            if ($underlyings->isEmpty()) {
                $this->warn("No active underlyings found.");
                if ($runOnce) break;
                sleep($interval);
                continue;
            }

            foreach ($underlyings as $underlying) {
                try {
                    $expiry = $underlying->selected_expiry ?: ($underlying->available_expiries[0] ?? now()->format('Y-m-d'));

                    // 1. Fetch live chain from abstract Market Data Provider
                    $rawChain = $marketService->getOptionChain($underlying->symbol, $expiry);

                    // 2. Process through OI Intelligence Engine (Analyzes OI movement, detects reversals, saves snapshot)
                    $previousAnalysis = Cache::get("options:live:analysis:{$underlying->symbol}");
                    $analysis = $intelligenceEngine->processChain($underlying, $rawChain, $expiry);

                    // Attach market session status to live analysis
                    $analysis['market_session'] = $session;

                    // 3. Cache current analysis in Redis/Cache
                    Cache::put("options:live:analysis:{$underlying->symbol}", $analysis, 30);

                    // 4. Run Alert Engine (Support/resistance shifts, breakout/breakdown, heavy build-up/unwinding)
                    $alertEngine->evaluateAndDispatch($underlying, $analysis, $previousAnalysis);

                    // 5. Broadcast live WebSocket event
                    event(new OptionChainUpdated($underlying->symbol, $analysis));

                    $this->line(sprintf(
                        "[%s] %-10s Spot: %-8.2f ATM: %-7.0f PCR: %-5.2f Sup: %-7.0f Res: %-7.0f MaxPain: %-7.0f | Saved 11 strikes",
                        now('Asia/Kolkata')->format('H:i:s'),
                        $underlying->symbol,
                        $analysis['spot_price'],
                        $analysis['atm_strike'],
                        $analysis['totals']['pcr'],
                        $analysis['levels']['support_1'],
                        $analysis['levels']['resistance_1'],
                        $analysis['levels']['max_pain']
                    ));
                } catch (\Throwable $e) {
                    $this->error("Error processing {$underlying->symbol}: " . $e->getMessage());
                }
            }

            if ($runOnce) {
                break;
            }

            sleep($interval);
        } while (true);

        return Command::SUCCESS;
    }
}
