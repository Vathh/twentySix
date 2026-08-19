<?php

namespace App\Services\Push;

use App\Enums\InvitationPushType;
use App\Jobs\SendInvitationPushJob;

class InvitationPushService
{
    public function notifyFriendInvitation(int $recipientUserId, int $invitationId, string $senderName): void
    {
        $this->dispatch(
            recipientUserId: $recipientUserId,
            type: InvitationPushType::Friend,
            invitationId: $invitationId,
            context: ['senderName' => $senderName],
        );
    }

    public function notifyTournamentInvitation(int $recipientUserId, int $invitationId, string $tournamentName): void
    {
        $this->dispatch(
            recipientUserId: $recipientUserId,
            type: InvitationPushType::Tournament,
            invitationId: $invitationId,
            context: ['tournamentName' => $tournamentName],
        );
    }

    public function notifyLobbyInvitation(int $recipientUserId, int $invitationId, string $hostName): void
    {
        $this->dispatch(
            recipientUserId: $recipientUserId,
            type: InvitationPushType::Lobby,
            invitationId: $invitationId,
            context: ['hostName' => $hostName],
        );
    }

    public function notifyLeagueGameInvitation(int $recipientUserId, int $gameId, string $hostName): void
    {
        $this->dispatch(
            recipientUserId: $recipientUserId,
            type: InvitationPushType::League,
            invitationId: $gameId,
            context: ['hostName' => $hostName],
        );
    }

    public function notifyOrganizationInvitation(
        int $recipientUserId,
        int $invitationId,
        string $organizationName,
    ): void {
        $this->dispatch(
            recipientUserId: $recipientUserId,
            type: InvitationPushType::Organization,
            invitationId: $invitationId,
            context: ['organizationName' => $organizationName],
        );
    }

    /**
     * @param  array<string, string>  $context
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function buildMessage(InvitationPushType $type, int $invitationId, array $context): array
    {
        $body = match ($type) {
            InvitationPushType::Friend => sprintf(
                '%s zaprasza Cię do znajomych',
                $context['senderName'] ?? 'Gracz',
            ),
            InvitationPushType::Tournament => sprintf(
                'Zaproszenie do turnieju: %s',
                $context['tournamentName'] ?? 'Turniej',
            ),
            InvitationPushType::Lobby => sprintf(
                '%s zaprasza Cię do quick game',
                $context['hostName'] ?? 'Host',
            ),
            InvitationPushType::League => sprintf(
                '%s zaprasza Cię do meczu ligowego',
                $context['hostName'] ?? 'Rywal',
            ),
            InvitationPushType::Organization => sprintf(
                'Zaproszenie do organizacji: %s',
                $context['organizationName'] ?? 'Organizacja',
            ),
        };

        return [
            'title' => 'twentySix',
            'body' => $body,
            'data' => [
                'type' => $type->value,
                'invitationId' => (string) $invitationId,
                'screen' => 'Zaproszenia',
                'tab' => $type->tab(),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $context
     */
    private function dispatch(
        int $recipientUserId,
        InvitationPushType $type,
        int $invitationId,
        array $context,
    ): void {
        SendInvitationPushJob::dispatch(
            $recipientUserId,
            $type->value,
            $invitationId,
            $context,
        );
    }
}
