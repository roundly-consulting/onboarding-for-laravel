<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Closure;
use Illuminate\Database\Eloquent\Model;

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
    ): static {
        return new static($title, $cta, $action, $complete, $exclude, $meta, $for);
    }

    public function for(?Model $model): self
    {
        $this->for = $model;

        return $this;
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function cta(?string $cta): self
    {
        $this->cta = $cta;

        return $this;
    }

    public function action(?string $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function completeIf(?Closure $callback): self
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

    public function excludeIf(?Closure $callback): self
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'cta' => $this->cta,
            'action' => $this->action,
            'is_completed' => $this->isCompleted(),
            'meta' => $this->meta,
        ];
    }
}
