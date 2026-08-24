<?php

declare(strict_types=1);

namespace ClickTrail\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Persistence boundary for StoredState JSON. The host platform supplies the
 * implementation (session, cookie, cache); the middleware only decides
 * WHETHER persistence happens (consent gate), never HOW.
 */
interface StateStoreInterface
{
    /** Load previously persisted StoredState JSON for this request, or null. */
    public function load(RequestInterface $request): ?string;

    /** Persist StoredState JSON. Called by the middleware ONLY when consent allows analytics. */
    public function save(string $json): void;

    /**
     * Give the store a chance to attach itself to the outgoing response
     * (e.g. a Set-Cookie header). Default contract: return $response unchanged.
     */
    public function applyToResponse(ResponseInterface $response): ResponseInterface;
}
