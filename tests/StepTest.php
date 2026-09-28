<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\AuthIdentity;
use RoundlyConsulting\Onboarding\Tests\User;

afterEach(function () {
    Step::flushMacros();
});

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
            'group' => null,
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
        ->group('profile')
        ->route('home')
        ->url('/x')
        ->translatable()
        ->dismissible()
        ->completeWhen(fn () => true)
        ->excludeWhen(fn () => false);

    expect($step)->toBeInstanceOf(Step::class);
});

it('aliases completeWhen/excludeWhen to the if setters', function () {
    $step = Step::make('One')->completeWhen(fn () => false)->excludeWhen(fn () => true);

    expect($step)
        ->complete->toBeInstanceOf(Closure::class)
        ->exclude->toBeInstanceOf(Closure::class)
        ->isCompleted()->toBeFalse()
        ->isExcluded()->toBeTrue();
});

it('completes when an attribute is filled, with dot paths', function () {
    $filled = new User(['avatar_path' => '/me.jpg']);
    $empty = new User(['avatar_path' => '']);
    $null = new User;

    expect(Step::make('Photo')->completeWhenFilled('avatar_path')->for($filled)->isCompleted())->toBeTrue();
    expect(Step::make('Photo')->completeWhenFilled('avatar_path')->for($empty)->isCompleted())->toBeFalse();
    expect(Step::make('Photo')->completeWhenFilled('avatar_path')->for($null)->isCompleted())->toBeFalse();
    expect(Step::make('Photo')->completeWhenFilled('avatar_path')->isCompleted())->toBeFalse();

    $dotted = new User(['profile' => ['avatar' => 'x']]);
    expect(Step::make('Photo')->completeWhenFilled('profile.avatar')->for($dotted)->isCompleted())->toBeTrue();
    expect(Step::make('Photo')->completeWhenFilled('profile.avatar')->for(new User(['profile' => []]))->isCompleted())->toBeFalse();
});

it('completes when an attribute is truthy', function () {
    expect(Step::make('Verify')->completeWhenTrue('verified')->for(new User(['verified' => 1]))->isCompleted())->toBeTrue();
    expect(Step::make('Verify')->completeWhenTrue('verified')->for(new User(['verified' => 0]))->isCompleted())->toBeFalse();
    expect(Step::make('Verify')->completeWhenTrue('verified')->isCompleted())->toBeFalse();
});

it('prefers a no-arg method over an attribute for completeWhenTrue', function () {
    $subscribed = new User(['subscribed' => true]);
    $unsubscribed = new User(['subscribed' => false]);

    expect(Step::make('Sub')->completeWhenTrue('hasSubscription')->for($subscribed)->isCompleted())->toBeTrue();
    expect(Step::make('Sub')->completeWhenTrue('hasSubscription')->for($unsubscribed)->isCompleted())->toBeFalse();
});

it('falls back to an attribute when a method needs arguments', function () {
    // greet() requires an argument, so data_get('greet') is used (null → false).
    expect(Step::make('Greet')->completeWhenTrue('greet')->for(new User)->isCompleted())->toBeFalse();
});

it('completes when a relation resolves to a non-empty value', function () {
    expect(Step::make('Team')->completeWhenHas('rel')->for(new User(['rel' => new User]))->isCompleted())->toBeTrue();
    expect(Step::make('Team')->completeWhenHas('rel')->for(new User(['rel' => new Collection([1])]))->isCompleted())->toBeTrue();
    expect(Step::make('Team')->completeWhenHas('rel')->for(new User(['rel' => new Collection]))->isCompleted())->toBeFalse();
    expect(Step::make('Team')->completeWhenHas('rel')->for(new User(['rel' => ['a']]))->isCompleted())->toBeTrue();
    expect(Step::make('Team')->completeWhenHas('rel')->for(new User(['rel' => []]))->isCompleted())->toBeFalse();
    expect(Step::make('Team')->completeWhenHas('rel')->for(new User)->isCompleted())->toBeFalse();
    expect(Step::make('Team')->completeWhenHas('rel')->isCompleted())->toBeFalse();
});

it('mirrors the declarative helpers on the exclude side', function () {
    expect(Step::make('X')->excludeWhenFilled('avatar_path')->for(new User(['avatar_path' => 'x']))->isExcluded())->toBeTrue();
    expect(Step::make('X')->excludeWhenTrue('verified')->for(new User(['verified' => true]))->isExcluded())->toBeTrue();
    expect(Step::make('X')->excludeWhenHas('rel')->for(new User(['rel' => ['a']]))->isExcluded())->toBeTrue();
    expect(Step::make('X')->excludeWhenFilled('avatar_path')->isExcluded())->toBeFalse();
});

