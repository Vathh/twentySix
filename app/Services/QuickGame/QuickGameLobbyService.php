<?php

namespace App\Services\QuickGame;

use App\Repositories\QuickGame\QuickGameLobbyRepository;
use App\Repositories\Player\PlayerRepository;
use App\Models\QuickGameLobby;
use App\Models\QuickGameLobbyPlayer;

class QuickGameLobbyService
{
    public function __construct(
        private QuickGameLobbyRepository $lobbyRepository,
        private PlayerRepository $playerRepository,
        private QuickGameSessionService $sessionService
    ) {
    }

    /**
     * Tworzy nowe lobby
     * @param int $hostUserId ID użytkownika tworzącego lobby
     * @return QuickGameLobby
     */
    public function create(int $hostUserId): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->create($hostUserId);
        
        // Automatycznie dodaj hosta do lobby jako gracza
        $hostPlayer = $this->playerRepository->findByUserId($hostUserId);
        if ($hostPlayer) {
            $this->lobbyRepository->addPlayer($lobby->id, $hostPlayer->id, null, true);
        }

        return $lobby->fresh(['host.player', 'players.player']);
    }

    /**
     * Dołącza do lobby po kodzie
     * @param string $code Kod lobby
     * @param int|null $userId ID użytkownika (null jeśli nie zalogowany)
     * @param string|null $tempPlayerName Nazwa gracza tymczasowego
     * @return QuickGameLobby
     */
    public function joinByCode(string $code, ?int $userId = null, ?string $tempPlayerName = null): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->findByCode($code);

        if (!$lobby) {
            throw new \RuntimeException('Lobby nie zostało znalezione');
        }

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie przyjmuje już graczy');
        }

        if ($userId) {
            $player = $this->playerRepository->findByUserId($userId);
            if (!$player) {
                throw new \RuntimeException('Nie znaleziono gracza dla użytkownika');
            }
            $this->lobbyRepository->addPlayer($lobby->id, $player->id, null, true);
        } else {
            if (!$tempPlayerName) {
                throw new \RuntimeException('Musisz podać nazwę gracza tymczasowego');
            }
            $this->lobbyRepository->addPlayer($lobby->id, null, $tempPlayerName, false);
        }

        return $lobby->fresh(['host.player', 'players.player']);
    }

    /**
     * Dołącza do lobby przez zaproszenie (z panelu znajomych)
     * @param int $lobbyId
     * @param int $userId ID użytkownika który dołącza
     * @return QuickGameLobby
     */
    public function joinById(int $lobbyId, int $userId): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie przyjmuje już graczy');
        }

        $player = $this->playerRepository->findByUserId($userId);
        if (!$player) {
            throw new \RuntimeException('Nie znaleziono gracza dla użytkownika');
        }

        $this->lobbyRepository->addPlayer($lobby->id, $player->id, null, true);
        $this->lobbyRepository->markInvitationAccepted($lobbyId, $player->id);

        return $lobby->fresh(['host.player', 'players.player']);
    }

    /**
     * Wysyła zaproszenie do lobby (tylko host).
     * @param int $lobbyId
     * @param int $hostUserId ID hosta
     * @param int $invitedPlayerId ID gracza (players.id) zapraszanego do lobby
     */
    public function invite(int $lobbyId, int $hostUserId, int $invitedPlayerId): void
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($lobby->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może zapraszać do lobby');
        }

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie przyjmuje już graczy');
        }

        $alreadyInLobby = $lobby->players->contains('player_id', $invitedPlayerId);
        if ($alreadyInLobby) {
            throw new \RuntimeException('Ten gracz jest już w lobby');
        }

        if ($this->lobbyRepository->hasPendingInvitation($lobbyId, $invitedPlayerId)) {
            throw new \RuntimeException('Zaproszenie do tego gracza zostało już wysłane');
        }

        $this->lobbyRepository->createInvitation($lobbyId, $invitedPlayerId);
    }

    /**
     * Pobiera oczekujące zaproszenia do lobby dla zalogowanego użytkownika.
     * @param int $userId ID użytkownika
     * @return \Illuminate\Support\Collection
     */
    public function getPendingInvitationsForUser(int $userId): \Illuminate\Support\Collection
    {
        $player = $this->playerRepository->findByUserId($userId);
        if (!$player) {
            return collect([]);
        }

        return $this->lobbyRepository->getPendingInvitationsForPlayer($player->id)
            ->map(function ($inv) {
                $lobby = $inv->lobby;
                $hostName = $lobby->host->player?->name ?? 'Host';
                return [
                    'id' => $inv->id,
                    'lobbyId' => $lobby->id,
                    'lobbyCode' => $lobby->code,
                    'hostName' => $hostName,
                ];
            });
    }

    /**
     * Odrzuca zaproszenie do lobby.
     */
    public function rejectInvitation(int $invitationId, int $userId): void
    {
        $player = $this->playerRepository->findByUserId($userId);
        if (!$player) {
            throw new \RuntimeException('Nie znaleziono gracza');
        }
        $this->lobbyRepository->markInvitationRejected($invitationId, $player->id);
    }

    /**
     * Opuszcza lobby
     * @param int $lobbyId
     * @param int|null $userId ID użytkownika (null dla tymczasowego)
     * @param string|null $tempPlayerName Nazwa gracza tymczasowego
     * @return void
     */
    public function leave(int $lobbyId, ?int $userId = null, ?string $tempPlayerName = null): void
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        $playerId = null;
        if ($userId) {
            $player = $this->playerRepository->findByUserId($userId);
            $playerId = $player?->id;
        }

        $this->lobbyRepository->removePlayer($lobbyId, $playerId, $tempPlayerName);

        // Jeśli host opuszcza lobby, usuń lobby
        if ($userId && $lobby->host_id === $userId) {
            $this->lobbyRepository->delete($lobbyId);
        }
    }

    /**
     * Pobiera stan lobby
     * @param int $lobbyId
     * @return QuickGameLobby
     */
    public function get(int $lobbyId): QuickGameLobby
    {
        return $this->lobbyRepository->find($lobbyId);
    }

    /**
     * Pobiera lobby po kodzie
     * @param string $code
     * @return QuickGameLobby|null
     */
    public function getByCode(string $code): ?QuickGameLobby
    {
        return $this->lobbyRepository->findByCode($code);
    }

    /**
     * Dodaje gracza tymczasowego (gościa) do lobby – bez konta, tylko nazwa.
     * Tylko host może dodawać gości. Dla kogoś, kto stoi obok i chce zagrać bez rejestracji.
     *
     * @param int $lobbyId
     * @param int $hostUserId ID hosta (tylko host może dodać gościa)
     * @param string $tempPlayerName Nazwa wyświetlana gościa
     * @return QuickGameLobby
     */
    public function addGuest(int $lobbyId, int $hostUserId, string $tempPlayerName): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($lobby->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może dodawać gości do lobby');
        }

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie przyjmuje już graczy');
        }

        $name = trim($tempPlayerName);
        if ($name === '') {
            throw new \RuntimeException('Podaj nazwę gracza');
        }

        $playersCount = $lobby->players()->count();
        if ($playersCount >= 6) {
            throw new \RuntimeException('W lobby może być maksymalnie 6 graczy');
        }

        $this->lobbyRepository->addPlayer($lobby->id, null, $name, false);

        return $lobby->fresh(['host.player', 'players.player']);
    }

    /**
     * Ustawia status gotowości gracza
     * @param int $lobbyId
     * @param int $userId
     * @param bool $isReady
     * @return QuickGameLobby
     */
    public function setReady(int $lobbyId, int $userId, bool $isReady): QuickGameLobby
    {
        $player = $this->playerRepository->findByUserId($userId);
        if (!$player) {
            throw new \RuntimeException('Nie znaleziono gracza');
        }

        $this->lobbyRepository->setPlayerReady($lobbyId, $player->id, $isReady);

        return $this->lobbyRepository->find($lobbyId);
    }

    /**
     * Rozpoczyna mecz (tworzy QuickGame, sesję synchronizacji i zmienia status lobby)
     * @param int $lobbyId
     * @param int $hostUserId ID hosta (musi być hostem)
     * @param int|null $legsCount Liczba legów do wygranej (1-15), domyślnie 3
     * @param string|null $gameType Typ gry: '501' lub 'cricket'
     * @param string|null $scoringMode Tryb liczenia: 'one_device' | 'each_own'
     * @return QuickGameLobby
     */
    public function startGame(
        int $lobbyId,
        int $hostUserId,
        ?int $legsCount = null,
        ?string $gameType = null,
        ?string $scoringMode = null,
        ?array $playerOrderIds = null
    ): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($lobby->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może rozpocząć mecz');
        }

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie jest gotowe do rozpoczęcia');
        }

        // Sprawdź czy są co najmniej 2 graczy
        $playersCount = $lobby->players()->count();
        if ($playersCount < 2) {
            throw new \RuntimeException('Musi być co najmniej 2 graczy');
        }

        // Wszyscy zarejestrowani gracze muszą być gotowi; host jest zawsze uznawany za gotowego
        $hostPlayerId = $lobby->host->player?->id;
        foreach ($lobby->players as $lp) {
            if ($lp->player_id === null) {
                continue; // gość – nie liczymy gotowości
            }
            if ($lp->player_id === $hostPlayerId) {
                continue; // host – zawsze gotowy
            }
            if (!$lp->is_ready) {
                throw new \RuntimeException('Wszyscy zarejestrowani gracze muszą potwierdzić gotowość');
            }
        }

        $legs = $legsCount ?? $lobby->legs_count ?? 3;
        $game = $gameType ?? $lobby->game_type ?? '501';
        $mode = $scoringMode ?? $lobby->scoring_mode ?? 'each_own';

        $players = $lobby->players->values();
        $defaultOrderIds = $players->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $finalOrderIds = $defaultOrderIds;
        if (is_array($playerOrderIds) && count($playerOrderIds) > 0) {
            $requested = array_values(array_unique(array_map('intval', $playerOrderIds)));
            $allowed = array_flip($defaultOrderIds);
            $requestedFiltered = array_values(array_filter($requested, fn ($id) => isset($allowed[$id])));
            $missing = array_values(array_filter($defaultOrderIds, fn ($id) => !in_array($id, $requestedFiltered, true)));
            $finalOrderIds = array_values(array_merge($requestedFiltered, $missing));
        }

        $lobby = $this->lobbyRepository->startGame($lobbyId, $legs, $game, $mode);

        $this->sessionService->createSession(
            $lobbyId,
            $hostUserId,
            $mode,
            $game,
            $legs,
            $playersCount,
            $finalOrderIds
        );

        return $lobby;
    }

    /**
     * Aktualizuje ustawienia lobby (tylko host)
     */
    public function updateSettings(int $lobbyId, int $hostUserId, ?int $legsCount = null, ?string $gameType = null): QuickGameLobby
    {
        return $this->lobbyRepository->updateSettings($lobbyId, $hostUserId, $legsCount, $gameType);
    }

    /**
     * Ustawia tryb liczenia w lobby (tylko host).
     */
    public function updateScoringMode(int $lobbyId, int $hostUserId, string $scoringMode): QuickGameLobby
    {
        return $this->lobbyRepository->updateScoringMode($lobbyId, $hostUserId, $scoringMode);
    }
}











