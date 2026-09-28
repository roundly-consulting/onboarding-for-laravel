<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Testing;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Contracts\OnboardingStore;
use WeakMap;

/**
 * The store behind {@see OnboardingFake}: completions and dismissals held in memory, per
 * subject. A saved model is identified by its class and key, any other authenticatable by
 * its class and auth identifier, a null subject is its own bucket, and a subject without an
 * identifier (an unsaved model) by the object itself.
 */
final class InMemoryOnboardingStore implements OnboardingStore
{
    /** @var array<string, array{dismissed: array<string, true>, completed: array<string, DateTimeInterface>}> */
    private array $identified = [];

    /** @var WeakMap<object, array{dismissed: array<string, true>, completed: array<string, DateTimeInterface>}> */
    private WeakMap $anonymous;

    public function __construct()
    {
        $this->anonymous = new WeakMap;
    }

    public function isDismissed(Authenticatable|Model|null $subject, string $stepKey): bool
    {
        return isset($this->state($subject)['dismissed'][$stepKey]);
    }

    public function completedAt(Authenticatable|Model|null $subject, string $stepKey): ?DateTimeInterface
    {
        return $this->state($subject)['completed'][$stepKey] ?? null;
    }

    public function markDismissed(Authenticatable|Model|null $subject, string $stepKey): void
    {
        $state = $this->state($subject);
        $state['dismissed'][$stepKey] = true;

        $this->write($subject, $state);
    }

    public function markCompleted(Authenticatable|Model|null $subject, string $stepKey, ?DateTimeInterface $at = null): void
    {
        $state = $this->state($subject);
        $state['completed'][$stepKey] = $at ?? new DateTimeImmutable;

        $this->write($subject, $state);
    }

    /**
     * @return array{dismissed: array<string, true>, completed: array<string, DateTimeInterface>}
     */
    private function state(Authenticatable|Model|null $subject): array
    {
        $empty = ['dismissed' => [], 'completed' => []];

        if ($subject === null) {
            return $this->identified['null'] ?? $empty;
        }

        $key = self::identify($subject);

        return $key === null ? ($this->anonymous[$subject] ?? $empty) : ($this->identified[$key] ?? $empty);
    }

    /**
     * @param  array{dismissed: array<string, true>, completed: array<string, DateTimeInterface>}  $state
     */
    private function write(Authenticatable|Model|null $subject, array $state): void
    {
        if ($subject === null) {
            $this->identified['null'] = $state;

            return;
        }

        $key = self::identify($subject);

        if ($key === null) {
            $this->anonymous[$subject] = $state;

            return;
        }

        $this->identified[$key] = $state;
    }

    /**
     * A stable string for a subject with an identifier, or null for one without (an
     * unsaved model), which is then tracked by object.
     */
    private static function identify(Authenticatable|Model $subject): ?string
    {
        $identifier = $subject instanceof Model ? $subject->getKey() : $subject->getAuthIdentifier();

        return is_scalar($identifier) ? $subject::class.'#'.$identifier : null;
    }
}
