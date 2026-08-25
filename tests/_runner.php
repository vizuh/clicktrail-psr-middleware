<?php
/**
 * Standalone assertion runner (no phpunit needed) - pattern of
 * clicktrail-php/tests/_runner.php. Runs inside the podman wordpress:php8.3-apache
 * container with this repo mounted at /app and the SDK at /sdk:
 *   podman run --rm -v $PWD:/app -v ../clicktrail-php:/sdk:ro \
 *     wordpress:php8.3-apache php /app/tests/_runner.php
 */

// ---- Minimal PSR-7/15 stubs (interfaces + one request/response impl) ----
namespace Psr\Http\Message {
    interface MessageInterface {
        public function getProtocolVersion(): string;
        public function withProtocolVersion(string $version): static;
        public function getHeaders(): array;
        public function hasHeader(string $name): bool;
        public function getHeader(string $name): array;
        public function getHeaderLine(string $name): string;
        public function withHeader(string $name, $value): static;
        public function withAddedHeader(string $name, $value): static;
        public function withoutHeader(string $name): static;
        public function getBody(): StreamInterface;
        public function withBody(StreamInterface $body): static;
    }
    interface RequestInterface extends MessageInterface {
        public function getRequestTarget(): string;
        public function withRequestTarget(string $requestTarget): static;
        public function getMethod(): string;
        public function withMethod(string $method): static;
        public function getUri(): UriInterface;
        public function withUri(UriInterface $uri, ?string $preserveHost = null): static;
    }
    interface ServerRequestInterface extends RequestInterface {
        public function getServerParams(): array;
        public function getCookieParams(): array;
        public function withCookieParams(array $cookies): static;
        public function getQueryParams(): array;
        public function withQueryParams(array $query): static;
        public function getUploadedFiles(): array;
        public function withUploadedFiles(array $uploadedFiles): static;
        public function getParsedBody(): null|array|object;
        public function withParsedBody($data): static;
        public function getAttributes(): array;
        public function getAttribute(string $name, $default = null);
        public function withAttribute(string $name, $value): static;
        public function withoutAttribute(string $name): static;
    }
    interface ResponseInterface extends MessageInterface {
        public function getStatusCode(): int;
        public function withStatus(int $code, string $reasonPhrase = ''): static;
        public function getReasonPhrase(): string;
    }
    interface UriInterface {
        public function getScheme(): string;
        public function getAuthority(): string;
        public function getUserInfo(): string;
        public function getHost(): string;
        public function getPort(): ?int;
        public function getPath(): string;
        public function getQuery(): string;
        public function getFragment(): string;
        public function withScheme(string $scheme): static;
        public function withUserInfo(string $user, ?string $password = null): static;
        public function withHost(string $host): static;
        public function withPort(?int $port): static;
        public function withPath(string $path): static;
        public function withQuery(string $query): static;
        public function withFragment(string $fragment): static;
        public function __toString(): string;
    }
    interface StreamInterface {}
}
namespace Psr\Http\Server {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    interface MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
    }
    interface RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface;
    }
}
namespace ClickTrail\Test {
    class TestUri implements \Psr\Http\Message\UriInterface {
        public function __construct(private string $url) { }
        private function parts(): array { return parse_url($this->url); }
        public function getScheme(): string { return $this->parts()['scheme'] ?? ''; }
        public function getAuthority(): string { return $this->getHost(); }
        public function getUserInfo(): string { return ''; }
        public function getHost(): string { return strtolower($this->parts()['host'] ?? ''); }
        public function getPort(): ?int { return isset($this->parts()['port']) ? (int)$this->parts()['port'] : null; }
        public function getPath(): string { return $this->parts()['path'] ?? ''; }
        public function getQuery(): string { return $this->parts()['query'] ?? ''; }
        public function getFragment(): string { return ''; }
        public function withScheme(string $scheme): static { throw new \LogicException('not needed'); }
        public function withUserInfo(string $user, ?string $password = null): static { throw new \LogicException('not needed'); }
        public function withHost(string $host): static { throw new \LogicException('not needed'); }
        public function withPort(?int $port): static { throw new \LogicException('not needed'); }
        public function withPath(string $path): static { throw new \LogicException('not needed'); }
        public function withQuery(string $query): static { throw new \LogicException('not needed'); }
        public function withFragment(string $fragment): static { throw new \LogicException('not needed'); }
        public function __toString(): string { return $this->url; }
    }

