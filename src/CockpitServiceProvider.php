<?php

namespace lionelhenne\LaravelCockpitCms;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use lionelhenne\LaravelCockpitCms\Console\Commands\WarmupCockpitImages;

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

        // Enregistrement de la commande de warmup
        if ($this->app->runningInConsole()) {
            $this->commands([
                WarmupCockpitImages::class,
            ]);
        }

        // Enregistre la route pour les images
        $this->registerImageRoute();
    }

    protected function registerImageRoute(): void
    {
        Route::get('/cockpit-images/{path}', function ($path) {
            $cockpitUrl = config('cockpit.url');
            $remoteUrl = rtrim($cockpitUrl, '/') . '/storage/uploads/' . ltrim($path, '/');
            
            // On stocke dans storage/app/public/cockpit/
            $localPath = 'public/cockpit/' . ltrim($path, '/');

            // 1. Si déjà sur le disque : on sert le fichier directement
            if (Storage::exists($localPath)) {
                return response()->file(storage_path('app/' . $localPath), [
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }

            // 2. Si Local : Redirection directe vers Cockpit pour éviter de saturer PHP
            if (app()->environment('local')) {
                return redirect($remoteUrl);
            }

            // 3. Sinon (Production) : Téléchargement et stockage physique
            try {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::timeout(15)->get($remoteUrl);

                if ($response->successful()) {
                    Storage::put($localPath, $response->body());
                    
                    return response($response->body())
                        ->header('Content-Type', $response->header('Content-Type'))
                        ->header('Cache-Control', 'public, max-age=31536000');
                }
            } catch (\Exception $e) {
                \Log::error("Cockpit Mirror Error: " . $e->getMessage());
            }

            abort(404);
        })->where('path', '.*')->name('cockpit.image');
    }
}