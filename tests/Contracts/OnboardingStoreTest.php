<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use RoundlyConsulting\Onboarding\Events\FlowCompleted;
use RoundlyConsulting\Onboarding\Events\StepCompleted;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\ArrayOnboardingStore;

it('is a complete no-op without a bound store', function () {
    $flow = new Flow([
        Step::make('Bio')->key('bio')->optional()->dismissible()->completeIf(fn () => false),
    ]);

    $flow->dismiss('bio');

    expect($flow->steps())->toHaveCount(1)
        ->and($flow->step('bio')?->isDismissed())->toBeFalse();
});

it('hides a dismissed optional step and drops it from progress', function () {
    $store = new ArrayOnboardingStore;
    app()->instance(OnboardingStore::class, $store);

    $flow = new Flow([
        Step::make('Required')->key('required')->completeIf(fn () => true),
        Step::make('Bio')->key('bio')->optional()->dismissible()->completeIf(fn () => false),
    ]);

    expect($flow->steps())->toHaveCount(2)
        ->and($flow->percentageCompleted())->toBe(50.0);

    $flow->dismiss('bio');

    expect($store->dismissed)->toHaveKey('bio')
        ->and($flow->steps())->toHaveCount(1)
        ->and($flow->percentageCompleted())->toBe(100.0);
});

it('never dismisses a required step away even if flagged', function () {
    $store = new ArrayOnboardingStore;
    $store->dismissed['required'] = true;
    app()->instance(OnboardingStore::class, $store);

    $flow = new Flow([
        Step::make('Required')->key('required')->dismissible()->completeIf(fn () => true),
    ]);

    expect($flow->steps())->toHaveCount(1);
});

it('suppresses re-announcing a step already recorded as completed', function () {
    Event::fake();

    $store = new ArrayOnboardingStore;
    $store->completed['a'] = new DateTimeImmutable;
    app()->instance(OnboardingStore::class, $store);

    $flow = new Flow([
        Step::make('A')->key('a')->completeIf(fn () => true),
        Step::make('B')->key('b')->completeIf(fn () => true),
    ]);

    $flow->record();

    Event::assertDispatchedTimes(StepCompleted::class, 1);
    Event::assertDispatched(StepCompleted::class, fn (StepCompleted $e) => $e->step->stepKey() === 'b');
    Event::assertDispatched(FlowCompleted::class);
});

it('returns false from isDismissed when the container is unbound', function () {
    $app = App::getFacadeApplication();
    App::clearResolvedInstances();
    App::setFacadeApplication(null);

    try {
        expect(Step::make('Bio')->key('bio')->isDismissed())->toBeFalse();
    } finally {
        App::setFacadeApplication($app);
    }
});

it('returns dismissable state and completedAt through the bound store', function () {
    $store = new ArrayOnboardingStore;
    $store->dismissed['bio'] = true;
    $store->completed['bio'] = $at = new DateTimeImmutable;
    app()->instance(OnboardingStore::class, $store);

    $step = Step::make('Bio')->key('bio');

    expect($step->isDismissed())->toBeTrue()
        ->and($step->completedAt())->toBe($at);
});

it('regression: dismiss() writes nothing for a required step flagged dismissible', function () {
    $store = new ArrayOnboardingStore;
    app()->instance(OnboardingStore::class, $store);

    $flow = new Flow([
        Step::make('Name')->key('name')->dismissible()->completeIf(fn () => false),
    ]);

    $flow->dismiss('name');

    expect($store->dismissed)->toBe([])
        ->and($flow->hasStep('name'))->toBeTrue();
});
