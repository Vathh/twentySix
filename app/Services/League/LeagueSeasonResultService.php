<?php

namespace App\Services\League;

use App\Domain\GameScoring\GameLegScoreValidator;
use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameKind;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueWalkoverType;
use App\Models\League\LeagueGame;
use App\Repositories\League\LeagueRepository;
use App\Repositories\League\LeagueSeasonRepository;
use App\Services\Badge\BadgeAwardService;
use App\Services\Badge\CareerBadgeService;
use App\Services\Player\PlayerOverviewService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeagueSeasonResultService
{
    public function __construct(
        private LeagueRepository $leagueRepository,
        private LeagueSeasonRepository $leagueSeasonRepository,
        private PlayerOverviewService $playerOverviewService,
        private BadgeAwardService $badgeAwardService,
        private CareerBadgeService $careerBadgeService,
    ) {}

    public function getGameForPolicy(int $gameId): LeagueGame
    {
        return $this->leagueSeasonRepository->findGame($gameId);
    }

    public function recordResult(int $gameId, int $player1Score, int $player2Score): void
    {
        $game = $this->requireOpenGame($gameId);
        $this->badgeAwardService->retractForGame(GameKind::LEAGUE, $game->id);
        $format = MatchFormat::fromRecord($game);
        try {
            $winnerId = GameLegScoreValidator::validateAndResolveWinner(
                $game->player1_id,
                $game->player2_id,
                $player1Score,
                $player2Score,
                $format,
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['player1_score' => $e->getMessage()]);
        }

        $this->leagueSeasonRepository->updateGame($game->id, [
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
            'winner_id' => $winnerId,
            'status' => LeagueGameStatus::FINISHED,
            'walkover_type' => LeagueWalkoverType::NONE,
        ]);
        $this->playerOverviewService->rebuildRegistered([(int) $game->player1_id, (int) $game->player2_id]);
        $this->careerBadgeService->syncWinStreakForPlayers([(int) $game->player1_id, (int) $game->player2_id]);
    }

    public function recordWalkover(int $gameId, string $type, ?int $winnerPlayerId): void
    {
        $game = $this->requireOpenGame($gameId);
        $this->badgeAwardService->retractForGame(GameKind::LEAGUE, $game->id);
        $walkover = LeagueWalkoverType::from($type);
        $format = MatchFormat::fromRecord($game);

        if ($walkover === LeagueWalkoverType::BOTH) {
            $this->leagueSeasonRepository->updateGame($game->id, [
                'player1_score' => 0,
                'player2_score' => 0,
                'winner_id' => null,
                'status' => LeagueGameStatus::FINISHED,
                'walkover_type' => LeagueWalkoverType::BOTH,
            ]);
            $this->playerOverviewService->rebuildRegistered([(int) $game->player1_id, (int) $game->player2_id]);
            $this->careerBadgeService->syncWinStreakForPlayers([(int) $game->player1_id, (int) $game->player2_id]);

            return;
        }

        if ($winnerPlayerId === null || ! in_array($winnerPlayerId, [$game->player1_id, $game->player2_id], true)) {
            throw ValidationException::withMessages(['winner_id' => 'Wskaż zwycięzcę walkowera.']);
        }

        [$s1, $s2] = GameLegScoreValidator::walkoverScores($winnerPlayerId, $game->player1_id, $format);
        $this->leagueSeasonRepository->updateGame($game->id, [
            'player1_score' => $s1,
            'player2_score' => $s2,
            'winner_id' => $winnerPlayerId,
            'status' => LeagueGameStatus::FINISHED,
            'walkover_type' => LeagueWalkoverType::SINGLE,
        ]);
        $this->playerOverviewService->rebuildRegistered([(int) $game->player1_id, (int) $game->player2_id]);
        $this->careerBadgeService->syncWinStreakForPlayers([(int) $game->player1_id, (int) $game->player2_id]);
    }

    public function extendGame(int $gameId, string $deadlineAt): void
    {
        $game = $this->requireOpenGame($gameId);
        $this->leagueSeasonRepository->extendGameDeadline($game->id, Carbon::parse($deadlineAt)->endOfDay());
    }

    public function withdraw(int $seasonId, int $playerId): void
    {
        DB::transaction(function () use ($seasonId, $playerId) {
            $season = $this->leagueSeasonRepository->findWithGraph($seasonId);
            if (! $season->status->isOpen()) {
                throw new DomainException('Rezygnacja jest możliwa tylko w trakcie sezonu.');
            }
            $participant = $season->participants->firstWhere('player_id', $playerId);
            if ($participant === null || $participant->withdrawn_at !== null) {
                throw ValidationException::withMessages(['player_id' => 'Ten zawodnik nie jest aktywny w sezonie.']);
            }

            $voidedIds = $this->leagueSeasonRepository->voidGamesForPlayer($season->id, $playerId);
            foreach ($voidedIds as $gameId) {
                $this->badgeAwardService->retractForGame(GameKind::LEAGUE, $gameId);
            }
            $this->careerBadgeService->syncWinStreakForLeagueGames($voidedIds);
            $this->leagueSeasonRepository->markWithdrawn($season->id, $playerId, now());
            $this->leagueRepository->removePlayer($season->league_id, $playerId);
        });
    }

    private function requireOpenGame(int $gameId): LeagueGame
    {
        $game = $this->leagueSeasonRepository->findGame($gameId);
        if (! $game->season->status->isOpen()) {
            throw new DomainException('Sezon jest zamknięty.');
        }
        if ($game->status === LeagueGameStatus::VOIDED) {
            throw new DomainException('Ten mecz został anulowany (rezygnacja).');
        }
        if (in_array($game->status, [LeagueGameStatus::LOBBY, LeagueGameStatus::IN_PROGRESS], true)) {
            throw new DomainException('Mecz jest w lobby albo w trakcie — nie wpisuj wyniku ręcznie.');
        }

        return $game;
    }
}
