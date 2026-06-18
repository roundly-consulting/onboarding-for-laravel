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
