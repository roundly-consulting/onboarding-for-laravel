<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;

it('reports when no flows are registered', function () {
    $code = Artisan::call('onboarding:list');

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('No onboarding flows are registered.');
});

it('lists registered flows', function () {
    Onboarding::register('default', Flow::make('Profile Setup')->of([
        Step::make('One'),
        Step::make('Two')->completeIf(fn () => false),
    ]));

    $code = Artisan::call('onboarding:list');

    expect($code)->toBe(0)
        ->and(Artisan::output())
        ->toContain('default')
        ->toContain('Profile Setup')
        ->toContain('50.00%');
});

it('inspects a single flow by key', function () {
    Onboarding::register('default', Flow::make('Profile Setup')->of([
        Step::make('Upload Photo')->key('upload-photo'),
        Step::make('Add a bio')->optional(),
    ]));

    $code = Artisan::call('onboarding:list', ['key' => 'default']);

    expect($code)->toBe(0)
        ->and(Artisan::output())
        ->toContain('upload-photo')
        ->toContain('Add a bio');
});

it('fails when inspecting an unknown flow', function () {
    $code = Artisan::call('onboarding:list', ['key' => 'missing']);

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('No onboarding flow registered under [missing].');
});
