<?php

namespace App\Services\PlayoffGame;

use App\Domain\Game\PlayoffBye;
use App\Domain\Game\PlayoffGameDomain;
use App\Domain\Game\WinnerDestination;
use App\Domain\GameScoring\MatchFormat;
use App\Domain\Tournament\DoubleEliminationMatchFormatMap;
use App\Domain\Tournament\PlayoffConsolationSeeding;
use App\Domain\Tournament\PlayoffFirstRoundSeeding;
use App\DTO\GameResultDTO;
use App\Enums\BracketSide;
use App\Enums\GameType;
use App\Enums\GrandFinalMode;
use App\Enums\PlayerSlot;
use App\Factories\DoubleEliminationBracketFactory;
use App\Factories\PlayoffBracketFactory;
use App\Enums\TournamentFormat;
use App\Repositories\Game\GameRepository;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Tournament\TournamentMatchFormatRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Support\Tournament\PlayoffByePairing;
use App\Support\Tournament\PlayoffSlotIds;
use Illuminate\Support\Collection;

class PlayoffService
{
    private bool $resolvingByes = false;

    public function __construct(
        private PlayoffBracketFactory $bracketFactory,
        private DoubleEliminationBracketFactory $doubleElimFactory,
        private PlayoffGameRepository $gameRepository,
        private GameRepository $groupGameRepository,
        private PlayerRepository $playerRepository,
        private GroupStandingRepository $groupStandingRepository,
        private TournamentRepository $tournamentRepository,
        private TournamentMatchFormatRepository $matchFormatRepository,
    ) {}

    public function generateBracket(int $tournamentId): void
    {
        if ($this->gameRepository->existsForTournament($tournamentId, BracketSide::Main)) {
            return;
        }

        $advancesByGroup = $this->tournamentRepository->getGroupAdvancesByGroupNumber($tournamentId);

        $advancingPlayers = $this->groupStandingRepository
            ->getAdvancingPlayersWithGroups($tournamentId, $advancesByGroup)
            ->all();

        $bracketSize = $this->tournamentRepository->getBracketSize($tournamentId);

        $firstRoundPairs = PlayoffFirstRoundSeeding::pair($advancingPlayers);

        $playoffGames = $this->bracketFactory->create($tournamentId, $bracketSize, $firstRoundPairs);

        $this->gameRepository->createMany(
            $playoffGames,
            $this->formatsByStage($tournamentId, BracketSide::Main),
        );
    }

    /**
     * Gdy sąsiednia para grup jest już rozstrzygnięta, tworzy drabinkę główną
     * (albo uzupełnia oczekujące sloty). Status turnieju zostaje przy grupach.
     */
    public function syncEarlyMainSlots(int $tournamentId): void
    {
        $tournament = $this->tournamentRepository->findModel($tournamentId);
        if ($tournament->format !== TournamentFormat::GroupsPlayoff) {
            return;
        }

        $rawAdvances = $tournament->group_advances;
        if (! is_array($rawAdvances) || $rawAdvances === []) {
            return;
        }

        $advancesByGroup = [];
        foreach (array_values($rawAdvances) as $index => $count) {
            $advancesByGroup[$index + 1] = (int) $count;
        }
        if (! PlayoffFirstRoundSeeding::supportsEarlyRelease($advancesByGroup)) {
            return;
        }

        $groupNumbers = array_keys($advancesByGroup);
        sort($groupNumbers);
        $perGroup = (int) reset($advancesByGroup);
        $finished = array_flip($this->groupGameRepository->finishedGroupNumbers($tournamentId));

        $playersByGroup = [];
        foreach ($groupNumbers as $groupNumber) {
            $playersByGroup[$groupNumber] = isset($finished[$groupNumber]) ? [] : null;
        }

        foreach ($this->groupStandingRepository->getAdvancingPlayersWithGroups($tournamentId, $advancesByGroup) as $player) {
            $groupNumber = (int) $player['group_number'];
            if ($playersByGroup[$groupNumber] === null) {
                continue;
            }
            $playersByGroup[$groupNumber][] = $player;
        }

        $pairs = PlayoffFirstRoundSeeding::pairKnownSlots($playersByGroup, $groupNumbers, $perGroup);
        $hasKnownPair = false;
        foreach ($pairs as $pair) {
            if ($pair[0] !== null && $pair[1] !== null) {
                $hasKnownPair = true;
                break;
            }
        }
        if (! $hasKnownPair) {
            return;
        }

        if (! $this->gameRepository->existsForTournament($tournamentId, BracketSide::Main)) {
            $bracketSize = $this->tournamentRepository->getBracketSize($tournamentId);
            $playoffGames = $this->bracketFactory->create($tournamentId, $bracketSize, $pairs);
            $this->gameRepository->createMany(
                $playoffGames,
                $this->formatsByStage($tournamentId, BracketSide::Main),
            );

            return;
        }

        $bracketSize = $this->tournamentRepository->getBracketSize($tournamentId);
        $round = \App\Support\Tournament\PlayoffSlotIds::stageForFirstRoundMatchCount(intdiv($bracketSize, 2))->value;
        $games = $this->gameRepository->mainRoundGames($tournamentId, $round);
        foreach ($pairs as $index => $pair) {
            $game = $games->get($index);
            if ($game === null || $pair[0] === null || $pair[1] === null) {
                continue;
            }
            $this->gameRepository->replaceScheduledPlayers($game, $pair[0], $pair[1]);
        }
    }

