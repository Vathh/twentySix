<?php

namespace App\Services\Tournament;

use App\Domain\GameScoring\MatchFormat;
use App\Domain\Tournament\TournamentDomain;
use App\Domain\Tournament\TournamentGroupAdvanceDistribution;
use App\Domain\Tournament\TournamentGroupDistribution;
use App\Domain\Tournament\TournamentStartRules;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GrandFinalMode;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Tournament\Tournament;
use App\Repositories\Game\GameRepository;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\Season\SeasonRepository;
use App\Repositories\Tournament\TournamentMatchFormatRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Services\GameScoring\GameAuthorizationService;
use App\Services\Player\PlayerService;
use App\Services\PointScheme\PointSchemeService;
use App\Support\Tournament\PlayoffByePairing;
use App\Support\Tournament\TournamentMatchFormatRequestParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TournamentService
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private GameRepository $gameRepository,
        private GroupStandingRepository $groupStandingRepository,
        private LoginCodeService $loginCodeService,
        private PointSchemeService $pointSchemeService,
        private TournamentStartValidator $startValidator,
        private TournamentMatchFormatRepository $matchFormatRepository,
        private GameAuthorizationService $gameAuthorizationService,
        private \App\Services\PlayoffGame\PlayoffService $playoffService,
        private SeasonRepository $seasonRepository,
        private PlayerService $playerService,
    ) {}

    public function assertCanCreate(?int $seasonId): void
    {
        if ($seasonId !== null) {
            $season = $this->seasonRepository->findModel($seasonId, ['admins']);
            Gate::authorize('update', $season);

            return;
        }

        abort_unless(Auth::user()?->can_create_organizations, 403);
    }

    /**
     * Ładuje turniej (z relacjami do autoryzacji) i weryfikuje, że aktualny użytkownik może nim zarządzać.
     *
     * @param  list<string>  $additionalRelations
     */
    public function loadAndAuthorize(int $tournamentId, array $additionalRelations = []): TournamentDomain
    {
        $allRelations = array_merge($additionalRelations, ['season', 'admins']);
        $eagerLoad = array_unique(array_merge(['admins'], TournamentDomain::eagerLoadRelationsFor($allRelations)));
        $tournament = $this->tournamentRepository->findModel($tournamentId, $eagerLoad);
        $this->gameAuthorizationService->authorizeManageTournament($tournament);

        return TournamentDomain::fromEloquent($tournament, $allRelations);
    }

    /**
     * Surowy model Eloquent — dla przepływów, które muszą przekazać go do innych serwisów
     * (np. TournamentJoinRequestService) lub odczytać pola spoza TournamentDomain.
     */
    public function getModel(int $tournamentId, array $relations = []): Tournament
    {
        return $this->tournamentRepository->findModel($tournamentId, $relations);
    }

    public function getAll(): Collection
    {
        return $this->tournamentRepository->getAll()
            ->sortByDesc(
                fn ($tournament) => $tournament->date?->getTimestamp() ?? PHP_INT_MIN,
            )
            ->values();
    }

    /**
     * @return array{items: list<array{id: int, url: string, title: string, subtitle: string|null, subtitle_missing: bool, status_label: string, status_variant: string}>, has_more: bool}
     */
    public function getIndexPage(int $page): array
    {
        $pageData = $this->tournamentRepository->getPage($page);

        return [
            'items' => $pageData['items']->map(function ($tournament) {
                $date = $tournament->getPlayDateFormatted();

                return [
                    'id' => $tournament->id,
                    'url' => route('tournaments.show', ['tournament' => $tournament->id]),
                    'title' => $tournament->displayTitle(),
                    'subtitle' => $date ? 'Data rozgrywek: '.$date : null,
                    'subtitle_missing' => $date === null,
                    'status_label' => $tournament->status->label(),
                    'status_variant' => $tournament->status->badgeVariant(),
                ];
            })->all(),
            'has_more' => $pageData['has_more'],
        ];
    }

    public function create(
        ?int $seasonId,
        string $name,
        ?string $date = null,
        ?int $createdByUserId = null,
    ): int {
        return $this->tournamentRepository->create($seasonId, $name, $date, $createdByUserId);
    }

    public function addAdmin(int $tournamentId, int $userId): void
    {
        $this->tournamentRepository->addAdmin($tournamentId, $userId);
    }

    public function removeAdmin(int $tournamentId, int $userId): void
    {
        $this->tournamentRepository->removeAdmin($tournamentId, $userId);
    }

    public function getAdmins(int $tournamentId): Collection
    {
        return $this->tournamentRepository->getAdmins($tournamentId);
    }

    /**
     * @throws ValidationException
     */
    public function tryCreateGroupGames(
        int $tournamentId,
        array $playerIds,
        int $groupsCount,
        int $playoffBracketSize,
        array $formatsByStage = [],
        bool $hasConsolationBracket = false,
        ?int $consolationBracketSize = null,
        array $consolationFormatsByStage = [],
    ): bool {
        $this->startValidator->validate(
            playerCount: count($playerIds),
            groupsCount: $groupsCount,
            playoffBracketSize: $playoffBracketSize,
        );

        if ($formatsByStage === []) {
            $formatsByStage = \App\Support\Tournament\TournamentMatchFormatRequestParser::defaultsForBracketSize(
                $playoffBracketSize,
            );
        }

        $groupFormat = MatchFormat::fromArray(
            $formatsByStage[GameStage::GROUP->value] ?? MatchFormat::default()->toArray(),
        );

        $playersAmount = count($playerIds);
        $groupSizes = TournamentGroupDistribution::groupSizes($playersAmount, $groupsCount);
        $groupAdvances = TournamentGroupAdvanceDistribution::distribute($groupSizes, $playoffBracketSize);

        $groups = TournamentGroupDistribution::distribute($playerIds, $groupsCount);

        $gamesToInsert = [];

        foreach ($groups as $groupIndex => $group) {
            foreach ($this->generateGamesForGroup($group) as $game) {
                $gamesToInsert[] = array_merge([
                    'tournament_id' => $tournamentId,
                    'player1_id' => $game['player1_id'],
                    'player2_id' => $game['player2_id'],
                    'player1_score' => 0,
                    'player2_score' => 0,
                    'group_number' => $groupIndex + 1,
                    'status' => GameStatus::SCHEDULED,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $groupFormat->toDatabaseColumns());
            }
        }

        try {
            return DB::transaction(function () use (
                $tournamentId,
                $gamesToInsert,
                $groups,
                $playersAmount,
                $groupsCount,
                $playoffBracketSize,
                $groupAdvances,
                $formatsByStage,
                $hasConsolationBracket,
                $consolationBracketSize,
                $consolationFormatsByStage,
            ) {
                if ($this->tournamentRepository->checkIfTournamentCanBeStarted($tournamentId)) {
                    $this->matchFormatRepository->saveForTournament($tournamentId, $formatsByStage);
                    if ($hasConsolationBracket && $consolationFormatsByStage !== []) {
                        $this->matchFormatRepository->saveForTournament(
                            $tournamentId,
                            $consolationFormatsByStage,
                            \App\Enums\BracketSide::Consolation,
                        );
                    }
                    $this->tournamentRepository->saveStartConfiguration(
                        tournamentId: $tournamentId,
                        playoffBracketSize: $playoffBracketSize,
                        tabletsCount: 1,
                        format: \App\Enums\TournamentFormat::GroupsPlayoff,
                        groupsCount: $groupsCount,
                        groupAdvances: $groupAdvances,
                        hasConsolationBracket: $hasConsolationBracket,
                        consolationBracketSize: $consolationBracketSize,
                    );
                    $this->updatePointSchemeId($tournamentId, $playersAmount);
                    $this->groupStandingRepository->createEmptyStandings($tournamentId, $groups);
                    $this->gameRepository->createGames($gamesToInsert);
                    $this->loginCodeService->generateForTournament($tournamentId);
                    $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::GROUP);

                    return true;
                }

                return false;
            });
        } catch (Throwable $e) {
            $detail = $e->getMessage();
            throw new RuntimeException(
                'Nie udało się stworzyć grup'.($detail !== '' ? ': '.$detail : ''),
                0,
                $e,
            );
        }
    }

    /**
     * Start turnieju single elimination (bez fazy grupowej).
     *
     * @param  list<int>  $playerIds
     * @param  array<string, array<string, int|string>>  $formatsByStage
     *
     * @throws ValidationException
     */
    public function tryStartSingleElimination(
        int $tournamentId,
        array $playerIds,
        array $formatsByStage = [],
    ): bool {
        $playerCount = count($playerIds);

        if ($playerCount < TournamentStartRules::MIN_PLAYERS) {
            throw ValidationException::withMessages([
                'players' => 'Do startu potrzeba co najmniej '.TournamentStartRules::MIN_PLAYERS.' zawodników.',
            ]);
        }

        $bracketSize = \App\Support\Tournament\PlayoffByePairing::nextPowerOfTwo($playerCount);

        if ($bracketSize > TournamentStartRules::MAX_BRACKET_SIZE) {
            throw ValidationException::withMessages([
                'players' => 'Za dużo zawodników na drabinkę (max '.TournamentStartRules::MAX_BRACKET_SIZE.').',
            ]);
        }

        if ($formatsByStage === []) {
            $formatsByStage = TournamentMatchFormatRequestParser::defaultsForEliminationBracketSize($bracketSize);
        }

        try {
            return DB::transaction(function () use (
                $tournamentId,
                $playerIds,
                $playerCount,
                $bracketSize,
                $formatsByStage,
            ) {
                if (! $this->tournamentRepository->checkIfTournamentCanBeStarted($tournamentId)) {
                    return false;
                }

                $this->matchFormatRepository->saveForTournament($tournamentId, $formatsByStage);
                $this->tournamentRepository->saveStartConfiguration(
                    tournamentId: $tournamentId,
                    playoffBracketSize: $bracketSize,
                    tabletsCount: 1,
                    format: \App\Enums\TournamentFormat::SingleElimination,
                    groupsCount: null,
                    groupAdvances: null,
                );
                $this->updatePointSchemeId($tournamentId, $playerCount);
                $this->loginCodeService->generateForTournament($tournamentId);
                $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::PLAYOFF);

                $this->playoffService->generateSingleEliminationBracket($tournamentId, $playerIds);

                return true;
            });
        } catch (Throwable $e) {
            $detail = $e->getMessage();
            throw new RuntimeException(
                'Nie udało się wystartować single elimination'.($detail !== '' ? ': '.$detail : ''),
                0,
                $e,
            );
        }
    }

    /**
     * Start turnieju double elimination (bez fazy grupowej).
     *
     * @param  list<int>  $playerIds
     * @param  array<string, array<string, int|string>>  $formatsByStage
     *
     * @throws ValidationException
     */
    public function tryStartDoubleElimination(
        int $tournamentId,
        array $playerIds,
        \App\Enums\GrandFinalMode $grandFinalMode,
        array $formatsByStage = [],
    ): bool {
        $playerCount = count($playerIds);

        if ($playerCount < TournamentStartRules::MIN_PLAYERS) {
            throw ValidationException::withMessages([
                'players' => 'Do startu potrzeba co najmniej '.TournamentStartRules::MIN_PLAYERS.' zawodników.',
            ]);
        }

        $bracketSize = \App\Support\Tournament\PlayoffByePairing::nextPowerOfTwo($playerCount);

        if ($bracketSize > TournamentStartRules::MAX_BRACKET_SIZE) {
            throw ValidationException::withMessages([
                'players' => 'Za dużo zawodników na drabinkę (max '.TournamentStartRules::MAX_BRACKET_SIZE.').',
            ]);
        }

        if ($formatsByStage === []) {
            $formatsByStage = TournamentMatchFormatRequestParser::defaultsForEliminationBracketSize(
                $bracketSize,
                includeThird: false,
            );
        }

        try {
            return DB::transaction(function () use (
                $tournamentId,
                $playerIds,
                $playerCount,
                $bracketSize,
                $grandFinalMode,
                $formatsByStage,
            ) {
                if (! $this->tournamentRepository->checkIfTournamentCanBeStarted($tournamentId)) {
                    return false;
                }

                $this->matchFormatRepository->saveForTournament($tournamentId, $formatsByStage);
                $this->tournamentRepository->saveStartConfiguration(
                    tournamentId: $tournamentId,
                    playoffBracketSize: $bracketSize,
                    tabletsCount: 1,
                    format: \App\Enums\TournamentFormat::DoubleElimination,
                    groupsCount: null,
                    groupAdvances: null,
                    grandFinalMode: $grandFinalMode,
                );
                $this->updatePointSchemeId($tournamentId, $playerCount);
                $this->loginCodeService->generateForTournament($tournamentId);
                $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::PLAYOFF);

                $this->playoffService->generateDoubleEliminationBracket($tournamentId, $playerIds);

                return true;
            });
        } catch (Throwable $e) {
            $detail = $e->getMessage();
            throw new RuntimeException(
                'Nie udało się wystartować double elimination'.($detail !== '' ? ': '.$detail : ''),
                0,
                $e,
            );
        }
    }

    /**
     * Start turnieju z formularza web (format + input formatów meczu).
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $requestAll
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function runFromWeb(int $tournamentId, array $validated, array $requestAll): bool
    {
        $playerIds = $this->playerService
            ->getTournamentStartPool($tournamentId)
            ->pluck('id')
            ->all();

        if ($playerIds === []) {
            throw new RuntimeException(
                'Brak uczestników turnieju — dodaj zaakceptowanych zawodników lub gości',
            );
        }

        $format = TournamentFormat::from($validated['tournamentFormat']);

        if ($format === TournamentFormat::SingleElimination) {
            $bracketSize = PlayoffByePairing::nextPowerOfTwo(count($playerIds));
            $formatsByStage = TournamentMatchFormatRequestParser::fromRunInput(
                $requestAll,
                $bracketSize,
                includeGroupStage: false,
            );

            return $this->tryStartSingleElimination(
                $tournamentId,
                $playerIds,
                $formatsByStage,
            );
        }

        if ($format === TournamentFormat::DoubleElimination) {
            $bracketSize = PlayoffByePairing::nextPowerOfTwo(count($playerIds));
            $formatsByStage = TournamentMatchFormatRequestParser::fromRunInput(
                $requestAll,
                $bracketSize,
                includeGroupStage: false,
                includeThird: false,
            );
            $grandFinalMode = GrandFinalMode::from(
                $validated['grandFinalMode'] ?? GrandFinalMode::Reset->value,
            );

            return $this->tryStartDoubleElimination(
                $tournamentId,
                $playerIds,
                $grandFinalMode,
                $formatsByStage,
            );
        }

        $groupsCount = (int) $validated['groupsCount'];
        $playoffBracketSize = (int) $validated['playoffBracketSize'];
        $formatsByStage = TournamentMatchFormatRequestParser::fromRunInput(
            $requestAll,
            $playoffBracketSize,
        );

        $remaining = count($playerIds) - $playoffBracketSize;
        $hasConsolationBracket = $this->wantsConsolationBracket($validated, $requestAll);
        $consolationBracketSize = null;
        $consolationFormatsByStage = [];

        if ($hasConsolationBracket) {
            if ($remaining < 2) {
                throw ValidationException::withMessages([
                    'hasConsolationBracket' => 'Drabinka pocieszenia wymaga co najmniej 2 zawodników bez awansu.',
                ]);
            }

            $consolationBracketSize = PlayoffByePairing::nextPowerOfTwo($remaining);
            if ($consolationBracketSize > TournamentStartRules::MAX_BRACKET_SIZE) {
                throw ValidationException::withMessages([
                    'hasConsolationBracket' => 'Za dużo zawodników na drabinkę pocieszenia (max '
                        .TournamentStartRules::MAX_BRACKET_SIZE.').',
                ]);
            }

            $rawConsolation = $requestAll['consolationMatchFormats'] ?? [];
            $consolationFormatsByStage = is_array($rawConsolation) && $rawConsolation !== []
                ? TournamentMatchFormatRequestParser::fromRunInput(
                    ['matchFormats' => $rawConsolation],
                    $consolationBracketSize,
                    includeGroupStage: false,
                )
                : TournamentMatchFormatRequestParser::defaultsForEliminationBracketSize($consolationBracketSize);
        }

        return $this->tryCreateGroupGames(
            $tournamentId,
            $playerIds,
            $groupsCount,
            $playoffBracketSize,
            $formatsByStage,
            $hasConsolationBracket,
            $consolationBracketSize,
            $consolationFormatsByStage,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $requestAll
     */
    private function wantsConsolationBracket(array $validated, array $requestAll): bool
    {
        $raw = $validated['hasConsolationBracket'] ?? $requestAll['hasConsolationBracket'] ?? false;

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    private function generateGamesForGroup(array $group): array
    {
        $games = [];

        for ($i = 0; $i < count($group); $i++) {
            for ($j = $i + 1; $j < count($group); $j++) {
                $games[] = ['player1_id' => $group[$i], 'player2_id' => $group[$j]];
            }
        }

        return $games;
    }

    private function updatePointSchemeId(int $tournamentId, int $playersAmount): void
    {
        $pointScheme = $this->pointSchemeService->findByPlayersAmount($playersAmount);

        $this->tournamentRepository->updatePointSchemeId($tournamentId, $pointScheme->id);
    }
}
