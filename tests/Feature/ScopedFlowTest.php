<?php

declare(strict_types=1);

use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\User;

it('keeps a flow resolved for one user bound to that user', function (): void {
    Onboarding::register([Step::make('Bio')->key('bio')->completeWhenFilled('bio')]);

    $done = new User(['bio' => 'Hi']);
    $pending = new User(['bio' => null]);

    $doneFlow = $done->onboarding();
    $pendingFlow = $pending->onboarding();

    expect($doneFlow?->isCompleted())->toBeTrue()
        ->and($pendingFlow?->isCompleted())->toBeFalse();
});

it('keeps a resolved flow bound to its subject', function (): void {
    Onboarding::register([Step::make('Bio')->key('bio')->completeWhenFilled('bio')]);

    $done = new User(['bio' => 'Hi']);
    $pending = new User(['bio' => null]);

    $doneFlow = Onboarding::resolveFor($done);
    Onboarding::resolveFor($pending);

    expect($doneFlow?->isCompleted())->toBeTrue();
});
