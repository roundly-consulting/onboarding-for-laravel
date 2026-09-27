<?php

declare(strict_types=1);

use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Onboarding\Tests\User;

it('checks usage', function () {
    Onboarding::register('default', [
        Step::make('Upload Photo')
            ->cta('Upload Now')
            ->action('users@photo@upload')
            ->completeIf(fn (?User $user) => (bool) $user->photo),
    ]);

    $user = new User;

    expect($user->onboarding()->currentStep())
        ->title->toBe('Upload Photo')
        ->cta->toBe('Upload Now')
        ->action->toBe('users@photo@upload');

    $user = new User([
        'photo' => 'photo.jpg',
    ]);

    expect($user->onboarding()->currentStep())->toBeNull();
});
