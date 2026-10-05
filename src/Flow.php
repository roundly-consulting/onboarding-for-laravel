<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\Onboarding\DataTransferObjects\FlowData;
use RoundlyConsulting\Onboarding\DataTransferObjects\SectionData;
use RoundlyConsulting\Onboarding\DataTransferObjects\StepData;
use RoundlyConsulting\Onboarding\Events\FlowCompleted;
use RoundlyConsulting\Onboarding\Events\StepCompleted;

/** @phpstan-consistent-constructor */
class Flow
{
    use Macroable;

    /**
     * The section key used for steps that declare no group.
     */
    public const UNGROUPED_SECTION = 'general';

    /**
     * @param  array<int, Step>  $steps
     */
    public function __construct(
        public array $steps = [],
        public ?string $title = null,
        public Authenticatable|Model|null $for = null,
    ) {
        $this->setup();
    }

    protected function setup(): void
    {
        //
    }

    /**
     * A copy owns its steps, so binding the copy to a subject never rebinds the
     * original (the registered definition) or another copy.
     */
    public function __clone()
    {
        $this->steps = array_map(static fn (Step $step): Step => clone $step, $this->steps);
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

    public function for(Authenticatable|Model|null $subject): static
    {
        $this->for = $subject;

        return $this;
    }

    /**
     * The subject reads bind to: the explicit subject when set, otherwise the
     * authenticated user when an auth context is available, otherwise null.
     */
    public function subject(): Authenticatable|Model|null
    {
        return $this->resolveSubject();
    }

    protected function resolveSubject(): Authenticatable|Model|null
    {
        if ($this->for !== null) {
            return $this->for;
        }

        if (Auth::getFacadeApplication() === null) {
            return null;
        }

        return Auth::user();
    }

    /**
     * All steps (including excluded ones), bound to the flow's subject and ordered.
     *
     * Steps sort by their explicit `order` when any step declares one; otherwise
     * the original insertion order is preserved.
     *
     * @return Collection<int, Step>
     */
    public function all(): Collection
    {
        $subject = $this->resolveSubject();

        $steps = collect($this->steps)->map(fn (Step $step): Step => $step->for($subject));

        $hasOrder = $steps->contains(fn (Step $step): bool => ! is_null($step->order));

        if ($hasOrder) {
            return $steps
                ->sortBy(fn (Step $step): int => $step->order ?? PHP_INT_MAX)
                ->values();
        }

        return $steps->values();
    }

    /**
     * The steps visible to the bound subject (excluded and dismissed steps removed).
     *
     * @return Collection<int, Step>
     */
    public function steps(): Collection
    {
        return $this->all()
            ->filter->isNotExcluded()
            ->reject(fn (Step $step): bool => $this->isDismissedStep($step))
            ->values();
    }

    /**
     * A dismissible, optional step the bound subject has dismissed drops out of
     * the visible list. Required steps can never be dismissed away.
     */
    private function isDismissedStep(Step $step): bool
    {
        return $step->isOptional() && $step->isDismissible() && $step->isDismissed();
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
     * Per-group sections derived from the visible steps. Ungrouped steps fall
     * into the reserved "general" section. Sections follow each group's
     * first appearance in the ordered step list.
     *
     * @return Collection<int, SectionData>
     */
    public function sections(): Collection
    {
        $grouped = $this->steps()->groupBy(
            fn (Step $step): string => $step->groupName() ?? self::UNGROUPED_SECTION,
        );

        return $grouped
            ->map(fn (Collection $steps, string $group): SectionData => $this->makeSection($group, $steps))
            ->values();
    }

    /**
     * Alias of sections() for discoverability.
     *
     * @return Collection<int, SectionData>
     */
    public function groups(): Collection
    {
        return $this->sections();
    }

    public function section(string $group): ?SectionData
    {
        return $this->sections()->first(fn (SectionData $section): bool => $section->key === $group);
    }

    /**
     * @param  Collection<int, Step>  $steps
     */
    private function makeSection(string $group, Collection $steps): SectionData
    {
        $percentage = $steps->percentage(fn (Step $step): bool => $step->isCompleted()) ?? 100.0;

        $isCompleted = $steps->filter->isRequired()->every->isCompleted();

        return new SectionData(
            key: $group,
            title: $group,
            percentage: $percentage,
            isCompleted: $isCompleted,
            steps: array_values($steps->map(fn (Step $step): StepData => $step->toData())->all()),
        );
    }

    /**
     * Dismiss a step for the bound subject, through the manager (so `Onboarding::fake()`
     * records it). A no-op for a required or non-dismissible step, without a store, and
     * outside a booted application.
     */
    public function dismiss(string $key): static
    {
        if (App::getFacadeApplication() !== null) {
            App::make(OnboardingManager::class)->dismissStep($this, $key);
        }

        return $this;
    }

    /**
     * Build a redirect to the current step's target (route, then URL, then the
     * free-form action), or null when there is no resolvable target.
     */
    public function redirectToCurrentStep(): ?RedirectResponse
    {
        $step = $this->currentStep();

        if ($step === null) {
            return null;
        }

        return $this->redirectForStep($step);
    }

    private function redirectForStep(Step $step): ?RedirectResponse
    {
        if (Redirect::getFacadeApplication() === null) {
            return null;
        }

        $route = $step->route === null ? null : $this->namedRoute($step->route);

        if ($route !== null) {
            return $this->redirectToRoute($route, $step->routeParameters());
        }

        $target = $step->url ?? $step->action;

        if ($target === null) {
            return null;
        }

        $route = $this->namedRoute($target);

        if ($route !== null) {
            return $this->redirectToRoute($route);
        }

        if (Str::startsWith($target, ['http://', 'https://', '/'])) {
            return Redirect::to($target);
        }

        return null;
    }

    /**
     * A route the step's parameters cannot fill (a missing required parameter) is
     * reported and treated as "no resolvable target", so one misconfigured step never
     * turns every guarded request into a 500.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    private function redirectToRoute(RoutingRoute $route, array $parameters = []): ?RedirectResponse
    {
        try {
            $url = App::make(UrlGenerator::class)->toRoute($route, $parameters, true);
        } catch (UrlGenerationException $exception) {
            report($exception);

            return null;
        }

        return Redirect::to($url);
    }

    private function namedRoute(string $name): ?RoutingRoute
    {
        // namedRoute() is only reached from redirectForStep(), which has
        // already confirmed the (shared) facade application is bootstrapped.
        return Route::getRoutes()->getByName($name);
    }

    /**
     * Evaluate the flow against its bound subject and announce the current truth.
     *
     * With no argument, dispatches StepCompleted for every currently-complete
     * step and FlowCompleted when the whole flow is complete. With a step key,
     * announces only that step (if it is visible and complete), and fires
     * FlowCompleted only when that announcement finishes the flow: the step is
     * required, was announced, and the flow is now complete. An unknown,
     * incomplete, optional or already-recorded step never fires FlowCompleted.
     *
     * When a host has bound an OnboardingStore, steps already recorded as
     * completed (completedAt() is non-null) are suppressed, giving once-only
     * semantics — the host owns persistence via the event listener.
     *
     * Reads never dispatch — only this explicit call does, and only when
     * Laravel's event dispatcher is available.
     */
    public function record(?string $key = null): static
    {
        if (! $this->dispatcherIsAvailable()) {
            return $this;
        }

        $subject = $this->resolveSubject();

        $candidates = $key === null
            ? $this->steps()->filter->isCompleted()
            : $this->steps()->filter(fn (Step $step): bool => $step->stepKey() === $key && $step->isCompleted());

        $announced = $candidates
            ->reject(fn (Step $step): bool => $step->completedAt() !== null)
            ->each(fn (Step $step) => Event::dispatch(new StepCompleted($step, $subject)));

        $finishesFlow = $key === null || $announced->contains(fn (Step $step): bool => $step->isRequired());

        if ($finishesFlow && $this->isCompleted()) {
            Event::dispatch(new FlowCompleted($this, $subject));
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