    public function assertGroupCorrectionAllowed(int $tournamentId, int $groupNumber): void
    {
        $playerIds = $this->groupStandingRepository
            ->getByGroupNumberAndTournamentId($groupNumber, $tournamentId)
            ->map(fn ($standing) => $standing->player?->id)
            ->filter()
            ->all();

        if ($this->gameRepository->groupPlayerInStartedMainGame($tournamentId, $playerIds)) {
            throw new \DomainException('Nie można zmienić wyniku grupy — mecz playoff z tymi zawodnikami już trwa albo jest zakończony.');
        }
    }

    public function generateConsolationBracket(int $tournamentId): void
    {
        $tournament = $this->tournamentRepository->findModel($tournamentId);
        if (! $tournament->has_consolation_bracket) {
            return;
        }

        $consolationSize = (int) ($tournament->consolation_bracket_size ?? 0);
        if ($consolationSize < 2) {
            return;
        }

        if ($this->gameRepository->existsForTournament($tournamentId, BracketSide::Consolation)) {
            return;
        }

        $advancesByGroup = $this->tournamentRepository->getGroupAdvancesByGroupNumber($tournamentId);
        $remaining = $this->groupStandingRepository
            ->getGroupLosers($tournamentId, $advancesByGroup)
            ->map(fn ($standing) => [
                'player_id' => $standing->player->id,
                'group_number' => $standing->groupNumber,
                'place' => $standing->place,
            ])
            ->values()
            ->all();

        if (count($remaining) < 2) {
            return;
        }

        $firstRoundPairs = $this->pairConsolationFirstRound($remaining, $consolationSize);
        $includeThird = (bool) $tournament->has_consolation_third_place && $consolationSize >= 4;
        $playoffGames = $this->bracketFactory->create(
            $tournamentId,
            $consolationSize,
            $firstRoundPairs,
            BracketSide::Consolation,
            PlayoffSlotIds::CONSOLATION_PREFIX,
            $includeThird,
        );

        $this->gameRepository->createMany(
            $playoffGames,
            $this->formatsByStage($tournamentId, BracketSide::Consolation),
        );
        $this->resolveScheduledByes($tournamentId);
    }

    /**
     * @param  list<array{player_id: int, group_number: int, place: int}>  $remaining
     * @return list<array{0: int, 1: int}>
     */
    private function pairConsolationFirstRound(array $remaining, int $bracketSize): array
    {
        return PlayoffConsolationSeeding::pair(
            $remaining,
            $bracketSize,
            $this->playerRepository->byePlayerId(),
        );
    }

    /**
     * @return array<string, MatchFormat>
     */
    private function formatsByStage(int $tournamentId, BracketSide $side): array
    {
        return $this->matchFormatRepository->getForTournament($tournamentId, $side)
            ->mapWithKeys(fn ($row) => [$row->stage => $row->toMatchFormat()])
            ->all();
    }

