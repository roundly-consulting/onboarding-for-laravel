<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding;

use Illuminate\Support\ServiceProvider;

final class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class);
    }
}
