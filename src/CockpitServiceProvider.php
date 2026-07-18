<?php

namespace lionelhenne\LaravelCockpitCms;

use Illuminate\Support\ServiceProvider;

class CockpitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // IMPORTANT : Conserve bien ceci pour la config
        $this->mergeConfigFrom(
            __DIR__.'/../config/cockpit.php', 'cockpit'
        );

        $this->app->singleton(CockpitService::class, function ($app) {
            return new CockpitService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/cockpit.php' => config_path('cockpit.php'),
        ], 'cockpit-config');
    }
}