<?php

namespace lionelhenne\LaravelCockpitCms;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CockpitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cockpit.php', 'cockpit'
        );

        $this->app->singleton(CockpitService::class, function ($app) {
            return new CockpitService();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/cockpit.php' => config_path('cockpit.php'),
        ], 'cockpit-config');

        // Enregistre la route pour les images
        $this->registerImageRoute();
    }

    protected function registerImageRoute(): void
    {
        Route::get('/cockpit-images/{path}', function ($path) {
            $url = config('cockpit.url') . '/storage/uploads/' . ltrim($path, '/');
            
            // En dev, on saute le cache
            if (app()->environment('local')) {
                $response = Http::timeout(10)->get($url);
                
                if ($response->failed()) {
                    abort(404);
                }
                
                return response($response->body(), 200)
                    ->header('Content-Type', $response->header('Content-Type'))
                    ->header('Cache-Control', 'no-cache');
            }
            
            // En prod, on cache
            $cacheKey = 'cockpit_image_' . md5($path);
            
            $image = Cache::remember($cacheKey, now()->addYear(), function () use ($url) {
                $response = Http::timeout(10)->get($url);
                
                if ($response->failed()) {
                    return null;
                }
                
                return [
                    'body' => $response->body(),
                    'content_type' => $response->header('Content-Type')
                ];
            });
            
            if (!$image) {
                abort(404);
            }
            
            return response($image['body'], 200)
                ->header('Content-Type', $image['content_type'])
                ->header('Cache-Control', 'public, max-age=31536000');
        })->where('path', '.*')->name('cockpit.image');
    }
}