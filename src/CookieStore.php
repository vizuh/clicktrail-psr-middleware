<?php

declare(strict_types=1);

namespace ClickTrail\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * First-party cookie store. Reads the previous state from the request's
 * cookie params and queues a Set-Cookie on the response - but ONLY if the
 * middleware called save(), which happens solely when the consent snapshot
 * allows analytics. No consent grant -> no save() call -> no cookie written.
 */
final class CookieStore implements StateStoreInterface
{
    private ?string $pending = null;

    public function __construct(
        private readonly string $name = 'ct_attr',
        private readonly int $maxAgeSeconds = 15552000, // 180 days
        private readonly bool $secure = true,
        private readonly string $sameSite = 'Lax',
    ) {
    }

    public function load(RequestInterface $request): ?string
    {
        if (!$request instanceof \Psr\Http\Message\ServerRequestInterface) {
            return null;
        }
        $raw = $request->getCookieParams()[$this->name] ?? null;

        return is_string($raw) && $raw !== '' ? rawurldecode($raw) : null;
    }

    public function save(string $json): void
    {
        $this->pending = $json;
    }

    public function applyToResponse(ResponseInterface $response): ResponseInterface
    {
        if ($this->pending === null) {
            return $response;
        }
        $parts = [
            sprintf('%s=%s', $this->name, rawurlencode($this->pending)),
            sprintf('Max-Age=%d', $this->maxAgeSeconds),
            'Path=/',
            'HttpOnly',
            sprintf('SameSite=%s', $this->sameSite),
        ];
        if ($this->secure) {
            $parts[] = 'Secure';
        }

        return $response->withHeader('Set-Cookie', implode('; ', $parts));
    }
}
