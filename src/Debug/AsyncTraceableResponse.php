<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Debug;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/** Records a trace the first time this response is resolved. Debug-only. */
final class AsyncTraceableResponse implements ResponseInterface
{
    private bool $traced = false;

    public function __construct(
        private readonly ResponseInterface $response,
        private readonly ApaleoHttpClientTracer $tracer,
        private readonly string $method,
        private readonly string $url,
        private readonly float $start,
    ) {}

    public function getStatusCode(): int
    {
        try {
            $statusCode = $this->response->getStatusCode();
        } catch (TransportExceptionInterface $transportException) {
            $this->trace(null, $transportException->getMessage());

            throw $transportException;
        }

        $this->trace($statusCode);

        return $statusCode;
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->response->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        try {
            $content = $this->response->getContent($throw);
        } catch (TransportExceptionInterface $transportException) {
            $this->trace(null, $transportException->getMessage());

            throw $transportException;
        }

        $this->trace($this->response->getStatusCode());

        return $content;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(bool $throw = true): array
    {
        return $this->response->toArray($throw);
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    /** For AsyncTraceableHttpClient::stream() to delegate to the real client. */
    public function unwrap(): ResponseInterface
    {
        return $this->response;
    }

    private function trace(?int $statusCode, ?string $error = null): void
    {
        if ($this->traced) {
            return;
        }

        $this->traced = true;
        $this->tracer->record(new ApaleoRequestTrace(
            method: $this->method,
            uri: $this->url,
            statusCode: $statusCode,
            durationMs: (microtime(true) - $this->start) * 1000,
            error: $error,
        ));
    }
}
