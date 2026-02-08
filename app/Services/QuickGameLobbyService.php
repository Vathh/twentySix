<?php

namespace App\Services;

use App\Repositories\QuickGameLobbyRepository;
use App\Repositories\PlayerRepository;
use App\Models\QuickGameLobby;
use App\Models\QuickGameLobbyPlayer;

class QuickGameLobbyService
{
    public function __construct(
        private QuickGameLobbyRepository $lobbyRepository,
        private PlayerRepository $playerRepository
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

        return $lobby->fresh(['host.player', 'players.player']);
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
     * Rozpoczyna mecz (tworzy QuickGame i zmienia status lobby)
     * @param int $lobbyId
     * @param int $hostUserId ID hosta (musi być hostem)
     * @return QuickGameLobby
     */
    public function startGame(int $lobbyId, int $hostUserId): QuickGameLobby
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

        return $this->lobbyRepository->startGame($lobbyId);
    }
}
