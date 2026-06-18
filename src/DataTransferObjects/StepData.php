<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class StepData implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public ?string $title,
        public ?string $cta,
        public ?string $action,
        public bool $isCompleted,
        public bool $isOptional,
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'cta' => $this->cta,
            'action' => $this->action,
            'is_completed' => $this->isCompleted,
            'is_optional' => $this->isOptional,
            'meta' => $this->meta,
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
