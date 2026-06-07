<?php

namespace App\Repositories\Tournament;

use App\Domain\PlayerDomain;
use App\Domain\Tournament\TournamentInvitationDomain;
use App\Enums\TournamentInvitationStatus;
use App\Models\Tournament\TournamentInvitation;
use Illuminate\Support\Collection;

class TournamentInvitationRepository
{
    public function findById(int $invitationId): ?TournamentInvitationDomain
    {
        $invitation = TournamentInvitation::with(['user.player', 'tournament'])->find($invitationId);

        return $invitation ? TournamentInvitationDomain::fromEloquent($invitation) : null;
    }

    public function findByTournamentAndUser(int $tournamentId, int $userId): ?TournamentInvitationDomain
    {
        $invitation = TournamentInvitation::with(['user.player', 'tournament'])
            ->where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->first();

        return $invitation ? TournamentInvitationDomain::fromEloquent($invitation) : null;
    }

    /**
     * @return Collection<int, TournamentInvitationDomain>
     */
    public function getForTournament(int $tournamentId): Collection
    {
        return TournamentInvitation::with(['user.player', 'tournament'])
            ->where('tournament_id', $tournamentId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (TournamentInvitation $invitation) => TournamentInvitationDomain::fromEloquent($invitation));
    }

    /**
     * @return Collection<int, TournamentInvitationDomain>
     */
    public function getReceivedForUser(int $userId): Collection
    {
        return TournamentInvitation::with(['user.player', 'tournament'])
            ->where('user_id', $userId)
            ->whereIn('status', [
                TournamentInvitationStatus::PENDING,
                TournamentInvitationStatus::ACCEPTED,
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TournamentInvitation $invitation) => TournamentInvitationDomain::fromEloquent($invitation));
    }

    /**
     * @return Collection<int, PlayerDomain>
     */
    public function getAcceptedPlayers(int $tournamentId): Collection
    {
        return TournamentInvitation::with(['user.player'])
            ->where('tournament_id', $tournamentId)
            ->where('status', TournamentInvitationStatus::ACCEPTED)
            ->get()
            ->map(fn (TournamentInvitation $invitation) => PlayerDomain::fromEloquent($invitation->user->player))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function getActiveInvitedUserIds(int $tournamentId): Collection
    {
        return TournamentInvitation::where('tournament_id', $tournamentId)
            ->whereIn('status', [
                TournamentInvitationStatus::PENDING,
                TournamentInvitationStatus::ACCEPTED,
            ])
            ->pluck('user_id');
    }

    public function createOrReinvite(int $tournamentId, int $userId, int $invitedBy): TournamentInvitationDomain
    {
        $existing = TournamentInvitation::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->first();

        if ($existing === null) {
            $invitation = TournamentInvitation::create([
                'tournament_id' => $tournamentId,
                'user_id' => $userId,
                'invited_by' => $invitedBy,
                'status' => TournamentInvitationStatus::PENDING,
            ]);

            $invitation->load(['user.player', 'tournament']);

            return TournamentInvitationDomain::fromEloquent($invitation);
        }

        if ($existing->status->isActive()) {
            throw new \RuntimeException('Użytkownik ma już aktywne zaproszenie do tego turnieju');
        }

        $existing->update([
            'invited_by' => $invitedBy,
            'status' => TournamentInvitationStatus::PENDING,
            'responded_at' => null,
        ]);

        $existing->load(['user.player', 'tournament']);

        return TournamentInvitationDomain::fromEloquent($existing->fresh(['user.player', 'tournament']));
    }

    public function cancelPending(int $invitationId, int $tournamentId): void
    {
        $invitation = $this->findOwnedInvitation($invitationId, $tournamentId);

        if ($invitation->status !== TournamentInvitationStatus::PENDING) {
            throw new \RuntimeException('Można anulować tylko zaproszenie oczekujące');
        }

        TournamentInvitation::where('id', $invitationId)->update([
            'status' => TournamentInvitationStatus::CANCELLED,
            'responded_at' => now(),
        ]);
    }

    public function removeAccepted(int $invitationId, int $tournamentId): void
    {
        $invitation = $this->findOwnedInvitation($invitationId, $tournamentId);

        if ($invitation->status !== TournamentInvitationStatus::ACCEPTED) {
            throw new \RuntimeException('Można usunąć tylko zaakceptowanego uczestnika');
        }

        TournamentInvitation::where('id', $invitationId)->update([
            'status' => TournamentInvitationStatus::REMOVED,
            'responded_at' => now(),
        ]);
    }

    public function accept(int $invitationId, int $userId): void
    {
        $invitation = TournamentInvitation::findOrFail($invitationId);

        if ($invitation->user_id !== $userId) {
            throw new \RuntimeException('Nie możesz zaakceptować tego zaproszenia');
        }

        if ($invitation->status !== TournamentInvitationStatus::PENDING) {
            throw new \RuntimeException('Zaproszenie zostało już przetworzone');
        }

        $invitation->update([
            'status' => TournamentInvitationStatus::ACCEPTED,
            'responded_at' => now(),
        ]);
    }

    public function reject(int $invitationId, int $userId): void
    {
        $invitation = TournamentInvitation::findOrFail($invitationId);

        if ($invitation->user_id !== $userId) {
            throw new \RuntimeException('Nie możesz odrzucić tego zaproszenia');
        }

        if ($invitation->status !== TournamentInvitationStatus::PENDING) {
            throw new \RuntimeException('Zaproszenie zostało już przetworzone');
        }

        $invitation->update([
            'status' => TournamentInvitationStatus::REJECTED,
            'responded_at' => now(),
        ]);
    }

    public function withdraw(int $invitationId, int $userId): void
    {
        $invitation = TournamentInvitation::findOrFail($invitationId);

        if ($invitation->user_id !== $userId) {
            throw new \RuntimeException('Nie możesz wycofać udziału w tym turnieju');
        }

        if ($invitation->status !== TournamentInvitationStatus::ACCEPTED) {
            throw new \RuntimeException('Można wycofać udział tylko po akceptacji zaproszenia');
        }

        $invitation->update([
            'status' => TournamentInvitationStatus::WITHDRAWN,
            'responded_at' => now(),
        ]);
    }

    private function findOwnedInvitation(int $invitationId, int $tournamentId): TournamentInvitation
    {
        $invitation = TournamentInvitation::where('id', $invitationId)
            ->where('tournament_id', $tournamentId)
            ->firstOrFail();

        return $invitation;
    }
}
