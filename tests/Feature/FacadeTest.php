<?php

declare(strict_types=1);

use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use RoundlyConsulting\Onboarding\Exceptions\InvalidStoreException;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\OnboardingManager;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\ArrayOnboardingStore;
use RoundlyConsulting\Onboarding\Tests\User;

// No toReachEveryAction(): onboarding has no src/Actions — the manager, Flow and Step carry
// the behaviour, and every public method of the manager is pinned by toDocumentItsRoot().
it('documents its root and is fakeable', function (): void {
    expect(Onboarding::class)->toDocumentItsRoot()->toBeFakeable();
});

function profileFlow(): Flow
{
    return Flow::make('Profile')->of([
        Step::make('Name')->key('name')->completeWhenFilled('name'),
        Step::make('Bio')->key('bio')->optional()->dismissible()->completeWhenFilled('bio'),
        Step::make('Avatar')->key('avatar')->optional()->completeWhenFilled('avatar'),
    ]);
}

it('returns the flow for a subject by key, by resolver and by default', function (): void {
    Onboarding::register('default', profileFlow())
        ->register('admin', Flow::make('Admin'));

    $user = new User(['name' => 'Ada']);

    expect(Onboarding::for($user)?->title)->toBe('Profile')
        ->and(Onboarding::for($user)?->subject())->toBe($user)
        ->and(Onboarding::for($user, 'admin')?->title)->toBe('Admin')
        ->and(Onboarding::for($user, 'missing'))->toBeNull();

    Onboarding::resolveUsing(fn (): string => 'admin');

    expect(Onboarding::for($user)?->title)->toBe('Admin');
});

it('returns a copy per subject and leaves the registered flow unbound', function (): void {
    Onboarding::register('default', profileFlow());

    $first = Onboarding::for(new User(['name' => 'Ada']));
    $second = Onboarding::for(new User);

    expect($first)->not->toBe($second)
        ->and($first?->isCompleted())->toBeTrue()
        ->and($second?->isCompleted())->toBeFalse()
        ->and(Onboarding::find('default')?->for)->toBeNull();
});

it('is stateless until a store is configured', function (): void {
    expect(Onboarding::hasStore())->toBeFalse()
        ->and(Onboarding::store())->toBeNull();
});

it('uses a store instance given to useStore', function (): void {
    $store = new ArrayOnboardingStore;

    Onboarding::useStore($store)->register('default', profileFlow());

    $user = new User;
    Onboarding::for($user)?->dismiss('bio');

    expect(Onboarding::hasStore())->toBeTrue()
        ->and(Onboarding::store())->toBe($store)
        ->and($store->dismissed)->toBe(['bio' => true])
        ->and(Onboarding::for($user)?->hasStep('bio'))->toBeFalse();
});

it('resolves a store class through the container once', function (): void {
    Onboarding::useStore(ArrayOnboardingStore::class);

    expect(Onboarding::hasStore())->toBeTrue()
        ->and(Onboarding::store())->toBeInstanceOf(ArrayOnboardingStore::class)
        ->and(Onboarding::store())->toBe(Onboarding::store());
});

it('refuses a class that is not a store', function (): void {
    Onboarding::useStore(stdClass::class);
})->throws(InvalidStoreException::class, '[stdClass] does not implement '.OnboardingStore::class.'.');

it('refuses a store class the container resolves to something else', function (): void {
    app()->bind(ArrayOnboardingStore::class, fn (): stdClass => new stdClass);

    Onboarding::useStore(ArrayOnboardingStore::class)->store();
})->throws(InvalidStoreException::class);

it('falls back to a store bound in the container', function (): void {
    app()->instance(OnboardingStore::class, $store = new ArrayOnboardingStore);

    expect(Onboarding::hasStore())->toBeTrue()
        ->and(Onboarding::store())->toBe($store);
});

it('dismisses a step through the model trait', function (): void {
    Onboarding::useStore($store = new ArrayOnboardingStore)->register('default', profileFlow());

    $user = new User;
    $user->dismissOnboardingStep('bio');
    $user->dismissOnboardingStep('avatar');   // not dismissible
    $user->dismissOnboardingStep('missing');  // unknown
    $user->dismissOnboardingStep('bio', 'no-such-flow');

    expect($store->dismissed)->toBe(['bio' => true])
        ->and($user->onboarding()?->hasStep('bio'))->toBeFalse();
});

it('dismisses nothing without a store', function (): void {
    Onboarding::register('default', profileFlow());

    $user = new User;
    $user->dismissOnboardingStep('bio');

    expect($user->onboarding()?->hasStep('bio'))->toBeTrue();
});

it('serves the same API from the injected manager', function (): void {
    $manager = app(OnboardingManager::class);

    $manager->register('default', profileFlow());

    expect(Onboarding::has('default'))->toBeTrue()
        ->and($manager->for(new User(['name' => 'Ada']))?->isCompleted())->toBeTrue();
});
