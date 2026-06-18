<?php

declare(strict_types=1);

use RoundlyConsulting\Onboarding\Exceptions\InvalidFlowException;
use RoundlyConsulting\Onboarding\Exceptions\OnboardingException;

it('builds a key-must-be-string exception', function () {
    $exception = InvalidFlowException::keyMustBeString();

    expect($exception)
        ->toBeInstanceOf(OnboardingException::class)
        ->toBeInstanceOf(InvalidArgumentException::class)
        ->getMessage()->toBe('The flow key must be a string.');
});

it('builds a not-a-flow-class exception naming the class', function () {
    $exception = InvalidFlowException::notAFlowClass('App\\Nope');

    expect($exception->getMessage())->toContain('App\\Nope')
        ->and($exception->getMessage())->toContain('Flow');
});
