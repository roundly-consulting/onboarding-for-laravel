<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use RoundlyConsulting\Onboarding\Exceptions\InvalidFlowException;
use RoundlyConsulting\Onboarding\Exceptions\InvalidStoreException;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Testing\OnboardingFake;

/**
 * The root of the {@see Onboarding} facade: the flow registry, the resolver that picks a
 * subject's flow, and the persistence store. Inject it to use the same API without the
 * facade.
 *
 * Not final: {@see OnboardingFake} extends it, so code that injects the manager receives
 * the fake under `Onboarding::fake()`.
 */
class OnboardingManager
{
    use Macroable;

    public static string $default = 'default';

    /** @var array<string, Flow> */
    protected array $flows = [];

    /** @var (Closure(Authenticatable|Model|null): ?string)|null */
    protected ?Closure $resolver = null;

    /** @var OnboardingStore|class-string<OnboardingStore>|null */
    protected OnboardingStore|string|null $store = null;

    public function __construct(protected readonly Container $container) {}

    /**
     * @param  Flow|array<int, Step>|class-string<Flow>  $key
     * @param  Flow|array<int, Step>|class-string<Flow>|null  $flow
     */
    public function register(Flow|array|string $key, Flow|array|string|null $flow = null): self
    {
        if (is_null($flow)) {
            $this->flows[self::$default] = $this->resolveFlow($key);

            return $this;
        }

        if (! is_string($key)) {
            throw InvalidFlowException::keyMustBeString();
        }

        $this->flows[$key] = $this->resolveFlow($flow);

        return $this;
    }

    /**
     * Create, register, and return an empty named flow for fluent building.
     */
    public function flow(string $key): Flow
    {
        return $this->flows[$key] = new Flow;
    }

    /**
     * The registered flow definition itself — unbound, shared. Use {@see for()} to read a
     * flow for a subject.
     */
    public function find(string $key, ?Flow $default = null): ?Flow
    {
        return $this->all()->get($key, $default);
    }

    /**
     * The flow for a subject: the given key's flow, or — without a key — the resolver's
     * choice, falling back to the default flow. Null when there is no such flow.
     *
     * Each call returns its own copy bound to the subject, so flows resolved for two
     * subjects never share state.
     */
    public function for(Authenticatable|Model|null $subject, ?string $key = null): ?Flow
    {
        if ($key === null) {
            return $this->resolveFor($subject);
        }

        return $this->bind($this->find($key), $subject);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->flows);
    }

    public function forget(string $key): self
    {
        unset($this->flows[$key]);

        return $this;
    }

    public function flush(): self
    {
        $this->flows = [];
        $this->resolver = null;

        return $this;
    }

    /**
     * @return Collection<string, Flow>
     */
    public function all(): Collection
    {
        return collect($this->flows);
    }

    /**
     * Register a callback that maps a subject to the flow key it should use.
     *
     * @param  Closure(Authenticatable|Model|null): ?string  $resolver
     */
    public function resolveUsing(Closure $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Resolve the flow that applies to a subject: the resolver's choice when it
     * returns a known key, otherwise the `$fallback` key's flow (the default flow
     * when none is given). Null only when neither exists. The flow is a copy bound
     * to the subject.
     */
    public function resolveFor(Authenticatable|Model|null $subject, ?string $fallback = null): ?Flow
    {
        if ($this->resolver !== null) {
            $key = ($this->resolver)($subject);

            if (is_string($key) && $this->has($key)) {
                return $this->bind($this->find($key), $subject);
            }
        }

        return $this->bind($this->find($fallback ?? self::$default), $subject);
    }

    /**
     * Persist dismissals and read completions through this store: an instance, or a class
     * resolved through the container on first use. Without one, the manager falls back to
     * an `OnboardingStore` bound in the container, and is stateless when neither exists.
     *
     * @param  OnboardingStore|string  $store  an instance or the class-string of an OnboardingStore
     *
     * @throws InvalidStoreException for a class that does not implement OnboardingStore
     */
    public function useStore(OnboardingStore|string $store): self
    {
        if (is_string($store) && ! is_a($store, OnboardingStore::class, true)) {
            throw InvalidStoreException::notAStore($store);
        }

        $this->store = $store;

        return $this;
    }

    /**
     * The store in use: the one given to {@see useStore()}, else the container's
     * `OnboardingStore` binding, else null (stateless).
     */
    public function store(): ?OnboardingStore
    {
        if (is_string($this->store)) {
            $store = $this->container->make($this->store);

            if (! $store instanceof OnboardingStore) {
                throw InvalidStoreException::notAStore($this->store);
            }

            $this->store = $store;
        }

        if ($this->store !== null) {
            return $this->store;
        }

        return $this->container->bound(OnboardingStore::class)
            ? $this->container->make(OnboardingStore::class)
            : null;
    }

    /**
     * Whether a store is configured, without resolving it.
     */
    public function hasStore(): bool
    {
        return $this->store !== null || $this->container->bound(OnboardingStore::class);
    }

    /**
     * Dismiss a step of a flow for the flow's subject — the path every dismissal takes
     * ({@see Flow::dismiss()}, `$user->dismissOnboardingStep()`), so the fake sees them all.
     * A no-op for an unknown, required or non-dismissible step, and without a store.
     *
     * @internal
     */
    public function dismissStep(Flow $flow, string $step): void
    {
        $target = $flow->step($step);

        // Only an optional step can be dismissed away (Flow hides nothing else).
        if ($target === null || ! $target->isOptional() || ! $target->isDismissible()) {
            return;
        }

        $this->store()?->markDismissed($flow->subject(), $target->stepKey());
    }

    /**
     * Take over another manager's flows and resolver (the fake keeps the real registrations).
     */
    protected function adopt(self $manager): void
    {
        $this->flows = $manager->flows;

        if ($manager->resolver !== null) {
            $this->resolveUsing($manager->resolver);
        }
    }

    /**
     * A copy of a registered flow bound to a subject, so the shared definition is never
     * rebound under another caller.
     */
    protected function bind(?Flow $flow, Authenticatable|Model|null $subject): ?Flow
    {
        return $flow === null ? null : (clone $flow)->for($subject);
    }

    /**
     * @param  Flow|array<int, Step>|class-string<Flow>  $flow
     */
    protected function resolveFlow(Flow|array|string $flow): Flow
    {
        if (is_array($flow)) {
            return new Flow($flow);
        }

        if (is_string($flow)) {
            if ($flow !== Flow::class && ! is_subclass_of($flow, Flow::class)) {
                throw InvalidFlowException::notAFlowClass($flow);
            }

            return new $flow;
        }

        return $flow;
    }
}
