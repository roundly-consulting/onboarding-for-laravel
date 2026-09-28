<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Exceptions;

use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;

final class InvalidStoreException extends OnboardingException
{
    public static function notAStore(string $class): self
    {
        return new self("[{$class}] does not implement ".OnboardingStore::class.'.');
    }
}
