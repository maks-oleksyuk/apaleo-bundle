<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Tests;

use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Auth\ClientCredentialsTokenProvider;
use Oleksyuk\Apaleo\Auth\TokenProvider;
use Oleksyuk\Apaleo\Bundle\ApaleoBundle;
use Oleksyuk\Apaleo\Bundle\Debug\ApaleoDataCollector;
use Oleksyuk\Apaleo\Bundle\Debug\TraceableHttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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
            ->getDefinition(ClientCredentialsTokenProvider::class)
        ;

        self::assertSame('my-id', $definition->getArgument('$clientId'));
        self::assertSame('my-secret', $definition->getArgument('$clientSecret'));
    }

    public function testDefaultsToEnvVarPlaceholdersWhenNotConfigured(): void
    {
        $definition = $this->load([])->getDefinition(ClientCredentialsTokenProvider::class);

        self::assertSame('%env(APALEO_CLIENT_ID)%', $definition->getArgument('$clientId'));
        self::assertSame('%env(APALEO_CLIENT_SECRET)%', $definition->getArgument('$clientSecret'));
    }

    public function testTokenProviderAliasPointsToClientCredentialsTokenProvider(): void
    {
        $builder = $this->load([]);

        self::assertTrue($builder->hasAlias(TokenProvider::class));
        self::assertSame(ClientCredentialsTokenProvider::class, (string) $builder->getAlias(TokenProvider::class));
    }

    public function testBaseUriOverrideIsPassedWhenSet(): void
    {
        $definition = $this->load(['base_uri' => 'https://sandbox.apaleo.com'])->getDefinition(ApaleoClient::class);

        self::assertSame('https://sandbox.apaleo.com', $definition->getArgument('$baseUri'));
    }

    public function testBaseUriArgumentIsOmittedWhenNotSet(): void
    {
        $definition = $this->load([])->getDefinition(ApaleoClient::class);

        self::assertArrayNotHasKey('$baseUri', $definition->getArguments());
    }

    public function testDebugModeWrapsHttpClientWithTracerAndRegistersDataCollector(): void
    {
        $builder = $this->load([], debug: true);

        self::assertTrue($builder->hasDefinition(TraceableHttpClient::class) || $builder->hasDefinition('apaleo.http_client'));
        self::assertTrue($builder->hasDefinition(ApaleoDataCollector::class));

        $collectorDefinition = $builder->getDefinition(ApaleoDataCollector::class);
        $tags = $collectorDefinition->getTag('data_collector');
        self::assertNotEmpty($tags);
        $firstTag = $tags[0];
        self::assertIsArray($firstTag);
        self::assertSame('apaleo', $firstTag['id']);
    }

    public function testProductionModeAliasesHttpClientDirectlyWithoutTracer(): void
    {
        $builder = $this->load([], debug: false);

        self::assertFalse($builder->hasDefinition(ApaleoDataCollector::class));
        self::assertTrue($builder->hasAlias('apaleo.http_client'));
        self::assertSame(ClientInterface::class, (string) $builder->getAlias('apaleo.http_client'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $debug = false): ContainerBuilder
    {
        $bundle = new ApaleoBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $builder = new ContainerBuilder(new ParameterBag(['kernel.debug' => $debug]));
        $extension->load([$config], $builder);

        return $builder;
    }
}
