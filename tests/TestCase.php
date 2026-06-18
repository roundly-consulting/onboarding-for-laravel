<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Onboarding\OnboardingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            OnboardingServiceProvider::class,
        ];
    }
}
