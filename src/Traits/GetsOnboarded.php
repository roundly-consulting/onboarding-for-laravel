<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Traits;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Registry;
use RoundlyConsulting\Onboarding\Step;

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

    public function hasCompletedOnboarding(?string $key = null): bool
    {
        return $this->onboarding($key)?->isCompleted() ?? false;
    }

    public function isOnboarding(?string $key = null): bool
    {
        return $this->onboarding($key)?->isInProgress() ?? false;
    }

    public function onboardingProgress(?string $key = null): float
    {
        return $this->onboarding($key)?->percentageCompleted() ?? 0.0;
    }

    public function nextOnboardingStep(?string $key = null): ?Step
    {
        return $this->onboarding($key)?->nextStep();
    }
}
