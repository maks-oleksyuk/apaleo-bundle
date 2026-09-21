# apaleo-bundle

Symfony bundle for [`apaleo-php`](https://github.com/maks-oleksyuk/apaleo-php) — the framework-agnostic PHP SDK for the [Apaleo](https://apaleo.com) hotel PMS API.

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
```

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

See the [`apaleo-php` README](https://github.com/maks-oleksyuk/apaleo-php) for the full SDK API (Inventory resources, pagination, filters, exceptions).

## Debug toolbar

In `dev`/`debug` environments, every HTTP call the SDK makes (including the identity-server token request) is traced and shown in a dedicated "Apaleo" panel in the Symfony WebProfiler toolbar — method, URI, status code, and duration. No wiring needed; it's on automatically whenever `kernel.debug` is `true`, and adds zero overhead in `prod`.

## Development

This package has its own tooling, mirroring `apaleo-php`:

```bash
task lint   # PHPStan (max level), PHP CS Fixer, Rector, composer validate
task test   # PHPUnit
task fix    # auto-fix CS Fixer / Rector issues
```
