<?php

namespace Tests\Support;

use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\Tournament\TournamentGuestParticipantRepository;
use App\Repositories\Tournament\TournamentInvitationRepository;

trait SeedsTournamentParticipants
{
    /**
     * @param  array<int, Player>  $players
     */
    protected function addPlayersToTournamentPool(Tournament $tournament, array $players, User $invitedBy): void
    {
        $invitationRepository = app(TournamentInvitationRepository::class);
        $guestRepository = app(TournamentGuestParticipantRepository::class);

        foreach ($players as $player) {
            if ($player->user_id !== null) {
                $invitation = $invitationRepository->createOrReinvite(
                    $tournament->id,
                    $player->user_id,
                    $invitedBy->id,
                );
                $invitationRepository->accept($invitation->id, $player->user_id);
            } else {
                $guestRepository->add($tournament->id, $player->id);
            }
        }
    }
}
