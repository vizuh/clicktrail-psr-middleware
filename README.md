[English](README.md) | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**clicktrail/psr-middleware**

PSR-15 middleware that carries observed acquisition context into a PSR-7
request attribute. Persistence occurs only when the injected consent resolver
permits it.

</div>

[![CI](https://github.com/vizuh/clicktrail-psr-middleware/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-psr-middleware/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Index

- [Why](#why)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Storage adapters](#storage-adapters)
- [Consent](#consent)
- [Reading the context](#reading-the-context)
- [How it differs](#how-it-differs)
- [Testing](#testing)
- [License](#license)

## Why

Use this package when downstream PSR-15 handlers need the campaign context
observed on the incoming request. It runs the deterministic ClickTrail core and
attaches an immutable `AttributionContext` containing merged touches, resolved
consent, and recorded suppression reasons.

## Installation

```bash
composer require clicktrail/psr-middleware
```

## Quick start

```php
use ClickTrail\Middleware\ArrayStore;
use ClickTrail\Middleware\CaptureAttributionMiddleware as Capture;
use ClickTrail\Middleware\ConsentMiddleware;
use ClickTrail\Middleware\CookieStore;
use ClickTrail\Middleware\NullConsentResolver;

$clock = fn (): string => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->format('Y-m-d\TH:i:s.v\Z');

$app->add(new ConsentMiddleware($myCmpAdapter));   // optional; resolves the snapshot once
$app->add(new Capture(
    store: new CookieStore('ct_attr'),             // or ArrayStore / your session impl
    consentResolver: new NullConsentResolver(),    // swap in your CMP adapter
    clock: $clock,
));

// downstream handler/controller:
$context = $request->getAttribute(Capture::DEFAULT_ATTRIBUTE);
$context->firstTouch();       // ?Touch - original acquisition, untouched by later direct visits
$context->lastTouch();        // ?Touch - most recent signal
$context->canPersist();       // true only when consent allowed the storage write
$context->suppressionReasons; // audit trail of what was blocked and why
```

A paid-search hit followed by a direct visit leaves `firstTouch()` unchanged while `lastTouch()` moves; that is the merge law of the shared core, not this package's opinion. Without a consent grant, no store write happens at all.

## Storage adapters

The middleware never decides where state lives. Implement `StateStoreInterface` (session, database, cache) or ship one of the built-ins:

- **`ArrayStore`**; per-request memory. Tests and stateless workers.
- **`CookieStore`**; cookie-backed persistence, e.g. `new CookieStore('ct_attr')`.

If the injected `ConsentResolverInterface` returns no grant, `StateStoreInterface::save()` is never called; no cookie, no session entry, nothing.

## Consent

A `null` snapshot means *unknown*, which is denied by default per the
[shared SDK consent contract](https://github.com/vizuh/clicktrail-php/tree/main/src/Consent). Two ways to wire it:

- Pass a `consentResolver` to `CaptureAttributionMiddleware`; it gates persistence directly.
- Add `ConsentMiddleware` upstream; it resolves the snapshot once per request and attaches it under its own attribute (`clicktrail.consent`) for anything else downstream.

`NullConsentResolver` is the safe default: every persistence attempt becomes a recorded suppression reason instead of a write.

## Reading the context

`AttributionContext` is an immutable value object attached to the request (`Capture::DEFAULT_ATTRIBUTE`, override via the `attribute` constructor argument):

```php
$context->attribution;        // StoredState - full merged first/last touch state
$context->consent;            // ?ConsentSnapshot - null when unknown
$context->persisted;          // bool - did this request actually persist?
$context->suppressionReasons; // string[] - human-readable audit entries
```

Snapshots travel with the lead, so months later the conversion worker knows exactly which permissions existed at capture.

## How it differs

| Typical tracking middleware | clicktrail/psr-middleware |
|---|---|
| Makes remote calls during the request cycle | No remote calls, ever; event delivery belongs to `clicktrail/php-sdk` |
| Reads wall-clock time itself | Injected clock callable returning ISO-8601 millisecond timestamps |
| Writes cookies, then asks about consent | No grant → no `save()` call → no write |
| Bundles its own storage backend | Storage belongs to the adapter: bring `StateStoreInterface`, or use `ArrayStore` / `CookieStore` |

## Testing

```bash
podman run --rm -v "$PWD:/app" -v "$PWD/../clicktrail-php:/sdk:ro" \
  wordpress:php8.3-apache php /app/tests/_runner.php
```

## License

MIT © 2026 Vizuh OÜ