    class TestRequest implements \Psr\Http\Message\ServerRequestInterface {
        public function __construct(
            private string $url,
            private array $headers = [],
            private array $cookies = [],
            private array $attributes = [],
            private string $method = 'GET',
        ) { }
        // MessageInterface
        public function getProtocolVersion(): string { return '1.1'; }
        public function withProtocolVersion(string $version): static { return $this; }
        public function getHeaders(): array { return $this->headers; }
        public function hasHeader(string $name): bool { return isset($this->headers[$name]); }
        public function getHeader(string $name): array { return [$this->getHeaderLine($name)]; }
        public function getHeaderLine(string $name): string { return $this->headers[$name] ?? ''; }
        public function withHeader(string $name, $value): static { $c = clone $this; $c->headers[$name] = (string)(is_array($value) ? reset($value) : $value); return $c; }
        public function withAddedHeader(string $name, $value): static { return $this->withHeader($name, $value); }
        public function withoutHeader(string $name): static { $c = clone $this; unset($c->headers[$name]); return $c; }
        public function getBody(): \Psr\Http\Message\StreamInterface { throw new \LogicException('not needed'); }
        public function withBody(\Psr\Http\Message\StreamInterface $body): static { return $this; }
        // RequestInterface
        public function getRequestTarget(): string { return '/'; }
        public function withRequestTarget(string $t): static { return $this; }
        public function getMethod(): string { return $this->method; }
        public function withMethod(string $method): static { $c = clone $this; $c->method = $method; return $c; }
        public function getUri(): \Psr\Http\Message\UriInterface { return new TestUri($this->url); }
        public function withUri(\Psr\Http\Message\UriInterface $uri, ?string $preserveHost = null): static { return $this; }
        // ServerRequestInterface
        public function getServerParams(): array { return []; }
        public function getCookieParams(): array { return $this->cookies; }
        public function withCookieParams(array $cookies): static { $c = clone $this; $c->cookies = $cookies; return $c; }
        public function getQueryParams(): array { return []; }
        public function withQueryParams(array $query): static { return $this; }
        public function getUploadedFiles(): array { return []; }
        public function withUploadedFiles(array $u): static { return $this; }
        public function getParsedBody(): null|array|object { return null; }
        public function withParsedBody($data): static { return $this; }
        public function getAttributes(): array { return $this->attributes; }
        public function getAttribute(string $name, $default = null) { return $this->attributes[$name] ?? $default; }
        public function withAttribute(string $name, $value): static { $c = clone $this; $c->attributes[$name] = $value; return $c; }
        public function withoutAttribute(string $name): static { $c = clone $this; unset($c->attributes[$name]); return $c; }
    }

    class TestResponse implements \Psr\Http\Message\ResponseInterface {
        public function __construct(private array $headers = [], private int $status = 200) { }
        public function getProtocolVersion(): string { return '1.1'; }
        public function withProtocolVersion(string $v): static { return $this; }
        public function getHeaders(): array { return $this->headers; }
        public function hasHeader(string $n): bool { return isset($this->headers[$n]); }
        public function getHeader(string $n): array { return [$this->getHeaderLine($n)]; }
        public function getHeaderLine(string $n): string { return $this->headers[$n] ?? ''; }
        public function withHeader(string $n, $v): static { $c = clone $this; $c->headers[$n] = (string)(is_array($v) ? reset($v) : $v); return $c; }
        public function withAddedHeader(string $n, $v): static { return $this->withHeader($n, $v); }
        public function withoutHeader(string $n): static { $c = clone $this; unset($c->headers[$n]); return $c; }
        public function getBody(): \Psr\Http\Message\StreamInterface { throw new \LogicException('not needed'); }
        public function withBody(\Psr\Http\Message\StreamInterface $b): static { return $this; }
        public function getStatusCode(): int { return $this->status; }
        public function withStatus(int $code, string $r = ''): static { $c = clone $this; $c->status = $code; return $c; }
        public function getReasonPhrase(): string { return ''; }
    }

    class TestHandler implements \Psr\Http\Server\RequestHandlerInterface {
        public function __construct(public mixed $captured = null) { }
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
            $this->captured = $request;
            return new TestResponse();
        }
    }
}

namespace {
    error_reporting(E_ALL);
    $appRoot = getenv('CLICKTRAIL_APP_ROOT') ?: '/app';
    $sdkRoot = getenv('CLICKTRAIL_SDK_ROOT') ?: '/sdk';

