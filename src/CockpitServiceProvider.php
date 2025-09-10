<?php

namespace lionelhenne\LaravelCockpitCms;

use Illuminate\Support\ServiceProvider;
use lionelhenne\LaravelCockpitCms\CockpitService;

class CockpitServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 1. Fusionne la config par défaut pour que `config('cockpit.key')` fonctionne
        $this->mergeConfigFrom(
            __DIR__.'/../config/cockpit.php', 'cockpit'
        );

        // 2. On met ici la logique de ton AppServiceProvider
        // On enregistre CockpitService comme un singleton dans l'app
        $this->app->singleton(CockpitService::class, function ($app) {
            return new CockpitService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 3. On rend le fichier de config "publiable"
        // L'utilisateur pourra l'écraser avec `php artisan vendor:publish`
        $this->publishes([
            __DIR__.'/../config/cockpit.php' => config_path('cockpit.php'),
        ], 'cockpit-config'); // 'cockpit-config' est un tag optionnel mais propre
    }
}