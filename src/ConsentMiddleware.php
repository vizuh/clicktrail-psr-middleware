<?php

declare(strict_types=1);

namespace ClickTrail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves the normalized consent snapshot once per request and attaches it
 * as a request attribute so any handler can read it without talking to a
 * CMP directly.
 *
 * CONTRACT: this middleware performs NO remote HTTP calls. Consent sources
 * must be locally resolvable (session, cookie, server-side cache). Delivery
 * of anything to remote systems belongs to the queue/client layer.
 */
final class ConsentMiddleware implements MiddlewareInterface
{
    public const DEFAULT_ATTRIBUTE = 'clicktrail.consent';

    public function __construct(
        private readonly ConsentResolverInterface $resolver,
        private readonly string $attribute = self::DEFAULT_ATTRIBUTE,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $snapshot = $this->resolver->resolve($request);

        return $handler->handle($request->withAttribute($this->attribute, $snapshot));
    }
}