it('accepts a non-Eloquent Authenticatable subject', function () {
    $identity = new AuthIdentity;
    $identity->verified = true;

    $step = Step::make('Verify')->completeWhenTrue('verified')->for($identity);

    expect($step->isCompleted())->toBeTrue()
        ->and($step->for)->toBe($identity);
});

it('sets and reads a group', function () {
    expect(Step::make('Card')->group('billing'))
        ->groupName()->toBe('billing');

    expect(Step::make('Card')->groupName())->toBeNull();
});

it('resolves a translation key when one exists', function () {
    Lang::addLines(['onboarding.photo.title' => 'Upload your photo'], 'en');
    Lang::addLines(['onboarding.photo.cta' => 'Upload now'], 'en');

    $step = Step::make('onboarding.photo.title')->cta('onboarding.photo.cta');

    expect($step)
        ->resolvedTitle()->toBe('Upload your photo')
        ->resolvedCta()->toBe('Upload now');
});

it('passes through a dotted string with no matching translation', function () {
    $step = Step::make('Upload your photo.');

    expect($step->resolvedTitle())->toBe('Upload your photo.');
});

it('passes through a plain string verbatim', function () {
    expect(Step::make('Upload Photo')->resolvedTitle())->toBe('Upload Photo');
    expect(Step::make()->resolvedTitle())->toBeNull();
    expect(Step::make('X')->resolvedCta())->toBeNull();
});

it('forces translation when translatable(true)', function () {
    Lang::addLines(['onboarding.forced' => 'Forced'], 'en');

    expect(Step::make('onboarding.forced')->translatable()->resolvedTitle())->toBe('Forced');
});

it('forces a raw string when translatable(false)', function () {
    Lang::addLines(['onboarding.raw' => 'Translated'], 'en');

    expect(Step::make('onboarding.raw')->translatable(false)->resolvedTitle())->toBe('onboarding.raw');
});

it('falls back to raw copy when the translator is unavailable', function () {
    $app = Lang::getFacadeApplication();
    Lang::clearResolvedInstances();
    Lang::setFacadeApplication(null);

    try {
        expect(Step::make('onboarding.x.title')->resolvedTitle())->toBe('onboarding.x.title');
    } finally {
        Lang::setFacadeApplication($app);
    }
});

it('carries resolved copy into the dto', function () {
    Lang::addLines(['onboarding.dto.title' => 'Resolved Title'], 'en');

    expect(Step::make('onboarding.dto.title')->toData()->title)->toBe('Resolved Title');
});

it('registers and calls instance macros', function () {
    Step::macro('completeWhenVerified', fn () => $this->completeWhenTrue('verified'));

    $step = Step::make('Verify')->completeWhenVerified();

    expect(Step::hasMacro('completeWhenVerified'))->toBeTrue()
        ->and($step->for(new User(['verified' => true]))->isCompleted())->toBeTrue();
});

it('reports a missing macro', function () {
    expect(Step::hasMacro('missing'))->toBeFalse();
});

it('is dismissible only when flagged', function () {
    expect(Step::make('Bio')->isDismissible())->toBeFalse()
        ->and(Step::make('Bio')->dismissible()->isDismissible())->toBeTrue();
});

it('is never dismissed and has no completedAt without a store', function () {
    expect(Step::make('Bio')->dismissible()->isDismissed())->toBeFalse()
        ->and(Step::make('Bio')->completedAt())->toBeNull();
});

it('stores route parameters and resolves a closure against the bound subject', function () {
    $static = Step::make('Team')->route('teams.show', ['team' => 3]);
    $dynamic = Step::make('Team')
        ->route('teams.show', fn (?User $user) => ['team' => $user?->team_id])
        ->for(new User(['team_id' => 4]));

    expect($static->route)->toBe('teams.show')
        ->and($static->routeParameters())->toBe(['team' => 3])
        ->and($dynamic->routeParameters())->toBe(['team' => 4])
        ->and(Step::make('Plain')->route('home')->routeParameters())->toBe([])
        ->and(Step::make('Reset')->route('teams.show', ['team' => 3])->route('home')->routeParameters())->toBe([]);
});
