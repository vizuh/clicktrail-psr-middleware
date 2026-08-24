<?php

declare(strict_types=1);

namespace ClickTrail\Middleware;

use ClickTrail\Consent\ConsentSnapshot;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Safe default: every request resolves to null (unknown). Per the consent
 * contract unknown = denied, so persistence is always suppressed under this
 * resolver. Use it until a real adapter is wired.
 */
final class NullConsentResolver implements ConsentResolverInterface
{
    public function resolve(ServerRequestInterface $request): ?ConsentSnapshot
    {
        return null;
    }
}
