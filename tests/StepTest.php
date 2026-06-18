<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\User;

it('holds options in public properties', function () {
    $step = new Step(
        title: 'My Title',
        cta: 'Complete Profile',
        action: 'complete-profile',
        complete: fn () => true,
        exclude: fn () => false,
        meta: ['test' => 'yes'],
    );

    expect($step)
        ->title->toBe('My Title')
        ->cta->toBe('Complete Profile')
        ->action->toBe('complete-profile')
        ->complete->toBeInstanceOf(Closure::class)
        ->exclude->toBeInstanceOf(Closure::class)
        ->meta->toBe(['test' => 'yes']);
});

it('makes instance via static method', function () {
    expect(Step::make())->toBeInstanceOf(Step::class);
});

it('has setters to change options', function () {
    $step = new Step;

    $step->title('My Title')
        ->cta('Complete Profile')
        ->action('complete-profile')
        ->completeIf(fn () => true)
        ->excludeIf(fn () => false)
        ->meta(['test' => 'yes']);

    expect($step)
        ->title->toBe('My Title')
        ->cta->toBe('Complete Profile')
        ->action->toBe('complete-profile')
        ->complete->toBeInstanceOf(Closure::class)
        ->exclude->toBeInstanceOf(Closure::class)
        ->meta->toBe(['test' => 'yes']);
});

it('returns step as array with completness', function () {
    $step = new Step;

    $step->title('My Title')
        ->cta('Complete Profile')
        ->action('complete-profile')
        ->completeIf(fn () => true)
        ->meta(['test' => 'yes']);

    expect($step->toArray())
        ->toBe([
            'key' => 'my-title',
            'title' => 'My Title',
            'cta' => 'Complete Profile',
            'action' => 'complete-profile',
            'is_completed' => true,
            'is_optional' => false,
            'meta' => [
                'test' => 'yes',
            ],
        ]);
});

it('checks whether step is completed or not', function () {
    $step = new Step;

    expect($step)->isCompleted()->toBeTrue()
        ->isNotCompleted()->toBeFalse();

    $step->completeIf(fn () => false);

    expect($step)->isCompleted()->toBeFalse()
        ->isNotCompleted()->toBeTrue();
});

it('checks whether step is completed or not for specific model', function () {
    $step = new Step(complete: fn (?Model $model) => ! is_null($model));

    $model = new User;

    expect($step)->isCompleted()->toBeFalse()
        ->isNotCompleted()->toBeTrue()
        ->for($model)
        ->isCompleted()->toBeTrue()
        ->isNotCompleted()->toBeFalse();
});

it('checks whether step should be excluded or not', function () {
    $step = new Step;

    expect($step)->isExcluded()->toBeFalse()
        ->isNotExcluded()->toBeTrue();

    $step->excludeIf(fn () => true);

    expect($step)->isExcluded()->toBeTrue()
        ->isNotExcluded()->toBeFalse();
});

it('checks whether step should be excluded or not for specific model', function () {
    $step = new Step(exclude: fn (?Model $model) => ! is_null($model));

    $model = new User;

    expect($step)->isExcluded()->toBeFalse()
        ->isNotExcluded()->toBeTrue()
        ->for($model)
        ->isExcluded()->toBeTrue()
        ->isNotExcluded()->toBeFalse();
});

it('returns its explicit key', function () {
    expect(Step::make('Upload Photo')->key('upload-photo')->stepKey())
        ->toBe('upload-photo');
});

it('slugs its key from the title when none is set', function () {
    expect(Step::make('Upload A Photo')->stepKey())->toBe('upload-a-photo');
});

it('returns an empty key when there is no title or explicit key', function () {
    expect(Step::make()->stepKey())->toBe('');
});

it('is required by default and can be marked optional', function () {
    $step = Step::make('Add a bio');

    expect($step)
        ->isOptional()->toBeFalse()
        ->isRequired()->toBeTrue();

    $step->optional();

    expect($step)
        ->isOptional()->toBeTrue()
        ->isRequired()->toBeFalse();

    $step->required();

    expect($step)
        ->isOptional()->toBeFalse()
        ->isRequired()->toBeTrue();

    expect(Step::make('Add a bio')->optional(false)->isOptional())->toBeFalse();
});

it('holds an order value', function () {
    expect(Step::make('One')->order(3)->order)->toBe(3);
});

it('preserves its subtype through fluent setters', function () {
    $step = Step::make('One')
        ->title('Two')
        ->cta('Go')
        ->action('route')
        ->meta(['x' => 1])
        ->key('two')
        ->order(1)
        ->optional()
        ->completeIf(fn () => true)
        ->excludeIf(fn () => false);

    expect($step)->toBeInstanceOf(Step::class);
});
