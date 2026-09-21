<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Debug;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Wraps the real PSR-18 client used by the SDK, recording every call into the tracer. Debug-only. */
final readonly class TraceableHttpClient implements ClientInterface
{
    public function __construct(
        private ClientInterface $inner,
        private ApaleoHttpClientTracer $tracer,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $start = microtime(true);

        try {
            $response = $this->inner->sendRequest($request);
        } catch (ClientExceptionInterface $clientException) {
            $this->tracer->record(new ApaleoRequestTrace(
                method: $request->getMethod(),
                uri: (string) $request->getUri(),
                statusCode: null,
                durationMs: (microtime(true) - $start) * 1000,
                error: $clientException->getMessage(),
            ));

            throw $clientException;
        }

        $this->tracer->record(new ApaleoRequestTrace(
            method: $request->getMethod(),
            uri: (string) $request->getUri(),
            statusCode: $response->getStatusCode(),
            durationMs: (microtime(true) - $start) * 1000,
        ));

        return $response;
    }
}
