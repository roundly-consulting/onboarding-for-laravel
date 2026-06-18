<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Arrayable;
use RoundlyConsulting\Onboarding\DataTransferObjects\SectionData;
use RoundlyConsulting\Onboarding\DataTransferObjects\StepData;

it('serializes to an array with the documented keys', function () {
    $step = new StepData(
        key: 'card',
        title: 'Add a card',
        cta: null,
        action: null,
        isCompleted: true,
        isOptional: false,
        group: 'billing',
    );

    $section = new SectionData(
        key: 'billing',
        title: 'billing',
        percentage: 100.0,
        isCompleted: true,
        steps: [$step],
    );

    expect($section)->toBeInstanceOf(Arrayable::class);

    expect($section->toArray())->toBe([
        'key' => 'billing',
        'title' => 'billing',
        'percentage' => 100.0,
        'is_completed' => true,
        'steps' => [$step->toArray()],
    ]);
});

it('json encodes the same shape as toArray', function () {
    $section = new SectionData(
        key: 'general',
        title: 'general',
        percentage: 50.5,
        isCompleted: false,
        steps: [],
    );

    expect(json_decode(json_encode($section), true))->toBe($section->toArray());
});
