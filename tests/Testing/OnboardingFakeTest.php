<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\OnboardingManager;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Testing\InMemoryOnboardingStore;
use RoundlyConsulting\Onboarding\Testing\OnboardingFake;
use RoundlyConsulting\Onboarding\Tests\ArrayOnboardingStore;
use RoundlyConsulting\Onboarding\Tests\AuthIdentity;
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

it('keeps the flows and resolver registered before faking', function () {
    Onboarding::register('default', Flow::make('Default'))->register('admin', Flow::make('Admin'));
    Onboarding::resolveUsing(fn (): string => 'admin');

    Onboarding::fake();

    expect(Onboarding::has('default'))->toBeTrue()
        ->and(Onboarding::for(new User)?->title)->toBe('Admin');
});

it('hands the fake to injected managers and model traits', function () {
    $fake = Onboarding::fake();

    expect(app(OnboardingManager::class))->toBe($fake);
});

it('seeds completed steps the store reports and record() skips', function () {
    $fake = Onboarding::fake();
    $user = new User;

    Onboarding::register('default', [Step::make('Photo')->key('photo')->completeIf(fn () => true)]);
    $fake->seedCompleted($user, 'photo');

    expect($user->onboarding()?->step('photo')?->completedAt())->not->toBeNull()
        ->and((new User)->onboarding()?->step('photo')?->completedAt())->toBeNull();

    $user->onboarding()?->record();

    $fake->assertStepNotCompleted('photo');
});

it('seeds dismissed steps that drop out of the flow', function () {
    $fake = Onboarding::fake();
    $user = new User;

    Onboarding::register('default', [Step::make('Bio')->key('bio')->optional()->dismissible()->completeIf(fn () => false)]);
    $fake->seedDismissed($user, 'bio');

    expect($user->onboarding()?->hasStep('bio'))->toBeFalse()
        ->and((new User)->onboarding()?->hasStep('bio'))->toBeTrue();
});

it('records a dismissal through the model trait, the facade and a flow', function () {
    $fake = Onboarding::fake();
    Onboarding::register('default', [
        Step::make('Bio')->key('bio')->optional()->dismissible()->completeIf(fn () => false),
        Step::make('Tour')->key('tour')->optional()->dismissible()->completeIf(fn () => false),
        Step::make('Name')->key('name')->completeIf(fn () => false),
    ]);

    $user = new User;
    $user->id = 1;
    $other = new User;

    $user->dismissOnboardingStep('bio');
    Onboarding::for($other)?->dismiss('tour');
    $user->dismissOnboardingStep('name'); // not dismissible: not recorded

    $fake->assertDismissed('bio')
        ->assertDismissed('bio', $user)
        ->assertDismissed('tour', $other)
        ->assertNotDismissed('name')
        ->assertNotDismissed('tour', $user);

    expect($user->onboarding()?->hasStep('bio'))->toBeFalse();
});

it('ignores useStore under the fake', function () {
    $fake = Onboarding::fake();

    Onboarding::useStore(new ArrayOnboardingStore);

    expect(Onboarding::store())->toBeInstanceOf(InMemoryOnboardingStore::class)
        ->and($fake->store())->toBe(Onboarding::store());
});

it('asserts nothing was dismissed', function () {
    Onboarding::fake()->assertNothingDismissed();
});

it('fails assertNothingDismissed after a dismissal', function () {
    $fake = Onboarding::fake();
    Onboarding::register('default', [Step::make('Bio')->key('bio')->optional()->dismissible()]);

    (new User)->dismissOnboardingStep('bio');

    expect(fn () => $fake->assertNothingDismissed())->toThrow(ExpectationFailedException::class);
});

it('fails assertDismissed when the step was not dismissed', function () {
    expect(fn () => Onboarding::fake()->assertDismissed('bio'))->toThrow(ExpectationFailedException::class);
});

it('fails assertDismissed for another subject', function () {
    $fake = Onboarding::fake();
    Onboarding::register('default', [Step::make('Bio')->key('bio')->optional()->dismissible()]);

    (new User)->dismissOnboardingStep('bio');

    expect(fn () => $fake->assertDismissed('bio', new User))->toThrow(ExpectationFailedException::class);
});

it('fails assertNotDismissed when the step was dismissed', function () {
    $fake = Onboarding::fake();
    Onboarding::register('default', [Step::make('Bio')->key('bio')->optional()->dismissible()]);

    (new User)->dismissOnboardingStep('bio');

    expect(fn () => $fake->assertNotDismissed('bio'))->toThrow(ExpectationFailedException::class);
});

it('fails assertStepNotCompleted when the step was recorded', function () {
    $fake = Onboarding::fake();

    (new Flow([Step::make('Photo')->key('photo')->completeIf(fn () => true)]))->record();

    expect(fn () => $fake->assertStepNotCompleted('photo'))->toThrow(ExpectationFailedException::class);
});

it('fails assertFlowCompleted when no flow completed', function () {
    $fake = Onboarding::fake();

    (new Flow([Step::make('Photo')->completeIf(fn () => false)]))->record();

    expect(fn () => $fake->assertFlowCompleted())->toThrow(ExpectationFailedException::class);
});

it('keeps in-memory state per subject', function () {
    $store = new InMemoryOnboardingStore;
    $saved = new User;
    $saved->id = 7;
    $identity = new AuthIdentity;

    $store->markDismissed($saved, 'a');
    $store->markDismissed($identity, 'b');
    $store->markDismissed(null, 'c');
    $unsaved = new User;
    $store->markCompleted($unsaved, 'd');

    $sameRow = new User;
    $sameRow->id = 7;

    expect($store->isDismissed($sameRow, 'a'))->toBeTrue()
        ->and($store->isDismissed($identity, 'b'))->toBeTrue()
        ->and($store->isDismissed(null, 'c'))->toBeTrue()
        ->and($store->isDismissed(null, 'a'))->toBeFalse()
        ->and($store->completedAt($unsaved, 'd'))->not->toBeNull()
        ->and($store->completedAt(new User, 'd'))->toBeNull();
});
