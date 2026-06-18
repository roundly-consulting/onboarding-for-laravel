<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Events;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Flow;

final class FlowCompleted
{
    public function __construct(
        public readonly Flow $flow,
        public readonly ?Model $for = null,
    ) {}
}
