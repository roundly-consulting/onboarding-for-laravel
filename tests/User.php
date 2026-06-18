<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Traits\GetsOnboarded;

class User extends Model
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
}
