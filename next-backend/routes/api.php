<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\WeatherController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Weather API routes - no authentication required
Route::prefix('weather')->group(function () {
    Route::get('/health', [WeatherController::class, 'healthCheck']);
    Route::get('/city', [WeatherController::class, 'getWeatherByCity']);
    Route::get('/coordinates', [WeatherController::class, 'getWeatherByCoordinates']);
});