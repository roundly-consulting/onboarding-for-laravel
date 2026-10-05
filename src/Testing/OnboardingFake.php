<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use RoundlyConsulting\Onboarding\Events\FlowCompleted;
use RoundlyConsulting\Onboarding\Events\StepCompleted;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\OnboardingManager;

/**
 * A recording {@see OnboardingManager} for host-app tests, installed by
 * {@see Onboarding::fake()}. It keeps every flow and resolver the real manager had,
 * swaps the store for an {@see InMemoryOnboardingStore} you can seed
 * ({@see seedCompleted()}, {@see seedDismissed()}), records every dismissal — through
 * the facade, a flow, or `$user->dismissOnboardingStep()` — and captures the package's
 * events, mirroring Laravel's `Bus::fake()`. When events are already faked it keeps that
 * fake, so an earlier partial `Event::fake([...])` must include `StepCompleted` and
 * `FlowCompleted` for the event assertions to see them.
 */
final class OnboardingFake extends OnboardingManager
{
    /** @var list<array{subject: Authenticatable|Model|null, step: string}> */
    private array $dismissals = [];

    private readonly InMemoryOnboardingStore $memory;

    public function __construct(Container $container, ?OnboardingManager $manager = null)
    {
        parent::__construct($container);

        if ($manager !== null) {
            $this->adopt($manager);
        }

        $this->store = $this->memory = new InMemoryOnboardingStore;

        // Re-faking would wrap the real dispatcher again: a test's full Event::fake() would
        // stop faking (and lose what it recorded). A partial fake must list both events.
        if (! Event::isFake()) {
            Event::fake([StepCompleted::class, FlowCompleted::class]);
        }
    }

    /**
     * Seed steps the subject has already completed — `completedAt()` reports them, and
     * `record()` does not announce them again.
     */
    public function seedCompleted(Authenticatable|Model|null $subject, string ...$steps): self
    {
        foreach ($steps as $step) {
            $this->memory->markCompleted($subject, $step);
        }

        return $this;
    }

    /**
     * Seed steps the subject has already dismissed.
     */
    public function seedDismissed(Authenticatable|Model|null $subject, string ...$steps): self
    {
        foreach ($steps as $step) {
            $this->memory->markDismissed($subject, $step);
        }

        return $this;
    }

    /**
     * The fake never uses a real store; {@see useStore()} is accepted and ignored so host
     * code that configures one keeps working under the fake.
     */
    public function useStore(OnboardingStore|string $store): self
    {
        return $this;
    }

    public function dismissStep(Flow $flow, string $step): void
    {
        $target = $flow->step($step);

        if ($target === null || ! $target->isDismissible()) {
            return;
        }

        $this->dismissals[] = ['subject' => $flow->subject(), 'step' => $target->stepKey()];

        $this->memory->markDismissed($flow->subject(), $target->stepKey());
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

    /**
     * Assert a step was dismissed — for the given subject, or for any subject.
     */
    public function assertDismissed(string $step, Authenticatable|Model|null $subject = null): self
    {
        Assert::assertNotEmpty(
            $this->dismissalsOf($step, $subject),
            "Expected onboarding step [{$step}] to be dismissed".($subject === null ? '' : ' for the given subject').', but it was not.',
        );

        return $this;
    }

    public function assertNotDismissed(string $step, Authenticatable|Model|null $subject = null): self
    {
        Assert::assertEmpty(
            $this->dismissalsOf($step, $subject),
            "Expected onboarding step [{$step}] not to be dismissed".($subject === null ? '' : ' for the given subject').', but it was.',
        );

        return $this;
    }

    public function assertNothingDismissed(): self
    {
        Assert::assertSame([], $this->dismissals, 'Expected no onboarding step to be dismissed, but some were.');

        return $this;
    }

    /**
     * @return list<array{subject: Authenticatable|Model|null, step: string}>
     */
    private function dismissalsOf(string $step, Authenticatable|Model|null $subject): array
    {
        return array_values(array_filter(
            $this->dismissals,
            static fn (array $dismissal): bool => $dismissal['step'] === $step
                && ($subject === null || self::sameSubject($dismissal['subject'], $subject)),
        ));
    }

    private static function sameSubject(Authenticatable|Model|null $recorded, Authenticatable|Model $expected): bool
    {
        if ($recorded instanceof Model && $expected instanceof Model && $expected->getKey() !== null) {
            return $recorded->is($expected);
        }

        return $recorded === $expected;
    }
}
