<div align="center">

# apaleo-bundle

[![CI](https://img.shields.io/github/actions/workflow/status/maks-oleksyuk/apaleo-bundle/ci.yml?branch=main&style=flat&label=CI)](//github.com/maks-oleksyuk/apaleo-bundle/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/oleksyuk/apaleo-bundle.svg?style=flat&logo=packagist&logoColor=white&color=F28D1A)](//packagist.org/packages/oleksyuk/apaleo-bundle)
[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-777bb4?style=flat&logo=php&logoColor=white)](composer.json)
[![Symfony](https://img.shields.io/badge/Symfony-8.0-000000?style=flat&logo=symfony&logoColor=white)](composer.json)
[![Total Downloads](https://img.shields.io/packagist/dt/oleksyuk/apaleo-bundle.svg?style=flat&logo=packagist&logoColor=white&color=F28D1A)](//packagist.org/packages/oleksyuk/apaleo-bundle/stats)

Symfony bundle for [`apaleo-php`](//github.com/maks-oleksyuk/apaleo-php) — the framework-agnostic PHP SDK for the [Apaleo](//apaleo.com) hotel PMS API.

</div>

## Installation

```bash
composer require oleksyuk/apaleo-bundle
```

Register the bundle in `config/bundles.php` (Flex would do this automatically for a published recipe; do it by hand for now):

```php
return [
    // ...
    Oleksyuk\Apaleo\Bundle\ApaleoBundle::class => ['all' => true],
];
```

## Configuration

Add your Apaleo API credentials to `.env.local` (never commit real secrets to `.env`):

```dotenv
APALEO_CLIENT_ID=your-client-id
APALEO_CLIENT_SECRET=your-client-secret
```

These are the defaults the bundle reads (`%env(APALEO_CLIENT_ID)%` / `%env(APALEO_CLIENT_SECRET)%`). Override them or set a custom base URI (e.g., a sandbox environment), via `config/packages/apaleo.php`:

```php
<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'apaleo' => [
        'client_id' => env('APALEO_CLIENT_ID'),
        'client_secret' => env('APALEO_CLIENT_SECRET'),
        // 'base_uri' => 'https://api.sandbox.apaleo.com', // optional, defaults to https://api.apaleo.com
        // 'token_cache' => 'cache.redis', // optional PSR-6 pool for the access token, defaults to cache.app
        // 'identity_base_uri' => 'http://localhost:8080', // optional, defaults to https://identity.apaleo.com
    ],
]);
```

FrameworkBundle is optional. Without it, the bundle still wires its own HTTP client with the same timeout and retries, but the access token is cached in memory only (a new token per PHP-FPM request) unless `token_cache` points at a pool.

Run `bin/console config:dump-reference apaleo` at any time to see the full, current config tree.

## Usage

Inject `ApaleoClient` like any other autowired service:

```php
use Oleksyuk\Apaleo\ApaleoClient;

final readonly class PropertyController
{
    public function __construct(
        private ApaleoClient $apaleo
    ) {}

    public function list(): Response
    {
        $properties = $this->apaleo->inventory()->properties()->list();

        // ...
    }
}
```

See the [`apaleo-php` README](//github.com/maks-oleksyuk/apaleo-php) for the full SDK API (Inventory resources, pagination, filters, exceptions).

## HTTP client, timeouts and retries

The bundle registers a scoped client, `apaleo.http_client`, for every call the SDK makes (API and identity server). It defaults to a 10 s timeout and 2 retries of `5xx`/transport errors, only for `GET`/`HEAD`, because a `502` after a `POST` may still have been applied. A `429` isn't retried: the HTTP client would sleep for whatever `Retry-After` says, uncapped, inside your web request. It surfaces as `ApaleoRateLimitException` with `retryAfterSeconds` instead, so you decide whether to wait (e.g. re-dispatch a Messenger message with a `DelayStamp`). With FrameworkBundle, these calls show up under `apaleo.http_client` in the profiler's **HTTP Client** panel, and you can override any option the usual way:

```php
<?php

// config/packages/framework.php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'http_client' => [
            'scoped_clients' => [
                'apaleo.http_client' => [
                    'scope' => '.*',
                    'timeout' => 30,
                ],
            ],
        ],
    ],
]);
```

The profiler records request headers, including the token request's `Authorization: Basic` header, which carries your client secret. Keep the profiler on your own machine: don't enable it on a shared environment that uses production credentials.

## Token cache

The access token lives about an hour. With FrameworkBundle it's cached in `cache.app`, so every PHP-FPM request reuses it instead of asking the identity server again. With several app servers, point `token_cache` at a shared pool so they share one token too:

```php
<?php

// config/packages/cache.php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'cache' => [
            'pools' => [
                'cache.apaleo' => [
                    'adapter' => 'cache.adapter.redis',
                    'provider' => env('REDIS_URL'),
                ],
            ],
        ],
    ],
]);
```

```php
<?php

// config/packages/apaleo.php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'apaleo' => [
        'token_cache' => 'cache.apaleo',
    ],
]);
```

## Development

This package has its own tooling, mirroring `apaleo-php`:

```bash
task lint   # PHPStan (max level), PHP CS Fixer, Rector, composer validate
task test   # PHPUnit
task fix    # auto-fix CS Fixer / Rector issues
```
