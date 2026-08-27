[English](README.md) | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**clicktrail/psr-middleware**

PSR-15 中间件，将请求中观测到的获客上下文附加为 PSR-7 请求属性。只有注入的同意解析器允许时才会持久化。

</div>

[![CI](https://github.com/vizuh/clicktrail-psr-middleware/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-psr-middleware/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## 目录

- [为什么](#为什么)
- [安装](#安装)
- [快速上手](#快速上手)
- [存储适配器](#存储适配器)
- [同意控制](#同意控制)
- [读取上下文](#读取上下文)
- [与其他方案的区别](#与其他方案的区别)
- [测试](#测试)
- [许可证](#许可证)

## 为什么

当下游 PSR-15 处理器需要传入请求中观测到的营销活动上下文时，请使用此包。它运行确定性的 ClickTrail 核心，并附加不可变的 `AttributionContext`，其中包含合并后的触点、已解析的同意状态和记录的抑制原因。

## 安装

```bash
composer require clicktrail/psr-middleware
```

## 快速上手

```php
use ClickTrail\Middleware\ArrayStore;
use ClickTrail\Middleware\CaptureAttributionMiddleware as Capture;
use ClickTrail\Middleware\ConsentMiddleware;
use ClickTrail\Middleware\CookieStore;
use ClickTrail\Middleware\NullConsentResolver;

$clock = fn (): string => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->format('Y-m-d\TH:i:s.v\Z');

$app->add(new ConsentMiddleware($myCmpAdapter));   // 可选；每请求只解析一次快照
$app->add(new Capture(
    store: new CookieStore('ct_attr'),             // 或 ArrayStore / 你自己的会话实现
    consentResolver: new NullConsentResolver(),    // 换成你的 CMP 适配器
    clock: $clock,
));

// 下游 handler/controller：
$context = $request->getAttribute(Capture::DEFAULT_ATTRIBUTE);
$context->firstTouch();       // ?Touch - 最初获客来源，不受后续直接访问影响
$context->lastTouch();        // ?Touch - 最近一次信号
$context->canPersist();       // 仅当同意状态允许写入时为 true
$context->suppressionReasons; // 记录了被拦截内容及原因的审计轨迹
```

付费搜索命中之后再来的直接访问不会改变 `firstTouch()`，而 `lastTouch()` 会更新；这是共享核心的合并规则，不是本包自己的观点。没有同意授权时，任何存储写入都不会发生。

## 存储适配器

中间件从不决定状态存在哪里。实现 `StateStoreInterface`（会话、数据库、缓存）或使用内置实现：

- **`ArrayStore`**; 单请求内存。适合测试和无状态 worker。
- **`CookieStore`**; 基于 cookie 的持久化，例如 `new CookieStore('ct_attr')`。

如果注入的 `ConsentResolverInterface` 未返回授权，就永远不会调用 `StateStoreInterface::save()`；不写 cookie、不写会话，什么都不写。

## 同意控制

`null` 快照表示*未知*，按[共享 SDK 同意契约](https://github.com/vizuh/clicktrail-php/tree/main/src/Consent)默认拒绝。两种接入方式：

- 给 `CaptureAttributionMiddleware` 传入 `consentResolver`；它直接为持久化做门控。
- 在上游添加 `ConsentMiddleware`；它每请求解析一次快照，并挂在自己的属性下（`clicktrail.consent`），供下游其他组件使用。

`NullConsentResolver` 是安全默认值：每次持久化尝试都会变成一条已记录的抑制原因，而不是一次写入。

## 读取上下文

`AttributionContext` 是一个不可变值对象，挂在请求上（`Capture::DEFAULT_ATTRIBUTE`，可通过构造参数 `attribute` 覆盖）：

```php
$context->attribution;        // StoredState - 完整合并的首次/末次触点状态
$context->consent;            // ?ConsentSnapshot - 未知时为 null
$context->persisted;          // bool - 本次请求是否真的持久化了？
$context->suppressionReasons; // string[] - 可读的审计条目
```

快照随线索一同保存，因此数月之后，转化任务仍能确切知道采集时存在哪些权限。

## 与其他方案的区别

| 常见跟踪中间件 | clicktrail/psr-middleware |
|---|---|
| 在请求周期内发起远程调用 | 从不发远程调用；事件投递属于 `clicktrail/php-sdk` |
| 自行读取系统时钟 | 注入式 clock 回调，返回 ISO-8601 毫秒级时间戳 |
| 先写 cookie，再考虑同意问题 | 无授权 → 不调用 `save()` → 不写入 |
| 自带存储后端 | 存储属于适配器：自带 `StateStoreInterface` 实现，或使用 `ArrayStore` / `CookieStore` |

## 测试

```bash
podman run --rm -v "$PWD:/app" -v "$PWD/../clicktrail-php:/sdk:ro" \
  wordpress:php8.3-apache php /app/tests/_runner.php
```

## 许可证

MIT © 2026 Vizuh OÜ
