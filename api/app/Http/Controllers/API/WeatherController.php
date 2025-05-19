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
    protected $baseUrl = 'https://api.openweathermap.org/data/2.5/onecall';
    protected $geoUrl = 'https://api.openweathermap.org/geo/1.0/direct';
    protected $reverseGeoUrl = 'https://api.openweathermap.org/geo/1.0/reverse';

    public function __construct()
    {
        // Load API key and URLs from config
        $this->apiKey = config('services.openweather.key');
        $this->baseUrl = config('services.openweather.base_url');
        $this->geoUrl = config('services.openweather.geo_url');
        $this->reverseGeoUrl = config('services.openweather.reverse_geo_url');
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
                'units' => 'metric',
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
     */
    protected function fetchWeatherData($lat, $lon)
    {
        $response = Http::get($this->baseUrl, [
            'lat' => $lat,
            'lon' => $lon,
            'units' => 'metric',
            'exclude' => 'minutely,hourly,alerts',
            'appid' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            Log::error('Weather API error', [
                'lat' => $lat,
                'lon' => $lon,
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            
            throw new \Exception('Weather API returned error: ' . $response->status());
        }

        return $response->json();
    }

    /**
     * Transform weather data to our format
     */
    protected function transformWeatherData($data)
    {
        $current = $data['current'];
        $daily = $data['daily'];
        
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
        
        return [
            'location' => $data['location'],
            'current' => [
                'temp' => round($current['temp']),
                'tempF' => round(($current['temp'] * 9/5) + 32),
                'condition' => $current['weather'][0]['main'],
                'icon' => $mapIcon($current['weather'][0]['icon']),
                'wind' => round($current['wind_speed']),
                'humidity' => $current['humidity'],
                'precipitation' => $current['rain']['1h'] ?? 0,
                'feelsLike' => round($current['feels_like']),
                'uv' => $current['uvi'] ?? 0,
            ],
            'forecast' => array_map(function($day) use ($mapIcon, $formatDay) {
                return [
                    'date' => date('Y-m-d', $day['dt']),
                    'day' => $formatDay($day['dt']),
                    'tempMin' => round($day['temp']['min']),
                    'tempMax' => round($day['temp']['max']),
                    'tempMinF' => round(($day['temp']['min'] * 9/5) + 32),
                    'tempMaxF' => round(($day['temp']['max'] * 9/5) + 32),
                    'icon' => $mapIcon($day['weather'][0]['icon']),
                    'condition' => $day['weather'][0]['main'],
                    'precipitation' => round($day['pop'] * 100),
                ];
            }, array_slice($daily, 1, 3)), // Get next 3 days
            'lastUpdated' => now()->toIso8601String(),
        ];
    }
}