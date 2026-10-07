<?php

namespace Database\Seeders;

use App\Models\Underlying;
use App\Services\Analytics\OiIntelligenceEngine;
use App\Services\MarketData\MarketDataService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $today = Carbon::now();
        $thursday = $today->copy();
        if ($thursday->dayOfWeek !== Carbon::THURSDAY || $thursday->isPast()) {
            $thursday->next(Carbon::THURSDAY);
        }
        $exp1 = $thursday->format('Y-m-d');
        $exp2 = $thursday->copy()->addWeek()->format('Y-m-d');
        $exp3 = $thursday->copy()->addWeeks(2)->format('Y-m-d');
        $exp4 = $thursday->copy()->addWeeks(3)->format('Y-m-d');

        $availableExpiries = [$exp1, $exp2, $exp3, $exp4];

        $underlyings = [
            [
                'symbol' => 'NIFTY',
                'name' => 'NIFTY 50',
                'spot_price' => 24525.50,
                'strike_step' => 50.00,
                'lot_size' => 50,
                'available_expiries' => $availableExpiries,
                'selected_expiry' => $exp1,
                'is_active' => true,
            ],
            [
                'symbol' => 'BANKNIFTY',
                'name' => 'NIFTY BANK',
                'spot_price' => 52280.00,
                'strike_step' => 100.00,
                'lot_size' => 15,
                'available_expiries' => $availableExpiries,
                'selected_expiry' => $exp1,
                'is_active' => true,
            ],
            [
                'symbol' => 'FINNIFTY',
                'name' => 'NIFTY FINANCIAL SERVICES',
                'spot_price' => 23840.00,
                'strike_step' => 50.00,
                'lot_size' => 40,
                'available_expiries' => $availableExpiries,
                'selected_expiry' => $exp1,
                'is_active' => true,
            ],
        ];

        $marketService = app(MarketDataService::class);
        $engine = app(OiIntelligenceEngine::class);

        foreach ($underlyings as $data) {
            $model = Underlying::updateOrCreate(['symbol' => $data['symbol']], $data);

            // Pre-seed initial historical snapshots through the intelligence engine
            $rawChain = $marketService->getOptionChain($model->symbol, $model->selected_expiry);
            $engine->processChain($model, $rawChain, $model->selected_expiry);
        }
    }
}
