<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Debug;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/** Traces requests made via RequestPipeline::sendMany(), which bypasses TraceableHttpClient. Debug-only. */
final readonly class AsyncTraceableHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $inner,
        private ApaleoHttpClientTracer $tracer,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return new AsyncTraceableResponse(
            $this->inner->request($method, $url, $options),
            $this->tracer,
            $method,
            $url,
            microtime(true),
        );
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->inner->stream($this->unwrapAll($responses), $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new self($this->inner->withOptions($options), $this->tracer);
    }

    /** DI factory: passes a missing $inner through as null instead of failing to build the service. */
    public static function wrap(?HttpClientInterface $inner, ApaleoHttpClientTracer $tracer): ?HttpClientInterface
    {
        return $inner instanceof HttpClientInterface ? new self($inner, $tracer) : null;
    }

    /**
     * @param iterable<array-key, ResponseInterface>|ResponseInterface $responses
     *
     * @return iterable<array-key, ResponseInterface>|ResponseInterface
     */
    private function unwrapAll(iterable|ResponseInterface $responses): iterable|ResponseInterface
    {
        if ($responses instanceof AsyncTraceableResponse) {
            return $responses->unwrap();
        }

        if (!is_iterable($responses)) {
            return $responses;
        }

        return (static function () use ($responses): iterable {
            foreach ($responses as $response) {
                yield $response instanceof AsyncTraceableResponse ? $response->unwrap() : $response;
            }
        })();
    }
}
