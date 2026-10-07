<?php

namespace App\Providers;

use App\Contracts\AlertEngineInterface;
use App\Contracts\MarketDataProviderInterface;
use App\Contracts\OiIntelligenceEngineInterface;
use App\Repositories\Contracts\MarketAlertRepositoryInterface;
use App\Repositories\Contracts\OptionSnapshotRepositoryInterface;
use App\Repositories\Contracts\UnderlyingRepositoryInterface;
use App\Repositories\Eloquent\MarketAlertRepository;
use App\Repositories\Eloquent\OptionSnapshotRepository;
use App\Repositories\Eloquent\UnderlyingRepository;
use App\Services\Alerts\AlertEngine;
use App\Services\Analytics\OiIntelligenceEngine;
use App\Services\MarketData\MarketDataService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Repositories
        $this->app->bind(OptionSnapshotRepositoryInterface::class, OptionSnapshotRepository::class);
        $this->app->bind(MarketAlertRepositoryInterface::class, MarketAlertRepository::class);
        $this->app->bind(UnderlyingRepositoryInterface::class, UnderlyingRepository::class);

        // Bind Services
        $this->app->singleton(MarketDataService::class, fn () => new MarketDataService());
        $this->app->bind(MarketDataProviderInterface::class, fn ($app) => $app->make(MarketDataService::class)->getActiveProvider());
        $this->app->singleton(OiIntelligenceEngineInterface::class, OiIntelligenceEngine::class);
        $this->app->singleton(AlertEngineInterface::class, AlertEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
