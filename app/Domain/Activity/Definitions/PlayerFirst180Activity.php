<?php

namespace App\Domain\Activity\Definitions;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\ActivityDefinition;
use App\Domain\Activity\ActivityType;

final class PlayerFirst180Activity implements ActivityDefinition
{
    public const SCORE = 180;

    public function type(): ActivityType
    {
        return ActivityType::PlayerFirst180;
    }

    public function channels(): array
    {
        return [ActivityChannel::Friends];
    }

    public function subjectType(): string
    {
        return 'player';
    }

    public function replacesExisting(): bool
    {
        return false;
    }

    public function kind(): array
    {
        return ['code' => 'maximum', 'label' => '180'];
    }

    public function sentence(array $payload): string
    {
        $actor = (string) ($payload['actorName'] ?? 'Gracz');

        return "{$actor} właśnie zaliczył swoje pierwsze 180!";
    }
}
