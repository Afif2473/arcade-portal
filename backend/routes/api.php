<?php

use App\Http\Controllers\Api\GameCatalogController;
use App\Http\Controllers\Api\GameSaveController;
use App\Http\Controllers\Api\GameScoreController;
use App\Http\Controllers\Api\DeveloperGameController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public catalog & game details
Route::get('/games', [GameCatalogController::class, 'index']);
Route::get('/games/{slug}', [GameCatalogController::class, 'show']);
Route::get('/games/{slug}/leaderboard', [GameScoreController::class, 'leaderboard']);

// Authenticated player routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::prefix('developer')->group(function () {
        Route::get('/games', [DeveloperGameController::class, 'index']);
        Route::post('/games/{slug}/upload', [DeveloperGameController::class, 'uploadBundle']);
        Route::patch('/games/{slug}', [DeveloperGameController::class, 'updateStatus']);
    });

    // Score submission with rate limiter (10 per minute)
    Route::post('/games/{slug}/scores', [GameScoreController::class, 'submit'])
        ->middleware('throttle:10,1');

    // Cloud Saves
    Route::get('/games/{slug}/saves/{slot?}', [GameSaveController::class, 'show']);
    Route::post('/games/{slug}/saves', [GameSaveController::class, 'store'])
        ->middleware('throttle:20,1');
});
