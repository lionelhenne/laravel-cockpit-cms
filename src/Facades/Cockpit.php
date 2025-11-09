<?php

namespace lionelhenne\LaravelCockpitCms\Facades;

use Illuminate\Support\Facades\Facade;
use lionelhenne\LaravelCockpitCms\CockpitService;

/**
 * @method static array query(string $graphQLQuery, array $variables = [])
 * @method static string|null imageUrl(?string $path)
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