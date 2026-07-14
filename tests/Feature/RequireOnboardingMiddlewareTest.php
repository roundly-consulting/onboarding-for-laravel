<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\User;

beforeEach(function () {
    Route::get('/setup', fn () => 'setup')->name('onboarding.setup');
    Route::get('/external', fn () => 'external')->name('external');
    Route::get('/dashboard', fn () => 'dashboard')
        ->name('dashboard')
        ->middleware('onboarding');
    Route::get('/admin', fn () => 'admin')
        ->name('admin')
        ->middleware('onboarding:admin');
});

it('redirects an unfinished subject to a route-name target', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/dashboard')
        ->assertRedirect('/setup');
});

it('redirects an unfinished subject to a url target', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->url('/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/dashboard')
        ->assertRedirect('/setup');
});

it('passes through when the flow is complete', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => true),
    ]));

    $this->actingAs(new User)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('dashboard');
});

it('passes through when no flow is registered', function () {
    $this->actingAs(new User)
        ->get('/dashboard')
        ->assertOk();
});

it('passes through when the current step has no resolvable target', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->action('users@photo@upload')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/dashboard')
        ->assertOk();
});

it('avoids a redirect loop when already on the step route', function () {
    Route::get('/loop', fn () => 'loop')->middleware('onboarding')->name('onboarding.setup');

    Onboarding::register('default', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/loop')
        ->assertOk()
        ->assertSee('loop');
});

it('avoids a redirect loop when already on the step url target', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('/billing/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/billing/setup')
        ->assertOk()
        ->assertSee('billing');
});

it('avoids a redirect loop when already on an absolute step url target', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url(url('/billing/setup'))->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/billing/setup')
        ->assertOk()
        ->assertSee('billing');
});

it('avoids a redirect loop when already on the legacy action target', function () {
    Route::get('/legacy/setup', fn () => 'legacy')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Setup')->action('/legacy/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/legacy/setup')
        ->assertOk()
        ->assertSee('legacy');
});

it('avoids a redirect loop when the legacy action names the current route', function () {
    Route::get('/loop-action', fn () => 'loop-action')
        ->middleware('onboarding')
        ->name('onboarding.action');

    Onboarding::register('default', new Flow([
        Step::make('Setup')->action('onboarding.action')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/loop-action')
        ->assertOk()
        ->assertSee('loop-action');
});

it('passes through when the current step declares no target at all', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('dashboard');
});

it('still redirects to a url target from a different route', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('/billing/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/dashboard')
        ->assertRedirect('/billing/setup');
});

it('does not confuse a different path with the step url target', function () {
    Route::get('/billing/setup-other', fn () => 'other')
        ->middleware('onboarding')
        ->name('other');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('/billing/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/billing/setup-other')
        ->assertRedirect('/billing/setup');
});

it('selects the flow named by the middleware parameter', function () {
    Onboarding::register('default', new Flow([
        Step::make('Default')->completeIf(fn () => true),
    ]))->register('admin', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/admin')
        ->assertRedirect('/setup');
});

it('handles a guest without error and redirects the null-subject flow', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => false),
    ]));

    $this->get('/dashboard')->assertRedirect('/setup');
});
