<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A read-only persistence seam the package consumes when a host binds an
 * implementation. The package ships no implementation, table, or migration and
 * is a complete no-op when nothing is bound — it only ever reads from the store.
 *
 * Hosts own the writes: persist completions/dismissals (e.g. by listening to the
 * package's events) and expose them through this contract.
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
}
