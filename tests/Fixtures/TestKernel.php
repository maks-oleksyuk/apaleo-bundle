<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Tests\Fixtures;

use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Bundle\ApaleoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/** Minimal app that boots FrameworkBundle + ApaleoBundle, with the HTTP transport mocked. */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();

        yield new ApaleoBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/apaleo-bundle-test/'.$this->environment.($this->debug ? '-debug' : '').'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/apaleo-bundle-test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'http_client' => [
                'mock_response_factory' => RecordingResponseFactory::class,
                // An app overriding the bundle's scoped-client defaults; also keeps retries instant.
                'scoped_clients' => ['apaleo.http_client' => ['scope' => '.*', 'retry_failed' => ['delay' => 0]]],
            ],
        ]);

        $container->extension('apaleo', ['client_id' => 'id', 'client_secret' => 'secret']);

        $container->services()->set(RecordingResponseFactory::class)->public();
        // Nothing in this app injects the client, so without a user it'd be removed at compile time.
        $container->services()->alias('test.apaleo_client', ApaleoClient::class)->public();
    }
}
