<?php

namespace App\Domain\Activity;

use DateTimeInterface;

final class ActivityDraft
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ActivityType $type,
        public string $subjectType,
        public int $subjectId,
        public ?int $actorPlayerId,
        public ?int $actorUserId,
        public ?int $organizationId,
        public array $payload,
        public DateTimeInterface $occurredAt,
        public bool $replaceExisting,
        public bool $matchActor = false,
    ) {}
}
