<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Events;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Step;

final class StepCompleted
{
    public function __construct(
        public readonly Step $step,
        public readonly ?Model $for = null,
    ) {}
}
