<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\User;

it('returns null when no onboarding is defined in registry', function () {
    $user = new User;

    expect($user->onboarding())->toBeNull();
});

it('returns default flow when no onboarding is defined but we pass default flow to method', function () {
    $user = new User;

    $default = new Flow;

    expect($user->onboarding(default: $default))
        ->toBeInstanceOf(Flow::class)
        ->toBe($default);
});

it('returns default flow from registry when no name is specified', function () {
    Onboarding::register('default', $default = new Flow);

    $user = new User;

    expect($user->onboarding())
        ->toBeInstanceOf(Flow::class)
        ->toBe($default);
});

it('returns default flow from registry when no name is specified - using different default flow key', function () {
    Onboarding::register('different_one', $default = new Flow);

    $user = new User(['default_onboarding' => 'different_one']);

    expect($user->onboarding())
        ->toBeInstanceOf(Flow::class)
        ->toBe($default);
});

it('returns specific flow from registry by key', function () {
    Onboarding::register('default', $default = new Flow)
        ->register('custom', $custom = new Flow);

    $user = new User;

    expect($user->onboarding('custom'))
        ->toBeInstanceOf(Flow::class)
        ->toBe($custom)
        ->not->toBe($default);
});

it('uses current model as entity to filter steps', function () {
    $flow = new Flow([
        Step::make('First')->excludeIf(fn (?Model $model) => $model->testMe()),
        Step::make('Second'),
    ]);

    Onboarding::register('default', $flow);

    $user = $this->partialMock(User::class);
    $user->shouldReceive('testMe')->once()->andReturn(true);

    expect($user->onboarding())
        ->toBeInstanceOf(Flow::class)
        ->steps()->first()->title->toBe('Second');
});

it('has default onboarding key', function () {
    $user = new User;

    expect($user->defaultOnboardingKey())->toBe('default');
});

it('reports safe defaults when no flow is registered', function () {
    $user = new User;

    expect($user)
        ->hasCompletedOnboarding()->toBeFalse()
        ->isOnboarding()->toBeFalse()
        ->onboardingProgress()->toBe(0.0)
        ->nextOnboardingStep()->toBeNull();
});

it('reports progress for an in-progress flow', function () {
    Onboarding::register('default', new Flow([
        Step::make('One')->completeIf(fn () => true),
        Step::make('Two')->completeIf(fn () => false),
    ]));

    $user = new User;

    expect($user)
        ->hasCompletedOnboarding()->toBeFalse()
        ->isOnboarding()->toBeTrue()
        ->onboardingProgress()->toBe(50.0)
        ->nextOnboardingStep()->title->toBe('Two');
});

it('reports a completed flow through the model readers', function () {
    Onboarding::register('default', new Flow([
        Step::make('One')->completeIf(fn () => true),
    ]));

    $user = new User;

    expect($user)
        ->hasCompletedOnboarding()->toBeTrue()
        ->isOnboarding()->toBeFalse()
        ->onboardingProgress()->toBe(100.0)
        ->nextOnboardingStep()->toBeNull();
});

it('passes the bound model into completion closures via the readers', function () {
    $fakeModel = null;

    Onboarding::register('default', new Flow([
        Step::make('One')->completeIf(function (?Model $model) use (&$fakeModel) {
            $fakeModel = $model;

            return true;
        }),
    ]));

    $user = new User;
    $user->hasCompletedOnboarding();

    expect($fakeModel)->toBe($user);
});

it('reads a specific flow by key from the model', function () {
    Onboarding::register('admin', new Flow([
        Step::make('One')->completeIf(fn () => false),
    ]));

    $user = new User;

    expect($user)
        ->hasCompletedOnboarding('admin')->toBeFalse()
        ->onboardingProgress('admin')->toBe(0.0)
        ->nextOnboardingStep('admin')->title->toBe('One');
});
