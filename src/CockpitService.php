<?php

namespace lionelhenne\LaravelCockpitCms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CockpitService
{
    protected $endpoint;
    protected $token;

    public function __construct()
    {
        $this->endpoint = config('cockpit.endpoint');
        $this->token = config('cockpit.token');
    }

    /**
     * Executes a raw GraphQL query.
     *
     * @param string $graphQLQuery The complete GraphQL query string.
     * @param array $variables Optional variables for the query.
     * @return array
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function query(string $graphQLQuery, array $variables = []): array
    {
        // 1. On définit les headers minimaux obligatoires
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // 2. On n'ajoute le header "Authorization" QUE si un token est réellement configuré.
        // Dans ton cas (API Publique), $this->token sera vide, donc ce bloc sera ignoré.
        if (!empty($this->token)) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        try {
            // 3. On passe le tableau $headers dynamique à la requête
            $response = Http::withHeaders($headers)->post($this->endpoint, [
                'query' => $graphQLQuery,
                'variables' => $variables,
            ]);

            $response->throw();

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Cockpit API Error: " . $e->getMessage());
            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Assembles multiple query fragments into a single query.
     *
     * @param array $queries An array of GraphQL fragment strings.
     * @return string The assembled query (e.g., "{ fragment1 \n fragment2 }").
     */
    public function assembleQuery(array $queries): string
    {
        $body = implode("\n", $queries);
        return '{ ' . $body . ' }';
    }

    /**
     * Low-level tool to wrap a call in Cache::remember.
     * This method no longer contains environment-specific logic.
     *
     * @param string $key The cache key.
     * @param \DateTimeInterface|\DateInterval|int $duration The cache duration.
     * @param callable $callback The function to execute if the cache is empty.
     * @return mixed
     */
    public function cachedQuery(string $key, $duration, callable $callback)
    {
        return Cache::remember($key, $duration, $callback);
    }

    /**
     * High-level helper: Assembles and executes a batch of queries WITHOUT cache.
     *
     * @param array $queries An array of query fragments.
     * @return array The JSON response from the API.
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function execute(array $queries): array
    {
        $query = $this->assembleQuery($queries);
        return $this->query($query);
    }

    /**
     * High-level helper: Assembles, executes, and caches a batch of queries.
     *
     * @param array $queries An array of query fragments.
     * @param string $cacheKey The cache key.
     * @param \DateTimeInterface|\DateInterval|int|null $duration Duration (default: 1 month).
     * @return array The JSON response from the API (cached or fresh).
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function executeCached(array $queries, string $cacheKey, $duration = null): array
    {
        $cacheDuration = $duration ?? now()->addMonth();
        $callback = fn() => $this->execute($queries);
        
        // Calls the "clean" cachedQuery version
        return $this->cachedQuery($cacheKey, $cacheDuration, $callback);
    }

    /**
     * Generates the URL for a Cockpit image (handles proxy/cache in prod).
     *
     * @param string|null $path The image path (e.g., "/path/to/image.jpg").
     * @return string|null The full URL (direct in dev, via 'cockpit.image' route in prod).
     */
    public function imageUrl(?string $path): ?string
    {
        if ( ! $path) {
            return null;
        }

        // In dev, direct URL without proxy
        // This environment logic is still valid here as it's part of the proxy logic
        if (app()->environment('local')) {
            return config('cockpit.url') . '/storage/uploads/' . ltrim($path, '/');
        }

        // In prod, proxy with cache
        $cleanPath = ltrim($path, '/');
        return route('cockpit.image', ['path' => $cleanPath]);
    }
}