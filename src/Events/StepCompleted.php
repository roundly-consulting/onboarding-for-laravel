<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Step;

/**
 * Carries the live {@see Step} object, which holds closures, so the event cannot be
 * serialized: listeners must be synchronous (not `ShouldQueue`). For queued work, a
 * synchronous listener dispatches the host's own job with what it needs (e.g. the
 * step key and the subject). Listeners must also be idempotent: concurrent `record()`
 * calls can both announce the same completion.
 */
final class StepCompleted
{
    public function __construct(
        public readonly Step $step,
        public readonly Authenticatable|Model|null $for = null,
    ) {}
}
