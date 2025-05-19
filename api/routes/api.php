<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\WeatherController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/weather/current', [WeatherController::class, 'getWeatherByCity']);
    Route::get('/weather/coordinates', [WeatherController::class, 'getWeatherByCoordinates']);
    Route::get('/weather/health', [WeatherController::class, 'healthCheck']);
});
