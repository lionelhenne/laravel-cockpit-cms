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

## Usage

This package is designed to simplify fetching data by allowing you to batch multiple GraphQL query fragments into a single API call, with caching built-in.

### High-Level Helpers (Recommended)

The recommended way to interact with Cockpit is via the high-level `execute()` and `executeCached()` methods. These methods automatically assemble your query fragments.
- `Cockpit::execute(array $queries)`: Assembles and executes a batch of query fragments without caching.
- `Cockpit::executeCached(array $queries, string $cacheKey, $duration = null)`: Assembles, executes, and caches the result. The duration defaults to 1 month.

### Recommended Usage (Facade Example)

This example shows how to fetch all data for a homepage in a single, cached API call.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use lionelhenne\LaravelCockpitCms\Facades\Cockpit; // Import the facade

class HomepageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // 1. Define all the query fragments you need
        $queries = [
            $this->getSettingsModel(),
            $this->getHeroModel(),
            $this->getArticlesModel(3),
        ];

        $cacheKey = 'homepage_cockpit_data';

        // 2. The application decides the cache strategy
        if (app()->environment('local')) {
            // In local, run without cache
            $result = Cockpit::execute($queries);
        } else {
            // In production, use the cached helper
            $result = Cockpit::executeCached($queries, $cacheKey);
        }

        // 3. Transform your data and pass it to the view
        
        // ---
        // Option 1: Pass a single data collection (Simple & Direct)
        // You will use the raw Cockpit model keys in your view.
        // ---
        $data = collect($result['data'] ?? []);

        return view('homepage.index', compact('data'));
        // In Blade, you access: $data['settingsModel']['title'], $data['heroModel']['subtitle'], etc.


        /*
        // ---
        // Option 2: Pass a single, renamed data array (Cleaner for Blade)
        // This creates a more readable array for your view.
        // ---
        $data = [
            'settings' => collect($result['data']['settingsModel'] ?? []),
            'hero'     => collect($result['data']['heroModel'] ?? []),
            'articles' => collect($result['data']['articlesModel'] ?? []),
        ];

        return view('homepage.index', compact('data'));
        // In Blade, you access: $data['settings']['title'], $data['hero']['subtitle'], etc.
        */


        /*
        // ---
        // Option 3: Pass individual variables (Classic approach)
        // This makes each model a separate variable in your view.
        // ---
        $settings = collect($result['data']['settingsModel'] ?? []);
        $hero     = collect($result['data']['heroModel'] ?? []);
        $articles = collect($result['data']['articlesModel'] ?? []);

        return view('homepage.index', compact('settings','hero','articles'));
        // In Blade, you access: $settings['title'], $hero['subtitle'], etc.
        */
    }
}
```

You can store these fragments in a Trait (`app/Http/Controllers/Traits/CockpitGQLQueries.php`):

```php
<?php

namespace App\Http\Controllers\Traits;

trait CockpitGQLQueries
{
    protected function getSettingsModel(): string
    {
        return '
            settingsModel {
                title
                description
            }
        ';
    }

    protected function getHeroModel(): string
    {
        return '
            heroModel {
                title
                subtitle
                picture
            }
        ';
    }

    protected function getArticlesModel(int $limit = 3): string
    {
        return '
            articlesModel(limit: '.$limit.', filter: {_state: 1}, sort: {date: -1}) {
                _id
                date
                title
                excerpt
            }
        ';
    }
}
```

### Dependency Injection Example

If you prefer dependency injection, the same methods are available on the `CockpitService`.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use lionelhenne\LaravelCockpitCms\CockpitService;

class PageController extends Controller
{
    protected $cockpit;

    public function __construct(CockpitService $cockpit)
    {
        $this->cockpit = $cockpit;
    }

    public function __invoke(Request $request)
    {
        $queries = [ /* ... your query fragments ... */ ];
        $cacheKey = 'my_page_data';

        if (app()->environment('local')) {
            $result = $this->cockpit->execute($queries);
        } else {
            $result = $this->cockpit->executeCached($queries, $cacheKey);
        }

        $data = collect($result['data']['myModel'] ?? []);

        return view('page.index', compact('data'));
    }
}
```

### Low-Level Usage

For simple calls or testing, you can still use the low-level `query()` method, which takes a single, complete GraphQL query string.

```php
$query = '{
    bannerModel {
        content
    }
}';

$result = Cockpit::query($query);
$banner = collect($result['data']['bannerModel'] ?? []);
```

### Image Proxy

The package includes an automatic image proxy with caching. Simply use the `imageUrl()` method:

```php
// In your controller
$queries = [ $this->getBannerModel() ];
$result = Cockpit::executeCached($queries, 'banner_key');
$banner = $result['data']['bannerModel'];

// In your Blade view
<img src="{{ Cockpit::imageUrl($banner['image']['path']) }}" alt="">
```

The proxy automatically:
- Caches images in production (1 year)
- Serves images without cache in local environment
- Adds appropriate HTTP cache headers for browser caching

You can also use it with dependency injection:

```php
// In your controller
public function __construct(CockpitService $cockpit)
{
    $this->cockpit = $cockpit;
}

// In your view
<img src="{{ $cockpit->imageUrl($article['image']['path']) }}" alt="">
```

**Clear image cache in production:**

```bash
php artisan cache:clear
```

## License

This project is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

⚠️ This package was built for my personal needs. It may not be suitable for everyone, and I do not guarantee any support. Use it at your own risk.
