<?php

namespace App\Services\League;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\LeagueGameStatus;
use App\Models\League\LeagueGame;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\League\LeagueGameRepository;
use App\Services\Push\InvitationPushService;
use Carbon\Carbon;
use DomainException;

class LeagueGamePlayService
{
    public function __construct(
        private LeagueGameRepository $leagueGameRepository,
        private InvitationPushService $invitationPushService,
    ) {
    }

    /**
     * @return array{games: list<array<string, mixed>>}
     */
    public function mine(User $user): array
    {
        $player = $this->requirePlayer($user);

        return [
            'games' => $this->leagueGameRepository->mineForPlayer($player->id)
                ->map(fn (LeagueGame $game) => $this->serialize($game, $player->id))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{invitations: list<array<string, mixed>>}
     */
    public function invitations(User $user): array
    {
        $player = $this->requirePlayer($user);

        return [
            'invitations' => $this->leagueGameRepository->pendingInvitationsForPlayer($player->id)
                ->map(fn (LeagueGame $game) => $this->serializeInvitation($game, $player->id))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $user, int $gameId): array
    {
        $player = $this->requirePlayer($user);
        $game = $this->leagueGameRepository->findForPlay($gameId);
        $this->assertParticipant($game, $player->id);

        return $this->serialize($game, $player->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function openLobby(User $user, int $gameId): array
    {
        $player = $this->requirePlayer($user);
        $game = $this->leagueGameRepository->findForPlay($gameId);
        $this->assertParticipant($game, $player->id);

        if ($game->status === LeagueGameStatus::LOBBY && (int) $game->lobby_host_player_id === $player->id) {
            return $this->serialize($game, $player->id);
        }

        if ($game->status !== LeagueGameStatus::SCHEDULED) {
            throw new DomainException('Ten mecz nie czeka na start.');
        }
        if (! $game->season->status->isOpen()) {
            throw new DomainException('Sezon ligowy nie jest otwarty.');
        }
        if (! $this->isInCurrentWindow($game)) {
            throw new DomainException('Mecz można rozpocząć tylko w aktualnym oknie kolejki.');
        }

        $opponent = $this->opponent($game, $player->id);
        if ($opponent->user_id === null) {
            throw new DomainException('Przeciwnik nie ma konta — wynik wpisze admin na stronie.');
        }
        if ($this->leagueGameRepository->hasActivePlaySession($player->id, $game->id)) {
            throw new DomainException('Masz już otwarte lobby albo mecz ligowy w trakcie.');
        }
        if ($this->leagueGameRepository->hasActivePlaySession((int) $opponent->id, $game->id)) {
            throw new DomainException('Przeciwnik ma już otwarte lobby albo mecz ligowy w trakcie.');
        }

        $game->status = LeagueGameStatus::LOBBY;
        $game->lobby_host_player_id = $player->id;
        $game->opponent_accepted_at = null;
        $game->scoring_host_player_id = null;
        $this->leagueGameRepository->save($game);

        $this->invitationPushService->notifyLeagueGameInvitation(
            (int) $opponent->user_id,
            $game->id,
            $player->name,
        );

        return $this->serialize($game->fresh([
            'player1',
            'player2',
            'matchday',
            'seasonDivision',
            'season.league',
        ]), $player->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(User $user, int $gameId): array
    {
        $player = $this->requirePlayer($user);
        $game = $this->requireLobby($gameId);
        $this->assertParticipant($game, $player->id);

        if ((int) $game->lobby_host_player_id === $player->id) {
            throw new DomainException('Host nie akceptuje własnego lobby.');
        }
        if ($game->opponent_accepted_at !== null) {
            return $this->serialize($game, $player->id);
        }

        $game->opponent_accepted_at = now();
        $this->leagueGameRepository->save($game);

        return $this->serialize($game->fresh([
            'player1',
            'player2',
            'matchday',
            'seasonDivision',
            'season.league',
        ]), $player->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(User $user, int $gameId): array
    {
        $player = $this->requirePlayer($user);
        $game = $this->requireLobby($gameId);
        $this->assertParticipant($game, $player->id);

        if ((int) $game->lobby_host_player_id === $player->id) {
            throw new DomainException('Host anuluje lobby, a nie odrzuca zaproszenia.');
        }

        $this->resetLobby($game);

        return $this->serialize($game->fresh([
            'player1',
            'player2',
            'matchday',
            'seasonDivision',
            'season.league',
        ]), $player->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(User $user, int $gameId): array
    {
        $player = $this->requirePlayer($user);
        $game = $this->requireLobby($gameId);
        $this->assertParticipant($game, $player->id);

        if ((int) $game->lobby_host_player_id !== $player->id) {
            throw new DomainException('Tylko gospodarz lobby może je anulować.');
        }

        $this->resetLobby($game);

        return $this->serialize($game->fresh([
            'player1',
            'player2',
            'matchday',
            'seasonDivision',
            'season.league',
        ]), $player->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function start(User $user, int $gameId): array
    {
        $player = $this->requirePlayer($user);
        $game = $this->requireLobby($gameId);
        $this->assertParticipant($game, $player->id);

        if ((int) $game->lobby_host_player_id !== $player->id) {
            throw new DomainException('Tylko gospodarz lobby może wystartować mecz.');
        }
        if ($game->opponent_accepted_at === null) {
            throw new DomainException('Poczekaj, aż przeciwnik zaakceptuje zaproszenie.');
        }

        $game->status = LeagueGameStatus::IN_PROGRESS;
        $game->scoring_host_player_id = $player->id;
        $this->leagueGameRepository->save($game);

        return $this->serialize($game->fresh([
            'player1',
            'player2',
            'matchday',
            'seasonDivision',
            'season.league',
        ]), $player->id);
    }

    public function assertCanViewScoring(User $user, LeagueGame $game): void
    {
        $player = $this->requirePlayer($user);
        $this->assertParticipant($game, $player->id);
        if (! in_array($game->status, [LeagueGameStatus::IN_PROGRESS, LeagueGameStatus::FINISHED], true)) {
            throw new DomainException('Scoring jest dostępny po starcie meczu.');
        }
    }

    public function assertCanScore(User $user, LeagueGame $game): void
    {
        $this->assertCanViewScoring($user, $game);
        $player = $this->requirePlayer($user);
        if ((int) $game->scoring_host_player_id !== $player->id) {
            throw new DomainException('Wynik wpisuje gospodarz na jednym urządzeniu.');
        }
        if ($game->status !== LeagueGameStatus::IN_PROGRESS) {
            throw new DomainException('Mecz nie jest w trakcie.');
        }
    }

    private function requireLobby(int $gameId): LeagueGame
    {
        $game = $this->leagueGameRepository->findForPlay($gameId);
        if ($game->status !== LeagueGameStatus::LOBBY) {
            throw new DomainException('To lobby nie jest już aktywne.');
        }

        return $game;
    }

    private function resetLobby(LeagueGame $game): void
    {
        $game->status = LeagueGameStatus::SCHEDULED;
        $game->lobby_host_player_id = null;
        $game->opponent_accepted_at = null;
        $game->scoring_host_player_id = null;
        $this->leagueGameRepository->save($game);
    }

    private function requirePlayer(User $user): Player
    {
        $player = $user->player;
        if ($player === null) {
            throw new DomainException('Konto nie ma profilu gracza.');
        }

        return $player;
    }

    private function assertParticipant(LeagueGame $game, int $playerId): void
    {
        if (! in_array($playerId, [(int) $game->player1_id, (int) $game->player2_id], true)) {
            throw new DomainException('Nie jesteś zawodnikiem w tym meczu.');
        }
    }

    private function opponent(LeagueGame $game, int $playerId): Player
    {
        $opponent = (int) $game->player1_id === $playerId ? $game->player2 : $game->player1;
        if ($opponent === null) {
            throw new DomainException('Brak przeciwnika.');
        }

        return $opponent;
    }

    public function isInCurrentWindow(LeagueGame $game): bool
    {
        $now = Carbon::now();
        if ($game->matchday !== null) {
            $start = $game->matchday->window_start->copy()->startOfDay();
            $end = $game->matchday->window_end->copy()->endOfDay();

            return $now->gte($start) && $now->lte($end);
        }

        if ($game->deadline_at === null) {
            return true;
        }

        return $now->lte($game->deadline_at);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(LeagueGame $game, int $viewerPlayerId): array
    {
        $format = MatchFormat::fromRecord($game);
        $isHost = (int) $game->lobby_host_player_id === $viewerPlayerId;
        $opponent = $this->opponent($game, $viewerPlayerId);
        $inWindow = $this->isInCurrentWindow($game);
        $opponentHasAccount = $opponent->user_id !== null;
        $canOpenLobby = $game->status === LeagueGameStatus::SCHEDULED
            && $inWindow
            && $opponentHasAccount;
        $canEnterLobby = $game->status === LeagueGameStatus::LOBBY;
        $canStartScoring = $game->status === LeagueGameStatus::LOBBY
            && $isHost
            && $game->opponent_accepted_at !== null;
        $canResumeScoring = $game->status === LeagueGameStatus::IN_PROGRESS
            && (int) $game->scoring_host_player_id === $viewerPlayerId;

        return [
            'id' => $game->id,
            'status' => $game->status->value,
            'purpose' => $game->purpose->value,
            'isHost' => $isHost,
            'isScoringHost' => (int) $game->scoring_host_player_id === $viewerPlayerId,
            'opponentAccepted' => $game->opponent_accepted_at !== null,
            'inCurrentWindow' => $inWindow,
            'opponentHasAccount' => $opponentHasAccount,
            'canOpenLobby' => $canOpenLobby,
            'canEnterLobby' => $canEnterLobby,
            'canStartScoring' => $canStartScoring,
            'canResumeScoring' => $canResumeScoring,
            'canAccept' => $canEnterLobby && ! $isHost && $game->opponent_accepted_at === null,
            'canReject' => $canEnterLobby && ! $isHost,
            'canCancel' => $canEnterLobby && $isHost,
            'format' => $format->toArray(),
            'formatLabel' => $format->formatLabel(),
            'league' => [
                'id' => $game->season->league->id,
                'name' => $game->season->league->name,
            ],
            'season' => [
                'id' => $game->season->id,
                'name' => $game->season->name,
            ],
            'division' => $game->seasonDivision === null ? null : [
                'id' => $game->seasonDivision->id,
                'name' => $game->seasonDivision->name,
            ],
            'matchday' => $game->matchday === null ? null : [
                'roundNumber' => $game->matchday->round_number,
                'windowLabel' => $game->matchday->windowLabel(),
            ],
            'deadlineAt' => $game->deadline_at?->toIso8601String(),
            'player1' => $this->serializePlayer($game->player1),
            'player2' => $this->serializePlayer($game->player2),
            'player1Score' => $game->player1_score,
            'player2Score' => $game->player2_score,
            'winnerId' => $game->winner_id,
            'lobbyHostPlayerId' => $game->lobby_host_player_id,
            'scoringHostPlayerId' => $game->scoring_host_player_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvitation(LeagueGame $game, int $viewerPlayerId): array
    {
        $payload = $this->serialize($game, $viewerPlayerId);
        $host = (int) $game->lobby_host_player_id === (int) $game->player1_id
            ? $game->player1
            : $game->player2;

        return [
            'id' => $game->id,
            'type' => 'league',
            'hostName' => $host?->name ?? 'Rywal',
            'leagueName' => $game->season->league->name,
            'formatLabel' => $payload['formatLabel'],
            'game' => $payload,
        ];
    }

    /**
     * @return array{id: int, name: string, playerId: int}
     */
    private function serializePlayer(?Player $player): array
    {
        return [
            'id' => $player?->id,
            'name' => $player?->name ?? 'Gracz',
            'playerId' => $player?->id,
        ];
    }
}
