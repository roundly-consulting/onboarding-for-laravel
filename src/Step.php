<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use RoundlyConsulting\Onboarding\DataTransferObjects\StepData;

/** @phpstan-consistent-constructor */
class Step
{
    use Macroable;

    /**
     * @param  Closure|null  $complete  receives the bound subject (or null) and returns whether the step is complete
     * @param  Closure|null  $exclude  receives the bound subject (or null) and returns whether the step is excluded
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ?string $title = null,
        public ?string $cta = null,
        public ?string $action = null,
        public ?Closure $complete = null,
        public ?Closure $exclude = null,
        public array $meta = [],
        public Authenticatable|Model|null $for = null,
        public ?string $key = null,
        public bool $optional = false,
        public ?int $order = null,
        public ?string $group = null,
        public ?string $route = null,
        public ?string $url = null,
        public ?bool $translatable = null,
        public bool $dismissible = false,
    ) {}

    /**
     * @param  Closure|null  $complete  receives the bound subject (or null) and returns whether the step is complete
     * @param  Closure|null  $exclude  receives the bound subject (or null) and returns whether the step is excluded
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        ?string $title = null,
        ?string $cta = null,
        ?string $action = null,
        ?Closure $complete = null,
        ?Closure $exclude = null,
        array $meta = [],
        Authenticatable|Model|null $for = null,
        ?string $key = null,
        bool $optional = false,
        ?int $order = null,
        ?string $group = null,
        ?string $route = null,
        ?string $url = null,
        ?bool $translatable = null,
        bool $dismissible = false,
    ): static {
        return new static($title, $cta, $action, $complete, $exclude, $meta, $for, $key, $optional, $order, $group, $route, $url, $translatable, $dismissible);
    }

    public function for(Authenticatable|Model|null $subject): static
    {
        $this->for = $subject;

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
     * Set the named route the middleware redirects to for this step.
     */
    public function route(?string $route): static
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Set the absolute URL or path the middleware redirects to for this step.
     */
    public function url(?string $url): static
    {
        $this->url = $url;

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

    public function group(?string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function groupName(): ?string
    {
        return $this->group;
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
     * Force translation resolution of title/cta (true), force raw strings
     * (false), or let auto-detection decide (null, the default).
     */
    public function translatable(?bool $translatable = true): static
    {
        $this->translatable = $translatable;

        return $this;
    }

    /**
     * Mark this step as dismissible. Only optional steps can be dismissed away,
     * and only when a host has bound an OnboardingStore.
     */
    public function dismissible(bool $dismissible = true): static
    {
        $this->dismissible = $dismissible;

        return $this;
    }

    public function isDismissible(): bool
    {
        return $this->dismissible;
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

    /**
     * Alias of completeIf().
     */
    public function completeWhen(?Closure $callback): static
    {
        return $this->completeIf($callback);
    }

    /**
     * Complete when the subject's attribute (dot paths supported) is filled.
     */
    public function completeWhenFilled(string $attribute): static
    {
        return $this->completeIf($this->attributePredicate($attribute, 'filled'));
    }

    /**
     * Complete when the subject's attribute or no-arg method is truthy.
     */
    public function completeWhenTrue(string $attributeOrMethod): static
    {
        return $this->completeIf($this->attributePredicate($attributeOrMethod, 'true'));
    }

    /**
     * Complete when the subject's relation resolves to a non-empty value.
     */
    public function completeWhenHas(string $relation): static
    {
        return $this->completeIf($this->attributePredicate($relation, 'has'));
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

    /**
     * Alias of excludeIf().
     */
    public function excludeWhen(?Closure $callback): static
    {
        return $this->excludeIf($callback);
    }

    /**
     * Exclude when the subject's attribute (dot paths supported) is filled.
     */
    public function excludeWhenFilled(string $attribute): static
    {
        return $this->excludeIf($this->attributePredicate($attribute, 'filled'));
    }

    /**
     * Exclude when the subject's attribute or no-arg method is truthy.
     */
    public function excludeWhenTrue(string $attributeOrMethod): static
    {
        return $this->excludeIf($this->attributePredicate($attributeOrMethod, 'true'));
    }

    /**
     * Exclude when the subject's relation resolves to a non-empty value.
     */
    public function excludeWhenHas(string $relation): static
    {
        return $this->excludeIf($this->attributePredicate($relation, 'has'));
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
     * Has the bound subject dismissed this step? Always false without a store.
     */
    public function isDismissed(): bool
    {
        return $this->store()?->isDismissed($this->for, $this->stepKey()) ?? false;
    }

    /**
     * When (if ever) did the bound subject complete this step? Always null
     * without a store.
     */
    public function completedAt(): ?\DateTimeInterface
    {
        return $this->store()?->completedAt($this->for, $this->stepKey());
    }

    /**
     * The title with translation keys resolved per the resolution policy.
     */
    public function resolvedTitle(): ?string
    {
        return $this->resolveCopy($this->title);
    }

    /**
     * The CTA with translation keys resolved per the resolution policy.
     */
    public function resolvedCta(): ?string
    {
        return $this->resolveCopy($this->cta);
    }

    public function toData(): StepData
    {
        return new StepData(
            key: $this->stepKey(),
            title: $this->resolvedTitle(),
            cta: $this->resolvedCta(),
            action: $this->action,
            isCompleted: $this->isCompleted(),
            isOptional: $this->optional,
            meta: $this->meta,
            group: $this->group,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->toData()->toArray();
    }

    /**
     * Build a predicate closure that reads a value off the bound subject.
     */
    private function attributePredicate(string $name, string $mode): Closure
    {
        return function (mixed $subject) use ($name, $mode): bool {
            if ($subject === null) {
                return false;
            }

            $value = $this->resolveSubjectValue($subject, $name, $mode);

            return match ($mode) {
                'filled' => filled($value),
                'true' => (bool) $value,
                'has' => $this->relationHasValue($value),
                default => false,
            };
        };
    }

    private function resolveSubjectValue(mixed $subject, string $name, string $mode): mixed
    {
        if ($mode === 'true' && is_object($subject) && method_exists($subject, $name)) {
            if ((new \ReflectionMethod($subject, $name))->getNumberOfRequiredParameters() === 0) {
                return $subject->{$name}();
            }

            // A method exists but needs arguments: it cannot be resolved as a
            // truthiness check, so treat it as "no value" rather than risk
            // re-invoking it through data_get's accessor fallback.
            return null;
        }

        return data_get($subject, $name);
    }

    private function relationHasValue(mixed $value): bool
    {
        if ($value instanceof Model) {
            return true;
        }

        if ($value instanceof Collection) {
            return $value->isNotEmpty();
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return ! is_null($value);
    }

    private function resolveCopy(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $this->translatorIsAvailable()) {
            return $value;
        }

        if ($this->translatable === false) {
            return $value;
        }

        if ($this->translatable === true) {
            return (string) __($value);
        }

        if ($this->looksLikeTranslationKey($value) && Lang::has($value)) {
            return (string) __($value);
        }

        return $value;
    }

    private function looksLikeTranslationKey(string $value): bool
    {
        return str_contains($value, '.') || str_contains($value, '::');
    }

    private function translatorIsAvailable(): bool
    {
        return Lang::getFacadeApplication() !== null;
    }

    private function store(): ?OnboardingStore
    {
        if (App::getFacadeApplication() === null) {
            return null;
        }

        if (! App::bound(OnboardingStore::class)) {
            return null;
        }

        return App::make(OnboardingStore::class);
    }
}
