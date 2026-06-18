<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Support\Collection;

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
            throw new \InvalidArgumentException('The flow key must be a string.');
        }

        $this->flows[$key] = $this->resolveFlow($flow);

        return $this;
    }

    public function find(string $key, ?Flow $default = null): ?Flow
    {
        return $this->all()->get($key, $default);
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
            return new $flow;
        }

        return $flow;
    }
}
