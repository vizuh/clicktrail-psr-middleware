[English](README.md) | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**clicktrail/psr-middleware**

Middleware PSR-15 para atribuição ClickTrail em qualquer framework PSR-7 (Slim, Mezzio, Laminas, custom) — first/last touch determinístico na request, nada gravado sem consentimento.

</div>

[![CI](https://github.com/vizuh/clicktrail-psr-middleware/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-psr-middleware/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Índice

- [Por quê](#por-quê)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Adaptadores de armazenamento](#adaptadores-de-armazenamento)
- [Consentimento](#consentimento)
- [Lendo o contexto](#lendo-o-contexto)
- [Como é diferente](#como-é-diferente)
- [Testes](#testes)
- [Licença](#licença)

## Por quê

A maioria das middleware de atribuição captura UTMs num cookie e considera encerrado — sem lei de mesclagem, sem gate de consentimento, decisões de armazenamento embutidas no código. Este pacote roda o núcleo determinístico da ClickTrail dentro do seu stack PSR-15 e entrega aos seus handlers um `AttributionContext` imutável: touches mesclados, consentimento resolvido e uma trilha de auditoria de tudo que foi suprimido e por quê. Parte da expansão PHP/Twig da ClickTrail (polyrepo ADR-0001, layer 1).

## Instalação

```bash
composer require clicktrail/psr-middleware
```

## Início rápido

```php
use ClickTrail\Middleware\ArrayStore;
use ClickTrail\Middleware\CaptureAttributionMiddleware as Capture;
use ClickTrail\Middleware\ConsentMiddleware;
use ClickTrail\Middleware\CookieStore;
use ClickTrail\Middleware\NullConsentResolver;

$clock = fn (): string => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->format('Y-m-d\TH:i:s.v\Z');

$app->add(new ConsentMiddleware($myCmpAdapter));   // opcional; resolve o snapshot uma vez
$app->add(new Capture(
    store: new CookieStore('ct_attr'),             // ou ArrayStore / sua impl de sessão
    consentResolver: new NullConsentResolver(),    // troque pelo adapter do seu CMP
    clock: $clock,
));

// handler/controller downstream:
$context = $request->getAttribute(Capture::DEFAULT_ATTRIBUTE);
$context->firstTouch();       // ?Touch - aquisição original, intocada por visitas diretas posteriores
$context->lastTouch();        // ?Touch - sinal mais recente
$context->canPersist();       // true apenas quando o consentimento permitiu a gravação
$context->suppressionReasons; // trilha de auditoria do que foi bloqueado e por quê
```

Um hit de busca paga seguido de uma visita direta mantém `firstTouch()` intacto enquanto `lastTouch()` se move — essa é a lei de mesclagem do núcleo compartilhado, não opinião deste pacote. Sem concessão de consentimento, nenhuma gravação acontece.

## Adaptadores de armazenamento

A middleware nunca decide onde o estado vive. Implemente `StateStoreInterface` (sessão, banco, cache) ou use um dos embutidos:

- **`ArrayStore`** — memória por request. Testes e workers stateless.
- **`CookieStore`** — persistência via cookie, ex.: `new CookieStore('ct_attr')`.

Se o `ConsentResolverInterface` injetado não devolver concessão, `StateStoreInterface::save()` nunca é chamado — sem cookie, sem entrada de sessão, nada.

## Consentimento

Snapshot `null` significa *desconhecido*, negado por padrão conforme o [contrato de compatibilidade de consentimento](../docs/consent-compatibility-plan.md). Duas formas de ligar:

- Passe um `consentResolver` ao `CaptureAttributionMiddleware`; ele faz o gate de persistência diretamente.
- Adicione `ConsentMiddleware` upstream; ele resolve o snapshot uma vez por request e o anexa sob seu próprio atributo (`clicktrail.consent`) para qualquer outra coisa downstream.

`NullConsentResolver` é o padrão seguro: toda tentativa de persistência vira um motivo de supressão registrado, em vez de uma gravação.

## Lendo o contexto

`AttributionContext` é um value object imutável anexado à request (`Capture::DEFAULT_ATTRIBUTE`, sobrescrevível pelo argumento de construtor `attribute`):

```php
$context->attribution;        // StoredState - estado completo mesclado first/last touch
$context->consent;            // ?ConsentSnapshot - null quando desconhecido
$context->persisted;          // bool - esta request realmente persistiu?
$context->suppressionReasons; // string[] - entradas de auditoria legíveis
```

Os snapshots viajam com o lead, então meses depois o worker de conversão sabe exatamente quais permissões existiam na captura.

## Como é diferente

| Middleware de rastreamento típica | clicktrail/psr-middleware |
|---|---|
| Faz chamadas remotas durante o ciclo da request | Nenhuma chamada remota, nunca — a entrega de eventos pertence ao `clicktrail/php-sdk` |
| Lê o relógio do sistema sozinha | Clock injetável que retorna timestamps ISO-8601 em milissegundos |
| Grava cookies e depois pergunta sobre consentimento | Sem concessão → sem chamada a `save()` → sem gravação |
| Traz seu próprio backend de armazenamento | O armazenamento pertence ao adapter: traga seu `StateStoreInterface`, ou use `ArrayStore` / `CookieStore` |

## Testes

```bash
podman run --rm -v "$PWD:/app" -v "$PWD/../clicktrail-php:/sdk:ro" \
  wordpress:php8.3-apache php /app/tests/_runner.php
```

## Licença

MIT © 2026 Vizuh OÜ
