<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WeatherController extends Controller
{
    protected $apiKey;
    protected $currentWeatherUrl = 'https://api.openweathermap.org/data/2.5/weather';
    protected $forecastUrl = 'https://api.openweathermap.org/data/2.5/forecast';
    protected $geoUrl = 'https://api.openweathermap.org/geo/1.0/direct';
    protected $reverseGeoUrl = 'https://api.openweathermap.org/geo/1.0/reverse';

    public function __construct()
    {
        // Load API key from config
        $this->apiKey = config('services.openweather.key');
        
        // Optionally load URLs from config if they exist
        if (config('services.openweather.current_url')) {
            $this->currentWeatherUrl = config('services.openweather.current_url');
        }
        if (config('services.openweather.forecast_url')) {
            $this->forecastUrl = config('services.openweather.forecast_url');
        }
        if (config('services.openweather.geo_url')) {
            $this->geoUrl = config('services.openweather.geo_url');
        }
        if (config('services.openweather.reverse_geo_url')) {
            $this->reverseGeoUrl = config('services.openweather.reverse_geo_url');
        }
    }

    /**
     * Get weather data by city name
     */
    public function getWeatherByCity(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'city' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $city = $request->input('city');

        try {
            // Check cache first
            $cacheKey = 'weather_' . strtolower($city);
            if (Cache::has($cacheKey)) {
                return response()->json(Cache::get($cacheKey));
            }

            // Get coordinates for the city
            $geoResponse = Http::get($this->geoUrl, [
                'q' => $city,
                'limit' => 1,
                'appid' => $this->apiKey,
            ]);

            if (!$geoResponse->successful()) {
                Log::error('Geocoding API error', [
                    'city' => $city,
                    'status' => $geoResponse->status(),
                    'response' => $geoResponse->json()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to geocode city'
                ], $geoResponse->status());
            }

            $geoData = $geoResponse->json();

            if (empty($geoData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'City not found'
                ], 404);
            }

            // Get weather data using coordinates
            $lat = $geoData[0]['lat'];
            $lon = $geoData[0]['lon'];
            $locationName = $geoData[0]['name'];
            $country = $geoData[0]['country'];

            $weatherData = $this->fetchWeatherData($lat, $lon);
            
            // Add location information
            $weatherData['location'] = [
                'name' => $locationName,
                'country' => $country,
                'lat' => $lat,
                'lon' => $lon
            ];

            // Transform data to our format
            $transformedData = $this->transformWeatherData($weatherData);
            
            // Cache the result for 10 minutes
            Cache::put($cacheKey, $transformedData, now()->addMinutes(10));

            return response()->json($transformedData);
        } catch (\Exception $e) {
            Log::error('Weather API error', [
                'city' => $city,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch weather data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get weather data by coordinates
     */
    public function getWeatherByCoordinates(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lat = $request->input('lat');
        $lon = $request->input('lon');

        try {
            // Check cache first
            $cacheKey = 'weather_coord_' . $lat . '_' . $lon;
            if (Cache::has($cacheKey)) {
                return response()->json(Cache::get($cacheKey));
            }

            // Get location name using reverse geocoding
            $geoResponse = Http::get($this->reverseGeoUrl, [
                'lat' => $lat,
                'lon' => $lon,
                'limit' => 1,
                'appid' => $this->apiKey,
            ]);

            $locationName = 'Unknown Location';
            $country = '';

            if ($geoResponse->successful()) {
                $geoData = $geoResponse->json();
                if (!empty($geoData)) {
                    $locationName = $geoData[0]['name'];
                    $country = $geoData[0]['country'];
                }
            }

            $weatherData = $this->fetchWeatherData($lat, $lon);
            
            // Add location information
            $weatherData['location'] = [
                'name' => $locationName,
                'country' => $country,
                'lat' => $lat,
                'lon' => $lon
            ];

            // Transform data to our format
            $transformedData = $this->transformWeatherData($weatherData);
            
            // Cache the result for 10 minutes
            Cache::put($cacheKey, $transformedData, now()->addMinutes(10));

            return response()->json($transformedData);
        } catch (\Exception $e) {
            Log::error('Weather API error', [
                'lat' => $lat,
                'lon' => $lon,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch weather data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check endpoint
     */
    public function healthCheck()
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Weather API is operational',
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Fetch weather data from OpenWeatherMap API
     * This now fetches both current weather and forecast data
     */
    protected function fetchWeatherData($lat, $lon)
    {
        // Get current weather
        $currentResponse = Http::get($this->currentWeatherUrl, [
            'lat' => $lat,
            'lon' => $lon,
            'units' => 'metric',
            'appid' => $this->apiKey,
        ]);

        if (!$currentResponse->successful()) {
            Log::error('Current Weather API error', [
                'lat' => $lat,
                'lon' => $lon,
                'status' => $currentResponse->status(),
                'response' => $currentResponse->json()
            ]);
            
            throw new \Exception('Weather API returned error: ' . $currentResponse->status());
        }

        // Get forecast data
        $forecastResponse = Http::get($this->forecastUrl, [
            'lat' => $lat,
            'lon' => $lon,
            'units' => 'metric',
            'appid' => $this->apiKey,
        ]);

        if (!$forecastResponse->successful()) {
            Log::error('Forecast API error', [
                'lat' => $lat,
                'lon' => $lon,
                'status' => $forecastResponse->status(),
                'response' => $forecastResponse->json()
            ]);
            
            throw new \Exception('Forecast API returned error: ' . $forecastResponse->status());
        }

        // Combine the data
        return [
            'current' => $currentResponse->json(),
            'forecast' => $forecastResponse->json()
        ];
    }

    /**
     * Transform weather data to our format
     * Updated to handle the free tier API response format
     */
    protected function transformWeatherData($data)
    {
        $current = $data['current'];
        $forecast = $data['forecast'];
        
        // Map weather icon codes to our simplified set
        $mapIcon = function($iconCode) {
            $code = substr($iconCode, 0, 2);
            
            if ($code === '01') return 'sun';
            if (in_array($code, ['02', '03', '04'])) return 'cloud';
            if (in_array($code, ['09', '10'])) return 'rain';
            if ($code === '11') return 'storm';
            if ($code === '13') return 'snow';
            if ($code === '50') return 'mist';
            
            return 'sun'; // Default
        };
        
        // Format day of week
        $formatDay = function($timestamp) {
            return date('D j', $timestamp);
        };
        
        // Process forecast data to get daily forecasts
        // The 5-day forecast API returns data in 3-hour intervals
        // We'll get one forecast per day by taking the noon forecast
        $dailyForecasts = [];
        $processedDays = [];
        
        foreach ($forecast['list'] as $item) {
            // Get the date part only
            $date = date('Y-m-d', $item['dt']);
            
            // Skip if we already have this day or if it's today
            if (in_array($date, $processedDays) || $date === date('Y-m-d')) {
                continue;
            }
            
            // Only take forecasts around noon for consistency
            $hour = (int)date('H', $item['dt']);
            if ($hour < 11 || $hour > 13) {
                continue;
            }
            
            $processedDays[] = $date;
            $dailyForecasts[] = $item;
            
            // Stop after we have 3 days
            if (count($dailyForecasts) >= 3) {
                break;
            }
        }
        
        // If we couldn't get enough noon forecasts, just take the first forecast of each day
        if (count($dailyForecasts) < 3) {
            $processedDays = [];
            $dailyForecasts = [];
            
            foreach ($forecast['list'] as $item) {
                $date = date('Y-m-d', $item['dt']);
                
                if (in_array($date, $processedDays) || $date === date('Y-m-d')) {
                    continue;
                }
                
                $processedDays[] = $date;
                $dailyForecasts[] = $item;
                
                if (count($dailyForecasts) >= 3) {
                    break;
                }
            }
        }
        
        return [
            'location' => $data['location'],
            'current' => [
                'temp' => round($current['main']['temp']),
                'tempF' => round(($current['main']['temp'] * 9/5) + 32),
                'condition' => $current['weather'][0]['main'],
                'icon' => $mapIcon($current['weather'][0]['icon']),
                'wind' => round($current['wind']['speed']),
                'humidity' => $current['main']['humidity'],
                'precipitation' => $current['rain']['1h'] ?? 0,
                'feelsLike' => round($current['main']['feels_like']),
                'uv' => 0, // UV index not available in free tier
            ],
            'forecast' => array_map(function($day) use ($mapIcon, $formatDay) {
                return [
                    'date' => date('Y-m-d', $day['dt']),
                    'day' => $formatDay($day['dt']),
                    'tempMin' => round($day['main']['temp_min']),
                    'tempMax' => round($day['main']['temp_max']),
                    'tempMinF' => round(($day['main']['temp_min'] * 9/5) + 32),
                    'tempMaxF' => round(($day['main']['temp_max'] * 9/5) + 32),
                    'icon' => $mapIcon($day['weather'][0]['icon']),
                    'condition' => $day['weather'][0]['main'],
                    'precipitation' => round($day['pop'] * 100 ?? 0),
                ];
            }, $dailyForecasts),
            'lastUpdated' => now()->toIso8601String(),
        ];
    }
}