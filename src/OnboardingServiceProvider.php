<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Onboarding\Commands\OnboardingListCommand;

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
    }
}
