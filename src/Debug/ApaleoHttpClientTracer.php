<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Debug;

use Symfony\Contracts\Service\ResetInterface;

/** Accumulates ApaleoRequestTrace entries for the current request; read by ApaleoDataCollector. */
final class ApaleoHttpClientTracer implements ResetInterface
{
    /** @var list<ApaleoRequestTrace> */
    private array $traces = [];

    public function record(ApaleoRequestTrace $trace): void
    {
        $this->traces[] = $trace;
    }

    /** @return list<ApaleoRequestTrace> */
    public function all(): array
    {
        return $this->traces;
    }

    public function reset(): void
    {
        $this->traces = [];
    }
}
