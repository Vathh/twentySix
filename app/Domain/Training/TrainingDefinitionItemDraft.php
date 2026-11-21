<?php

namespace App\Domain\Training;

final readonly class TrainingDefinitionItemDraft
{
    public function __construct(
        public TrainingItemKind $kind,
        public ?string $exerciseKey,
        public ?int $breakSeconds,
        public ?int $durationSeconds,
        public ?int $dartCount,
        public bool $scored = true,
        public ?string $variant = null,
    ) {}
}
