<?php

use App\Http\Controllers\Api\OptionChainApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('option-chain')->group(function () {
    Route::get('/live/{symbol}', [OptionChainApiController::class, 'live']);
    Route::get('/history/{symbol}/{strike}', [OptionChainApiController::class, 'strikeHistory']);
    Route::get('/replay-timeline/{symbol}', [OptionChainApiController::class, 'replayTimeline']);
    Route::get('/replay/{symbol}', [OptionChainApiController::class, 'replay']);
    Route::get('/alerts/{symbol}', [OptionChainApiController::class, 'alerts']);
});
