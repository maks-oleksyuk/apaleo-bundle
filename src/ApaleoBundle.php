<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle;

use Http\Discovery\Psr17FactoryDiscovery;
use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Auth\ClientCredentialsTokenProvider;
use Oleksyuk\Apaleo\Auth\Psr16TokenCache;
use Oleksyuk\Apaleo\Auth\TokenProvider;
use Oleksyuk\Apaleo\Bundle\Debug\ApaleoDataCollector;
use Oleksyuk\Apaleo\Bundle\Debug\ApaleoHttpClientTracer;
use Oleksyuk\Apaleo\Bundle\Debug\AsyncTraceableHttpClient;
use Oleksyuk\Apaleo\Bundle\Debug\TraceableHttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClientInterface;

final class ApaleoBundle extends AbstractBundle
{
    private const string HTTP_CLIENT_SERVICE_ID = 'apaleo.http_client';

    private const string ASYNC_HTTP_CLIENT_SERVICE_ID = 'apaleo.async_http_client';

    protected string $extensionAlias = 'apaleo';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('client_id')->defaultValue('%env(APALEO_CLIENT_ID)%')->end()
            ->scalarNode('client_secret')->defaultValue('%env(APALEO_CLIENT_SECRET)%')->end()
            ->scalarNode('base_uri')->defaultNull()->end()
            ->end();
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
                ->setArgument('$tracer', new Reference(ApaleoHttpClientTracer::class));

            // Same tracer, for sendMany()'s concurrent path, which bypasses HTTP_CLIENT_SERVICE_ID.
            $builder->register(self::ASYNC_HTTP_CLIENT_SERVICE_ID, AsyncTraceableHttpClient::class)
                ->setFactory([AsyncTraceableHttpClient::class, 'wrap'])
                ->setArguments([
                    new Reference(SymfonyHttpClientInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                    new Reference(ApaleoHttpClientTracer::class),
                ]);
            $asyncHttpClientReference = new Reference(self::ASYNC_HTTP_CLIENT_SERVICE_ID);

            $builder->register(ApaleoDataCollector::class, ApaleoDataCollector::class)
                ->setArgument('$tracer', new Reference(ApaleoHttpClientTracer::class))
                ->addTag('data_collector', [
                    'template' => '@Apaleo/data_collector.html.twig',
                    'id' => 'apaleo',
                ]);
        } else {
            $builder->setAlias(self::HTTP_CLIENT_SERVICE_ID, ClientInterface::class);
            $asyncHttpClientReference = new Reference(SymfonyHttpClientInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        }

        if (!$builder->has(RequestFactoryInterface::class)) {
            $builder->register(RequestFactoryInterface::class, RequestFactoryInterface::class)
                ->setFactory([Psr17FactoryDiscovery::class, 'findRequestFactory']);
        }

        if (!$builder->has(StreamFactoryInterface::class)) {
            $builder->register(StreamFactoryInterface::class, StreamFactoryInterface::class)
                ->setFactory([Psr17FactoryDiscovery::class, 'findStreamFactory']);
        }

        $tokenProviderDefinition = $builder->register(ClientCredentialsTokenProvider::class, ClientCredentialsTokenProvider::class)
            ->setArgument('$httpClient', new Reference(self::HTTP_CLIENT_SERVICE_ID))
            ->setArgument('$requestFactory', new Reference(RequestFactoryInterface::class))
            ->setArgument('$streamFactory', new Reference(StreamFactoryInterface::class))
            ->setArgument('$clientId', $config['client_id'])
            ->setArgument('$clientSecret', $config['client_secret']);

        // Persist the access token across requests so it survives past the PHP-FPM request
        // lifetime instead of falling back to ClientCredentialsTokenProvider's default
        // InMemoryTokenCache, which fetches a fresh token on every page load. Referencing
        // 'cache.app' (registered by FrameworkBundle) resolves fine regardless of bundle
        // load order, since references are only resolved once the container is compiled.
        $builder->register(Psr16Cache::class, Psr16Cache::class)
            ->setArgument('$pool', new Reference('cache.app'));

        $builder->register(Psr16TokenCache::class, Psr16TokenCache::class)
            ->setArgument('$cache', new Reference(Psr16Cache::class));

        $tokenProviderDefinition->setArgument('$cache', new Reference(Psr16TokenCache::class));

        $builder->setAlias(TokenProvider::class, ClientCredentialsTokenProvider::class);

        $apaleoClient = $builder->register(ApaleoClient::class, ApaleoClient::class)
            ->setArgument('$httpClient', new Reference(self::HTTP_CLIENT_SERVICE_ID))
            ->setArgument('$requestFactory', new Reference(RequestFactoryInterface::class))
            ->setArgument('$streamFactory', new Reference(StreamFactoryInterface::class))
            ->setArgument('$tokenProvider', new Reference(TokenProvider::class))
            ->setArgument('$asyncHttpClient', $asyncHttpClientReference);

        if (\is_string($config['base_uri'] ?? null)) {
            $apaleoClient->setArgument('$baseUri', $config['base_uri']);
        }
    }
}
