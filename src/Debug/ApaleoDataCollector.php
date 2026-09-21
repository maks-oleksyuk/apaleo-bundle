<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Debug;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

final class ApaleoDataCollector extends DataCollector
{
    public function __construct(
        private readonly ApaleoHttpClientTracer $tracer,
    ) {}

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $traces = $this->tracer->all();

        $this->data = [
            'traces' => array_map(static fn (ApaleoRequestTrace $t): array => [
                'method' => $t->method,
                'uri' => $t->uri,
                'statusCode' => $t->statusCode,
                'durationMs' => $t->durationMs,
                'error' => $t->error,
            ], $traces),
            'totalDurationMs' => array_sum(array_map(static fn (ApaleoRequestTrace $t): float => $t->durationMs, $traces)),
        ];
    }

    /**
     * @return list<array{method: string, uri: string, statusCode: ?int, durationMs: float, error: ?string}>
     */
    public function getTraces(): array
    {
        if (!\is_array($this->data) || !\is_array($this->data['traces'] ?? null)) {
            return [];
        }

        // @phpstan-ignore return.type (DataCollector::$data is untyped array|Data; shape is guaranteed by collect() above)
        return $this->data['traces'];
    }

    public function getCallCount(): int
    {
        return \count($this->getTraces());
    }

    public function getTotalDurationMs(): float
    {
        $total = \is_array($this->data) ? ($this->data['totalDurationMs'] ?? 0.0) : 0.0;

        return \is_float($total) ? $total : 0.0;
    }

    public function getName(): string
    {
        return 'apaleo';
    }
}
