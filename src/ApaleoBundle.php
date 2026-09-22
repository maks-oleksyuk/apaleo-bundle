<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle;

use Nyholm\Psr7\Factory\Psr17Factory;
use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Auth\ClientCredentialsTokenProvider;
use Oleksyuk\Apaleo\Auth\Psr16TokenCache;
use Oleksyuk\Apaleo\Auth\TokenProvider;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApaleoBundle extends AbstractBundle
{
    /**
     * With FrameworkBundle, a scoped client (override it under framework.http_client.scoped_clients);
     * without it, the same timeout and retry policy on a plain RetryableHttpClient.
     */
    private const string HTTP_CLIENT_SERVICE_ID = 'apaleo.http_client';

    private const string PSR18_CLIENT_SERVICE_ID = 'apaleo.psr18_client';

    private const string PSR17_FACTORY_SERVICE_ID = 'apaleo.psr17_factory';

    private const string TOKEN_PROVIDER_SERVICE_ID = 'apaleo.token_provider';

    private const string TOKEN_CACHE_SERVICE_ID = 'apaleo.token_cache';

    private const string PSR6_CACHE_SERVICE_ID = 'cache.app';

    private const int TIMEOUT = 10;

    private const int MAX_RETRIES = 2;

    /** Only safe methods are retried on 5xx/transport errors: a 502 after a POST may still have booked. */
    private const array SAFE_METHODS = ['GET', 'HEAD'];

    /** GenericRetryStrategy format: code => methods it's retried for, or a bare code for any method. */
    private const array RETRY_STATUS_CODES = [
        0 => self::SAFE_METHODS,
        429,
        500 => self::SAFE_METHODS,
        502 => self::SAFE_METHODS,
        503 => self::SAFE_METHODS,
        504 => self::SAFE_METHODS,
    ];

    protected string $extensionAlias = 'apaleo';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('client_id')->defaultValue('%env(APALEO_CLIENT_ID)%')->end()
            ->scalarNode('client_secret')->defaultValue('%env(APALEO_CLIENT_SECRET)%')->end()
            ->scalarNode('base_uri')->defaultNull()->end()
            ->scalarNode('token_cache')
            ->info('PSR-6 cache pool service ID for the access token. Default: "cache.app" with FrameworkBundle, otherwise in-memory (a new token per PHP-FPM request).')
            ->defaultNull()
            ->end()
            ->end();
    }

    /**
     * A named scoped client gives the SDK its own timeout and retries, and shows its calls
     * (API and identity server) under "apaleo.http_client" in the profiler's HTTP Client panel.
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->hasFrameworkBundle($builder)) {
            return;
        }

        $httpCodes = [];
        foreach (self::RETRY_STATUS_CODES as $code => $methods) {
            if (\is_array($methods)) {
                $httpCodes[$code] = $methods;
            } else {
                $httpCodes[$methods] = true;
            }
        }

        $builder->prependExtensionConfig('framework', [
            'http_client' => [
                'scoped_clients' => [
                    self::HTTP_CLIENT_SERVICE_ID => [
                        'scope' => '.*',
                        'timeout' => self::TIMEOUT,
                        'retry_failed' => ['max_retries' => self::MAX_RETRIES, 'http_codes' => $httpCodes],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $hasFrameworkBundle = $this->hasFrameworkBundle($builder);

        if (!$hasFrameworkBundle) {
            $builder->register(self::HTTP_CLIENT_SERVICE_ID, RetryableHttpClient::class)
                ->setArguments([
                    new Definition(HttpClientInterface::class, [['timeout' => self::TIMEOUT]])->setFactory([HttpClient::class, 'create']),
                    new Definition(GenericRetryStrategy::class, [self::RETRY_STATUS_CODES]),
                    self::MAX_RETRIES,
                ]);
        }

        $builder->register(self::PSR17_FACTORY_SERVICE_ID, Psr17Factory::class);

        $builder->register(self::PSR18_CLIENT_SERVICE_ID, Psr18Client::class)
            ->setArguments([
                new Reference(self::HTTP_CLIENT_SERVICE_ID),
                new Reference(self::PSR17_FACTORY_SERVICE_ID),
                new Reference(self::PSR17_FACTORY_SERVICE_ID),
            ]);

        $tokenProvider = $builder->register(self::TOKEN_PROVIDER_SERVICE_ID, ClientCredentialsTokenProvider::class)
            ->setArgument('$httpClient', new Reference(self::PSR18_CLIENT_SERVICE_ID))
            ->setArgument('$requestFactory', new Reference(self::PSR17_FACTORY_SERVICE_ID))
            ->setArgument('$streamFactory', new Reference(self::PSR17_FACTORY_SERVICE_ID))
            ->setArgument('$clientId', $config['client_id'])
            ->setArgument('$clientSecret', $config['client_secret']);

        // Persist the access token across requests: ClientCredentialsTokenProvider's default
        // InMemoryTokenCache starts empty on every PHP-FPM request.
        $tokenCachePool = $config['token_cache'] ?? ($hasFrameworkBundle ? self::PSR6_CACHE_SERVICE_ID : null);
        if (\is_string($tokenCachePool)) {
            $builder->register(self::TOKEN_CACHE_SERVICE_ID, Psr16TokenCache::class)
                ->setArgument('$cache', new Definition(Psr16Cache::class, [new Reference($tokenCachePool)]));
            $tokenProvider->setArgument('$cache', new Reference(self::TOKEN_CACHE_SERVICE_ID));
        }

        $builder->setAlias(TokenProvider::class, self::TOKEN_PROVIDER_SERVICE_ID);

        $apaleoClient = $builder->register(ApaleoClient::class, ApaleoClient::class)
            ->setArgument('$httpClient', new Reference(self::PSR18_CLIENT_SERVICE_ID))
            ->setArgument('$requestFactory', new Reference(self::PSR17_FACTORY_SERVICE_ID))
            ->setArgument('$streamFactory', new Reference(self::PSR17_FACTORY_SERVICE_ID))
            ->setArgument('$tokenProvider', new Reference(TokenProvider::class))
            ->setArgument('$asyncHttpClient', new Reference(self::HTTP_CLIENT_SERVICE_ID));

        if (\is_string($config['base_uri'] ?? null)) {
            $apaleoClient->setArgument('$baseUri', $config['base_uri']);
        }
    }

    private function hasFrameworkBundle(ContainerBuilder $builder): bool
    {
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];

        return \is_array($bundles) && isset($bundles['FrameworkBundle']);
    }
}
