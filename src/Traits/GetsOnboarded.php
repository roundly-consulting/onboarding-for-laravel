<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Traits;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\OnboardingManager;
use RoundlyConsulting\Onboarding\Step;

/**
 * Onboarding reads and writes for a model. Every method goes through the
 * {@see OnboardingManager}, so `Onboarding::fake()` sees them.
 *
 * @mixin Model
 */
trait GetsOnboarded
{
    /**
     * This model's flow: the given key's, else {@see defaultOnboardingKey()}'s, else the
     * `$default` flow bound to this model.
     */
    public function onboarding(?string $key = null, ?Flow $default = null): ?Flow
    {
        return app(OnboardingManager::class)->for($this, $key ?? $this->defaultOnboardingKey())
            ?? $default?->for($this);
    }

    /**
     * Let the manager's resolver pick the right flow for this model.
     */
    public function resolvedOnboarding(): ?Flow
    {
        return app(OnboardingManager::class)->for($this);
    }

    public function defaultOnboardingKey(): string
    {
        return OnboardingManager::$default;
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
        return $this->onboarding($key)?->currentStep();
    }

    /**
     * Dismiss an optional, dismissible step of this model's flow. A no-op without a
     * store, for an unknown flow or step, and for a step that is not dismissible.
     */
    public function dismissOnboardingStep(string $step, ?string $key = null): void
    {
        $this->onboarding($key)?->dismiss($step);
    }
}
