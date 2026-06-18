<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use RoundlyConsulting\Onboarding\Traits\GetsOnboarded;

class User extends Authenticatable
{
    use GetsOnboarded {
        defaultOnboardingKey as traitDefaultOnboardingKey;
    }

    /**
     * @var array<string>|bool
     */
    protected $guarded = [];

    public function defaultOnboardingKey(): string
    {
        if (isset($this->default_onboarding)) {
            return $this->default_onboarding;
        }

        return $this->traitDefaultOnboardingKey();
    }

    public function hasSubscription(): bool
    {
        return (bool) ($this->subscribed ?? false);
    }

    public function greet(string $name): string
    {
        return "Hello {$name}";
    }
}
