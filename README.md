# Weather App - Full Stack Project

A comprehensive weather application with a Laravel backend API and Next.js frontend that displays current weather conditions and forecasts.


## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Features](#features)
- [Backend Setup (Laravel)](#backend-setup-laravel)
- [Frontend Setup (Next.js)](#frontend-setup-nextjs)

## Overview

This Weather App provides users with real-time weather information and forecasts for any city worldwide or their current location. It uses the OpenWeatherMap API as the data source, with a Laravel backend that handles API communication, data transformation, and caching, and a Next.js frontend that delivers a responsive, user-friendly interface.

## Architecture

```
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│                 │      │                 │      │                 │
│  Next.js        │──────▶  Laravel        │──────▶  OpenWeatherMap │
│  Frontend       │      │  Backend API    │      │  External API   │
│                 │      │                 │      │                 │
└─────────────────┘      └─────────────────┘      └─────────────────┘
```

**Data Flow:**
1. User requests weather data in the Next.js app
2. Next.js makes a request to its internal API routes
3. Next.js API routes proxy the request to the Laravel backend
4. Laravel checks its cache for the requested data
5. If not cached, Laravel requests data from OpenWeatherMap
6. Laravel transforms and caches the data
7. Laravel returns the data to Next.js
8. Next.js displays the data to the user

## Features

- **Search weather by city name**
- **Get weather for current location**
- **Current weather conditions:**
  - Temperature (Celsius/Fahrenheit)
  - Weather condition and icon
  - Wind speed
  - Humidity
  - Precipitation
  - Feels like temperature
  - UV index
- **3-day weather forecast**
- **Toggle between Celsius and Fahrenheit**
- **Responsive design**
- **API connection status indicator**
- **Client-side and server-side caching**
- **Error handling and fallbacks**

## Backend Setup (Laravel)

### Requirements

- PHP 8.1 or higher
- Composer
- MySQL or another database supported by Laravel
- OpenWeatherMap API key

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/cloneyjay/next-php-weatherApi.git
   cd next-backend

2. Install Dependencies
    ```bash
    composer install

3. copy the environment file
    ```bash
    cp .env.example .env

4. Generate application key
    ```bash
    php artisan key:generate


5. Add your OpenWeatherMap API key to the `.env` file:
    ```bash
    OPENWEATHER_API_KEY=your_api_key_here

6. run development server
    ```bash
    composer run dev

## Backend configuration
The API configuration is stored in `config/services.php`:
    ```bash
    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
        'current_url' => 'https://api.openweathermap.org/data/2.5/weather',
        'forecast_url' => 'https://api.openweathermap.org/data/2.5/forecast',
        'geo_url' => 'https://api.openweathermap.org/geo/1.0/direct',
        'reverse_geo_url' => 'https://api.openweathermap.org/geo/1.0/reverse',
    ],

## CORS configuration
For cross-origin requests, update your `config/cors.php` file:
    ```bash
    return [
        'paths' => ['api/*', 'sanctum/csrf-cookie'],
        'allowed_methods' => ['*'],
        'allowed_origins' => ['http://localhost:3000'], // Your frontend URL
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => true,
    ];

## Front-End Set-UP
Requirement
- Node.js 18.x or higher
- npm or yarn
    ```bash
    git clone https://github.com/cloneyjay/weatherapp.git