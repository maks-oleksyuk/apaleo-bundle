<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle;

use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Auth\ClientCredentialsTokenProvider;
use Oleksyuk\Apaleo\Auth\TokenProvider;
use Oleksyuk\Apaleo\Bundle\Debug\ApaleoDataCollector;
use Oleksyuk\Apaleo\Bundle\Debug\ApaleoHttpClientTracer;
use Oleksyuk\Apaleo\Bundle\Debug\TraceableHttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ApaleoBundle extends AbstractBundle
{
    private const string HTTP_CLIENT_SERVICE_ID = 'apaleo.http_client';

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
        $debug = $builder->hasParameter('kernel.debug') && (bool) $builder->getParameter('kernel.debug');

        if ($debug) {
            $builder->register(ApaleoHttpClientTracer::class, ApaleoHttpClientTracer::class);

            $builder->register(self::HTTP_CLIENT_SERVICE_ID, TraceableHttpClient::class)
                ->setArgument('$inner', new Reference(ClientInterface::class))
                ->setArgument('$tracer', new Reference(ApaleoHttpClientTracer::class))
            ;

            $builder->register(ApaleoDataCollector::class, ApaleoDataCollector::class)
                ->setArgument('$tracer', new Reference(ApaleoHttpClientTracer::class))
                ->addTag('data_collector', [
                    'template' => '@Apaleo/data_collector.html.twig',
                    'id' => 'apaleo',
                ])
            ;
        } else {
            $builder->setAlias(self::HTTP_CLIENT_SERVICE_ID, ClientInterface::class);
        }

        $builder->register(ClientCredentialsTokenProvider::class, ClientCredentialsTokenProvider::class)
            ->setArgument('$httpClient', new Reference(self::HTTP_CLIENT_SERVICE_ID))
            ->setArgument('$requestFactory', new Reference(RequestFactoryInterface::class))
            ->setArgument('$streamFactory', new Reference(StreamFactoryInterface::class))
            ->setArgument('$clientId', $config['client_id'])
            ->setArgument('$clientSecret', $config['client_secret'])
        ;
        $builder->setAlias(TokenProvider::class, ClientCredentialsTokenProvider::class);

        $apaleoClient = $builder->register(ApaleoClient::class, ApaleoClient::class)
            ->setArgument('$httpClient', new Reference(self::HTTP_CLIENT_SERVICE_ID))
            ->setArgument('$requestFactory', new Reference(RequestFactoryInterface::class))
            ->setArgument('$streamFactory', new Reference(StreamFactoryInterface::class))
            ->setArgument('$tokenProvider', new Reference(TokenProvider::class))
        ;

        if (\is_string($config['base_uri'] ?? null)) {
            $apaleoClient->setArgument('$baseUri', $config['base_uri']);
        }
    }
}
