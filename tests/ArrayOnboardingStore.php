<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Tests;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;

/**
 * An in-memory OnboardingStore double standing in for a host's persistence.
 */
class ArrayOnboardingStore implements OnboardingStore
{
    /** @var array<string, bool> */
    public array $dismissed = [];

    /** @var array<string, DateTimeInterface> */
    public array $completed = [];

    public function isDismissed(Authenticatable|Model|null $subject, string $stepKey): bool
    {
        return $this->dismissed[$stepKey] ?? false;
    }

    public function completedAt(Authenticatable|Model|null $subject, string $stepKey): ?DateTimeInterface
    {
        return $this->completed[$stepKey] ?? null;
    }

    public function markDismissed(Authenticatable|Model|null $subject, string $stepKey): void
    {
        $this->dismissed[$stepKey] = true;
    }
}
