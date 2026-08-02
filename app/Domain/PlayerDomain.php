<?php

namespace App\Domain;

use App\Models\League\League;
use App\Models\Player\Player;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlayerDomain
{
    /** Maksymalna długość opisu profilu gracza (zgodna z regułą walidacji `max:1000`). */
    public const DESCRIPTION_MAX_LENGTH = 1000;

    /**
     * @param int $id
     * @param string $name
     * @param int|null $userId
     * @param Collection<AchievementDomain> $achievements
     */
    public function __construct(
        public readonly int         $id,
        public readonly string      $name,
        public readonly ?int        $userId = null,
        public readonly Collection $achievements = new Collection()
    )
    {
    }

    /**
     * @param Player|null $player
     * @param array $with
     * @return PlayerDomain|null
     */
    public static function fromEloquent(?Player $player, array $with = []): ?PlayerDomain
    {
        if($player === null) {
            return null;
        }

        $player->loadMissing(array_intersect($with, ['achievements']));

        return new self(
            id: $player->id,
            name: $player->name,
            userId: $player->user_id,
            achievements: in_array('achievements', $with)
                ? $player->achievements->map(fn($achievement) => AchievementDomain::fromEloquent($achievement))->values()
                : collect()
        );
    }

    /** Normalizuje opis profilu: przycina białe znaki, pusty string traktuje jako brak opisu. */
    public static function normalizeDescription(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }
}

