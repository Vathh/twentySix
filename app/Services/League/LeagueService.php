<?php

namespace App\Services\League;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\AssignableEntityType;
use App\Enums\LeagueSeasonStatus;
use App\Models\League\League;
use App\Repositories\League\LeagueRepository;
use App\Repositories\Player\PlayerRepository;
use App\Services\Player\PlayerService;
use App\Services\User\UserService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeagueService
{
    public function __construct(
        private LeagueRepository $leagueRepository,
        private PlayerService $playerService,
        private PlayerRepository $playerRepository,
        private UserService $userService,
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

        $eligible = $this->leagueRepository->eligiblePlayers($leagueId);
        if (! $eligible->contains('id', $playerId)) {
            throw ValidationException::withMessages(['player_id' => 'Gracz musi być w puli ligi (powiązany użytkownik albo gość).']);
        }

        $this->leagueRepository->assignPlayer($leagueId, $divisionId, $playerId);
    }

    public function removePlayer(int $leagueId, int $playerId): void
    {
        $this->assertPyramidUnlocked($leagueId);
        $this->leagueRepository->removePlayer($leagueId, $playerId);
    }

    public function updateDivisionCapacity(int $leagueId, int $divisionId, int $capacity): void
    {
        $this->assertPyramidUnlocked($leagueId);
        $league = $this->leagueRepository->findWithRoster($leagueId);
        $division = $league->divisions->firstWhere('id', $divisionId);
        if ($division === null) {
            throw ValidationException::withMessages(['division_id' => 'Nieprawidłowy szczebel.']);
        }
        if ($capacity < 2 || $capacity > 16) {
            throw ValidationException::withMessages(['capacity' => 'Pojemność szczebla: 2–16.']);
        }
        if ($capacity < $division->members->count()) {
            throw ValidationException::withMessages(['capacity' => 'Nie można zmniejszyć poniżej liczby zawodników na szczeblu.']);
        }
        if ((int) $division->promote_direct + (int) $division->promote_playoff >= $capacity) {
            throw ValidationException::withMessages(['capacity' => 'Suma awansów i baraży musi być mniejsza niż pojemność szczebla.']);
        }

        $this->leagueRepository->updateDivisionCapacity($division->id, $capacity);
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

    /**
     * @return array<string, mixed>
     */
    public function showForApi(int $leagueId): array
    {
        $data = $this->showData($leagueId);
        $league = $data['league'];
        $organization = $data['organization'];
        $activeSeason = $data['activeSeason'];

        return [
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
                'description' => $league->description,
            ],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'activeSeason' => $activeSeason === null ? null : [
                'id' => $activeSeason->id,
                'name' => $activeSeason->name,
                'status' => $activeSeason->status->value,
                'statusLabel' => $activeSeason->status->label(),
            ],
            'divisions' => $league->divisions->map(function ($division) {
                return [
                    'id' => $division->id,
                    'position' => (int) $division->position,
                    'name' => $division->name,
                    'capacity' => (int) $division->capacity,
                    'memberCount' => $division->members->count(),
                    'startingScore' => (int) $division->starting_score,
                    'legsToWinSet' => (int) $division->legs_to_win_set,
                    'setsToWinMatch' => (int) $division->sets_to_win_match,
                    'promoteDirect' => (int) $division->promote_direct,
                    'promotePlayoff' => (int) $division->promote_playoff,
                    'members' => $division->members->map(fn ($member) => [
                        'playerId' => $member->player?->id,
                        'playerName' => $member->player?->name,
                        'userId' => $member->player?->user_id,
                    ])->values()->all(),
                ];
            })->values()->all(),
            'seasons' => $league->seasons->map(function ($season) {
                $status = $season->status;

                return [
                    'id' => $season->id,
                    'name' => $season->name,
                    'status' => $status->value,
                    'statusLabel' => $status->label(),
                    'statusVariant' => $status->isOpen()
                        ? 'live'
                        : ($status === LeagueSeasonStatus::FINISHED ? 'finished' : 'planned'),
                    'startDate' => $season->start_date?->format('Y-m-d'),
                    'endDate' => $season->end_date?->format('Y-m-d'),
                ];
            })->values()->all(),
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
        $available = $this->leagueRepository->eligiblePlayers($leagueId)
            ->reject(fn ($player) => $assignedIds->contains($player->id))
            ->values();
        $availableRelated = $available->filter(fn ($player) => $player->user_id !== null)->values();
        $availableGuests = $available->filter(fn ($player) => $player->user_id === null)->values();
        $toChip = fn ($player) => [
            'id' => $player->id,
            'name' => $player->name,
            'kind' => $player->user_id ? 'related' : 'guest',
        ];
        $locked = $league->seasons->contains(fn ($season) => $season->status->locksPyramid());

        return [
            'league' => $league,
            'organization' => $league->organization,
            'divisions' => $league->divisions,
            'availableRelatedPlayers' => $availableRelated,
            'availableGuests' => $availableGuests,
            'locked' => $locked,
            'board' => [
                'locked' => $locked,
                'assignUrl' => route('leagues.roster.assign', $league),
                'removeUrl' => route('leagues.roster.remove', $league),
                'capacityUrl' => route('leagues.roster.capacity', $league),
                'csrfToken' => csrf_token(),
                'relatedManageUrl' => route('leagues.relatedUsers', $league),
                'guestsManageUrl' => route('leagues.guests', $league),
                'divisions' => $league->divisions->map(fn ($division) => [
                    'id' => $division->id,
                    'name' => $division->name,
                    'capacity' => (int) $division->capacity,
                    'promoteDirect' => (int) $division->promote_direct,
                    'promotePlayoff' => (int) $division->promote_playoff,
                    'players' => $division->members->map(fn ($member) => [
                        'id' => (int) $member->player_id,
                        'name' => $member->player?->name ?? '—',
                        'kind' => $member->player?->user_id ? 'related' : 'guest',
                    ])->values()->all(),
                ])->values()->all(),
                'related' => $availableRelated->map($toChip)->values()->all(),
                'guests' => $availableGuests->map($toChip)->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function relatedUsersData(int $leagueId, ?string $search): array
    {
        $league = $this->leagueRepository->findWithOrganizationAdmins($leagueId);
        $league->loadMissing('relatedUsers.player');

        return [
            'league' => $league,
            'relatedUsers' => $this->userService->sortByName(
                $league->relatedUsers->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->player?->name ?? $user->email,
                ])->all(),
            ),
            'users' => $this->userService->search($league->relatedUsers, $search),
        ];
    }

    public function addRelatedUser(int $leagueId, int $userId): void
    {
        $playerDomain = $this->playerRepository->findByUserId($userId);
        if ($playerDomain) {
            $guest = $this->playerService->findGuestByName($playerDomain->name, null, null, $leagueId);
            if ($guest) {
                $newName = $this->playerService->generateUniqueGuestName($playerDomain->name, null, null, $leagueId);
                $this->playerService->updateGuestName($guest->id, $newName);
            }
        }

        $this->leagueRepository->addRelatedUser($leagueId, $userId);
    }

    public function removeRelatedUser(int $leagueId, int $userId): void
    {
        $player = $this->playerRepository->findByUserId($userId);
        if ($player !== null) {
            $this->removeFromRosterIfPresent($leagueId, $player->id);
        }

        $this->leagueRepository->removeRelatedUser($leagueId, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function guestsData(int $leagueId): array
    {
        $league = $this->leagueRepository->findWithOrganizationAdmins($leagueId);
        $league->loadMissing('guests');

        return [
            'league' => $league,
            'guests' => $this->userService->sortByName(
                $league->guests->map(fn ($guest) => [
                    'id' => $guest->id,
                    'name' => $guest->name,
                ])->all(),
            ),
        ];
    }

    public function addGuest(int $leagueId, string $name): void
    {
        $this->playerService->createGuest($name, $leagueId, AssignableEntityType::LEAGUE);
    }

    public function removeGuest(int $leagueId, int $playerId): void
    {
        $this->removeFromRosterIfPresent($leagueId, $playerId);
        $this->playerService->removeGuest($playerId);
    }

    private function removeFromRosterIfPresent(int $leagueId, int $playerId): void
    {
        $league = $this->leagueRepository->findWithRoster($leagueId);
        $onRoster = $league->members->contains('player_id', $playerId);
        if (! $onRoster) {
            return;
        }

        $this->assertPyramidUnlocked($leagueId);
        $this->leagueRepository->removePlayer($leagueId, $playerId);
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
