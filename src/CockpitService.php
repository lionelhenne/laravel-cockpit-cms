<?php

namespace lionelhenne\LaravelCockpitCms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CockpitService
{
    protected $endpoint;
    protected $token;

    public function __construct()
    {
        $this->endpoint = config('cockpit.endpoint');
        $this->token = config('cockpit.token');
    }

    public function query(string $graphQLQuery, array $variables = []): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ])->post($this->endpoint, [
                'query' => $graphQLQuery,
                'variables' => $variables,
            ]);

            $response->throw();

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Erreur Cockpit API: " . $e->getMessage());
            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    public function imageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // En dev, URL directe sans proxy
        if (app()->environment('local')) {
            return config('cockpit.url') . '/storage/uploads/' . ltrim($path, '/');
        }

        // En prod, proxy avec cache
        $cleanPath = ltrim($path, '/');
        return route('cockpit.image', ['path' => $cleanPath]);
    }
}