    /**
     * @param  list<int>  $playerIds
     */
    public function generateSingleEliminationBracket(int $tournamentId, array $playerIds): void
    {
        $bracketSize = $this->tournamentRepository->getBracketSize($tournamentId);
        $firstRoundPairs = PlayoffByePairing::pair(
            $playerIds,
            $bracketSize,
            $this->playerRepository->byePlayerId(),
        );
        $playoffGames = $this->bracketFactory->create($tournamentId, $bracketSize, $firstRoundPairs);

        $this->gameRepository->createMany(
            $playoffGames,
            $this->formatsByStage($tournamentId, BracketSide::Main),
        );
        $this->resolveScheduledByes($tournamentId);
    }

    /**
     * @param  list<int>  $playerIds
     */
    public function generateDoubleEliminationBracket(int $tournamentId, array $playerIds): void
    {
        $bracketSize = $this->tournamentRepository->getBracketSize($tournamentId);
        $tournament = $this->tournamentRepository->findModel($tournamentId);
        $reset = ($tournament->grand_final_mode?->value ?? GrandFinalMode::Reset->value)
            === GrandFinalMode::Reset->value;

        $firstRoundPairs = PlayoffByePairing::pair(
            $playerIds,
            $bracketSize,
            $this->playerRepository->byePlayerId(),
        );
        $playoffGames = $this->doubleElimFactory->create(
            $tournamentId,
            $bracketSize,
            $firstRoundPairs,
            $reset,
        );

        $formatsByStage = $this->formatsByStage($tournamentId, BracketSide::Main);

        $this->gameRepository->createMany(
            $playoffGames,
            $this->formatsForDoubleEliminationRounds($playoffGames, $formatsByStage, $bracketSize),
        );
        $this->resolveScheduledByes($tournamentId);
    }

    /**
     * Zamyka mecze z jawnym BYE (gracz vs BYE oraz BYE vs BYE) i kaskaduje awanse.
     * Puste sloty (null) to TBD — czekają na feedera, nie są walkowerem.
     */
    public function resolveScheduledByes(int $tournamentId): void
    {
        if ($this->resolvingByes) {
            return;
        }

        $this->resolvingByes = true;

        try {
            $byePlayerId = $this->playerRepository->byePlayerId();

            for ($guard = 0; $guard < 128; $guard++) {
                $progress = false;

                foreach ($this->gameRepository->getScheduledByeGames($tournamentId, $byePlayerId) as $game) {
                    $winnerId = $game->byeAdvanceWinnerId($byePlayerId);
                    if ($winnerId === null || $game->id === null) {
                        continue;
                    }

                    $byeVsBye = PlayoffBye::isByeId($game->player1Id, $byePlayerId)
                        && PlayoffBye::isByeId($game->player2Id, $byePlayerId);

                    $dto = new GameResultDTO(
                        gameId: $game->id,
                        type: GameType::PLAYOFF,
                        player1Id: $game->player1Id ?? 0,
                        player2Id: $game->player2Id ?? 0,
                        player1Score: $byeVsBye ? 0 : ($winnerId === $game->player1Id ? 1 : 0),
                        player2Score: $byeVsBye ? 0 : ($winnerId === $game->player2Id ? 1 : 0),
                        winnerId: $winnerId,
                        tournamentId: $tournamentId,
                    );

                    $this->gameRepository->finish($dto);
                    $this->propagateResult($dto, $game);
                    $progress = true;
                }

                if (! $progress) {
                    break;
                }
            }
        } finally {
            $this->resolvingByes = false;
        }
    }

    public function update(GameResultDTO $dto, PlayoffGameDomain $gameToUpdate): void
    {
        $this->gameRepository->finish($dto);
        $this->applyWinnerAdvancement($dto, $gameToUpdate);
    }

    public function applyWinnerAdvancement(GameResultDTO $dto, PlayoffGameDomain $gameToUpdate): void
    {
        $this->propagateResult($dto, $gameToUpdate);
        $this->resolveScheduledByes($gameToUpdate->tournamentId);
    }

