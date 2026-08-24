<?php

declare(strict_types=1);

namespace ClickTrail\Middleware;

use ClickTrail\Consent\ConsentSnapshot;
use ClickTrail\Core\StoredState;
use ClickTrail\Core\Touch;

/**
 * Immutable value object attached to the request for downstream handlers
 * and controllers. Exposes the merged attribution state, the resolved
 * consent snapshot (null = unknown), and the audit trail of suppression
 * reasons explaining what was NOT persisted or sent and why.
 */
final class AttributionContext
{
    /**
     * @param string[] $suppressionReasons human-readable audit entries
     */
    public function __construct(
        public readonly StoredState $attribution,
        public readonly ?ConsentSnapshot $consent,
        public readonly array $suppressionReasons = [],
        public readonly bool $persisted = false,
    ) {
    }

    public function firstTouch(): ?Touch
    {
        return $this->attribution->first;
    }

    public function lastTouch(): ?Touch
    {
        return $this->attribution->last;
    }

    /** True when analytics persistence was allowed by the consent snapshot. */
    public function canPersist(): bool
    {
        return $this->persisted;
    }
}
