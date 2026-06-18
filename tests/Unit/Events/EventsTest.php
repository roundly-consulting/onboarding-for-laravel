<?php

declare(strict_types=1);

use RoundlyConsulting\Onboarding\Events\FlowCompleted;
use RoundlyConsulting\Onboarding\Events\StepCompleted;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\User;

it('holds the step and the model on StepCompleted', function () {
    $step = Step::make('One');
    $user = new User;

    $event = new StepCompleted($step, $user);

    expect($event->step)->toBe($step)
        ->and($event->for)->toBe($user);
});

it('holds the flow and the model on FlowCompleted', function () {
    $flow = new Flow;
    $user = new User;

    $event = new FlowCompleted($flow, $user);

    expect($event->flow)->toBe($flow)
        ->and($event->for)->toBe($user);
});

it('allows a null model', function () {
    expect((new StepCompleted(Step::make('One')))->for)->toBeNull()
        ->and((new FlowCompleted(new Flow))->for)->toBeNull();
});
