# Laravel Client for Cockpit CMS (GraphQL)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lionelhenne/laravel-cockpit-cms.svg?style=flat-square)](https://packagist.org/packages/lionelhenne/laravel-cockpit-cms)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](https://opensource.org/licenses/MIT)

A simple and lightweight client to query the [Cockpit CMS](https://getcockpit.com/) GraphQL API from a Laravel application.

## Installation

You can install this package via Composer.

```bash
composer require lionelhenne/laravel-cockpit-cms
```

The Service Provider will be automatically registered thanks to Laravel's package discovery.

## Configuration

1.  **Publish the configuration file**

    To set your API credentials, you first need to publish the package's configuration file. It will be copied to `config/cockpit.php`.

    ```bash
    php artisan vendor:publish --tag="cockpit-config"
    ```

2.  **Add your environment variables**

Next, open your `.env` file and add the following keys with your information:

```.env
COCKPIT_URL="https://your-cockpit-site.com"
COCKPIT_GRAPHQL_ENDPOINT="https://your-cockpit-site.com/api/gql"
COCKPIT_API_TOKEN="API-xxxxxxxxxxxxxxxxxxxx"
```

> **Note regarding Public APIs:** If your Cockpit API access is configured as **Public** (no token required), you can leave `COCKPIT_API_TOKEN` empty in your `.env` file. The client will automatically skip the `Authorization` header to avoid 401 errors.

3.  **Serve Cockpit images via a symlink**

    This package does not proxy or cache images itself. Cockpit images are expected to be served as static files through a symlink pointing to Cockpit's own upload directory:

    ```bash
    ln -s /path/to/cockpit/storage/uploads public/cockpit-uploads
    ```

    `Cockpit::image()` (see below) generates paths assuming this symlink exists at `public/cockpit-uploads`. Adjust the symlink name to match if you used a different one.

## Usage

This package is designed to simplify fetching data by allowing you to batch multiple GraphQL query fragments into a single API call, with caching built-in.

### High-Level Helpers (Recommended)

The recommended way to interact with Cockpit is via the high-level `execute()` and `executeCached()` methods. These methods automatically assemble your query fragments and return the contents of the GraphQL `data` key directly — no need to unwrap an envelope.

- `Cockpit::execute(array $queries)`: Assembles and executes a batch of query fragments without caching.
- `Cockpit::executeCached(array $queries, string $cacheKey, $duration = null)`: Assembles, executes, and caches the result. The duration defaults to 1 month.

Both methods throw a `CockpitRequestException` on failure (network error, HTTP error, GraphQL error, or malformed response) — see [Error Handling](#error-handling) below.

### Recommended Usage (Base Controller Pattern)

Every controller that needs Cockpit data has to decide between `execute()` (no cache, for local development) and `executeCached()` (production). Rather than repeating that ternary in every controller, push it once into your app's base `Controller`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CockpitGQLQueries;
use lionelhenne\LaravelCockpitCms\Facades\Cockpit;

abstract class Controller
{
    use CockpitGQLQueries;

    protected function executeCockpitQueries(array $queries, string $cacheKey): array
    {
        return app()->environment('local')
            ? Cockpit::execute($queries)
            : Cockpit::executeCached($queries, $cacheKey);
    }
}
```

This way, every controller extending `Controller` gets `executeCockpitQueries()` for free, and the cache-vs-no-cache decision lives in exactly one place. `CockpitRequestException` is left uncaught here on purpose — see [Error Handling](#error-handling).

GraphQL fragments live in a trait, kept separate from the controller logic:

```php
<?php

namespace App\Http\Controllers\Traits;

trait CockpitGQLQueries
{
    protected function getBanner(): string
    {
        return '
            banner: bannerModel {
                content
                _id
            }
        ';
    }

    protected function getLastNews(int $number = 2): string
    {
        return '
            last_news: newsModel(sort: {date: -1}, limit: '.$number.') {
                _id
                date
                tag
                title
                content
                image
            }
        ';
    }

    protected function getOneNews(string $id): string
    {
        return '
            one_news: newsModel(filter: {_state: 1, _id:"'.$id.'"}) {
                _id
                date
                tag
                title
                content
                image
            }
        ';
    }
}
```

A controller can then batch several fragments — a singleton (`bannerModel`), a limited list, and a single item filtered by ID — into one API call:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CockpitTestController extends Controller
{
    public function test(Request $request)
    {
        $data = $this->executeCockpitQueries([
            $this->getBanner(),
            $this->getLastNews(2),
            $this->getOneNews('1e91181a37396565590000f6'),
        ], 'cache_banner');

        return view('cockpit-test.index', [
            'banner'    => $data['banner'],
            'last_news' => $data['last_news'],
            'one_news'  => collect($data['one_news'] ?? [])->first(),
        ]);
    }
}
```

A couple of things worth noting here:

- `bannerModel` is a Cockpit **singleton**, so `$data['banner']` is already a single associative array — no unwrapping needed.
- `newsModel` is a **collection**, so it's always returned as a list, even when filtered down to a single `_id`. That's why `getOneNews()` is unwrapped with `collect($data['one_news'] ?? [])->first()` in the controller — this gives the view a plain array (or `null` if nothing matched) instead of a single-item list, so `$one_news['title']` works directly in Blade rather than `$one_news[0]['title']`.

```blade
{{-- resources/views/cockpit-test/index.blade.php --}}
@dump($banner)

@dump($last_news)

@dump($one_news)
```

### Low-Level Usage

For simple calls or testing, you can still use the low-level `query()` method, which takes a single, complete GraphQL query string.

```php
$query = '{
    banner: bannerModel {
        content
    }
}';

$result = Cockpit::query($query);
$banner = $result['banner'] ?? [];
```

### Images

This package does not proxy, download, or cache images — it relies on the symlink described in [Configuration](#configuration). `Cockpit::image()` simply builds the relative path:

```php
// In your controller
$data = $this->executeCockpitQueries([ $this->getBanner() ], 'cache_banner');
$banner = $data['banner'];
```

```blade
{{-- In your Blade view --}}
<img src="{{ Cockpit::image($banner['image']['path']) }}" alt="">
```

`Cockpit::image()` returns a domain-relative path (e.g. `/cockpit-uploads/2026/06/photo.jpg`), suitable for `<img src>` on your own pages. If you need an absolute URL (Open Graph tags, RSS feeds, emails), combine it with Laravel's built-in `asset()` helper:

```blade
<meta property="og:image" content="{{ asset(ltrim(Cockpit::image($banner['image']['path']), '/')) }}">
```

## Error Handling

Every API-calling method (`query`, `execute`, `executeCached`) throws a `lionelhenne\LaravelCockpitCms\CockpitRequestException` on failure — unreachable API, HTTP error response, GraphQL errors, or a response missing the expected `data` key. None of these methods silently return an error array.

```php
use lionelhenne\LaravelCockpitCms\CockpitRequestException;

try {
    $data = Cockpit::execute(['newsModel { title }']);
} catch (CockpitRequestException $e) {
    report($e);
    // handle the failure (fallback view, retry, etc.)
}
```

If you don't catch it, the exception propagates normally and Laravel renders its standard error page (full details in local/debug mode, a generic 500 in production — always logged to `storage/logs/laravel.log`).

## API Reference

| Method | Description |
|---|---|
| `query(string $graphQLQuery, array $variables = [])` | Executes a raw GraphQL query, returns the contents of `data`. |
| `execute(array $queries)` | Assembles multiple fragments into one query and executes it. |
| `executeCached(array $queries, string $cacheKey, $duration = null)` | Same as `execute()`, with result caching. |
| `cachedQuery(string $key, $duration, callable $callback)` | Low-level helper, wraps `Cache::remember()`. |
| `assembleQuery(array $queries)` | Assembles fragments into a single `{ ... }` GraphQL query string. |
| `image(?string $path)` | Builds the relative path to a Cockpit image, via the symlink. |

## License

This project is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

⚠️ This package was built for my personal needs. It may not be suitable for everyone, and I do not guarantee any support. Use it at your own risk.
