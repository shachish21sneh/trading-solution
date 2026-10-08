<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalOptionChainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_can_fetch_historical_dates_for_symbol(): void
    {
        $response = $this->getJson(route('api.historical.dates', ['symbol' => 'NIFTY']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'symbol',
                'dates',
            ]);
    }

    public function test_can_fetch_replay_timeline_for_symbol(): void
    {
        $response = $this->getJson(route('api.replay.timeline', [
            'symbol' => 'NIFTY',
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'symbol' => 'NIFTY',
            ])
            ->assertJsonStructure([
                'timeline',
                'min_time',
                'max_time',
                'total_points',
            ]);
    }

    public function test_can_replay_at_timestamp(): void
    {
        $response = $this->getJson(route('api.replay', [
            'symbol' => 'NIFTY',
            'timestamp' => now()->toDateTimeString(),
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonStructure([
                'data' => [
                    'symbol',
                    'spot_price',
                    'strikes',
                    'is_replay',
                ],
                'replay_time',
                'replay_time_only',
                'replay_date_only',
            ]);
    }

    public function test_dashboard_contains_available_historical_dates(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
