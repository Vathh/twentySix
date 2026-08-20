<?php

namespace App\Repositories\Player;

use App\Domain\PlayerDomain;
use App\Enums\AssignableEntityType;
use App\Models\League\League;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function updateDescription(Player $player, ?string $description): Player
    {
        $player->update([
            'description' => $description,
        ]);

        return $player->fresh();
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
     * Tworzy gościa bez konta (bez organizacji/sezonu), np. dla turnieju jednorazowego.
     * Zwraca surowy model Eloquent — wywołujący potrzebuje ->id.
     */
    public function createGuestPlayer(string $name): Player
    {
        return Player::create([
            'name' => $name,
            'user_id' => null,
            'organization_id' => null,
            'season_id' => null,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function createGuest(string $name, int $targetId, AssignableEntityType $targetType): void
    {
        match ($targetType) {
            AssignableEntityType::ORGANIZATION => $this->addToOrganization($name, $targetId),
            AssignableEntityType::SEASON => $this->addToSeason($name, $targetId),
            AssignableEntityType::LEAGUE => $this->addToLeague($name, $targetId),
        };
    }

    public function removeGuest(int $playerId, AssignableEntityType $targetType, int $targetId): void
    {
        $player = Player::query()->find($playerId);
        if ($player === null || $player->user_id !== null) {
            throw ValidationException::withMessages(['player_id' => 'Nieprawidłowy gość.']);
        }

        $belongs = match ($targetType) {
            AssignableEntityType::ORGANIZATION => (int) $player->organization_id === $targetId,
            AssignableEntityType::SEASON => (int) $player->season_id === $targetId,
            AssignableEntityType::LEAGUE => (int) $player->league_id === $targetId,
        };
        if (! $belongs) {
            throw ValidationException::withMessages(['player_id' => 'Ten gość nie należy do tej puli.']);
        }

        match ($targetType) {
            AssignableEntityType::ORGANIZATION => $player->organization_id = null,
            AssignableEntityType::SEASON => $player->season_id = null,
            AssignableEntityType::LEAGUE => $player->league_id = null,
        };

        if ($player->organization_id === null && $player->season_id === null && $player->league_id === null) {
            $player->delete();

            return;
        }

        $player->save();
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
     * Znajduje gościa o danej nazwie w sezonie, organizacji albo lidze.
     */
    public function findGuestByName(
        string $name,
        ?int $seasonId = null,
        ?int $organizationId = null,
        ?int $leagueId = null,
    ): ?PlayerDomain {
        $query = Player::where('name', $name)
            ->whereNull('user_id'); // Tylko goście

        if ($seasonId) {
            $query->where('season_id', $seasonId);
        }

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
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
     */
    public function generateUniqueGuestName(
        string $baseName,
        ?int $seasonId = null,
        ?int $organizationId = null,
        ?int $leagueId = null,
    ): string {
        if (! $this->findGuestByName($baseName, $seasonId, $organizationId, $leagueId)) {
            return $baseName;
        }

        $counter = 1;
        $newName = $baseName.' '.$counter;

        while ($this->findGuestByName($newName, $seasonId, $organizationId, $leagueId)) {
            $counter++;
            $newName = $baseName.' '.$counter;
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
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function getNamesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Player::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Surowe modele Eloquent dla podanych ID (np. do budowy payloadu z nazwami graczy).
     *
     * @param  list<int>  $ids
     * @return Collection<int, Player>
     */
    public function findManyByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Player::whereIn('id', $ids)->get();
    }

    /**
     * ID gości bez konta (user_id === null) spośród podanych ID graczy.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function getGuestPlayerIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Player::query()
            ->whereIn('id', $ids)
            ->whereNull('user_id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return Collection<int, Player>
     */
    public function searchRegisteredByName(string $nameQuery, int $limit = 50): Collection
    {
        return Player::query()
            ->whereNotNull('user_id')
            ->where('name', 'like', '%'.$nameQuery.'%')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * @throws \Throwable
     * @return Collection<int, PlayerDomain>
     */
    public function getRelatedPlayers(int $seasonId): Collection
    {
       $season = Season::with(['organization.relatedUsers.player', 'relatedUsers.player', 'guests'])->findOrFail($seasonId);
       $seasonRelatedUsersPlayers = $season->relatedUsers->map(fn($user) => $user->player)->values();
       $seasonGuests = $season->guests;
       $organizationRelatedUsersPlayers = $season->organization->relatedUsers->map(fn($user) => $user->player)->values();
       $organizationGuests = $season->organization->guests;

        return collect()
                ->merge($seasonRelatedUsersPlayers)
                ->merge($seasonGuests)
                ->merge($organizationRelatedUsersPlayers)
                ->merge($organizationGuests)
                ->unique('id')
                ->map(fn($player) => PlayerDomain::fromEloquent($player));
    }

    /**
     * Zarejestrowani użytkownicy ze składu organizacji + sezonu (bez gości).
     *
     * @return Collection<int, PlayerDomain>
     */
    public function getRelatedRegisteredUsers(int $seasonId): Collection
    {
        $season = Season::with(['organization.relatedUsers.player', 'relatedUsers.player'])->findOrFail($seasonId);

        return collect()
            ->merge($season->relatedUsers->map(fn ($user) => $user->player))
            ->merge($season->organization->relatedUsers->map(fn ($user) => $user->player))
            ->filter()
            ->unique('id')
            ->map(fn ($player) => PlayerDomain::fromEloquent($player))
            ->values();
    }

    /**
     * Goście sezonu i organizacji (bez zarejestrowanych użytkowników).
     *
     * @return Collection<int, PlayerDomain>
     */
    public function getSeasonGuests(int $seasonId): Collection
    {
        $season = Season::with(['organization.guests', 'guests'])->findOrFail($seasonId);

        return collect()
            ->merge($season->guests)
            ->merge($season->organization->guests)
            ->unique('id')
            ->map(fn ($player) => PlayerDomain::fromEloquent($player))
            ->values();
    }

    private function addToOrganization(string $name, int $organizationId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $organization->guests()->create([
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

    private function addToLeague(string $name, int $leagueId): void
    {
        $league = League::query()->findOrFail($leagueId);
        $league->guests()->create([
            'name' => $name,
        ]);
    }
}












