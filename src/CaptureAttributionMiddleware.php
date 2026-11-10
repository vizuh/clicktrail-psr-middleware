<?php

declare(strict_types=1);

namespace ClickTrail\Middleware;

use ClickTrail\Consent\ConsentBehavior;
use ClickTrail\Consent\ConsentSnapshot;
use ClickTrail\Core\AttributionInput;
use ClickTrail\Core\StoredState;
use ClickTrail\Core\TouchMerger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 capture middleware: reads UTMs / ad click IDs from the incoming
 * request URI, merges them into first/last-touch state via the deterministic
 * core, persists ONLY when the consent snapshot allows analytics, and
 * attaches an AttributionContext attribute for downstream handlers.
 *
 * CONTRACT:
 *  - NO remote HTTP calls happen inside this middleware. Delivery of tracked
 *    events belongs to the queue/client layer (see clicktrail/php-sdk).
 *  - Core never requests time: the caller injects a Clock callable returning
 *    an ISO-8601 millisecond timestamp (e.g. fn() => now()->format('Y-m-d\TH:i:s.v\Z')).
 *  - Persistence is gated through the injected ConsentResolver; null snapshot
 *    (unknown) counts as denied per the consent compatibility contract.
 */
final class CaptureAttributionMiddleware implements MiddlewareInterface
{
    public const DEFAULT_ATTRIBUTE = 'clicktrail.attribution_context';

    /** @var callable():string */
    private $clock;

    /**
     * @param callable():string|null $clock returns ISO-8601 ms timestamp; defaults to gmdate
     */
    public function __construct(
        private readonly StateStoreInterface $store,
        private readonly ConsentResolverInterface $consentResolver,
        ?callable $clock = null,
        private readonly string $attribute = self::DEFAULT_ATTRIBUTE,
    ) {
        $this->clock = $clock ?? static fn (): string => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        parse_str($uri->getQuery(), $query);
        $referrer = $request->getHeaderLine('Referer');

        $input = new AttributionInput(
            query: $query,
            host: strtolower($uri->getHost() !== '' ? $uri->getHost() : $request->getHeaderLine('Host')),
            landingPage: (string) $uri,
            referrer: $referrer !== '' ? $referrer : null,
            touchTimestamp: ($this->clock)(),
        );

        $stored = self::restore($this->store->load($request));
        $state = TouchMerger::observe($stored, $input);

        $snapshot = $this->consentResolver->resolve($request);
        $suppression = [];
        $persisted = false;

        if ($snapshot !== null && ConsentBehavior::can($snapshot, ConsentSnapshot::CAP_ANALYTICS)) {
            // observe() returns $stored itself when there is no new signal:
            // nothing changed, so no write.
            if ($state !== $stored) {
                $this->store->save($state->toJson());
            }
            $persisted = true;
        } elseif ($snapshot === null) {
            $suppression[] = 'analytics_storage unknown at capture (source: none) - persistence blocked';
        } else {
            $reason = ConsentBehavior::suppressionReason($snapshot, ConsentSnapshot::CAP_ANALYTICS);
            if ($reason !== null) {
                $suppression[] = $reason;
            }
            // Denied: no write of any kind through the store. Identifier
            // clearing/rotation on withdrawal belongs to a dedicated consent
            // listener in the host platform, not to capture.
        }

        $context = new AttributionContext(
            attribution: $state,
            consent: $snapshot,
            suppressionReasons: $suppression,
            persisted: $persisted,
        );

        $response = $handler->handle($request->withAttribute($this->attribute, $context));

        return $this->store->applyToResponse($response);
    }

    /**
     * Restore persisted state from untrusted input (e.g. a cookie). Anything
     * oversized or malformed degrades to empty state instead of a 500.
     */
    private static function restore(?string $json): StoredState
    {
        // Browsers drop cookies over 4096 bytes, so no legitimate state is larger.
        if ($json === null || strlen($json) > 4096) {
            return StoredState::empty();
        }
        $data = json_decode($json, true, 8);
        foreach (['first', 'last'] as $key) {
            $clickIds = is_array($data) && is_array($data[$key] ?? null) ? ($data[$key]['click_ids'] ?? null) : null;
            if ($clickIds === null) {
                continue;
            }
            if (!is_array($clickIds)) {
                return StoredState::empty();
            }
            foreach ($clickIds as $id => $value) {
                if (!is_string($id) || !is_string($value)) {
                    return StoredState::empty();
                }
            }
        }
        try {
            // Touch's typed ?string properties reject every other non-string field.
            $state = StoredState::fromJson($json);
        } catch (\Throwable) {
            return StoredState::empty();
        }
        // A touch with no known field set (JSON list, unknown keys only) is not a
        // real touch; keeping it would let it displace the visitor's real first touch.
        foreach ([$state->first, $state->last] as $touch) {
            if ($touch !== null && array_filter($touch->toArray(), static fn ($v): bool => $v !== null) === []) {
                return StoredState::empty();
            }
        }

        return $state;
    }
}
