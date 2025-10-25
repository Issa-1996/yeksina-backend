<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GeocodingService;

class GeocodingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(GeocodingService::class, function ($app) {
            return new GeocodingService();
        });
    }

    public function boot()
    {
        //
    }
}