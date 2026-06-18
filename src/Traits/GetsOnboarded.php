<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Traits;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Registry;

/**
 * @mixin Model
 */
trait GetsOnboarded
{
    public function onboarding(?string $key = null, ?Flow $default = null): ?Flow
    {
        if (is_null($key)) {
            $key = $this->defaultOnboardingKey();
        }

        $flow = Onboarding::find($key, $default);

        return $flow?->for($this);
    }

    public function defaultOnboardingKey(): string
    {
        return Registry::$default;
    }
}