    private function propagateResult(GameResultDTO $dto, PlayoffGameDomain $gameToUpdate): void
    {
        if ($gameToUpdate->slot === 'GF1') {
            $this->advanceGrandFinal($dto, $gameToUpdate);

            return;
        }

        if ($gameToUpdate->slot === 'GF2' || PlayoffSlotIds::isTerminal($gameToUpdate->slot)) {
            return;
        }

        if ($gameToUpdate->winnerDestinationSlot !== null) {
            $winnerDestination = WinnerDestination::parse($gameToUpdate->winnerDestinationSlot);
            $this->advancePlayer(
                $gameToUpdate->tournamentId,
                $winnerDestination->playoffSlot,
                $dto->winnerId,
                $winnerDestination->playerSlot,
            );
        }

        $loserId = $dto->winnerId === $dto->player1Id ? $dto->player2Id : $dto->player1Id;

        if ($loserId > 0 && $gameToUpdate->loserDestinationSlot !== null) {
            $loserDestination = WinnerDestination::parse($gameToUpdate->loserDestinationSlot);
            $this->advancePlayer(
                $gameToUpdate->tournamentId,
                $loserDestination->playoffSlot,
                $loserId,
                $loserDestination->playerSlot,
            );
        } elseif (
            $loserId > 0
            && $gameToUpdate->winnerDestinationSlot !== null
            && PlayoffSlotIds::isFinalSlot(WinnerDestination::parse($gameToUpdate->winnerDestinationSlot)->playoffSlot)
        ) {
            $winnerDestination = WinnerDestination::parse($gameToUpdate->winnerDestinationSlot);
            $thirdSlot = PlayoffSlotIds::thirdSlotForFinal($winnerDestination->playoffSlot);
            if (! $this->gameRepository->slotExists($gameToUpdate->tournamentId, $thirdSlot)) {
                return;
            }
            $this->advancePlayer(
                $gameToUpdate->tournamentId,
                $thirdSlot,
                $loserId,
                $winnerDestination->playerSlot,
            );
        }
    }

    private function advanceGrandFinal(GameResultDTO $dto, PlayoffGameDomain $game): void
    {
        $tournament = $this->tournamentRepository->findModelOrNull($game->tournamentId);
        if ($tournament === null) {
            return;
        }

        $mode = $tournament->grand_final_mode ?? GrandFinalMode::Single;
        if ($mode !== GrandFinalMode::Reset) {
            return;
        }

        // Slot A = mistrz WB, B = mistrz LB. Reset tylko gdy wygra LB (player2).
        if ($dto->winnerId !== $dto->player2Id) {
            return;
        }

        $this->advancePlayer($game->tournamentId, 'GF2', $dto->player1Id, PlayerSlot::A);
        $this->advancePlayer($game->tournamentId, 'GF2', $dto->player2Id, PlayerSlot::B);
    }

    public function advancePlayer(int $tournamentId, string $playoffSlot, int $winnerId, PlayerSlot $playerSlot): void
    {
        match ($playerSlot) {
            PlayerSlot::A => $this->gameRepository->setPlayer1Slot($tournamentId, $playoffSlot, $winnerId),
            PlayerSlot::B => $this->gameRepository->setPlayer2Slot($tournamentId, $playoffSlot, $winnerId),
        };
    }

    /**
     * @param  Collection<int, PlayoffGameDomain>  $games
     * @param  array<string, MatchFormat>  $formatsByStage
     * @return array<string, MatchFormat>
     */
    private function formatsForDoubleEliminationRounds(Collection $games, array $formatsByStage, int $bracketSize): array
    {
        $byRound = [];

        foreach ($games as $game) {
            if (isset($byRound[$game->round])) {
                continue;
            }

            $stage = DoubleEliminationMatchFormatMap::stageForRound($game->round, $bracketSize);
            $byRound[$game->round] = $formatsByStage[$game->round]
                ?? $formatsByStage[$stage->value]
                ?? MatchFormat::default();
        }

        return $byRound;
    }
}
