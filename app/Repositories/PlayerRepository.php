<?php

namespace App\Repositories;

use App\Domain\PlayerDomain;
use App\Enums\AssignableEntityType;
use App\Models\League;
use App\Models\Player;
use App\Models\Season;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayerRepository
{
    public function create(string $name, int $userId): PlayerDomain
    {
        $player = Player::create([
            'name' => $name,
            'user_id' => $userId,
        ]);

        return PlayerDomain::fromEloquent($player);
    }

    /**
     * @throws \Throwable
     */
    public function createGuest(string $name, int $targetId, AssignableEntityType $targetType): void
    {
        match ($targetType) {
            AssignableEntityType::LEAGUE => $this->addToLeague($name, $targetId),
            AssignableEntityType::SEASON => $this->addToSeason($name, $targetId)
        };
    }

    public function removeGuest(int $playerId): void
    {
        Player::destroy($playerId);
    }

    /**
     * @throws \Throwable
     */
    public function getRelatedPlayers(int $seasonId): Collection
    {
       $season = Season::with(['league.relatedUsers.player', 'relatedUsers.player', 'guests'])->findOrFail($seasonId);
       $seasonRelatedUsersPlayers = $season->relatedUsers->map(fn($user) => $user->player)->values();
       $seasonGuests = $season->guests;
       $leagueRelatedUsersPlayers = $season->league->relatedUsers->map(fn($user) => $user->player)->values();
       $leagueGuests = $season->league->guests;

        return collect()
                ->merge($seasonRelatedUsersPlayers)
                ->merge($seasonGuests)
                ->merge($leagueRelatedUsersPlayers)
                ->merge($leagueGuests)
                ->unique('id')
                ->values();
    }

    private function addToLeague(string $name, int $leagueId): void
    {
        $league = League::findOrFail($leagueId);
        $league->guests()->create([
            'name' => $name
        ]);
    }

    private function addToSeason(string $name, int $seasonId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->guests()->create([
            'name' => $name
        ]);
    }
}
