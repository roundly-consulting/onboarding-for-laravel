<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The persistence seam the package uses once a host provides one — through
 * `Onboarding::useStore()` or a container binding. The package ships no implementation,
 * table, or migration and is stateless without a store.
 *
 * Hosts own the storage: persist completions (e.g. by listening to the package's
 * `StepCompleted` event) and dismissals (`markDismissed()`, called when a subject
 * dismisses an optional step), and expose them through the two reads.
 */
interface OnboardingStore
{
    /**
     * Has the subject dismissed this (optional) step?
     */
    public function isDismissed(Authenticatable|Model|null $subject, string $stepKey): bool;

    /**
     * When (if ever) did the subject complete this step? null = unknown / never.
     */
    public function completedAt(Authenticatable|Model|null $subject, string $stepKey): ?DateTimeInterface;

    /**
     * Record that the subject dismissed this (optional, dismissible) step.
     */
    public function markDismissed(Authenticatable|Model|null $subject, string $stepKey): void;
}
