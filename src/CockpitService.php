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

            $response->throw(); // Lance une exception si la requête échoue

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Erreur Cockpit API: " . $e->getMessage());
            return ['data' => null, 'error' => $e->getMessage()];
        }
    }
}