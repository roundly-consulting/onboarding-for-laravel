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

    $default = new Flow(title: 'Fallback');

    $flow = $user->onboarding(default: $default);

    // A copy bound to the user, never the caller's instance (see the shared-default regression).
    expect($flow)
        ->toBeInstanceOf(Flow::class)
        ->not->toBe($default)
        ->and($flow?->title)->toBe('Fallback')
        ->and($flow?->for)->toBe($user)
        ->and($default->for)->toBeNull();
});

it('returns default flow from registry when no name is specified', function () {
    Onboarding::register('default', Flow::make('Default'));

    $user = new User;

    expect($user->onboarding())
        ->toBeInstanceOf(Flow::class)
        ->title->toBe('Default');
});

it('returns default flow from registry when no name is specified - using different default flow key', function () {
    Onboarding::register('different_one', Flow::make('Different'));

    $user = new User(['default_onboarding' => 'different_one']);

    expect($user->onboarding())
        ->toBeInstanceOf(Flow::class)
        ->title->toBe('Different');
});

it('returns specific flow from registry by key', function () {
    Onboarding::register('default', Flow::make('Default'))
        ->register('custom', Flow::make('Custom'));

    $user = new User;

    expect($user->onboarding('custom'))
        ->toBeInstanceOf(Flow::class)
        ->title->toBe('Custom');
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

it('resolves the right flow through the resolver and binds the model', function () {
    Onboarding::register('default', new Flow)
        ->register('admin', $admin = new Flow([Step::make('One')], 'Admin'));

    $captured = null;
    Onboarding::resolveUsing(function ($subject) use (&$captured) {
        $captured = $subject;

        return 'admin';
    });

    $user = new User;

    $resolved = $user->resolvedOnboarding();

    expect($resolved?->title)->toBe('Admin')
        ->and($resolved?->for)->toBe($user)
        ->and($admin->for)->toBeNull()
        ->and($captured)->toBe($user);
});

it('consults the resolver in every no-key reader', function () {
    Onboarding::register('default', new Flow([Step::make('Profile')->completeIf(fn () => false)], 'Default'))
        ->register('admin', new Flow([Step::make('Invite')->completeIf(fn () => true)], 'Admin'));

    Onboarding::resolveUsing(fn ($subject) => $subject?->name === 'boss' ? 'admin' : null);

    $boss = new User(['name' => 'boss']);

    expect($boss->onboarding()?->title)->toBe('Admin')
        ->and($boss->onboarding()?->for)->toBe($boss)
        ->and($boss->hasCompletedOnboarding())->toBeTrue()
        ->and($boss->isOnboarding())->toBeFalse()
        ->and($boss->onboardingProgress())->toBe(100.0)
        ->and($boss->nextOnboardingStep())->toBeNull();
});

it('falls back to the model default key when the resolver has no answer', function () {
    Onboarding::register('default', Flow::make('Default'))
        ->register('different_one', Flow::make('Different'));

    Onboarding::resolveUsing(fn () => 'no-such-flow');

    expect((new User(['default_onboarding' => 'different_one']))->onboarding()?->title)->toBe('Different')
        ->and((new User)->onboarding()?->title)->toBe('Default');
});

it('prefers an explicit key over the resolver', function () {
    Onboarding::register('default', Flow::make('Default'))
        ->register('admin', Flow::make('Admin'));

    Onboarding::resolveUsing(fn () => 'admin');

    expect((new User)->onboarding('default')?->title)->toBe('Default')
        ->and((new User)->hasCompletedOnboarding('default'))->toBeTrue();
});

it('regression: a shared default flow is copied per subject, not rebound', function () {
    $default = new Flow([Step::make('Verify')->completeWhenTrue('verified')]);

    $a = (new User(['verified' => true]))->onboarding('missing', $default);

    expect($a?->isCompleted())->toBeTrue();

    $b = (new User(['verified' => false]))->onboarding('missing', $default);

    expect($a)->not->toBe($b)
        ->and($a?->isCompleted())->toBeTrue()
        ->and($b?->isCompleted())->toBeFalse()
        ->and($default->for)->toBeNull();
});