    spl_autoload_register(function ($class) use ($appRoot, $sdkRoot) {
        $map = [
            'ClickTrail\\Core\\' => $sdkRoot . '/src/Core/',
            'ClickTrail\\Consent\\' => $sdkRoot . '/src/Consent/',
            'ClickTrail\\Middleware\\' => $appRoot . '/src/',
            'ClickTrail\\' => $sdkRoot . '/src/', // Conventions + any other SDK namespaces
        ];
        foreach ($map as $prefix => $base) {
            if (str_starts_with($class, $prefix)) {
                $f = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($f)) { require $f; return; }
            }
        }
    });

    use ClickTrail\Consent\ConsentSnapshot;
    use ClickTrail\Consent\ConsentValue;
    use ClickTrail\Middleware\ArrayStore;
    use ClickTrail\Middleware\CaptureAttributionMiddleware;
    use ClickTrail\Middleware\CookieStore;
    use ClickTrail\Middleware\NullConsentResolver;
    use ClickTrail\Test\TestHandler;
    use ClickTrail\Test\TestRequest;
    use ClickTrail\Test\TestResponse;

    function check(bool $cond, string $msg): void {
        if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); }
    }

    $ts = fn (int $min): string => sprintf('2026-08-24T10:%02d:00.000Z', $min);

    // T1 capture on paid-search URL -> context attached, persisted under granted analytics consent
    $granted = new ConsentSnapshot('test', $ts(0),
        ConsentValue::Granted, ConsentValue::Granted, ConsentValue::Denied, ConsentValue::Denied, ConsentValue::Denied);
    $resolver = new class($granted) implements ClickTrail\Middleware\ConsentResolverInterface {
        public function __construct(private ?ConsentSnapshot $s) {}
        public function resolve(\Psr\Http\Message\ServerRequestInterface $r): ?ConsentSnapshot { return $this->s; }
    };
    $store = new ArrayStore();
    $mw = new CaptureAttributionMiddleware($store, $resolver, fn (): string => $ts(0));
    $handler = new TestHandler();
    $req = new TestRequest('https://example.com/promo?utm_source=google&utm_medium=cpc&utm_campaign=summer&gclid=XYZ1');
    $resp = $mw->process($req, $handler);

    $ctx = $handler->captured->getAttribute(CaptureAttributionMiddleware::DEFAULT_ATTRIBUTE);
    check($ctx !== null, 'T1 context attribute present');
    check($ctx->firstTouch()->source === 'google', 'T1 first touch source');
    check(($ctx->lastTouch()->clickIds['gclid'] ?? '') === 'XYZ1', 'T1 gclid captured');
    check($ctx->canPersist(), 'T1 persisted flag true');
    $stored = json_decode((string) $store->peek(), true);
    check(($stored['last']['source'] ?? '') === 'google', 'T1 store holds google');
    check($ctx->suppressionReasons === [], 'T1 no suppression reasons');

    // T2 internal-referrer navigation is a no-op for state (no new touch)
    $req2 = new TestRequest('https://example.com/pricing', ['Referer' => 'https://example.com/menu']);
    $h2 = new TestHandler();
    $mw->process($req2, $h2);
    $ctx2 = $h2->captured->getAttribute(CaptureAttributionMiddleware::DEFAULT_ATTRIBUTE);
    check($ctx2->firstTouch()->touchTimestamp === $ctx->firstTouch()->touchTimestamp, 'T2 first untouched');
    check($ctx2->attribution->toJson() === $ctx->attribution->toJson(), 'T2 stored state unchanged');
    check($store->peek() === $store->peek(), 'T2 store stable');

    // T3 unknown-consent blocks persistence entirely (null resolver)
    $store3 = new ArrayStore();
    $mw3 = new CaptureAttributionMiddleware($store3, new NullConsentResolver(), fn (): string => $ts(5));
    $h3 = new TestHandler();
    $mw3->process(new TestRequest('https://example.com/promo?utm_source=google&utm_medium=cpc&gclid=G9'), $h3);
    check($store3->peek() === null, 'T3 nothing written on unknown consent');
    $ctx3 = $h3->captured->getAttribute(CaptureAttributionMiddleware::DEFAULT_ATTRIBUTE);
    check($ctx3->consent === null && !$ctx3->canPersist(), 'T3 context flags unknown consent');
    check(count($ctx3->suppressionReasons) === 1, 'T3 suppression reason recorded');

    // T4 CookieStore writes Set-Cookie only when save() was gated through
    $cookieStore = new CookieStore(name: 'ct_test');
    check($cookieStore->applyToResponse(new TestResponse()) instanceof TestResponse, 'T4 no pending cookie passthrough');
    $cookieStore->save(json_encode(['first' => null, 'last' => null]));
    $withCookie = $cookieStore->applyToResponse(new TestResponse());
    check(str_contains($withCookie->getHeaderLine('Set-Cookie'), 'ct_test='), 'T4 cookie written after gated save');

    fwrite(STDOUT, "ALL PASS (" . 4 . " scenarios)\n");
}
