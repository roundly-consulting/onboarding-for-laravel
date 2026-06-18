<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\CustomFlow;
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
            'key' => 'two',
            'title' => 'Two',
            'cta' => null,
            'action' => 'second',
            'is_completed' => false,
            'is_optional' => false,
            'meta' => [],
        ],
        'current_step' => [
            'key' => 'two',
            'title' => 'Two',
            'cta' => null,
            'action' => 'second',
            'is_completed' => false,
            'is_optional' => false,
            'meta' => [],
        ],
        'is_completed' => false,
        'steps' => [
            [
                'key' => 'one',
                'title' => 'One',
                'cta' => 'Yep',
                'action' => 'first',
                'is_completed' => true,
                'is_optional' => false,
                'meta' => ['yep'],
            ],
            [
                'key' => 'two',
                'title' => 'Two',
                'cta' => null,
                'action' => 'second',
                'is_completed' => false,
                'is_optional' => false,
                'meta' => [],
            ],
        ],
    ]);
});

it('finds a step by key and reports whether it exists', function () {
    $flow = new Flow([
        Step::make('Upload Photo')->key('upload-photo'),
        Step::make('Add a bio'),
    ]);

    expect($flow)
        ->step('upload-photo')->title->toBe('Upload Photo')
        ->step('add-a-bio')->title->toBe('Add a bio')
        ->step('missing')->toBeNull()
        ->hasStep('upload-photo')->toBeTrue()
        ->hasStep('missing')->toBeFalse();
});

it('keeps step keys when bound to a model', function () {
    $flow = (new Flow([Step::make('Upload Photo')->key('upload-photo')]))->for(new User);

    expect($flow->step('upload-photo')?->title)->toBe('Upload Photo');
});

it('reports position, index and counts across completion states', function () {
    $fakeModel = new User;

    $flow = new Flow([
        Step::make('One')->completeIf(fn (?Model $model) => is_null($model)),
        Step::make('Two')->completeIf(fn (?Model $model) => $model === $fakeModel),
        Step::make('Three')->completeIf(fn () => false),
    ]);

    // unbound: only step one is complete (null model)
    expect($flow)
        ->count()->toBe(3)
        ->completedCount()->toBe(1)
        ->isStarted()->toBeTrue()
        ->currentStepIndex()->toBe(1)
        ->position()->toBe(2)
        ->currentStep()->title->toBe('Two');

    // bound to the fake model: step one incomplete, step two complete
    $flow->for($fakeModel);

    expect($flow)
        ->completedCount()->toBe(1)
        ->currentStepIndex()->toBe(0)
        ->position()->toBe(2)
        ->currentStep()->title->toBe('One');
});

it('reports a fully completed flow position at the end', function () {
    $flow = new Flow([
        Step::make('One'),
        Step::make('Two'),
    ]);

    expect($flow)
        ->isCompleted()->toBeTrue()
        ->currentStep()->toBeNull()
        ->currentStepIndex()->toBeNull()
        ->completedCount()->toBe(2)
        ->position()->toBe(2);
});

it('treats an empty flow as complete and not started', function () {
    $flow = new Flow;

    expect($flow)
        ->isEmpty()->toBeTrue()
        ->count()->toBe(0)
        ->completedCount()->toBe(0)
        ->isStarted()->toBeFalse()
        ->isCompleted()->toBeTrue()
        ->currentStep()->toBeNull()
        ->currentStepIndex()->toBeNull()
        ->position()->toBe(1)
        ->percentageCompleted()->toBe(100.0);
});

it('ignores optional steps when deciding completion', function () {
    $flow = new Flow([
        Step::make('Required')->completeIf(fn () => true),
        Step::make('Optional')->optional()->completeIf(fn () => false),
    ]);

    expect($flow)
        ->isCompleted()->toBeTrue()
        ->requiredSteps()->count()->toBe(1)
        ->optionalSteps()->count()->toBe(1)
        ->requiredPercentageCompleted()->toBe(100.0)
        ->percentageCompleted()->toBe(50.0);
});

it('blocks completion on an incomplete required step', function () {
    $flow = new Flow([
        Step::make('Required')->completeIf(fn () => false),
        Step::make('Optional')->optional()->completeIf(fn () => true),
    ]);

    expect($flow)
        ->isCompleted()->toBeFalse()
        ->requiredPercentageCompleted()->toBe(0.0)
        ->percentageCompleted()->toBe(50.0);
});

it('treats a flow with only optional steps as complete', function () {
    $flow = new Flow([
        Step::make('Optional')->optional()->completeIf(fn () => false),
    ]);

    expect($flow)
        ->isCompleted()->toBeTrue()
        ->requiredSteps()->count()->toBe(0)
        ->requiredPercentageCompleted()->toBe(100.0);
});

it('never counts excluded steps toward required progress', function () {
    $flow = new Flow([
        Step::make('Skipped')->excludeIf(fn () => true)->completeIf(fn () => false),
        Step::make('Done')->completeIf(fn () => true),
    ]);

    expect($flow)
        ->isCompleted()->toBeTrue()
        ->count()->toBe(1)
        ->requiredPercentageCompleted()->toBe(100.0);
});

it('sorts steps by explicit order when any is set', function () {
    $flow = new Flow([
        Step::make('Third')->order(3),
        Step::make('First')->order(1),
        Step::make('Second')->order(2),
    ]);

    expect($flow->steps()->map->title->all())
        ->toBe(['First', 'Second', 'Third']);
});

it('keeps insertion order when no step declares an order', function () {
    $flow = new Flow([
        Step::make('Alpha'),
        Step::make('Beta'),
    ]);

    expect($flow->steps()->map->title->all())->toBe(['Alpha', 'Beta']);
});

it('preserves its subtype through fluent flow methods', function () {
    $flow = CustomFlow::make('Custom')
        ->title('Renamed')
        ->of([Step::make('One')])
        ->for(new User)
        ->addStep(Step::make('Two'));

    expect($flow)->toBeInstanceOf(CustomFlow::class);
});
