<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Onboarding\Exceptions\InvalidFlowException;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Registry;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\CustomFlow;

it('works as registry', function () {
    $registry = new Registry;

    $registry->register('default', $firstFlow = new Flow)
        ->register('alternative', $secondFlow = new Flow)
        ->register('custom', CustomFlow::class);

    expect($registry)
        ->all()->toBeInstanceOf(Collection::class)
        ->all()->toHaveCount(3)
        ->find('default')->toBe($firstFlow)
        ->find('alternative')->toBe($secondFlow)
        ->find('third')->toBeNull()
        ->find('third', $third = new Flow)->toBe($third)
        ->find('custom')->title->toBe('My Custom Flow');
});

it('registers flows from array of steps', function () {
    $registry = new Registry;

    $registry->register('default', [
        Step::make('My Title'),
    ]);

    expect($registry)
        ->all()->toBeInstanceOf(Collection::class)
        ->all()->toHaveCount(1)
        ->find('default')->all()->first()->title->toBe('My Title');
});

it('registers default flow', function () {
    $registry = new Registry;

    $registry->register(CustomFlow::class);

    expect($registry)
        ->all()->toBeInstanceOf(Collection::class)
        ->all()->toHaveCount(1)
        ->find('default')->title->toBe('My Custom Flow');
});

it('registers default flow using array', function () {
    $registry = new Registry;

    $registry->register([
        Step::make('My Title'),
    ]);

    expect($registry)
        ->all()->toBeInstanceOf(Collection::class)
        ->all()->toHaveCount(1)
        ->find('default')->all()->first()->title->toBe('My Title');
});

it('uses custom default name', function () {
    Registry::$default = 'my_custom_default';

    $registry = new Registry;

    $registry->register(CustomFlow::class);

    expect($registry)
        ->all()->toBeInstanceOf(Collection::class)
        ->all()->toHaveCount(1)
        ->find('my_custom_default')->title->toBe('My Custom Flow');

    Registry::$default = 'default';
});

it('rejects a non-string key when a flow is given', function () {
    $registry = new Registry;

    $registry->register(new Flow, new Flow);
})->throws(InvalidArgumentException::class, 'The flow key must be a string.');

it('throws a typed exception for a non-flow class string', function () {
    $registry = new Registry;

    $registry->register('default', Step::class);
})->throws(InvalidFlowException::class);

it('creates, registers and returns a chainable named flow', function () {
    $registry = new Registry;

    $flow = $registry->flow('admin');
    $flow->add('Invite team')->cta('Invite');

    expect($registry)
        ->has('admin')->toBeTrue()
        ->find('admin')->toBe($flow)
        ->find('admin')->steps()->first()->title->toBe('Invite team');
});

it('reports, forgets and flushes registered flows', function () {
    $registry = new Registry;

    $registry->register('default', new Flow)
        ->register('admin', new Flow);

    expect($registry->has('default'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse();

    $registry->forget('default');

    expect($registry->has('default'))->toBeFalse()
        ->and($registry->all())->toHaveCount(1);

    $registry->flush();

    expect($registry->all())->toHaveCount(0);
});
