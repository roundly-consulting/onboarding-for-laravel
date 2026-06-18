<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class FlowData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<StepData>  $steps
     */
    public function __construct(
        public ?string $title,
        public float $percentage,
        public ?StepData $nextStep,
        public ?StepData $currentStep,
        public array $steps,
        public bool $isCompleted,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'percentage' => $this->percentage,
            'next_step' => $this->nextStep?->toArray(),
            'current_step' => $this->currentStep?->toArray(),
            'is_completed' => $this->isCompleted,
            'steps' => array_map(
                static fn (StepData $step): array => $step->toArray(),
                $this->steps,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
