<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Debug;

/** One recorded HTTP call made by the SDK (Apaleo API or identity server), for the profiler panel. */
final readonly class ApaleoRequestTrace
{
    public function __construct(
        public string $method,
        public string $uri,
        public ?int $statusCode,
        public float $durationMs,
        public ?string $error = null,
    ) {}
}
