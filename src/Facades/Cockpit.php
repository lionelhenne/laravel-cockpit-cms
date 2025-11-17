<?php

namespace lionelhenne\LaravelCockpitCms\Facades;

use Illuminate\Support\Facades\Facade;
use lionelhenne\LaravelCockpitCms\CockpitService;

/**
 * @method static array query(string $graphQLQuery, array $variables = [])
 * @method static string|null imageUrl(?string $path)
 * @method static string assembleQuery(array $queries)
 * @method static mixed cachedQuery(string $key, $duration, callable $callback)
 * 
 * @method static array execute(array $queries)
 * @method static array executeCached(array $queries, string $cacheKey, $duration = null)
 *
 * @see \lionelhenne\LaravelCockpitCms\CockpitService
 */
class Cockpit extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return CockpitService::class;
    }
}