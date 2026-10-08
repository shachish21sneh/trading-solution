<?php

use App\Http\Controllers\Api\OptionChainApiController;
use App\Http\Controllers\BrokerAuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Broker 1-Click OAuth & Management Routes
Route::prefix('broker')->group(function () {
    Route::get('/status', [BrokerAuthController::class, 'status'])->name('broker.status');
    Route::post('/save-credentials', [BrokerAuthController::class, 'saveCredentials'])->name('broker.save');
    Route::get('/zerodha/login', [BrokerAuthController::class, 'zerodhaLogin'])->name('broker.zerodha.login');
    Route::get('/zerodha/callback', [BrokerAuthController::class, 'zerodhaCallback'])->name('broker.zerodha.callback');
    Route::get('/upstox/login', [BrokerAuthController::class, 'upstoxLogin'])->name('broker.upstox.login');
    Route::get('/upstox/callback', [BrokerAuthController::class, 'upstoxCallback'])->name('broker.upstox.callback');
    Route::match(['get', 'post'], '/angelone/login', [BrokerAuthController::class, 'angeloneLogin'])->name('broker.angelone.login');
    Route::post('/angelone/authorize', [BrokerAuthController::class, 'angeloneAuthorize'])->name('broker.angelone.authorize');
});

// Also expose API routes on web for frictionless Axios/Fetch calls in Inertia
Route::prefix('api/option-chain')->group(function () {
    Route::get('/live/{symbol}', [OptionChainApiController::class, 'live'])->name('api.live');
    Route::get('/history/{symbol}/{strike}', [OptionChainApiController::class, 'strikeHistory'])->name('api.history');
    Route::get('/historical-dates/{symbol}', [OptionChainApiController::class, 'historicalDates'])->name('api.historical.dates');
    Route::get('/replay-timeline/{symbol}', [OptionChainApiController::class, 'replayTimeline'])->name('api.replay.timeline');
    Route::get('/replay/{symbol}', [OptionChainApiController::class, 'replay'])->name('api.replay');
    Route::get('/alerts/{symbol}', [OptionChainApiController::class, 'alerts'])->name('api.alerts');
});
