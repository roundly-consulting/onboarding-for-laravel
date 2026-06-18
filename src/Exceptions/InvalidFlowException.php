<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Exceptions;

use RoundlyConsulting\Onboarding\Flow;

final class InvalidFlowException extends OnboardingException
{
    public static function keyMustBeString(): self
    {
        return new self('The flow key must be a string.');
    }

    public static function notAFlowClass(string $class): self
    {
        return new self("[{$class}] is not a ".Flow::class.' class.');
    }
}
