<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle;

use Oleksyuk\Apaleo\Auth\AccessToken;
use Oleksyuk\Apaleo\Auth\TokenCacheInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Keeps the access token in a PSR-6 pool (cache.app by default) until it expires, so PHP-FPM
 * requests share one token instead of each fetching its own.
 */
final readonly class Psr6TokenCache implements TokenCacheInterface
{
    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {}

    public function get(string $key): ?AccessToken
    {
        $token = $this->pool->getItem($key)->get();

        // Anything else (e.g., an entry left by an older AccessToken shape) counts as a miss.
        return $token instanceof AccessToken ? $token : null;
    }

    public function set(string $key, AccessToken $token): void
    {
        $this->pool->save($this->pool->getItem($key)->set($token)->expiresAt($token->expiresAt));
    }
}
