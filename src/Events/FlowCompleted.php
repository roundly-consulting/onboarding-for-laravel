<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Flow;

final class FlowCompleted
{
    public function __construct(
        public readonly Flow $flow,
        public readonly Authenticatable|Model|null $for = null,
    ) {}
}
