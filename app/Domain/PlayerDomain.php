<?php

namespace App\Domain;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Models\Player\Player;
use Illuminate\Support\Collection;

class PlayerDomain
{
    use AssertsRelationsLoaded;

    /** Maksymalna długość opisu profilu gracza (zgodna z regułą walidacji `max:1000`). */
    public const DESCRIPTION_MAX_LENGTH = 1000;

    /** @var list<string> */
    private const RELATIONS = ['achievements'];

    /**
     * @param  Collection<AchievementDomain>  $achievements
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $userId = null,
        public readonly Collection $achievements = new Collection,
        public readonly bool $isBye = false,
    ) {}

    public static function fromEloquent(?Player $player, array $with = []): ?PlayerDomain
    {
        if ($player === null) {
            return null;
        }

        self::assertRelationsLoaded($player, $with, self::RELATIONS);

        return new self(
            id: $player->id,
            name: $player->name,
            userId: $player->user_id,
            achievements: in_array('achievements', $with)
                ? $player->achievements->map(fn ($achievement) => AchievementDomain::fromEloquent($achievement))->values()
                : collect(),
            isBye: (bool) $player->is_bye,
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
