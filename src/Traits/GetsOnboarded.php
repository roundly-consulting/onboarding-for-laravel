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
     * This model's flow: the given key's; without a key, the resolver's choice
     * ({@see OnboardingManager::resolveUsing()}), else {@see defaultOnboardingKey()}'s.
     * Falls back to a copy of the `$default` flow bound to this model. Every no-key
     * reader below (and the `onboarding` middleware) goes through here.
     */
    public function onboarding(?string $key = null, ?Flow $default = null): ?Flow
    {
        $manager = app(OnboardingManager::class);

        $flow = $key === null
            ? $manager->resolveFor($this, $this->defaultOnboardingKey())
            : $manager->for($this, $key);

        // A copy, like the manager's: binding the caller's flow in place would let two
        // models sharing one `$default` overwrite each other's subject.
        return $flow ?? ($default === null ? null : (clone $default)->for($this));
    }

    /**
     * The manager's choice for this model, exactly as `Onboarding::for($model)`: the
     * resolver's flow, else the default flow (ignores {@see defaultOnboardingKey()}).
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
     * store, for an unknown flow or step, and for a required or non-dismissible step.
     */
    public function dismissOnboardingStep(string $step, ?string $key = null): void
    {
        $this->onboarding($key)?->dismiss($step);
    }
}
