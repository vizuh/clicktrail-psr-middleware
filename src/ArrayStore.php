<?php

declare(strict_types=1);

namespace ClickTrail\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * In-memory store. Useful for tests, workers and platforms where the
 * application layer reads the stored state within the same request cycle.
 */
final class ArrayStore implements StateStoreInterface
{
    private ?string $state = null;

    public function load(RequestInterface $request): ?string
    {
        return $this->state;
    }

    public function save(string $json): void
    {
        $this->state = $json;
    }

    public function applyToResponse(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }

    /** Test/diagnostic accessor. */
    public function peek(): ?string
    {
        return $this->state;
    }
}
