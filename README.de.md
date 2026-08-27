[English](README.md) | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**clicktrail/psr-middleware**

PSR-15-Middleware, die beobachteten Akquisitionskontext als Attribut an einen
PSR-7-Request hängt. Persistiert wird nur, wenn der injizierte Consent-Resolver
es erlaubt.

</div>

[![CI](https://github.com/vizuh/clicktrail-psr-middleware/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-psr-middleware/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Index

- [Warum](#warum)
- [Installation](#installation)
- [Schnellstart](#schnellstart)
- [Storage-Adapter](#storage-adapter)
- [Consent](#consent)
- [Den Kontext lesen](#den-kontext-lesen)
- [Wie es sich unterscheidet](#wie-es-sich-unterscheidet)
- [Tests](#tests)
- [Lizenz](#lizenz)

## Warum

Verwenden Sie dieses Paket, wenn nachgelagerte PSR-15-Handler den im
eingehenden Request beobachteten Kampagnenkontext benötigen. Es führt den
deterministischen ClickTrail-Core aus und hängt einen unveränderlichen
`AttributionContext` mit zusammengeführten Touches, aufgelöstem Consent und
protokollierten Unterdrückungsgründen an.

## Installation

```bash
composer require clicktrail/psr-middleware
```

## Schnellstart

```php
use ClickTrail\Middleware\ArrayStore;
use ClickTrail\Middleware\CaptureAttributionMiddleware as Capture;
use ClickTrail\Middleware\ConsentMiddleware;
use ClickTrail\Middleware\CookieStore;
use ClickTrail\Middleware\NullConsentResolver;

$clock = fn (): string => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->format('Y-m-d\TH:i:s.v\Z');

$app->add(new ConsentMiddleware($myCmpAdapter));   // optional; löst den Snapshot einmal auf
$app->add(new Capture(
    store: new CookieStore('ct_attr'),             // oder ArrayStore / Ihre Session-Impl
    consentResolver: new NullConsentResolver(),    // gegen Ihren CMP-Adapter tauschen
    clock: $clock,
));

// downstream Handler/Controller:
$context = $request->getAttribute(Capture::DEFAULT_ATTRIBUTE);
$context->firstTouch();       // ?Touch - ursprüngliche Akquisition, von späteren Direktbesuchen unberührt
$context->lastTouch();        // ?Touch - jüngstes Signal
$context->canPersist();       // true nur wenn Consent den Storage-Write erlaubte
$context->suppressionReasons; // Audit-Trail dessen, was blockiert wurde und warum
```

Ein Paid-Search-Hit gefolgt von einem Direktbesuch lässt `firstTouch()` unverändert, während `lastTouch()` wandert; das ist die Merge-Regel des gemeinsamen Cores, nicht die Meinung dieses Pakets. Ohne Consent-Grant findet überhaupt kein Store-Write statt.

## Storage-Adapter

Die Middleware entscheidet nie, wo der State lebt. Implementieren Sie `StateStoreInterface` (Session, Datenbank, Cache) oder nutzen Sie einen der Built-ins:

- **`ArrayStore`**; Memory pro Request. Tests und zustandslose Worker.
- **`CookieStore`**; Cookie-basierte Persistenz, z. B. `new CookieStore('ct_attr')`.

Liefert der injizierte `ConsentResolverInterface` keinen Grant, wird `StateStoreInterface::save()` nie aufgerufen; kein Cookie, kein Session-Eintrag, nichts.

## Consent

Ein `null`-Snapshot bedeutet *unbekannt* und wird gemäß dem
[Consent-Vertrag des gemeinsamen SDK](https://github.com/vizuh/clicktrail-php/tree/main/src/Consent) standardmäßig verweigert. Zwei
Wege zur Verdrahtung:

- Übergeben Sie einen `consentResolver` an `CaptureAttributionMiddleware`; er gatet die Persistenz direkt.
- Ergänzen Sie `ConsentMiddleware` upstream; er löst den Snapshot einmal pro Request auf und hängt ihn unter seinem eigenen Attribut (`clicktrail.consent`) für alles Weitere downstream an.

`NullConsentResolver` ist der sichere Default: Jeder Persistenzversuch wird zu einem protokollierten Suppression-Grund statt zu einem Write.

## Den Kontext lesen

`AttributionContext` ist ein unveränderliches Value Object, das an den Request angehängt wird (`Capture::DEFAULT_ATTRIBUTE`, überschreibbar über das Konstruktorargument `attribute`):

```php
$context->attribution;        // StoredState - vollständiger gemergter First-/Last-Touch-State
$context->consent;            // ?ConsentSnapshot - null wenn unbekannt
$context->persisted;          // bool - hat dieser Request tatsächlich persistiert?
$context->suppressionReasons; // string[] - lesbare Audit-Einträge
```

Snapshots reisen mit dem Lead, sodass der Conversion-Worker Monate später genau weiß, welche Berechtigungen bei der Erfassung galten.

## Wie es sich unterscheidet

| Typische Tracking-Middleware | clicktrail/psr-middleware |
|---|---|
| Macht Remote-Aufrufe im Request-Zyklus | Keine Remote-Aufrufe, niemals; Event-Delivery gehört zu `clicktrail/php-sdk` |
| Liest selbst die Wanduhr | Injizierbare Clock-Callable mit ISO-8601-Millisekunden-Timestamps |
| Schreibt Cookies und fragt dann nach Consent | Kein Grant → kein `save()`-Aufruf → kein Write |
| Bringt ihr eigenes Storage-Backend mit | Storage gehört zum Adapter: eigenes `StateStoreInterface` mitbringen oder `ArrayStore` / `CookieStore` nutzen |

## Tests

```bash
podman run --rm -v "$PWD:/app" -v "$PWD/../clicktrail-php:/sdk:ro" \
  wordpress:php8.3-apache php /app/tests/_runner.php
```

## Lizenz

MIT © 2026 Vizuh OÜ
