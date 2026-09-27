<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Tests;

use Oleksyuk\Apaleo\Auth\AccessToken;
use Oleksyuk\Apaleo\Bundle\Psr6TokenCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(Psr6TokenCache::class)]
final class Psr6TokenCacheTest extends TestCase
{
    public function testSetThenGetRoundTripsTheToken(): void
    {
        $tokenCache = new Psr6TokenCache(new ArrayAdapter());
        $token = new AccessToken('abc123', new \DateTimeImmutable('+1 hour'));

        $tokenCache->set('key', $token);

        $result = $tokenCache->get('key');

        self::assertNotNull($result);
        self::assertSame('abc123', $result->value);
        self::assertSame($token->expiresAt->getTimestamp(), $result->expiresAt->getTimestamp());
    }

    public function testGetReturnsNullWhenNothingCached(): void
    {
        self::assertNull(new Psr6TokenCache(new ArrayAdapter())->get('key'));
    }

    public function testGetReturnsNullForSomethingOtherThanAToken(): void
    {
        $pool = new ArrayAdapter();
        $pool->save($pool->getItem('key')->set(['value' => 'abc123']));

        self::assertNull(new Psr6TokenCache($pool)->get('key'));
    }

    public function testEntryExpiresWithTheToken(): void
    {
        $tokenCache = new Psr6TokenCache(new ArrayAdapter());

        $tokenCache->set('key', new AccessToken('abc123', new \DateTimeImmutable('-1 second')));

        self::assertNull($tokenCache->get('key'));
    }
}
