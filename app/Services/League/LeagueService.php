<?php

namespace App\Services\League;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\LeagueSeasonStatus;
use App\Models\League\League;
use App\Repositories\League\LeagueRepository;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeagueService
{
    public function __construct(
        private LeagueRepository $leagueRepository,
    ) {
    }

    /**
     * @return Collection<int, League>
     */
    public function listForOrganization(int $organizationId): Collection
    {
        return $this->leagueRepository->listForOrganization($organizationId);
    }

    public function getForPolicy(int $leagueId): League
    {
        return $this->leagueRepository->findWithOrganizationAdmins($leagueId);
    }

    /**
     * @param  list<array<string, mixed>>  $divisionInput
     */
    public function create(int $organizationId, string $name, ?string $description, array $divisionInput): League
    {
        $divisions = $this->normalizeDivisions($divisionInput);

        return $this->leagueRepository->create($organizationId, $name, $description, $divisions);
    }

    public function update(int $leagueId, string $name, ?string $description): void
    {
        $this->assertPyramidUnlocked($leagueId);
        $this->leagueRepository->updateDetails($leagueId, $name, $description);
    }

    /**
     * @param  list<array<string, mixed>>  $divisionInput
     */
    public function updateDivisions(int $leagueId, array $divisionInput): void
    {
        $this->assertPyramidUnlocked($leagueId);
        $divisions = $this->normalizeDivisions($divisionInput);
        $this->leagueRepository->replaceDivisions($leagueId, $divisions);
    }

    public function assignPlayer(int $leagueId, int $divisionId, int $playerId): void
    {
        $this->assertPyramidUnlocked($leagueId);
        $league = $this->leagueRepository->findWithRoster($leagueId);
        $division = $league->divisions->firstWhere('id', $divisionId);
        if ($division === null) {
            throw ValidationException::withMessages(['division_id' => 'Nieprawidłowy szczebel.']);
        }
        if ($division->members->count() >= $division->capacity) {
            throw ValidationException::withMessages(['player_id' => 'Ten szczebel jest pełny.']);
        }

        $eligible = $this->leagueRepository->eligiblePlayers($league->organization_id);
        if (! $eligible->contains('id', $playerId)) {
            throw ValidationException::withMessages(['player_id' => 'Gracz musi być powiązany z organizacją albo gościem organizacji.']);
        }

        $this->leagueRepository->assignPlayer($leagueId, $divisionId, $playerId);
    }

    public function removePlayer(int $leagueId, int $playerId): void
    {
        $this->assertPyramidUnlocked($leagueId);
        $this->leagueRepository->removePlayer($leagueId, $playerId);
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(int $leagueId): array
    {
        $league = $this->leagueRepository->findWithRoster($leagueId);

        return [
            'league' => $league,
            'organization' => $league->organization,
            'divisions' => $league->divisions,
            'seasons' => $league->seasons,
            'hasOpenSeason' => $this->leagueRepository->hasOpenSeason($leagueId),
            'activeSeason' => $league->seasons->first(fn ($season) => $season->status->isOpen()),
        ];
    }

    public function hasOpenSeason(int $leagueId): bool
    {
        return $this->leagueRepository->hasOpenSeason($leagueId);
    }

    /**
     * @return array<string, mixed>
     */
    public function rosterData(int $leagueId): array
    {
        $league = $this->leagueRepository->findWithRoster($leagueId);
        $assignedIds = $league->members->pluck('player_id');
        $available = $this->leagueRepository->eligiblePlayers($league->organization_id)
            ->reject(fn ($player) => $assignedIds->contains($player->id))
            ->values();

        return [
            'league' => $league,
            'organization' => $league->organization,
            'divisions' => $league->divisions,
            'availablePlayers' => $available,
            'locked' => $league->seasons->contains(fn ($season) => $season->status->locksPyramid()),
        ];
    }

    private function assertPyramidUnlocked(int $leagueId): void
    {
        $league = $this->leagueRepository->find($leagueId);
        $open = $league->seasons()
            ->whereIn('status', [
                LeagueSeasonStatus::IN_PROGRESS->value,
                LeagueSeasonStatus::PLAYOFFS->value,
            ])
            ->exists();

        if ($open) {
            throw new DomainException('W trakcie sezonu ligowego nie można zmieniać piramidy ani składu.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $divisionInput
     * @return list<array<string, mixed>>
     */
    private function normalizeDivisions(array $divisionInput): array
    {
        if ($divisionInput === []) {
            throw ValidationException::withMessages(['divisions' => 'Liga musi mieć przynajmniej jeden szczebel.']);
        }

        $normalized = [];
        foreach (array_values($divisionInput) as $position => $row) {
            $format = MatchFormat::fromArray([
                'startingScore' => $row['startingScore'] ?? $row['starting_score'] ?? 501,
                'legsToWinSet' => $row['legsToWinSet'] ?? $row['legs_to_win_set'] ?? 2,
                'setsToWinMatch' => $row['setsToWinMatch'] ?? $row['sets_to_win_match'] ?? 1,
                'gameType' => 'x01',
            ]);
            if (! $format->isX01()) {
                throw ValidationException::withMessages([
                    "divisions.$position" => 'Liga indywidualna obsługuje tylko format X01.',
                ]);
            }
            try {
                $format->validate();
            } catch (DomainException $e) {
                throw ValidationException::withMessages(["divisions.$position" => $e->getMessage()]);
            }

            $capacity = (int) ($row['capacity'] ?? 0);
            if ($capacity < 2 || $capacity > 16) {
                throw ValidationException::withMessages(["divisions.$position.capacity" => 'Pojemność szczebla: 2–16.']);
            }

            $promoteDirect = $position === 0 ? 0 : (int) ($row['promoteDirect'] ?? $row['promote_direct'] ?? 0);
            $promotePlayoff = $position === 0 ? 0 : (int) ($row['promotePlayoff'] ?? $row['promote_playoff'] ?? 0);
            if ($promoteDirect + $promotePlayoff >= $capacity) {
                throw ValidationException::withMessages([
                    "divisions.$position" => 'Suma awansów i baraży musi być mniejsza niż pojemność szczebla.',
                ]);
            }

            $normalized[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'name' => (string) $row['name'],
                'capacity' => $capacity,
                'starting_score' => $format->startingScore,
                'legs_to_win_set' => $format->legsToWinSet,
                'sets_to_win_match' => $format->setsToWinMatch,
                'game_type' => 'x01',
                'promote_direct' => $promoteDirect,
                'promote_playoff' => $promotePlayoff,
            ];
        }

        return $normalized;
    }
}
