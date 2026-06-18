<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\User;

it('makes instance by static method', function () {
    $flow = Flow::make('First Flow')->of([
        Step::make('One')->cta('Yep'),
        Step::make('Two'),
    ]);

    expect($flow)
        ->title->toBe('First Flow')
        ->title('New Title')
        ->title->toBe('New Title')
        ->all()->toBeInstanceOf(Collection::class)
        ->all()->count()->toBe(2)
        ->all()->first()->title->toBe('One')
        ->all()->first()->cta->toBe('Yep')
        ->all()->last()->title->toBe('Two');
});

it('returns all flow steps', function () {
    $flow = new Flow([
        Step::make('One')->cta('Yep'),
        Step::make('Two'),
    ], 'First Flow');

    expect($flow)
        ->title->toBe('First Flow')
        ->all()->toBeInstanceOf(Collection::class)
        ->all()->count()->toBe(2)
        ->all()->first()->title->toBe('One')
        ->all()->first()->cta->toBe('Yep')
        ->all()->last()->title->toBe('Two');
});

it('returns flow steps for specific model', function () {
    $flow = new Flow([
        Step::make('One')->cta('Yep')->excludeIf(fn (?Model $model) => ! is_null($model)),
        Step::make('Two'),
    ]);

    $model = new User;

    expect($flow)
        ->steps()->toBeInstanceOf(Collection::class)
        ->steps()->count()->toBe(2)
        ->steps()->first()->title->toBe('One')
        ->for($model)
        ->steps()->toBeInstanceOf(Collection::class)
        ->steps()->count()->toBe(1)
        ->steps()->first()->title->toBe('Two');
});

it('returns flow steps for specific model when using global model', function () {
    $flow = new Flow([
        Step::make('One')->cta('Yep')->excludeIf(fn (?Model $model) => ! is_null($model)),
        Step::make('Two'),
    ]);

    $flow->for(new User);

    expect($flow)
        ->steps()->toBeInstanceOf(Collection::class)
        ->steps()->count()->toBe(1)
        ->steps()->first()->title->toBe('Two');
});

it('adds step to existing flow with fluent options to step', function () {
    $flow = new Flow;

    $flow->add('First Step')->cta('My CTA');

    expect($flow)
        ->steps()->toBeInstanceOf(Collection::class)
        ->steps()->count()->toBe(1)
        ->steps()->first()->title->toBe('First Step')
        ->steps()->first()->cta->toBe('My CTA');
});

it('adds step to existing flow', function () {
    $flow = new Flow;

    $step = Step::make('My Title');

    $flow->addStep($step);

    expect($flow)
        ->steps()->toBeInstanceOf(Collection::class)
        ->steps()->count()->toBe(1)
        ->steps()->first()->title->toBe('My Title');
});

it('checks whether all steps are completed', function () {
    $flow = new Flow([
        Step::make('My Step')->completeIf(fn (?Model $model) => is_null($model)),
    ]);

    $model = new User;

    expect($flow)
        ->isCompleted()->toBeTrue();

    $flow->for($model);

    expect($flow->isCompleted())->toBeFalse();
});

it('checks whether some steps are not completed', function () {
    $flow = new Flow([
        Step::make('My Step')->completeIf(fn (?Model $model) => is_null($model)),
    ]);

    $model = new User;

    expect($flow)
        ->isInProgress()->toBeFalse()
        ->for($model)
        ->isInProgress()->toBeTrue();
});

it('returns next step', function () {
    $flow = new Flow([
        Step::make('My Step')->completeIf(fn (?Model $model) => is_null($model)),
        Step::make('Second Step')->completeIf(fn (?Model $model) => ! is_null($model)),
    ]);

    $model = new User;

    expect($flow)
        ->nextStep()->title->toBe('Second Step')
        ->for($model)
        ->nextStep()->title->toBe('My Step');
});

it('returns percentage of completness', function () {
    $fakeModel = new User;

    $flow = new Flow([
        Step::make('My Step')->completeIf(fn (?Model $model) => is_null($model)),
        Step::make('Second Step')->completeIf(fn (?Model $model) => $model === $fakeModel),
        Step::make('Third Step')->completeIf(fn (?Model $model) => $model !== $fakeModel),
    ]);

    expect($flow)
        ->percentageCompleted()->toBe(66.67)
        ->for($fakeModel)
        ->percentageCompleted()->toBe(33.33)
        ->for(new User)
        ->percentageCompleted()->toBe(33.33);

    expect(new Flow)
        ->percentageCompleted()->toBe(100.0);
});

it('returns array of flow with steps and completness info', function () {
    $flow = Flow::make('First Flow')->of([
        Step::make('One')->cta('Yep')->action('first')->meta(['yep']),
        Step::make('Two')->action('second')->completeIf(fn () => false),
    ]);

    expect($flow->toArray())->toBe([
        'title' => 'First Flow',
        'percentage' => 50.0,
        'next_step' => [
            'title' => 'Two',
            'cta' => null,
            'action' => 'second',
            'is_completed' => false,
            'meta' => [],
        ],
        'steps' => [
            [
                'title' => 'One',
                'cta' => 'Yep',
                'action' => 'first',
                'is_completed' => true,
                'meta' => ['yep'],
            ],
            [
                'title' => 'Two',
                'cta' => null,
                'action' => 'second',
                'is_completed' => false,
                'meta' => [],
            ],
        ],
    ]);
});
