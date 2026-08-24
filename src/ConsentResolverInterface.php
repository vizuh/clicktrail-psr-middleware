<?php

declare(strict_types=1);

namespace ClickTrail\Middleware;

use ClickTrail\Consent\ConsentSnapshot;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Adapter-facing consent source. CMP-specific logic (WP Consent API,
 * CookieYes, Cookiebot, ...) lives behind this interface; the middleware and
 * downstream code only ever see the normalized ClickTrailConsentSnapshot.
 *
 * Contract: return null when no decision is known. Null means "unknown",
 * which is denied by default per the consent compatibility contract.
 */
interface ConsentResolverInterface
{
    public function resolve(ServerRequestInterface $request): ?ConsentSnapshot;
}
