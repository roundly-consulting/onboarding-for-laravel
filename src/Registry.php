<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Support\Collection;
use RoundlyConsulting\Onboarding\Exceptions\InvalidFlowException;

class Registry
{
    public static string $default = 'default';

    /** @var array<string, Flow> */
    protected array $flows = [];

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

    public function find(string $key, ?Flow $default = null): ?Flow
    {
        return $this->all()->get($key, $default);
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
