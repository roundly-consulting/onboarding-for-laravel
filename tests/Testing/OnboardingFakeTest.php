<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Testing\OnboardingFake;
use RoundlyConsulting\Onboarding\Tests\User;

it('returns a fake and asserts a recorded step', function () {
    $fake = Onboarding::fake();

    expect($fake)->toBeInstanceOf(OnboardingFake::class);

    (new Flow([Step::make('Photo')->key('photo')->completeIf(fn () => true)]))->record();

    $fake->assertStepCompleted('photo');
});

it('fails to assert a step that was not recorded', function () {
    $fake = Onboarding::fake();

    (new Flow([Step::make('Photo')->key('photo')->completeIf(fn () => true)]))->record();

    expect(fn () => $fake->assertStepCompleted('missing'))
        ->toThrow(ExpectationFailedException::class);
});

it('asserts a step was not completed', function () {
    $fake = Onboarding::fake();

    (new Flow([Step::make('Photo')->key('photo')->completeIf(fn () => false)]))->record();

    $fake->assertStepNotCompleted('photo');
});

it('asserts the flow completed', function () {
    $fake = Onboarding::fake();

    (new Flow([Step::make('One')->completeIf(fn () => true)]))->title('Setup')->record();

    $fake->assertFlowCompleted()->assertFlowCompleted('Setup');
});

it('asserts nothing was recorded', function () {
    Onboarding::fake()->assertNothingRecorded();
});

it('fails assertNothingRecorded after a record', function () {
    $fake = Onboarding::fake();

    (new Flow([Step::make('One')->completeIf(fn () => true)]))->record();

    expect(fn () => $fake->assertNothingRecorded())
        ->toThrow(ExpectationFailedException::class);
});

it('passes the recorded event to the assertion callback', function () {
    $fake = Onboarding::fake();
    $user = new User;

    (new Flow([Step::make('Photo')->key('photo')->completeIf(fn () => true)]))
        ->for($user)
        ->record();

    $fake->assertStepCompleted('photo', fn ($event) => $event->for === $user);
});
