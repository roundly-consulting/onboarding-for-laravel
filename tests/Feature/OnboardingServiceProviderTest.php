<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Http\Middleware\RequireOnboarding;
use RoundlyConsulting\Onboarding\OnboardingManager;
use RoundlyConsulting\Onboarding\OnboardingServiceProvider;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\ArrayOnboardingStore;

function renderAbout(): string
{
    Artisan::call('about', ['--only' => 'onboarding']);

    return Artisan::output();
}

it('registers the registry as a singleton', function () {
    expect(app(OnboardingManager::class))->toBe(app(OnboardingManager::class));
});

it('registers the onboarding:list command', function () {
    expect(Artisan::all())->toHaveKey('onboarding:list');
});

it('registers the onboarding middleware alias', function () {
    expect(app(Router::class)->getMiddleware())
        ->toHaveKey('onboarding')
        ->and(app(Router::class)->getMiddleware()['onboarding'])
        ->toBe(RequireOnboarding::class);
});

it('publishes nothing at all', function () {
    expect(ServiceProvider::pathsToPublish(OnboardingServiceProvider::class))->toBe([]);

    $groups = array_keys(ServiceProvider::publishableGroups());

    expect($groups)
        ->not->toContain('onboarding-config')
        ->not->toContain('onboarding-migrations')
        ->not->toContain('onboarding-views')
        ->not->toContain('onboarding-translations');
});

it('never auto-loads its migrations', function () {
    $packageRoot = dirname(__DIR__, 2);

    // The package is stateless: it ships no migrations directory at all, and the
    // provider must never put one on the migrator's path list.
    expect(is_dir($packageRoot.'/database/migrations'))->toBeFalse();

    foreach (app('migrator')->paths() as $path) {
        expect($path)->not->toStartWith($packageRoot.DIRECTORY_SEPARATOR);
    }
});

it('contributes an about section', function () {
    Onboarding::register(Flow::make('Profile')->of([Step::make('Upload photo')]));

    $rendered = renderAbout();

    expect($rendered)
        ->toContain('Flows')
        ->toContain('1 registered')
        ->toContain('Steps')
        ->toContain('1 declared')
        ->toContain('Default flow')
        ->toContain('REGISTERED');
});

it('reports an empty registry and a stateless store', function () {
    $rendered = renderAbout();

    expect($rendered)
        ->toContain('NONE')
        ->toContain('stateless');
});

it('reports a bound persistence store without instantiating it', function () {
    app()->bind(OnboardingStore::class, function (): never {
        throw new RuntimeException('about must not resolve the host store');
    });

    expect(renderAbout())->toContain('BOUND');
});

it('reports a real bound store', function () {
    app()->instance(OnboardingStore::class, new ArrayOnboardingStore);

    expect(renderAbout())->toContain('BOUND');
});

it('reports a store configured with useStore without instantiating it', function () {
    app()->bind(ArrayOnboardingStore::class, function (): never {
        throw new RuntimeException('about must not resolve the host store');
    });

    Onboarding::useStore(ArrayOnboardingStore::class);

    expect(renderAbout())->toContain('BOUND');
});

it('never renders a flow key, a step title, or a redirect target', function () {
    Onboarding::register('series-c-payout', Flow::make('Series C Payout')->of([
        Step::make('Upload cap table')
            ->key('upload-cap-table')
            ->cta('Upload the signed table')
            ->route('internal.captable.upload')
            ->url('/internal/cap-table'),
    ]));

    $rendered = renderAbout();

    // Guard the guard: an empty capture would make every negative assertion below
    // pass vacuously.
    expect($rendered)->toContain('1 registered');

    expect($rendered)
        ->not->toContain('series-c-payout')
        ->not->toContain('Series C Payout')
        ->not->toContain('Upload cap table')
        ->not->toContain('upload-cap-table')
        ->not->toContain('Upload the signed table')
        ->not->toContain('internal.captable.upload')
        ->not->toContain('/internal/cap-table');
});
