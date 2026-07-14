<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Routing\Router;
use RoundlyConsulting\Onboarding\Commands\OnboardingListCommand;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use RoundlyConsulting\Onboarding\Http\Middleware\RequireOnboarding;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

/**
 * The package is entirely in-memory: it ships no config file, no migrations, no
 * views and no translations, so the builder declares only the console command
 * and the `about` section. The `Onboarding` facade alias is composer-declared
 * (`extra.laravel.aliases`), so no alias is registered here.
 */
final class OnboardingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('onboarding')
            ->hasCommands([
                OnboardingListCommand::class,
            ])
            ->contributesToAbout(fn (): array => $this->aboutOnboarding());
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(Registry::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The toolkit owns no middleware-alias helper, so this stays bespoke.
        if ($this->app->bound(Router::class)) {
            $this->app->make(Router::class)
                ->aliasMiddleware('onboarding', RequireOnboarding::class);
        }
    }

    /**
     * Report the shape of the registered onboarding, never its content: a flow
     * key names a host's own business process ("kyc", "series-c-payout") and a
     * step carries the host's copy, routes and URLs. Counts and presence only.
     *
     * @return array<string, string>
     */
    private function aboutOnboarding(): array
    {
        $registry = $this->app->make(Registry::class);
        $flows = $registry->all();

        // Count the declared steps, never call count()/percentageCompleted():
        // those evaluate each step's completion closure against the current
        // subject, which `about` has no business doing.
        $steps = $flows->reduce(
            fn (int $carry, Flow $flow): int => $carry + count($flow->steps),
            0,
        );

        return [
            'Flows' => $flows->isEmpty() ? 'NONE' : $flows->count().' registered',
            'Steps' => $steps.' declared',
            'Default flow' => $registry->has(Registry::$default) ? 'REGISTERED' : 'NONE',
            'Persistence store' => $this->app->bound(OnboardingStore::class)
                ? 'BOUND'
                : 'NONE (stateless)',
            'Middleware alias' => 'onboarding',
        ];
    }
}
