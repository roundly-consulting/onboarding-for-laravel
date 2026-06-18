<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Arrayable;
use RoundlyConsulting\Onboarding\DataTransferObjects\StepData;

it('exposes its data through readonly properties', function () {
    $data = new StepData(
        key: 'upload-photo',
        title: 'Upload Photo',
        cta: 'Upload Now',
        action: 'users@photo@upload',
        isCompleted: true,
        isOptional: false,
        meta: ['icon' => 'camera'],
    );

    expect($data)
        ->toBeInstanceOf(Arrayable::class)
        ->key->toBe('upload-photo')
        ->title->toBe('Upload Photo')
        ->cta->toBe('Upload Now')
        ->action->toBe('users@photo@upload')
        ->isCompleted->toBeTrue()
        ->isOptional->toBeFalse()
        ->meta->toBe(['icon' => 'camera']);
});

it('serializes to an array with the documented keys', function () {
    $data = new StepData(
        key: 'bio',
        title: 'Add a bio',
        cta: null,
        action: null,
        isCompleted: false,
        isOptional: true,
    );

    expect($data->toArray())->toBe([
        'key' => 'bio',
        'title' => 'Add a bio',
        'cta' => null,
        'action' => null,
        'is_completed' => false,
        'is_optional' => true,
        'meta' => [],
    ]);
});

it('json encodes the same shape as toArray', function () {
    $data = new StepData(
        key: 'bio',
        title: 'Add a bio',
        cta: null,
        action: null,
        isCompleted: false,
        isOptional: true,
    );

    expect(json_decode(json_encode($data), true))->toBe($data->toArray());
});
