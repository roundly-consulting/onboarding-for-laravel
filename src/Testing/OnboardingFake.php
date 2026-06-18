<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Testing;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Onboarding\Events\FlowCompleted;
use RoundlyConsulting\Onboarding\Events\StepCompleted;

/**
 * Captures the package's events so tests can assert onboarding outcomes with
 * first-class methods. The real Registry is left in place — flows resolve
 * normally and only the dispatcher is faked, mirroring Laravel's Bus::fake().
 */
final class OnboardingFake
{
    public function __construct()
    {
        Event::fake([StepCompleted::class, FlowCompleted::class]);
    }

    /**
     * Assert a StepCompleted was recorded for the given step key. The optional
     * callback receives the event for extra assertions (e.g. the subject).
     *
     * @param  (callable(StepCompleted): bool)|null  $callback
     */
    public function assertStepCompleted(string $key, ?callable $callback = null): self
    {
        Event::assertDispatched(
            StepCompleted::class,
            fn (StepCompleted $event): bool => $event->step->stepKey() === $key
                && ($callback === null || $callback($event)),
        );

        return $this;
    }

    public function assertStepNotCompleted(string $key): self
    {
        Event::assertNotDispatched(
            StepCompleted::class,
            fn (StepCompleted $event): bool => $event->step->stepKey() === $key,
        );

        return $this;
    }

    /**
     * Assert a FlowCompleted was recorded. With a flow key, filters by the
     * completed flow's title (titles, not registry keys, are carried on the
     * event).
     */
    public function assertFlowCompleted(?string $flowKey = null): self
    {
        Event::assertDispatched(
            FlowCompleted::class,
            fn (FlowCompleted $event): bool => $flowKey === null || $event->flow->title === $flowKey,
        );

        return $this;
    }

    public function assertNothingRecorded(): self
    {
        Event::assertNotDispatched(StepCompleted::class);
        Event::assertNotDispatched(FlowCompleted::class);

        return $this;
    }
}
