<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Facades;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\OnboardingManager;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Testing\OnboardingFake;

/**
 * @method static OnboardingManager register(Flow|array<int, Step>|class-string<Flow> $key, Flow|array<int, Step>|class-string<Flow>|null $flow = null)
 * @method static Flow flow(string $key)
 * @method static Flow|null find(string $key, ?Flow $default = null)
 * @method static Flow|null for(Authenticatable|Model|null $subject, ?string $key = null)
 * @method static bool has(string $key)
 * @method static OnboardingManager forget(string $key)
 * @method static OnboardingManager flush()
 * @method static Collection<string, Flow> all()
 * @method static OnboardingManager resolveUsing(Closure $resolver)
 * @method static Flow|null resolveFor(Authenticatable|Model|null $subject, ?string $fallback = null)
 * @method static OnboardingManager useStore(OnboardingStore|string $store)
 * @method static OnboardingStore|null store()
 * @method static bool hasStore()
 * @method static OnboardingFake seedCompleted(Authenticatable|Model|null $subject, string ...$steps)
 * @method static OnboardingFake seedDismissed(Authenticatable|Model|null $subject, string ...$steps)
 * @method static OnboardingFake assertStepCompleted(string $key, ?callable $callback = null)
 * @method static OnboardingFake assertStepNotCompleted(string $key)
 * @method static OnboardingFake assertFlowCompleted(?string $flowKey = null)
 * @method static OnboardingFake assertNothingRecorded()
 * @method static OnboardingFake assertDismissed(string $step, Authenticatable|Model|null $subject = null)
 * @method static OnboardingFake assertNotDismissed(string $step, Authenticatable|Model|null $subject = null)
 * @method static OnboardingFake assertNothingDismissed()
 *
 * @see OnboardingManager
 * @see OnboardingFake
 */
final class Onboarding extends Facade
{
    /**
     * Swap the manager for a recording {@see OnboardingFake} — behind the facade and in
     * the container, so injected managers and model traits get it too — and return it.
     * The fake keeps every registered flow, uses a seedable in-memory store, records
     * dismissals and captures the package's events.
     */
    public static function fake(): OnboardingFake
    {
        $container = app();

        $fake = new OnboardingFake($container, $container->make(OnboardingManager::class));

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return OnboardingManager::class;
    }
}
