<?php

namespace App\Services\Tournament;

use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameStage;
use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentStatus;
use App\Repositories\Tournament\TournamentRepository;
use App\Services\Player\PlayerService;
use App\Domain\GameScoring\MatchFormat;
use App\Support\League\LeagueMatchFormatPresets;
use App\Support\Tournament\TournamentStartRules;
use Illuminate\Http\Request;

/**
 * Buduje dane widoku strony startu turnieju (`tournaments.start`): pulę zawodników,
 * pipeline zaproszeń, konfigurację grup/playoff oraz snapshoty „live” uczestników.
 */
class TournamentStartPageService
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private TournamentInvitationService $invitationService,
        private TournamentJoinRequestService $joinRequestService,
        private PlayerService $playerService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(TournamentDomain $tournament, int $tournamentId, Request $request): array
    {
        $tournamentModel = $this->tournamentRepository->findModel($tournamentId);
        if ($tournamentModel->status === TournamentStatus::CREATED) {
            $tournamentModel = $this->joinRequestService->ensureJoinCode($tournamentModel);
        }
        $seasonId = $tournament->season?->id;

        $invitations = $this->invitationService->getForTournament($tournamentId);
        $invitationByUserId = $invitations->keyBy(fn ($inv) => $inv->userId);

        $regulars = $seasonId !== null
            ? $this->playerService
                ->getRelatedRegisteredUsers($seasonId)
                ->map(function ($player) use ($invitationByUserId) {
                    $invitation = $invitationByUserId->get($player->userId);

                    return [
                        'userId' => $player->userId,
                        'playerId' => $player->id,
                        'name' => $player->name,
                        'invitationId' => $invitation?->id,
                        'invitationStatus' => $invitation?->status,
                        'canInvite' => $invitation === null || $invitation->status->canReinvite(),
                    ];
                })
                ->sortBy('name')
                ->values()
            : collect();

        $tournamentGuests = $this->playerService->getTournamentGuestParticipants($tournamentId);
        $tournamentGuestIds = $tournamentGuests->pluck('id');

        $relatedGuests = $seasonId !== null
            ? $this->playerService
                ->getSeasonGuests($seasonId)
                ->map(fn ($guest) => [
                    'playerId' => $guest->id,
                    'name' => $guest->name,
                    'inTournament' => $tournamentGuestIds->contains($guest->id),
                ])
                ->sortBy('name')
                ->values()
            : collect();

        $participants = $this->participantsList($invitations, $tournamentGuests);
        $invitationPipeline = $this->pendingInvitations($invitations);

        $addTab = in_array($request->input('tab'), ['registered', 'guests'], true)
            ? $request->input('tab')
            : 'registered';

        $participantCount = $participants->count();
        $groupCountOptions = TournamentStartRules::allowedGroupCountsForPlayers($participantCount);
        $bracketOptionsByGroupCount = TournamentStartRules::bracketOptionsByGroupCountForPlayers($participantCount);
        $defaultGroupsCount = (int) old('groupsCount', $groupCountOptions[0] ?? 2);
        $defaultBracketOptions = $bracketOptionsByGroupCount[$defaultGroupsCount] ?? [];
        $defaultPlayoffBracketSize = (int) old(
            'playoffBracketSize',
            $defaultBracketOptions[0]['value'] ?? 4,
        );

        $matchFormatStagesByBracket = [];
        $matchFormatStagesByBracketSe = [];
        foreach ([2, 4, 8, 16, 32, 64, 128] as $bracketSize) {
            $matchFormatStagesByBracket[$bracketSize] = array_map(
                static fn (GameStage $stage): array => [
                    'value' => $stage->value,
                    'label' => $stage->label(),
                ],
                GameStage::forPlayoffBracketSize($bracketSize),
            );
            $matchFormatStagesByBracketSe[$bracketSize] = array_map(
                static fn (GameStage $stage): array => [
                    'value' => $stage->value,
                    'label' => $stage->label(),
                ],
                GameStage::forEliminationBracketSize($bracketSize),
            );
        }

        $defaultTournamentFormat = (string) old(
            'tournamentFormat',
            \App\Enums\TournamentFormat::GroupsPlayoff->value,
        );

        $seBracketSize = $participantCount >= TournamentStartRules::MIN_PLAYERS
            ? \App\Support\Tournament\PlayoffByePairing::nextPowerOfTwo($participantCount)
            : 4;
        $seByeCount = max(0, $seBracketSize - $participantCount);

        $leaguePresets = $tournament->season?->league?->matchFormatPresets;
        $defaultMatchFormatsByStage = LeagueMatchFormatPresets::defaultsByStage(
            $leaguePresets !== [] ? $leaguePresets : null,
        );
        $hasLeagueFormatPresets = is_array($leaguePresets) && $leaguePresets !== [];

        $pendingJoinRequests = $tournament->status === TournamentStatus::CREATED
            ? $this->joinRequestService->getPendingForTournament($tournamentId)
            : collect();
        $pendingJoinRequestsLive = $tournament->status === TournamentStatus::CREATED
            ? $this->joinRequestService->pendingSnapshot($tournamentId)['requests']
            : [];

        return [
            'tournament' => $tournament,
            'invitationPipeline' => $invitationPipeline,
            'invitationPipelineLive' => $this->mapInvitationPipelineLive($invitationPipeline, $tournamentId),
            'regulars' => $regulars,
            'participants' => $participants,
            'participantsLive' => $this->mapParticipantsLive($participants, $tournamentId),
            'participantCount' => $participantCount,
            'relatedGuests' => $relatedGuests,
            'pendingJoinRequests' => $pendingJoinRequests,
            'pendingJoinRequestsLive' => $pendingJoinRequestsLive,
            'joinCode' => $tournamentModel->join_code,
            'joinCodeEnabled' => (bool) $tournamentModel->join_code_enabled,
            'joinUrl' => $tournamentModel->join_code
                ? $this->joinRequestService->joinUrl($tournamentModel)
                : null,
            'addTab' => $addTab,
            'canManageParticipants' => $tournament->status === TournamentStatus::CREATED,
            'groupCountOptions' => $groupCountOptions,
            'bracketOptionsByGroupCount' => $bracketOptionsByGroupCount,
            'defaultBracketOptions' => $defaultBracketOptions,
            'startConfigPreview' => TournamentStartRules::startConfigPreview($participantCount),
            'minPlayers' => TournamentStartRules::MIN_PLAYERS,
            'minPlayersPerGroup' => TournamentStartRules::MIN_PLAYERS_PER_GROUP,
            'defaultGroupsCount' => $defaultGroupsCount,
            'defaultPlayoffBracketSize' => $defaultPlayoffBracketSize,
            'defaultTournamentFormat' => $defaultTournamentFormat,
            'seBracketSize' => $seBracketSize,
            'seByeCount' => $seByeCount,
            'startingScoreOptions' => MatchFormat::ALLOWED_STARTING_SCORES,
            'defaultMatchFormat' => MatchFormat::default()->toArray(),
            'defaultMatchFormatsByStage' => $defaultMatchFormatsByStage,
            'hasLeagueFormatPresets' => $hasLeagueFormatPresets,
            'matchFormatStagesByBracket' => $matchFormatStagesByBracket,
            'matchFormatStagesByBracketSe' => $matchFormatStagesByBracketSe,
            'oldMatchFormats' => old('matchFormats', []),
        ];
    }

    /**
     * @return list<array{id: int, userId: int, name: string, status: string, statusLabel: string, isPending: bool, canReinvite: bool, cancelUrl: string}>
     */
    public function buildInvitationPipelineLive(int $tournamentId): array
    {
        $invitations = $this->pendingInvitations($this->invitationService->getForTournament($tournamentId));

        return $this->mapInvitationPipelineLive($invitations, $tournamentId);
    }

    /**
     * @return list<array{kind: string, playerId: int|null, name: string, invitationId: int|null, removeUrl: string|null}>
     */
    public function buildParticipantsLive(int $tournamentId): array
    {
        $invitations = $this->invitationService->getForTournament($tournamentId);
        $tournamentGuests = $this->playerService->getTournamentGuestParticipants($tournamentId);
        $participants = $this->participantsList($invitations, $tournamentGuests);

        return $this->mapParticipantsLive($participants, $tournamentId);
    }

    /**
     * Snapshot uczestników + pipeline zaproszeń (API / WS na stronie startu).
     *
     * @return array{
     *     tournamentId: int,
     *     participants: list<array<string, mixed>>,
     *     participantCount: int,
     *     invitationPipeline: list<array<string, mixed>>,
     *     minPlayers: int
     * }
     */
    public function rosterLiveSnapshot(int $tournamentId): array
    {
        $participants = $this->buildParticipantsLive($tournamentId);

        return [
            'tournamentId' => $tournamentId,
            'participants' => $participants,
            'participantCount' => count($participants),
            'invitationPipeline' => $this->buildInvitationPipelineLive($tournamentId),
            'minPlayers' => TournamentStartRules::MIN_PLAYERS,
        ];
    }

    /**
     * Zaakceptowani uczestnicy (użytkownicy + goście), posortowani po nazwie.
     *
     * @param  \Illuminate\Support\Collection  $invitations
     * @param  \Illuminate\Support\Collection  $tournamentGuests
     * @return \Illuminate\Support\Collection<int, array{kind: string, playerId: int|null, name: string, invitationId: int|null}>
     */
    private function participantsList($invitations, $tournamentGuests)
    {
        return $invitations
            ->toBase()
            ->filter(fn ($inv) => $inv->status === TournamentInvitationStatus::ACCEPTED)
            ->map(fn ($inv) => [
                'kind' => 'user',
                'playerId' => $inv->userPlayer?->id,
                'name' => $inv->userPlayer?->name ?? '—',
                'invitationId' => $inv->id,
            ])
            ->merge(
                $tournamentGuests->toBase()->map(fn ($guest) => [
                    'kind' => 'guest',
                    'playerId' => $guest->id,
                    'name' => $guest->name,
                    'invitationId' => null,
                ])
            )
            ->sortBy('name')
            ->values();
    }

    /**
     * Zaproszenia w pipeline (nie zaakceptowane jeszcze), posortowane po nazwie zapraszanego.
     *
     * @param  \Illuminate\Support\Collection  $invitations
     * @return \Illuminate\Support\Collection
     */
    private function pendingInvitations($invitations)
    {
        return $invitations
            ->filter(fn ($inv) => $inv->status !== TournamentInvitationStatus::ACCEPTED)
            ->sortBy(fn ($inv) => $inv->userPlayer?->name ?? '')
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection  $participants
     * @return list<array{kind: string, playerId: int|null, name: string, invitationId: int|null, removeUrl: string|null}>
     */
    private function mapParticipantsLive($participants, int $tournamentId): array
    {
        return $participants
            ->map(fn ($p) => [
                'kind' => $p['kind'],
                'playerId' => $p['playerId'],
                'name' => $p['name'],
                'invitationId' => $p['invitationId'],
                'removeUrl' => $p['kind'] === 'user' && $p['invitationId']
                    ? route('tournaments.invitations.remove', [$tournamentId, $p['invitationId']], false)
                    : ($p['kind'] === 'guest'
                        ? route('tournaments.participants.guests.remove', $tournamentId, false)
                        : null),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection  $invitations
     * @return list<array{id: int, userId: int, name: string, status: string, statusLabel: string, isPending: bool, canReinvite: bool, cancelUrl: string}>
     */
    private function mapInvitationPipelineLive($invitations, int $tournamentId): array
    {
        return $invitations
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'userId' => $inv->userId,
                'name' => $inv->userPlayer?->name ?? '—',
                'status' => $inv->status->value,
                'statusLabel' => $inv->status->label(),
                'isPending' => $inv->status === TournamentInvitationStatus::PENDING,
                'canReinvite' => $inv->status->canReinvite(),
                'cancelUrl' => route('tournaments.invitations.cancel', [$tournamentId, $inv->id], false),
            ])
            ->all();
    }
}
