<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Onboarding\Events\FlowCompleted;
use RoundlyConsulting\Onboarding\Events\StepCompleted;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\User;

it('dispatches a completed event for every complete step', function () {
    Event::fake();

    $flow = new Flow([
        Step::make('One')->key('one')->completeIf(fn () => true),
        Step::make('Two')->key('two')->completeIf(fn () => false),
    ]);

    $flow->record();

    Event::assertDispatchedTimes(StepCompleted::class, 1);
    Event::assertDispatched(StepCompleted::class, fn (StepCompleted $e) => $e->step->stepKey() === 'one');
});

it('dispatches flow completed only when the flow is complete', function () {
    Event::fake();

    $incomplete = new Flow([Step::make('One')->completeIf(fn () => false)]);
    $incomplete->record();

    Event::assertNotDispatched(FlowCompleted::class);

    $complete = new Flow([Step::make('One')->completeIf(fn () => true)]);
    $complete->record();

    Event::assertDispatchedTimes(FlowCompleted::class, 1);
});

it('attributes events to the bound model', function () {
    Event::fake();

    $user = new User;

    $flow = (new Flow([Step::make('One')->completeIf(fn () => true)]))->for($user);
    $flow->record();

    Event::assertDispatched(StepCompleted::class, fn (StepCompleted $e) => $e->for === $user);
    Event::assertDispatched(FlowCompleted::class, fn (FlowCompleted $e) => $e->for === $user);
});

it('returns itself from record for chaining', function () {
    Event::fake();

    $flow = new Flow([Step::make('One')]);

    expect($flow->record())->toBe($flow);
});

it('is a safe no-op when no event dispatcher is available', function () {
    $app = Event::getFacadeApplication();

    Event::clearResolvedInstances();
    Event::setFacadeApplication(null);

    try {
        $flow = new Flow([Step::make('One')->completeIf(fn () => true)]);

        expect($flow->record())->toBe($flow);
    } finally {
        Event::setFacadeApplication($app);
    }
});

it('announces only the targeted step', function () {
    Event::fake();

    $flow = new Flow([
        Step::make('One')->key('one')->completeIf(fn () => true),
        Step::make('Two')->key('two')->completeIf(fn () => true)->optional(),
    ]);

    $flow->record('one');

    Event::assertDispatchedTimes(StepCompleted::class, 1);
    Event::assertDispatched(StepCompleted::class, fn (StepCompleted $e) => $e->step->stepKey() === 'one');
});

it('dispatches nothing for an incomplete targeted step', function () {
    Event::fake();

    $flow = new Flow([Step::make('One')->key('one')->completeIf(fn () => false)]);
    $flow->record('one');

    Event::assertNotDispatched(StepCompleted::class);
});

it('dispatches nothing for an unknown targeted step', function () {
    Event::fake();

    $flow = new Flow([Step::make('One')->key('one')->completeIf(fn () => true)]);
    $flow->record('missing');

    Event::assertNotDispatched(StepCompleted::class);
});

it('fires flow completed when a targeted record finishes the flow', function () {
    Event::fake();

    $flow = new Flow([Step::make('One')->key('one')->completeIf(fn () => true)]);
    $flow->record('one');

    Event::assertDispatchedTimes(FlowCompleted::class, 1);
});

it('does not fire flow completed when a targeted record leaves it incomplete', function () {
    Event::fake();

    $flow = new Flow([
        Step::make('One')->key('one')->completeIf(fn () => true),
        Step::make('Two')->key('two')->completeIf(fn () => false),
    ]);
    $flow->record('one');

    Event::assertDispatchedTimes(StepCompleted::class, 1);
    Event::assertNotDispatched(FlowCompleted::class);
});

it('is a no-op for targeted record without a dispatcher', function () {
    $app = Event::getFacadeApplication();

    Event::clearResolvedInstances();
    Event::setFacadeApplication(null);

    try {
        $flow = new Flow([Step::make('One')->key('one')->completeIf(fn () => true)]);

        expect($flow->record('one'))->toBe($flow);
    } finally {
        Event::setFacadeApplication($app);
    }
});

it('never dispatches events on read paths', function () {
    Event::fake();

    $flow = new Flow([
        Step::make('One')->completeIf(fn () => true),
        Step::make('Two')->completeIf(fn () => false),
    ]);

    $flow->steps();
    $flow->currentStep();
    $flow->currentStep();
    $flow->isCompleted();
    $flow->percentageCompleted();
    $flow->toArray();
    $flow->toData();

    Event::assertNotDispatched(StepCompleted::class);
    Event::assertNotDispatched(FlowCompleted::class);
});
