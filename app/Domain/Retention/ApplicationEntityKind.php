<?php

namespace App\Domain\Retention;

use App\Models\League\League;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;

enum ApplicationEntityKind: string
{
    case Organization = 'organization';
    case Season = 'season';
    case Tournament = 'tournament';
    case League = 'league';
    case LeagueSeason = 'league_season';

    public function label(): string
    {
        return match ($this) {
            self::Organization => 'Organizacja',
            self::Season => 'Sezon',
            self::Tournament => 'Turniej',
            self::League => 'Liga',
            self::LeagueSeason => 'Sezon ligowy',
        };
    }

    /**
     * @return class-string<Organization|Season|Tournament|League|LeagueSeason>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Organization => Organization::class,
            self::Season => Season::class,
            self::Tournament => Tournament::class,
            self::League => League::class,
            self::LeagueSeason => LeagueSeason::class,
        };
    }

    public static function fromRoute(string $kind): self
    {
        return self::tryFrom($kind) ?? throw new \InvalidArgumentException('Nieznany byt aplikacji.');
    }
}
