<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
    public function of(array $steps = []): self
    {
        $this->steps = $steps;

        return $this;
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function for(?Model $model): self
    {
        $this->for = $model;

        return $this;
    }

    /**
     * @return Collection<int, Step>
     */
    public function all(): Collection
    {
        return collect($this->steps)->map(fn (Step $step): Step => $step->for($this->for));
    }

    /**
     * @return Collection<int, Step>
     */
    public function steps(): Collection
    {
        return $this->all()->filter->isNotExcluded()->values();
    }

    public function add(string $title): Step
    {
        $step = new Step($title);

        $this->addStep($step);

        return $step;
    }

    public function addStep(Step ...$step): self
    {
        $this->steps = array_values(array_merge($this->steps, $step));

        return $this;
    }

    public function isInProgress(): bool
    {
        return ! $this->isCompleted();
    }

    public function isCompleted(): bool
    {
        return $this->steps()->every->isCompleted();
    }

    public function nextStep(): ?Step
    {
        return $this->steps()->first->isNotCompleted();
    }

    public function percentageCompleted(): float
    {
        $percentage = $this->steps()
            ->percentage(fn (Step $step): bool => $step->isCompleted());

        return $percentage ?: 100.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'percentage' => $this->percentageCompleted(),
            'next_step' => $this->nextStep()?->toArray(),
            'steps' => $this->steps()->map->toArray()->toArray(),
        ];
    }
}
