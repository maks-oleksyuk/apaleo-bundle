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

These are the defaults the bundle reads (`%env(APALEO_CLIENT_ID)%` / `%env(APALEO_CLIENT_SECRET)%`). Override them, or set a custom base URI (e.g. a sandbox environment), via `config/packages/apaleo.yaml`:

```yaml
apaleo:
    client_id: '%env(APALEO_CLIENT_ID)%'
    client_secret: '%env(APALEO_CLIENT_SECRET)%'
    # base_uri: 'https://api.sandbox.apaleo.com' # optional, defaults to https://api.apaleo.com
    # token_cache: cache.redis # optional PSR-6 pool for the access token, defaults to cache.app
```

FrameworkBundle is optional. Without it, the bundle still wires its own HTTP client with the same timeout and retries, but the access token is cached in memory only (a new token per PHP-FPM request) unless `token_cache` points at a pool.

Run `bin/console config:dump-reference apaleo` at any time to see the full, current config tree.

## Usage

Inject `ApaleoClient` like any other autowired service:

```php
use Oleksyuk\Apaleo\ApaleoClient;

final class PropertyController
{
    public function __construct(private readonly ApaleoClient $apaleo) {}

    public function list(): Response
    {
        $properties = $this->apaleo->inventory()->properties()->list();

        // ...
    }
}
```

See the [`apaleo-php` README](//github.com/maks-oleksyuk/apaleo-php) for the full SDK API (Inventory resources, pagination, filters, exceptions).

## HTTP client, timeouts and retries

The bundle registers a scoped client, `apaleo.http_client`, for every call the SDK makes (API and identity server). It defaults to a 10 s timeout and 2 retries: `429` is always retried (honoring `Retry-After`), and `5xx`/transport errors only for `GET`/`HEAD`, because a `502` after a `POST` may still have been applied. With FrameworkBundle, these calls show up under `apaleo.http_client` in the profiler's **HTTP Client** panel, and you can override any option the usual way:

```yaml
# config/packages/framework.yaml
framework:
    http_client:
        scoped_clients:
            apaleo.http_client:
                scope: '.*'
                timeout: 30
```

## Development

This package has its own tooling, mirroring `apaleo-php`:

```bash
task lint   # PHPStan (max level), PHP CS Fixer, Rector, composer validate
task test   # PHPUnit
task fix    # auto-fix CS Fixer / Rector issues
```
