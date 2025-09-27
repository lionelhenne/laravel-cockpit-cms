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

    Next, open your `.env` file and add the following two keys with your information:

    ```.env
    COCKPIT_GRAPHQL_ENDPOINT="https://your-cockpit-site.com/api/gql"
    COCKPIT_API_TOKEN="API-xxxxxxxxxxxxxxxxxxxx"
    ```

## Usage

The `CockpitService` is registered in Laravel's service container. You can simply inject it as a dependency in any controller, job, or service where you need it.

The main method is `query()`, which takes a GraphQL string as its first argument and an optional array of variables as its second.

### Dependency Injection Example

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
        $query = '{
            bannerModel {
                content
            }
            articlesModel(limit: 2, filter: {_state: 1}, sort: {_created: -1}) {
                _id
                date
                tag
                title
                excerpt
                content
                image
            }
        }';

        $result = $this->cockpit->query($query);

        $banner = collect($result['data']['bannerModel'] ?? []);
        $articles = collect($result['data']['articlesModel'] ?? []);

        return view('blog.index', compact('banner','articles'));
    }
}
```

### Facade Example

If you prefer, you can use the `Cockpit` facade for a more concise syntax.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use lionelhenne\LaravelCockpitCms\Facades\Cockpit; // Import the facade

class PageController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = '{
            bannerModel {
                content
            }
        }';

        // Use the facade directly
        $result = Cockpit::query($query);

        $banner = collect($result['data']['bannerModel'] ?? []);

        return view('blog.index', compact('banner'));
    }
}
```

## License

This project is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

⚠️ This package was built for my personal needs. It may not be suitable for everyone, and I do not guarantee any support. Use it at your own risk.
