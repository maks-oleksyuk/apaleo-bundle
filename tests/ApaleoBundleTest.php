<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Tests;

use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Bundle\ApaleoBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApaleoBundleTest extends TestCase
{
    public function testRegistersApaleoClientServiceWithConfiguredCredentials(): void
    {
        $builder = $this->load(['client_id' => 'my-id', 'client_secret' => 'my-secret']);

        self::assertTrue($builder->hasDefinition(ApaleoClient::class));
        $definition = $builder->getDefinition(ApaleoClient::class);
        self::assertSame('my-id', $definition->getArgument('$clientId'));
        self::assertSame('my-secret', $definition->getArgument('$clientSecret'));
        self::assertSame([ApaleoClient::class, 'create'], $definition->getFactory());
    }

    public function testDefaultsToEnvVarPlaceholdersWhenNotConfigured(): void
    {
        $definition = $this->load([])->getDefinition(ApaleoClient::class);

        self::assertSame('%env(APALEO_CLIENT_ID)%', $definition->getArgument('$clientId'));
        self::assertSame('%env(APALEO_CLIENT_SECRET)%', $definition->getArgument('$clientSecret'));
    }

    public function testBaseUriOverrideIsPassedWhenSet(): void
    {
        $definition = $this->load(['base_uri' => 'https://sandbox.apaleo.com'])->getDefinition(ApaleoClient::class);

        self::assertSame('https://sandbox.apaleo.com', $definition->getArgument('$baseUri'));
    }

    public function testBaseUriArgumentIsOmittedWhenNotSet(): void
    {
        $definition = $this->load([])->getDefinition(ApaleoClient::class);

        self::assertFalse($definition->hasErrors());
        self::assertArrayNotHasKey('$baseUri', $definition->getArguments());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $bundle = new ApaleoBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $builder = new ContainerBuilder();
        $extension->load([$config], $builder);

        return $builder;
    }
}
