<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\Onboarding\DataTransferObjects\StepData;

/** @phpstan-consistent-constructor */
class Step
{
    /**
     * @param  Closure|null  $complete  receives the bound model (or null) and returns whether the step is complete
     * @param  Closure|null  $exclude  receives the bound model (or null) and returns whether the step is excluded
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ?string $title = null,
        public ?string $cta = null,
        public ?string $action = null,
        public ?Closure $complete = null,
        public ?Closure $exclude = null,
        public array $meta = [],
        public ?Model $for = null,
        public ?string $key = null,
        public bool $optional = false,
        public ?int $order = null,
    ) {}

    /**
     * @param  Closure|null  $complete  receives the bound model (or null) and returns whether the step is complete
     * @param  Closure|null  $exclude  receives the bound model (or null) and returns whether the step is excluded
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        ?string $title = null,
        ?string $cta = null,
        ?string $action = null,
        ?Closure $complete = null,
        ?Closure $exclude = null,
        array $meta = [],
        ?Model $for = null,
        ?string $key = null,
        bool $optional = false,
        ?int $order = null,
    ): static {
        return new static($title, $cta, $action, $complete, $exclude, $meta, $for, $key, $optional, $order);
    }

    public function for(?Model $model): static
    {
        $this->for = $model;

        return $this;
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function cta(?string $cta): static
    {
        $this->cta = $cta;

        return $this;
    }

    public function action(?string $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function key(?string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function order(?int $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function optional(bool $optional = true): static
    {
        $this->optional = $optional;

        return $this;
    }

    public function required(): static
    {
        $this->optional = false;

        return $this;
    }

    /**
     * The stable identifier for this step, falling back to a slug of its title.
     */
    public function stepKey(): string
    {
        if (! is_null($this->key)) {
            return $this->key;
        }

        return Str::slug((string) $this->title);
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function isRequired(): bool
    {
        return ! $this->optional;
    }

    public function completeIf(?Closure $callback): static
    {
        $this->complete = $callback;

        return $this;
    }

    public function isCompleted(): bool
    {
        if (is_null($this->complete)) {
            return true;
        }

        return (bool) call_user_func($this->complete, $this->for);
    }

    public function isNotCompleted(): bool
    {
        return ! $this->isCompleted();
    }

    public function excludeIf(?Closure $callback): static
    {
        $this->exclude = $callback;

        return $this;
    }

    public function isExcluded(): bool
    {
        if (is_null($this->exclude)) {
            return false;
        }

        return (bool) call_user_func($this->exclude, $this->for);
    }

    public function isNotExcluded(): bool
    {
        return ! $this->isExcluded();
    }

    public function toData(): StepData
    {
        return new StepData(
            key: $this->stepKey(),
            title: $this->title,
            cta: $this->cta,
            action: $this->action,
            isCompleted: $this->isCompleted(),
            isOptional: $this->optional,
            meta: $this->meta,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->toData()->toArray();
    }
}
