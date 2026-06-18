<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SectionData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<StepData>  $steps
     */
    public function __construct(
        public string $key,
        public ?string $title,
        public float $percentage,
        public bool $isCompleted,
        public array $steps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'percentage' => $this->percentage,
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
