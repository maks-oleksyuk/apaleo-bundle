<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Tests;

use Oleksyuk\Apaleo\ApaleoClient;
use Oleksyuk\Apaleo\Bundle\Tests\Fixtures\BareKernel;
use Oleksyuk\Apaleo\Bundle\Tests\Fixtures\RecordingResponseFactory;
use Oleksyuk\Apaleo\Bundle\Tests\Fixtures\TestKernel;
use Oleksyuk\Apaleo\Exception\ApaleoServerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Boots a real kernel, so wiring mistakes (missing services, bad references, framework
 * config the bundle prepends) fail here rather than in the host app.
 *
 * @internal
 *
 * @coversNothing
 */
final class ApaleoBundleKernelTest extends TestCase
{
    private Kernel $kernel;

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        new Filesystem()->remove(sys_get_temp_dir().'/apaleo-bundle-test');
    }

    #[DataProvider('provideContainerCompilesAndBuildsTheClientCases')]
    public function testContainerCompilesAndBuildsTheClient(bool $debug): void
    {
        self::assertInstanceOf(ApaleoClient::class, $this->boot($debug)->get(ApaleoClient::class));
    }

    /** @return iterable<string, array{bool}> */
    public static function provideContainerCompilesAndBuildsTheClientCases(): iterable
    {
        yield 'prod' => [false];

        yield 'debug' => [true];
    }

    public function testWorksWithoutFrameworkBundle(): void
    {
        $this->kernel = new BareKernel('test', false);
        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        self::assertInstanceOf(ApaleoClient::class, $container->get('test.apaleo_client'));
        self::assertInstanceOf(RetryableHttpClient::class, $container->get('test.apaleo_http_client'));
    }

    public function testRetriesASafeRequestOn503(): void
    {
        $container = $this->boot();
        $responses = $this->responses($container);
        $responses->reply(
            new MockResponse('{"access_token":"t","expires_in":3600}'),
            new MockResponse('{}', ['http_code' => 503]),
            new MockResponse('{"count":0,"properties":[]}'),
        );

        $client = $container->get(ApaleoClient::class);
        self::assertInstanceOf(ApaleoClient::class, $client);
        $client->inventory()->properties()->list();

        self::assertCount(3, $responses->calls, 'token + failed GET + retried GET');
    }

    public function testDoesNotRetryAnUnsafeRequestOn503(): void
    {
        $container = $this->boot();
        $responses = $this->responses($container);
        $responses->reply(
            new MockResponse('{"access_token":"t","expires_in":3600}'),
            new MockResponse('{}', ['http_code' => 503]),
        );

        $client = $container->get(ApaleoClient::class);
        self::assertInstanceOf(ApaleoClient::class, $client);

        try {
            $client->inventory()->properties()->archive('MUC');
            self::fail('Expected ApaleoServerException.');
        } catch (ApaleoServerException) {
        }

        self::assertCount(2, $responses->calls, 'a 503 after a write may still have been applied');
    }

    private function boot(bool $debug = false): ContainerInterface
    {
        $this->kernel = new TestKernel('test', $debug);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function responses(ContainerInterface $container): RecordingResponseFactory
    {
        $responses = $container->get(RecordingResponseFactory::class);
        self::assertInstanceOf(RecordingResponseFactory::class, $responses);

        return $responses;
    }
}
