<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Onboarding\Commands\OnboardingListCommand;
use RoundlyConsulting\Onboarding\Http\Middleware\RequireOnboarding;

final class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                OnboardingListCommand::class,
            ]);
        }

        if ($this->app->bound(Router::class)) {
            $this->app->make(Router::class)
                ->aliasMiddleware('onboarding', RequireOnboarding::class);
        }
    }
}
