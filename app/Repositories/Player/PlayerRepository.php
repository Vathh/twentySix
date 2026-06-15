<?php

namespace App\Repositories\Player;

use App\Domain\PlayerDomain;
use App\Enums\AssignableEntityType;
use App\Models\League\League;
use App\Models\Player\Player;
use App\Models\Season\Season;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayerRepository
{
    /**
     * @param string $name
     * @param int $userId
     * @return PlayerDomain
     */
    public function create(string $name, int $userId): PlayerDomain
    {
        $player = Player::create([
            'name' => $name,
            'user_id' => $userId,
        ]);

        return PlayerDomain::fromEloquent($player);
    }

    public function createQuickGameGuest(string $name): PlayerDomain
    {
        $player = Player::create([
            'name' => $name,
            'user_id' => null,
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
     * Zmienia nazwę gościa
     * @param int $playerId
     * @param string $newName
     * @return void
     */
    public function updateGuestName(int $playerId, string $newName): void
    {
        Player::where('id', $playerId)
            ->whereNull('user_id') // Tylko goście
            ->update(['name' => $newName]);
    }

    /**
     * Znajduje gościa o danej nazwie w sezonie lub lidze
     * @param string $name
     * @param int|null $seasonId
     * @param int|null $leagueId
     * @return PlayerDomain|null
     */
    public function findGuestByName(string $name, ?int $seasonId = null, ?int $leagueId = null): ?PlayerDomain
    {
        $query = Player::where('name', $name)
            ->whereNull('user_id'); // Tylko goście

        if ($seasonId) {
            $query->where('season_id', $seasonId);
        }

        if ($leagueId) {
            $query->where('league_id', $leagueId);
        }

        $player = $query->first();

        return $player ? PlayerDomain::fromEloquent($player) : null;
    }

    /**
     * Generuje unikalną nazwę dla gościa (dodaje numer jeśli potrzeba)
     * Format: "Tomek", "Tomek 1", "Tomek 2", itd.
     * @param string $baseName
     * @param int|null $seasonId
     * @param int|null $leagueId
     * @return string
     */
    public function generateUniqueGuestName(string $baseName, ?int $seasonId = null, ?int $leagueId = null): string
    {
        // Sprawdź czy sama nazwa bazowa jest wolna
        if (!$this->findGuestByName($baseName, $seasonId, $leagueId)) {
            return $baseName;
        }

        // Jeśli nie, dodaj numer
        $counter = 1;
        $newName = $baseName . ' ' . $counter;

        while ($this->findGuestByName($newName, $seasonId, $leagueId)) {
            $counter++;
            $newName = $baseName . ' ' . $counter;
        }

        return $newName;
    }

    /**
     * Znajduje gracza po ID
     * @param int $playerId
     * @return PlayerDomain|null
     */
    public function findById(int $playerId): ?PlayerDomain
    {
        $player = Player::find($playerId);
        return $player ? PlayerDomain::fromEloquent($player) : null;
    }

    /**
     * Znajduje gracza po user_id
     * @param int $userId
     * @return PlayerDomain|null
     */
    public function findByUserId(int $userId): ?PlayerDomain
    {
        $player = Player::where('user_id', $userId)->first();
        return $player ? PlayerDomain::fromEloquent($player) : null;
    }

    /**
     * @throws \Throwable
     * @return Collection<int, PlayerDomain>
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
                ->map(fn($player) => PlayerDomain::fromEloquent($player));
    }

    /**
     * Zarejestrowani użytkownicy ze składu ligi + sezonu (bez gości).
     *
     * @return Collection<int, PlayerDomain>
     */
    public function getRelatedRegisteredUsers(int $seasonId): Collection
    {
        $season = Season::with(['league.relatedUsers.player', 'relatedUsers.player'])->findOrFail($seasonId);

        return collect()
            ->merge($season->relatedUsers->map(fn ($user) => $user->player))
            ->merge($season->league->relatedUsers->map(fn ($user) => $user->player))
            ->filter()
            ->unique('id')
            ->map(fn ($player) => PlayerDomain::fromEloquent($player))
            ->values();
    }

    /**
     * Goście sezonu i ligi (bez zarejestrowanych użytkowników).
     *
     * @return Collection<int, PlayerDomain>
     */
    public function getSeasonGuests(int $seasonId): Collection
    {
        $season = Season::with(['league.guests', 'guests'])->findOrFail($seasonId);

        return collect()
            ->merge($season->guests)
            ->merge($season->league->guests)
            ->unique('id')
            ->map(fn ($player) => PlayerDomain::fromEloquent($player))
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












