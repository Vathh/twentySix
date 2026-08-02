<?php

namespace App\Services\Tournament;

use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentJoinRequestStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentJoinRequestsUpdated;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentJoinRequest;
use App\Models\Users\User;
use App\Repositories\Tournament\TournamentInvitationRepository;
use App\Repositories\Tournament\TournamentJoinRequestRepository;
use App\Repositories\Tournament\TournamentRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TournamentJoinRequestService
{
    private const CODE_LENGTH = 8;

    public function __construct(
        private TournamentJoinRequestRepository $joinRequestRepository,
        private TournamentInvitationRepository $invitationRepository,
        private TournamentRepository $tournamentRepository,
    ) {
    }

    public function ensureJoinCode(Tournament $tournament): Tournament
    {
        if ($tournament->join_code) {
            return $tournament;
        }

        return $this->regenerateJoinCode($tournament);
    }

    public function regenerateJoinCode(Tournament $tournament): Tournament
    {
        $this->assertNotStarted($tournament);

        do {
            $code = strtoupper(Str::random(self::CODE_LENGTH));
        } while ($this->tournamentRepository->joinCodeExists($code));

        return $this->tournamentRepository->setJoinCode($tournament, $code);
    }

    public function toggleJoinCode(Tournament $tournament, bool $enabled): Tournament
    {
        $this->assertNotStarted($tournament);

        return $this->tournamentRepository->setJoinCodeEnabled($tournament, $enabled);
    }

    public function findByJoinCode(string $code): ?Tournament
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return null;
        }

        return $this->tournamentRepository->findByJoinCode($normalized);
    }

    /**
     * @return array{tournamentName: string, leagueName: ?string, canApply: bool, reason: ?string, alreadyParticipant: bool, alreadyPending: bool}
     */
    public function previewForUser(string $code, ?int $userId): array
    {
        $tournament = $this->findByJoinCode($code);
        if ($tournament === null) {
            throw new \RuntimeException('Nieprawidłowy kod turnieju.');
        }

        $canApply = true;
        $reason = null;
        $alreadyParticipant = false;
        $alreadyPending = false;

        if ($tournament->status !== TournamentStatus::CREATED) {
            $canApply = false;
            $reason = 'Turniej już wystartował lub jest zakończony.';
        } elseif (! $tournament->join_code_enabled) {
            $canApply = false;
            $reason = 'Przyjmowanie zgłoszeń jest wyłączone.';
        } elseif ($userId !== null) {
            $invitation = $this->invitationRepository->findByTournamentAndUser(
                $tournament->id,
                $userId,
            );
            if ($invitation !== null && $invitation->status === TournamentInvitationStatus::ACCEPTED) {
                $canApply = false;
                $alreadyParticipant = true;
                $reason = 'Jesteś już uczestnikiem tego turnieju.';
            } elseif ($this->joinRequestRepository->findPending($tournament->id, $userId) !== null) {
                $canApply = false;
                $alreadyPending = true;
                $reason = 'Zgłoszenie już zostało wysłane — czekaj na decyzję organizatora.';
            }
        }

        return [
            'tournamentId' => $tournament->id,
            'tournamentName' => $tournament->name,
            'leagueName' => $tournament->season?->league?->name,
            'canApply' => $canApply,
            'reason' => $reason,
            'alreadyParticipant' => $alreadyParticipant,
            'alreadyPending' => $alreadyPending,
            'joinCode' => $tournament->join_code,
        ];
    }

    public function apply(string $code, User $user): TournamentJoinRequest
    {
        $tournament = $this->findByJoinCode($code);
        if ($tournament === null) {
            throw new \RuntimeException('Nieprawidłowy kod turnieju.');
        }

        $this->assertNotStarted($tournament);

        if (! $tournament->join_code_enabled) {
            throw new \RuntimeException('Przyjmowanie zgłoszeń jest wyłączone.');
        }

        if ($user->player === null) {
            $user->loadMissing('player');
        }
        if ($user->player === null) {
            throw new \RuntimeException('Aby zgłosić się do turnieju, potrzebujesz profilu gracza.');
        }

        $invitation = $this->invitationRepository->findByTournamentAndUser($tournament->id, $user->id);
        if ($invitation !== null && $invitation->status === TournamentInvitationStatus::ACCEPTED) {
            throw new \RuntimeException('Jesteś już uczestnikiem tego turnieju.');
        }

        $pending = $this->joinRequestRepository->findPending($tournament->id, $user->id);
        if ($pending !== null) {
            return $pending;
        }

        $latest = $this->joinRequestRepository->findLatestForUser($tournament->id, $user->id);
        if ($latest !== null && $latest->status === TournamentJoinRequestStatus::REJECTED) {
            $request = $this->joinRequestRepository->reactivateAsPending($latest);
            $this->broadcastPending($tournament->id);

            return $request;
        }

        $request = $this->joinRequestRepository->createPending($tournament->id, $user->id);
        $this->broadcastPending($tournament->id);

        return $request;
    }

    /**
     * @return Collection<int, TournamentJoinRequest>
     */
    public function getPendingForTournament(int $tournamentId): Collection
    {
        return $this->joinRequestRepository->getPendingForTournament($tournamentId);
    }

    /**
     * Snapshot listy oczekujących zgłoszeń (API / WS).
     *
     * @return array{tournamentId: int, requests: list<array{id: int, playerName: string, createdAt: string, approveUrl: string, rejectUrl: string}>}
     */
    public function pendingSnapshot(int $tournamentId): array
    {
        return [
            'tournamentId' => $tournamentId,
            'requests' => $this->serializePending($tournamentId),
        ];
    }

    public function approve(int $tournamentId, int $requestId, int $adminId): void
    {
        $tournament = $this->tournamentRepository->findModel($tournamentId);
        $this->assertNotStarted($tournament);

        $request = $this->joinRequestRepository->findById($requestId);
        if ($request === null || (int) $request->tournament_id !== $tournamentId) {
            throw new \RuntimeException('Zgłoszenie nie istnieje.');
        }
        if ($request->status !== TournamentJoinRequestStatus::PENDING) {
            throw new \RuntimeException('Zgłoszenie zostało już przetworzone.');
        }

        $this->invitationRepository->acceptByAdmin($tournamentId, (int) $request->user_id, $adminId);
        $this->joinRequestRepository->markApproved($request, $adminId);
        $this->broadcastPending($tournamentId);
    }

    public function reject(int $tournamentId, int $requestId, int $adminId): void
    {
        $tournament = $this->tournamentRepository->findModel($tournamentId);
        $this->assertNotStarted($tournament);

        $request = $this->joinRequestRepository->findById($requestId);
        if ($request === null || (int) $request->tournament_id !== $tournamentId) {
            throw new \RuntimeException('Zgłoszenie nie istnieje.');
        }
        if ($request->status !== TournamentJoinRequestStatus::PENDING) {
            throw new \RuntimeException('Zgłoszenie zostało już przetworzone.');
        }

        $this->joinRequestRepository->markRejected($request, $adminId);
        $this->broadcastPending($tournamentId);
    }

    public function joinUrl(Tournament $tournament): string
    {
        $code = $tournament->join_code ?? '';

        return rtrim((string) config('app.url'), '/').'/join-tournament/'.$code;
    }

    public function broadcastPending(int $tournamentId): void
    {
        broadcast(new TournamentJoinRequestsUpdated(
            $tournamentId,
            $this->pendingSnapshot($tournamentId),
        ));
    }

    /**
     * @return list<array{id: int, playerName: string, createdAt: string, approveUrl: string, rejectUrl: string}>
     */
    private function serializePending(int $tournamentId): array
    {
        return $this->getPendingForTournament($tournamentId)
            ->map(static function (TournamentJoinRequest $request) use ($tournamentId): array {
                return [
                    'id' => (int) $request->id,
                    'playerName' => $request->user?->player?->name ?? '—',
                    'createdAt' => $request->created_at?->format('d.m.Y H:i') ?? '',
                    'approveUrl' => route('tournaments.join-requests.approve', [$tournamentId, $request->id], false),
                    'rejectUrl' => route('tournaments.join-requests.reject', [$tournamentId, $request->id], false),
                ];
            })
            ->values()
            ->all();
    }

    private function assertNotStarted(Tournament $tournament): void
    {
        if ($tournament->status !== TournamentStatus::CREATED) {
            throw new \RuntimeException('Turniej już wystartował — zgłoszenia są zamknięte.');
        }
    }
}
