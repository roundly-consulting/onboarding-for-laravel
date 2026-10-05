<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\AuthIdentity;
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

it('avoids a redirect loop when already on the free-form action target', function () {
    Route::get('/action/setup', fn () => 'action-setup')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Setup')->action('/action/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)
        ->get('/action/setup')
        ->assertOk()
        ->assertSee('action-setup');
});

it('avoids a redirect loop when the free-form action names the current route', function () {
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

it('lets a guest through untouched', function () {
    $evaluated = false;

    Onboarding::register('default', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(function () use (&$evaluated): bool {
            $evaluated = true;

            return false;
        }),
    ]));

    $this->get('/dashboard')->assertOk()->assertSee('dashboard');
    $this->get('/admin')->assertOk()->assertSee('admin');

    expect($evaluated)->toBeFalse();
});

it('avoids a redirect loop when the step url target carries a query string', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('/billing/setup?from=onboarding')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/dashboard')->assertRedirect('/billing/setup?from=onboarding');
    $this->actingAs(new User)->get('/billing/setup?from=onboarding')->assertOk()->assertSee('billing');
    $this->actingAs(new User)->get('/billing/setup')->assertOk()->assertSee('billing');
});

it('avoids a redirect loop when the step url target carries a fragment', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('/billing/setup#card')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/billing/setup')->assertOk()->assertSee('billing');
});

it('avoids a redirect loop on an absolute url target with a query string', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url(url('/billing/setup').'/?from=onboarding')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/billing/setup?from=onboarding')->assertOk()->assertSee('billing');
});

it('still redirects to an absolute url target on another host', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('https://billing.example.com/billing/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/billing/setup')->assertRedirect('https://billing.example.com/billing/setup');
});

it('treats an explicit default port as the same url, and another port as a different one', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('http://localhost:80/billing/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/billing/setup')->assertOk()->assertSee('billing');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('http://localhost:8080/billing/setup')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/billing/setup')->assertRedirect('http://localhost:8080/billing/setup');
});

it('avoids a redirect loop when the app is served from a sub-directory', function () {
    Route::get('/billing/setup', fn () => 'billing')->middleware('onboarding');

    Onboarding::register('default', new Flow([
        Step::make('Add billing')->url('/billing/setup')->completeIf(fn () => false),
    ]));

    $this->withServerVariables([
        'SCRIPT_FILENAME' => '/var/www/app/public/index.php',
        'SCRIPT_NAME' => '/app/index.php',
        'PHP_SELF' => '/app/index.php',
    ]);

    $this->actingAs(new User)->get('http://localhost/app/dashboard')
        ->assertRedirect('http://localhost/app/billing/setup');
    $this->actingAs(new User)->get('http://localhost/app/billing/setup')
        ->assertOk()
        ->assertSee('billing');
});

it('honours the flow resolver when no flow key is given', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => false),
    ]))->register('admin', new Flow([
        Step::make('Invite')->completeIf(fn () => true),
    ]));

    Onboarding::resolveUsing(fn ($subject) => $subject?->name === 'boss' ? 'admin' : 'default');

    $this->actingAs(new User(['name' => 'boss']))->get('/dashboard')->assertOk()->assertSee('dashboard');
    $this->actingAs(new User(['name' => 'staff']))->get('/dashboard')->assertRedirect('/setup');
});

it('honours the flow resolver for a subject without the trait', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => false),
    ]))->register('admin', new Flow([
        Step::make('Invite')->completeIf(fn () => true),
    ]));

    Onboarding::resolveUsing(fn ($subject) => $subject instanceof AuthIdentity ? 'admin' : 'default');

    $this->actingAs(new AuthIdentity)->get('/dashboard')->assertOk()->assertSee('dashboard');
});

it('keeps the explicit middleware flow key over the resolver', function () {
    Onboarding::register('default', new Flow([
        Step::make('Default')->completeIf(fn () => true),
    ]))->register('admin', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => false),
    ]));

    Onboarding::resolveUsing(fn () => 'default');

    $this->actingAs(new User)->get('/admin')->assertRedirect('/setup');
});

it('redirects to a route target with parameters', function () {
    Route::get('/teams/{team}', fn (string $team) => "team {$team}")
        ->middleware('onboarding')
        ->name('teams.show');

    Onboarding::register('default', new Flow([
        Step::make('Team')
            ->route('teams.show', fn (?User $user) => ['team' => $user?->team_id])
            ->completeIf(fn () => false),
    ]));

    $this->actingAs(new User(['team_id' => 7]))->get('/dashboard')->assertRedirect('/teams/7');
    $this->actingAs(new User(['team_id' => 7]))->get('/teams/7')->assertOk()->assertSee('team 7');
});

it('passes through instead of failing when a route target misses its parameters', function () {
    Route::get('/teams/{team}', fn (string $team) => "team {$team}")->name('teams.show');

    Onboarding::register('default', new Flow([
        Step::make('Team')->route('teams.show')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/dashboard')->assertOk()->assertSee('dashboard');
});

it('regression: enforces the first incomplete required step past an optional step without a target', function () {
    Route::get('/kyc', fn () => 'kyc')->name('required.step');

    Onboarding::register('default', new Flow([
        Step::make('Tour')->optional()->completeIf(fn () => false),
        Step::make('KYC')->route('required.step')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/dashboard')->assertRedirect('/kyc');
});

it('regression: an incomplete optional step never traps the subject ahead of the required one', function () {
    Route::get('/optional', fn () => 'optional')->middleware('onboarding');
    Route::get('/required-guarded', fn () => 'required')->middleware('onboarding')->name('required.step');

    Onboarding::register('default', new Flow([
        Step::make('Tour')->optional()->url('/optional')->completeIf(fn () => false),
        Step::make('KYC')->route('required.step')->completeIf(fn () => false),
    ]));

    $this->actingAs(new User)->get('/required-guarded')->assertOk()->assertSee('required');
    $this->actingAs(new User)->get('/dashboard')->assertRedirect('/required-guarded');
    $this->actingAs(new User)->get('/optional')->assertRedirect('/required-guarded');
});

it('regression: ignores an unrelated onboarding() method on a subject without the trait', function () {
    Onboarding::register('default', new Flow([
        Step::make('Setup')->route('onboarding.setup')->completeIf(fn () => false),
    ]));

    $subject = new class extends Authenticatable
    {
        public function onboarding(): HasOne
        {
            return $this->hasOne(User::class);
        }
    };

    $this->actingAs($subject)->get('/dashboard')->assertRedirect('/setup');
});
