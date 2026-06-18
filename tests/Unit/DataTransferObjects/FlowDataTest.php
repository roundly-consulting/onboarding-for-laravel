<?php

declare(strict_types=1);

use RoundlyConsulting\Onboarding\DataTransferObjects\FlowData;
use RoundlyConsulting\Onboarding\DataTransferObjects\StepData;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;

it('serializes a flow with steps and a current step', function () {
    $step = new StepData(
        key: 'one',
        title: 'One',
        cta: null,
        action: null,
        isCompleted: false,
        isOptional: false,
    );

    $data = new FlowData(
        title: 'My Flow',
        percentage: 33.33,
        nextStep: $step,
        currentStep: $step,
        steps: [$step],
        isCompleted: false,
    );

    expect($data->toArray())->toBe([
        'title' => 'My Flow',
        'percentage' => 33.33,
        'next_step' => $step->toArray(),
        'current_step' => $step->toArray(),
        'is_completed' => false,
        'steps' => [$step->toArray()],
    ]);

    expect(json_decode(json_encode($data), true))->toBe($data->toArray());
});

it('serializes a completed flow with null next and current step', function () {
    $data = new FlowData(
        title: null,
        percentage: 100.0,
        nextStep: null,
        currentStep: null,
        steps: [],
        isCompleted: true,
    );

    expect($data->toArray())->toBe([
        'title' => null,
        'percentage' => 100.0,
        'next_step' => null,
        'current_step' => null,
        'is_completed' => true,
        'steps' => [],
    ]);
});

it('builds flow data for an empty flow', function () {
    $data = (new Flow)->toData();

    expect($data)
        ->toBeInstanceOf(FlowData::class)
        ->percentage->toBe(100.0)
        ->nextStep->toBeNull()
        ->currentStep->toBeNull()
        ->isCompleted->toBeTrue()
        ->steps->toBe([]);
});

it('builds flow data carrying step dtos', function () {
    $flow = Flow::make('Setup')->of([
        Step::make('One')->key('one'),
        Step::make('Two')->completeIf(fn () => false),
    ]);

    $data = $flow->toData();

    expect($data->steps)->toHaveCount(2)
        ->and($data->steps[0])->toBeInstanceOf(StepData::class)
        ->and($data->steps[0]->key)->toBe('one')
        ->and($data->currentStep?->title)->toBe('Two');
});
