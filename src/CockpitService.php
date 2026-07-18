<?php

namespace lionelhenne\LaravelCockpitCms;

use Illuminate\Support\Facades\Http;
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
     * @throws CockpitRequestException
     */
    public function query(string $graphQLQuery, array $variables = []): array
    {
        // 1. Define minimal required headers
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // 2. Add "Authorization" header ONLY if a token is actually configured.
        // In your case (Public API), $this->token will be empty, so this block will be ignored.
        if (!empty($this->token)) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        // 3. Pass the dynamic $headers array to the request
        $http = Http::withHeaders($headers);

        // In local development, disable SSL verification
        if (app()->environment('local')) {
            $http = $http->withOptions(['verify' => false]);
        }

        try {
            $response = $http->post($this->endpoint, [
                'query' => $graphQLQuery,
                'variables' => $variables,
            ]);
        } catch (\Throwable $e) {
            throw new CockpitRequestException('Cockpit unreachable: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new CockpitRequestException('Cockpit HTTP error (' . $response->status() . '): ' . $response->body());
        }

        $decoded = $response->json();

        if (isset($decoded['errors'])) {
            throw new CockpitRequestException('GraphQL error: ' . json_encode($decoded['errors']));
        }

        if (!isset($decoded['data'])) {
            throw new CockpitRequestException('Unexpected response from Cockpit (no data key)');
        }

        return $decoded['data'];
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
     * @throws CockpitRequestException
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
     * @throws CockpitRequestException
     */
    public function executeCached(array $queries, string $cacheKey, $duration = null): array
    {
        $cacheDuration = $duration ?? now()->addMonth();
        $callback = fn() => $this->execute($queries);
        
        // Calls the "clean" cachedQuery version
        return $this->cachedQuery($cacheKey, $cacheDuration, $callback);
    }

    /**
     * Generates the URL for a Cockpit image, served via the local symlink
     * (public/cockpit-uploads -> Cockpit's storage/uploads directory).
     *
     * @param string|null $path The image path (e.g., "/path/to/image.jpg").
     * @return string|null The local static URL, or null if no path given.
     */
    public function image(?string $path): ?string
    {
        if ( ! $path) {
            return null;
        }

        return '/cockpit-uploads/' . ltrim($path, '/');
    }
}