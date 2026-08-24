# clicktrail/psr-middleware

PSR-15 middleware for [ClickTrail](../clicktrail-php) attribution in any PSR-7
framework (Slim, Mezzio, Laminas, custom). Captures UTMs and ad click IDs from
the incoming request, preserves first/last touch via the deterministic core,
and attaches an `AttributionContext` to the request for downstream handlers.

Part of the ClickTrail PHP/Twig expansion (ADR-0001 polyrepo, layer 1).

## Install

```bash
composer require clicktrail/psr-middleware
```

## Usage

```php
use ClickTrail\Middleware\ArrayStore;
use ClickTrail\Middleware\CaptureAttributionMiddleware;
use ClickTrail\Middleware\CaptureAttributionMiddleware as Capture;
use ClickTrail\Middleware\ConsentMiddleware;
use ClickTrail\Middleware\CookieStore;
use ClickTrail\Middleware\NullConsentResolver;

$clock = fn (): string => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->format('Y-m-d\TH:i:s.v\Z');

$app->add(new ConsentMiddleware($myCmpAdapter));           // optional, resolves snapshot once
$app->add(new CaptureAttributionMiddleware(
    store: new CookieStore('ct_attr'),                     // or ArrayStore / your session impl
    consentResolver: new NullConsentResolver(),            // swap for your CMP adapter
    clock: $clock,
));

// downstream handler/controller:
$context = $request->getAttribute(Capture::DEFAULT_ATTRIBUTE);
$context->firstTouch();      // ?Touch - original acquisition
$context->lastTouch();       // ?Touch - most recent signal
$context->canPersist();      // did consent allow storage?
$context->suppressionReasons; // audit trail of what was blocked and why
```

## Contracts

- **No remote calls.** The middleware never performs HTTP requests during the
  request cycle. Event delivery belongs to the queue/client layer
  (`clicktrail/php-sdk`).
- **Injected clock.** The core never requests time; you pass a callable that
  returns an ISO-8601 millisecond timestamp.
- **Consent gate.** Persistence goes through the injected
  `ConsentResolverInterface`. A `null` snapshot means *unknown*, which is
  denied by default per the [consent compatibility contract](../docs/consent-compatibility-plan.md).
  No grant → no `StateStoreInterface::save()` call → no cookie/session write.
- **Storage belongs to the adapter.** Implement `StateStoreInterface`
  (session, database, cache) or ship `ArrayStore` / `CookieStore`.

## Tests

```bash
podman run --rm -v "$PWD:/app" -v "$PWD/../clicktrail-php:/sdk:ro" \
  wordpress:php8.3-apache php /app/tests/_runner.php
```

## License

MIT © 2026 Vizuh OÜ
