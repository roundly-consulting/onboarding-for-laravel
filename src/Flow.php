<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Onboarding\DataTransferObjects\FlowData;
use RoundlyConsulting\Onboarding\DataTransferObjects\StepData;
use RoundlyConsulting\Onboarding\Events\FlowCompleted;
use RoundlyConsulting\Onboarding\Events\StepCompleted;

/** @phpstan-consistent-constructor */
class Flow
{
    /**
     * @param  array<int, Step>  $steps
     */
    public function __construct(
        public array $steps = [],
        public ?string $title = null,
        public ?Model $for = null,
    ) {
        $this->setup();
    }

    protected function setup(): void
    {
        //
    }

    public static function make(string $title): static
    {
        return new static(title: $title);
    }

    /**
     * @param  array<int, Step>  $steps
     */
    public function of(array $steps = []): static
    {
        $this->steps = $steps;

        return $this;
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function for(?Model $model): static
    {
        $this->for = $model;

        return $this;
    }

    /**
     * All steps (including excluded ones), bound to the flow's model and ordered.
     *
     * Steps sort by their explicit `order` when any step declares one; otherwise
     * the original insertion order is preserved.
     *
     * @return Collection<int, Step>
     */
    public function all(): Collection
    {
        $steps = collect($this->steps)->map(fn (Step $step): Step => $step->for($this->for));

        $hasOrder = $steps->contains(fn (Step $step): bool => ! is_null($step->order));

        if ($hasOrder) {
            return $steps
                ->sortBy(fn (Step $step): int => $step->order ?? PHP_INT_MAX)
                ->values();
        }

        return $steps->values();
    }

    /**
     * The steps visible to the bound model (excluded steps removed).
     *
     * @return Collection<int, Step>
     */
    public function steps(): Collection
    {
        return $this->all()->filter->isNotExcluded()->values();
    }

    /**
     * @return Collection<int, Step>
     */
    public function requiredSteps(): Collection
    {
        return $this->steps()->filter->isRequired()->values();
    }

    /**
     * @return Collection<int, Step>
     */
    public function optionalSteps(): Collection
    {
        return $this->steps()->filter->isOptional()->values();
    }

    public function add(string $title): Step
    {
        $step = new Step($title);

        $this->addStep($step);

        return $step;
    }

    public function addStep(Step ...$step): static
    {
        $this->steps = array_values(array_merge($this->steps, $step));

        return $this;
    }

    public function step(string $key): ?Step
    {
        return $this->steps()->first(fn (Step $step): bool => $step->stepKey() === $key);
    }

    public function hasStep(string $key): bool
    {
        return ! is_null($this->step($key));
    }

    public function isEmpty(): bool
    {
        return $this->steps()->isEmpty();
    }

    public function count(): int
    {
        return $this->steps()->count();
    }

    public function completedCount(): int
    {
        return $this->steps()->filter->isCompleted()->count();
    }

    public function isStarted(): bool
    {
        return $this->completedCount() > 0;
    }

    public function isInProgress(): bool
    {
        return ! $this->isCompleted();
    }

    /**
     * A flow is completed once every required, non-excluded step is complete.
     * Optional steps never block completion.
     */
    public function isCompleted(): bool
    {
        return $this->requiredSteps()->every->isCompleted();
    }

    /**
     * The first incomplete, non-excluded step — the one the user should resume on.
     */
    public function currentStep(): ?Step
    {
        return $this->steps()->first->isNotCompleted();
    }

    /**
     * Alias of currentStep(), kept for backward compatibility.
     */
    public function nextStep(): ?Step
    {
        return $this->currentStep();
    }

    /**
     * Zero-based index of the current step within steps(), or null when completed.
     */
    public function currentStepIndex(): ?int
    {
        $index = $this->steps()->search(fn (Step $step): bool => $step->isNotCompleted());

        return $index === false ? null : $index;
    }

    /**
     * One-based human position ("step 3"), capped at the visible step count.
     */
    public function position(): int
    {
        return min($this->completedCount() + 1, max($this->count(), 1));
    }

    /**
     * Completion across all visible steps (optional steps included).
     * Empty flows are considered fully complete.
     */
    public function percentageCompleted(): float
    {
        $percentage = $this->steps()
            ->percentage(fn (Step $step): bool => $step->isCompleted());

        return $percentage ?? 100.0;
    }

    /**
     * Completion across required steps only — the "blocking progress" number.
     * A flow with no required steps is fully complete.
     */
    public function requiredPercentageCompleted(): float
    {
        $percentage = $this->requiredSteps()
            ->percentage(fn (Step $step): bool => $step->isCompleted());

        return $percentage ?? 100.0;
    }

    /**
     * Evaluate the flow against its bound model and announce the current truth.
     *
     * Because the package is stateless it cannot diff transitions; it dispatches
     * StepCompleted for every step that is currently complete and FlowCompleted
     * when the whole flow is complete. Reads never dispatch — only this explicit
     * call does, and only when Laravel's event dispatcher is available.
     */
    public function record(): static
    {
        if (! $this->dispatcherIsAvailable()) {
            return $this;
        }

        $this->steps()
            ->filter->isCompleted()
            ->each(fn (Step $step) => Event::dispatch(new StepCompleted($step, $this->for)));

        if ($this->isCompleted()) {
            Event::dispatch(new FlowCompleted($this, $this->for));
        }

        return $this;
    }

    public function toData(): FlowData
    {
        return new FlowData(
            title: $this->title,
            percentage: $this->percentageCompleted(),
            nextStep: $this->currentStep()?->toData(),
            currentStep: $this->currentStep()?->toData(),
            steps: array_values($this->steps()
                ->map(fn (Step $step): StepData => $step->toData())
                ->all()),
            isCompleted: $this->isCompleted(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->toData()->toArray();
    }

    private function dispatcherIsAvailable(): bool
    {
        return Event::getFacadeApplication() !== null;
    }
}
