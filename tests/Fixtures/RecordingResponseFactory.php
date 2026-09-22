<?php

declare(strict_types=1);

namespace Oleksyuk\Apaleo\Bundle\Tests\Fixtures;

use Symfony\Component\HttpClient\Response\MockResponse;

/** MockHttpClient response factory: records each call and replies from a queue (last reply repeats). */
final class RecordingResponseFactory
{
    /** @var list<string> "METHOD url" per call */
    public array $calls = [];

    /** @var list<MockResponse> */
    private array $queue = [];

    public function __invoke(string $method, string $url): MockResponse
    {
        $this->calls[] = $method.' '.$url;

        return \count($this->queue) > 1 ? array_shift($this->queue) : ($this->queue[0] ?? new MockResponse('{}'));
    }

    public function reply(MockResponse ...$responses): void
    {
        $this->queue = array_values($responses);
    }
}
