<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Flow;

/**
 * Carries the live {@see Flow} object, which holds closures, so the event cannot be
 * serialized: listeners must be synchronous (not `ShouldQueue`). For queued work, a
 * synchronous listener dispatches the host's own job with what it needs (e.g. the
 * flow's title and the subject). Listeners must also be idempotent: concurrent
 * `record()` calls can both announce the same completion.
 */
final class FlowCompleted
{
    public function __construct(
        public readonly Flow $flow,
        public readonly Authenticatable|Model|null $for = null,
    ) {}
}
