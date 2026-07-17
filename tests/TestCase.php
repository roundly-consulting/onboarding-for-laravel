<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Onboarding\OnboardingServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider onboarding hard-requires, in registration order. A host auto-discovers
     * these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [OnboardingServiceProvider::class];
    }

    // No migrationSources(): the package is entirely in-memory and ships no migrations.
    // No configBeforeBoot(): it ships no config file either.
}
