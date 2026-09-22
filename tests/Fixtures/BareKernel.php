<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Tests\Fixtures;

use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Bundle\ApaleoBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/** ApaleoBundle alone, without FrameworkBundle: no cache.app, no framework.http_client. */
final class BareKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield new ApaleoBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('apaleo', ['client_id' => 'id', 'client_secret' => 'secret']);
            $container->setAlias('test.apaleo_client', ApaleoClient::class)->setPublic(true);
            $container->setAlias('test.apaleo_http_client', 'apaleo.http_client')->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/apaleo-bundle-test/bare/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/apaleo-bundle-test/log';
    }
}
