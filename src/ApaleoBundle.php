<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle;

use Oleksyuk\Apaleo\ApaleoClient;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ApaleoBundle extends AbstractBundle
{
    protected string $extensionAlias = 'apaleo';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('client_id')->defaultValue('%env(APALEO_CLIENT_ID)%')->end()
            ->scalarNode('client_secret')->defaultValue('%env(APALEO_CLIENT_SECRET)%')->end()
            ->scalarNode('base_uri')->defaultNull()->end()
            ->end()
        ;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $definition = $builder->register(ApaleoClient::class, ApaleoClient::class)
            ->setFactory([ApaleoClient::class, 'create'])
            ->setArgument('$clientId', $config['client_id'])
            ->setArgument('$clientSecret', $config['client_secret'])
            ->setAutowired(true)
        ;

        if (\is_string($config['base_uri'] ?? null)) {
            $definition->setArgument('$baseUri', $config['base_uri']);
        }
    }
}
