<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Tests;

use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Auth\ClientCredentialsTokenProvider;
use Oleksyuk\Apaleo\Auth\TokenProvider;
use Oleksyuk\Apaleo\Bundle\ApaleoBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApaleoBundleTest extends TestCase
{
    public function testRegistersTokenProviderWithConfiguredCredentials(): void
    {
        $definition = $this->load(['client_id' => 'my-id', 'client_secret' => 'my-secret'])
            ->getDefinition('apaleo.token_provider');

        self::assertSame('my-id', $definition->getArgument('$clientId'));
        self::assertSame('my-secret', $definition->getArgument('$clientSecret'));
    }

    public function testDefaultsToEnvVarPlaceholdersWhenNotConfigured(): void
    {
        $definition = $this->load([])->getDefinition('apaleo.token_provider');

        self::assertSame('%env(APALEO_CLIENT_ID)%', $definition->getArgument('$clientId'));
        self::assertSame('%env(APALEO_CLIENT_SECRET)%', $definition->getArgument('$clientSecret'));
    }

    public function testTokenProviderAliasPointsToClientCredentialsTokenProvider(): void
    {
        $builder = $this->load([]);

        self::assertTrue($builder->hasAlias(TokenProvider::class));
        self::assertSame('apaleo.token_provider', (string) $builder->getAlias(TokenProvider::class));
    }

    public function testBaseUriOverrideIsPassedWhenSet(): void
    {
        $definition = $this->load(['base_uri' => 'https://sandbox.apaleo.com'])->getDefinition(ApaleoClient::class);

        self::assertSame('https://sandbox.apaleo.com', $definition->getArgument('$baseUri'));
    }

    public function testIdentityBaseUriIsPassedToTheTokenProvider(): void
    {
        $definition = $this->load(['identity_base_uri' => 'http://mock-identity'])->getDefinition('apaleo.token_provider');

        self::assertSame('http://mock-identity', $definition->getArgument('$identityBaseUri'));
        self::assertSame(ClientCredentialsTokenProvider::class, $definition->getClass());
    }

    public function testBaseUriArgumentIsOmittedWhenNotSet(): void
    {
        $definition = $this->load([])->getDefinition(ApaleoClient::class);

        self::assertArrayNotHasKey('$baseUri', $definition->getArguments());
    }

    public function testTokenCacheDefaultsToCacheAppWithFrameworkBundle(): void
    {
        $builder = $this->load([], withFrameworkBundle: true);

        self::assertSame('cache.app', $this->tokenCachePool($builder));
    }

    public function testTokenCacheIsInMemoryWithoutFrameworkBundle(): void
    {
        $builder = $this->load([]);

        self::assertFalse($builder->hasDefinition('apaleo.token_cache'));
        self::assertArrayNotHasKey('$cache', $builder->getDefinition('apaleo.token_provider')->getArguments());
    }

    public function testTokenCacheUsesConfiguredPool(): void
    {
        self::assertSame('cache.redis', $this->tokenCachePool($this->load(['token_cache' => 'cache.redis'])));
    }

    private function tokenCachePool(ContainerBuilder $builder): string
    {
        $cache = $builder->getDefinition('apaleo.token_provider')->getArgument('$cache');
        self::assertInstanceOf(Reference::class, $cache);
        self::assertSame('apaleo.token_cache', (string) $cache);

        $psr16 = $builder->getDefinition('apaleo.token_cache')->getArgument('$cache');
        self::assertInstanceOf(Definition::class, $psr16);
        $pool = $psr16->getArgument(0);
        self::assertInstanceOf(Reference::class, $pool);

        return (string) $pool;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $withFrameworkBundle = false): ContainerBuilder
    {
        $bundle = new ApaleoBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $builder = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.environment' => 'test',
            'kernel.bundles' => $withFrameworkBundle ? ['FrameworkBundle' => FrameworkBundle::class] : [],
        ]));
        $extension->load([$config], $builder);

        return $builder;
    }
}
