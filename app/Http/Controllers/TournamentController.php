<?php

namespace App\Http\Controllers;

use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameStage;
use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentStatus;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Queries\GetTournamentData;
use App\Services\GameScoring\GameAuthorizationService;
use App\Services\Player\PlayerService;
use App\Services\Tournament\LoginCodeService;
use App\Services\Tournament\TournamentGuestParticipantService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use App\Services\Tournament\TournamentInvitationService;
use App\Services\Tournament\TournamentJoinRequestService;
use App\Services\Tournament\TournamentService;
use Illuminate\Http\JsonResponse;
use App\Services\User\UserService;
use App\Support\Tournament\TournamentStartRules;
use App\Support\GameScoring\MatchFormat;
use App\Support\League\LeagueMatchFormatPresets;
use App\Support\Tournament\TournamentMatchFormatRequestParser;
use DomainException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TournamentController extends Controller
{

    public function __construct(
        private TournamentService $tournamentService,
        private PlayerService $playerService,
        private TournamentInvitationService $invitationService,
        private TournamentJoinRequestService $joinRequestService,
        private TournamentGuestParticipantService $guestParticipantService,
        private UserService $userService,
        private GetTournamentData $getTournamentGroupResults,
        private LoginCodeService $loginCodeService,
        private GameAuthorizationService $gameAuthorizationService,
        private TournamentGroupMatrixLiveService $groupMatrixLiveService,
    ) {
    }

    public function index(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $data = $this->tournamentService->getIndexPage($page);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('tournaments.index', [
            'items' => $data['items'],
            'hasMore' => $data['has_more'],
        ]);
    }

    public function create(Request $request): Factory|View
    {
        $seasonId = $request->query('seasonId');

        if ($seasonId !== null) {
            $this->authorize('update', Season::findOrFail($seasonId));
        } else {
            abort_unless(Auth::user()?->can_create_leagues, 403);
        }

        return view('tournaments.create', ['seasonId' => $seasonId]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournamentName' => 'required|string|max:25',
            'date' => 'required|date',
        ]);

        $seasonId = $request->query('seasonId');

        if ($seasonId !== null) {
            $this->authorize('update', Season::findOrFail($seasonId));
        } else {
            abort_unless(Auth::user()?->can_create_leagues, 403);
        }

        $tournamentId = $this->tournamentService->create(
            $seasonId !== null ? (int) $seasonId : null,
            $validated['tournamentName'],
            $validated['date'],
            Auth::id(),
        );

        if ($seasonId !== null) {
            return redirect()
                ->route('seasons.show', ['season' => $seasonId])
                ->with('success', 'Pomyślnie stworzono turniej!');
        }

        return redirect()
            ->route('tournaments.start', ['tournament' => $tournamentId])
            ->with('success', 'Pomyślnie stworzono turniej jednorazowy!');
    }

    public function show(Tournament $tournament)
    {
        $viewModel = $this->getTournamentGroupResults->get($tournament->id);
        $season = $viewModel->season();
        $tournamentDomain = $viewModel->tournament();
        $canManageTournament = $this->gameAuthorizationService->canManageTournament($tournament);

        $loginCodes = ($canManageTournament && $tournamentDomain->showsTabletLoginCodes())
            ? $this->loginCodeService->getCodesForTournament($tournament->id)
            : collect();

        return view('tournaments.show', [
            'tournament' => $tournamentDomain,
            'season' => $season,
            'groupStandings' => $viewModel->groupStandings(),
            'players' => $viewModel->players(),
            'games' => $viewModel->games(),
            'playoffGames' => $viewModel->playoffGames(),
            'groupNumbers' => $viewModel->groupNumbers(),
            'groupPlayoffHighlights' => $viewModel->groupPlayoffHighlights(),
            'achievements' => $viewModel->achievements(),
            'results' => $viewModel->results(),
            'tab' => \request()->get('tab', 'results'),
            'canManageTournament' => $canManageTournament,
            'loginCodes' => $loginCodes,
        ]);
    }

    public function groupsLive(Tournament $tournament): JsonResponse
    {
        return response()->json(
            $this->groupMatrixLiveService->snapshot($tournament->id),
        );
    }

    public function joinRequestsLive(Tournament $tournament): JsonResponse
    {
        $this->loadAndAuthorize($tournament->id);

        return response()->json(
            $this->joinRequestService->pendingSnapshot($tournament->id),
        );
    }

    public function edit(Tournament $tournament)
    {
        //
    }

    public function update(Request $request, Tournament $tournament)
    {
        //
    }

    public function destroy(Tournament $tournament)
    {
        //
    }

    public function start(Request $request, int $tournamentId): Factory|View
    {
        $tournament = $this->loadAndAuthorize($tournamentId);
        $tournamentModel = Tournament::findOrFail($tournamentId);
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

        $participants = $invitations
            ->filter(fn ($inv) => $inv->status === TournamentInvitationStatus::ACCEPTED)
            ->map(fn ($inv) => [
                'kind' => 'user',
                'playerId' => $inv->userPlayer?->id,
                'name' => $inv->userPlayer?->name ?? '—',
                'invitationId' => $inv->id,
            ])
            ->merge(
                $tournamentGuests->map(fn ($guest) => [
                    'kind' => 'guest',
                    'playerId' => $guest->id,
                    'name' => $guest->name,
                    'invitationId' => null,
                ])
            )
            ->sortBy('name')
            ->values();

        $invitationPipeline = $invitations
            ->filter(fn ($inv) => $inv->status !== TournamentInvitationStatus::ACCEPTED)
            ->sortBy(fn ($inv) => $inv->userPlayer?->name ?? '')
            ->values();

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
        foreach ([2, 4, 8, 16, 32] as $bracketSize) {
            $matchFormatStagesByBracket[$bracketSize] = array_map(
                static fn (GameStage $stage): array => [
                    'value' => $stage->value,
                    'label' => $stage->label(),
                ],
                GameStage::forPlayoffBracketSize($bracketSize),
            );
        }

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

        return view('tournaments.start', [
            'tournament' => $tournament,
            'invitationPipeline' => $invitationPipeline,
            'invitationPipelineLive' => $this->buildInvitationPipelineLive($tournamentId),
            'regulars' => $regulars,
            'participants' => $participants,
            'participantsLive' => $participants->map(fn ($p) => [
                'kind' => $p['kind'],
                'playerId' => $p['playerId'],
                'name' => $p['name'],
                'invitationId' => $p['invitationId'],
                'removeUrl' => $p['kind'] === 'user' && $p['invitationId']
                    ? route('tournaments.invitations.remove', [$tournamentId, $p['invitationId']], false)
                    : ($p['kind'] === 'guest'
                        ? route('tournaments.participants.guests.remove', $tournamentId, false)
                        : null),
            ])->values()->all(),
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
            'startingScoreOptions' => MatchFormat::ALLOWED_STARTING_SCORES,
            'defaultMatchFormat' => MatchFormat::default()->toArray(),
            'defaultMatchFormatsByStage' => $defaultMatchFormatsByStage,
            'hasLeagueFormatPresets' => $hasLeagueFormatPresets,
            'matchFormatStagesByBracket' => $matchFormatStagesByBracket,
            'oldMatchFormats' => old('matchFormats', []),
        ]);
    }

    public function searchInvitationUsers(Request $request, int $tournamentId): JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $search = trim((string) $request->input('q', $request->input('search', '')));

        try {
            $excludeIds = $this->invitationService->getActiveInvitedUserIds($tournamentId);
            $users = $this->userService->searchForTournamentInvitations($search, $excludeIds);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Nieprawidłowe wyszukiwanie.';

            return response()->json(['message' => $message, 'users' => []], 422);
        }

        return response()->json([
            'users' => $users
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->player?->name ?? '—',
                ])
                ->values()
                ->all(),
        ]);
    }

    public function sendInvitation(Request $request, int $tournamentId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $this->invitationService->send(
                $tournamentId,
                (int) $validated['user_id'],
                (int) Auth::id(),
            );
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($this->invitationActionPayload($tournamentId, 'Zaproszenie wysłane'));
        }

        return back()->with('success', 'Zaproszenie wysłane');
    }

    public function sendBulkInvitations(Request $request, int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $result = $this->invitationService->sendBulk(
            $tournamentId,
            $validated['user_ids'],
            (int) Auth::id(),
        );

        $message = sprintf(
            'Wysłano %d zaproszeń%s',
            $result['sent'],
            $result['skipped'] > 0 ? sprintf(' (%d pominięto)', $result['skipped']) : '',
        );

        return back()->with('success', $message);
    }

    public function cancelInvitation(Request $request, int $tournamentId, int $invitationId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        try {
            $this->invitationService->cancel($tournamentId, $invitationId);
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($this->invitationActionPayload($tournamentId, 'Zaproszenie anulowane'));
        }

        return back()->with('success', 'Zaproszenie anulowane');
    }

    public function removeParticipant(Request $request, int $tournamentId, int $invitationId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        try {
            $this->invitationService->removeParticipant($tournamentId, $invitationId);
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($this->joinActionPayload(
                $tournamentId,
                'Uczestnik usunięty z turnieju',
            ));
        }

        return back()->with('success', 'Uczestnik usunięty z turnieju');
    }

    public function regenerateJoinCode(int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);
        $tournament = Tournament::findOrFail($tournamentId);

        try {
            $this->joinRequestService->regenerateJoinCode($tournament);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Wygenerowano nowy kod QR');
    }

    public function toggleJoinCode(Request $request, int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);
        $tournament = Tournament::findOrFail($tournamentId);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        try {
            $this->joinRequestService->toggleJoinCode($tournament, (bool) $validated['enabled']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            $validated['enabled'] ? 'Przyjmowanie zgłoszeń włączone' : 'Przyjmowanie zgłoszeń wyłączone',
        );
    }

    public function approveJoinRequest(Request $request, int $tournamentId, int $requestId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        try {
            $this->joinRequestService->approve($tournamentId, $requestId, (int) Auth::id());
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($this->joinActionPayload(
                $tournamentId,
                'Zawodnik dołączony do turnieju',
            ));
        }

        return back()->with('success', 'Zawodnik dołączony do turnieju');
    }

    public function rejectJoinRequest(Request $request, int $tournamentId, int $requestId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        try {
            $this->joinRequestService->reject($tournamentId, $requestId, (int) Auth::id());
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($this->joinActionPayload(
                $tournamentId,
                'Zgłoszenie odrzucone',
            ));
        }

        return back()->with('success', 'Zgłoszenie odrzucone');
    }

    public function addGuestParticipant(Request $request, int $tournamentId): RedirectResponse
    {
        $tournament = $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'player_id' => 'required|integer|exists:players,id',
        ]);

        try {
            if ($tournament->season === null) {
                return back()->with('error', 'Ten turniej nie jest powiązany z sezonem — dodawaj uczestników przez wyszukiwanie użytkowników.');
            }

            $this->guestParticipantService->addFromRelatedPool(
                $tournamentId,
                (int) $validated['player_id'],
                $tournament->season->id,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournaments.start', ['tournament' => $tournamentId, 'tab' => 'guests'])
            ->with('success', 'Gość dodany do turnieju');
    }

    public function createGuestParticipant(Request $request, int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'name' => 'required|string|max:20',
        ]);

        try {
            $this->guestParticipantService->createAndAdd($tournamentId, $validated['name']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Gość dodany do turnieju');
    }

    public function removeGuestParticipant(Request $request, int $tournamentId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'player_id' => 'required|integer|exists:players,id',
        ]);

        try {
            $this->guestParticipantService->remove($tournamentId, (int) $validated['player_id']);
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($this->joinActionPayload(
                $tournamentId,
                'Gość usunięty z turnieju',
            ));
        }

        return redirect()
            ->route('tournaments.start', ['tournament' => $tournamentId, 'tab' => 'guests'])
            ->with('success', 'Gość usunięty z turnieju');
    }

    public function runTournament(Request $request, int $tournamentId)
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'groupsCount' => ['required', 'integer', 'min:2'],
            'playoffBracketSize' => ['required', 'integer', 'min:4'],
            'tabletsCount' => ['sometimes', 'integer', 'min:1'],
        ]);

        $playerIds = $this->playerService
            ->getTournamentStartPool($tournamentId)
            ->pluck('id')
            ->all();

        $groupsCount = (int) $validated['groupsCount'];
        $playoffBracketSize = (int) $validated['playoffBracketSize'];
        $tabletsCount = isset($validated['tabletsCount'])
            ? (int) $validated['tabletsCount']
            : $groupsCount;

        try {
            $formatsByStage = TournamentMatchFormatRequestParser::fromRunInput(
                $request->all(),
                $playoffBracketSize,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($playerIds === []) {
            return back()->with('error', 'Brak uczestników turnieju — dodaj zaakceptowanych zawodników lub gości');
        }

        try {
            if (! $this->tournamentService->tryCreateGroupGames(
                $tournamentId,
                $playerIds,
                $groupsCount,
                $playoffBracketSize,
                $tabletsCount,
                $formatsByStage,
            )) {
                return back()->with('error', 'Turniej już wystartował');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('tournaments.show', ['tournament' => $tournamentId])
            ->with('success', 'Turniej wystartował!');
    }

    public function admins(Request $request, int $tournamentId): Factory|View
    {
        $tournament = $this->loadAndAuthorize($tournamentId);
        $admins = $this->tournamentService->getAdmins($tournamentId);
        $searchUsers = collect();
        $search = $request->input('search');

        if ($search !== null && trim($search) !== '') {
            $excludeIds = $admins->pluck('id');
            $searchUsers = $this->userService->searchForTournamentInvitations($search, $excludeIds);
        }

        return view('tournaments.admins', [
            'tournament' => $tournament,
            'admins' => $admins,
            'searchUsers' => $searchUsers,
            'search' => $search,
        ]);
    }

    public function addAdmin(Request $request, int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->tournamentService->addAdmin($tournamentId, (int) $validated['user_id']);

        return redirect()
            ->route('tournaments.admins', $tournamentId)
            ->with('success', 'Uprawnienie administratora nadano pomyślnie');
    }

    public function removeAdmin(Request $request, int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $this->tournamentService->removeAdmin($tournamentId, (int) $validated['user_id']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournaments.admins', $tournamentId)
            ->with('success', 'Uprawnienie administratora usunięto pomyślnie');
    }

    public function loadAndAuthorize(int $tournamentId, array $additionalRelations = []): TournamentDomain
    {
        $allRelations = array_merge($additionalRelations, ['season', 'admins']);
        $tournament = Tournament::with($allRelations)->findOrFail($tournamentId);
        $this->gameAuthorizationService->authorizeManageTournament($tournament);

        return TournamentDomain::fromEloquent($tournament, $allRelations);
    }

    /**
     * @return array{message: string, requests: list<array<string, mixed>>, participants: list<array<string, mixed>>, participantCount: int, minPlayers: int}
     */
    private function joinActionPayload(int $tournamentId, string $message): array
    {
        $participants = $this->buildParticipantsLive($tournamentId);

        return [
            'message' => $message,
            'requests' => $this->joinRequestService->pendingSnapshot($tournamentId)['requests'],
            'participants' => $participants,
            'participantCount' => count($participants),
            'minPlayers' => TournamentStartRules::MIN_PLAYERS,
        ];
    }

    /**
     * @return array{message: string, invitationPipeline: list<array<string, mixed>>}
     */
    private function invitationActionPayload(int $tournamentId, string $message): array
    {
        return [
            'message' => $message,
            'invitationPipeline' => $this->buildInvitationPipelineLive($tournamentId),
        ];
    }

    /**
     * @return list<array{id: int, userId: int, name: string, status: string, statusLabel: string, isPending: bool, canReinvite: bool, cancelUrl: string}>
     */
    private function buildInvitationPipelineLive(int $tournamentId): array
    {
        $invitations = $this->invitationService->getForTournament($tournamentId);

        return $invitations
            ->filter(fn ($inv) => $inv->status !== TournamentInvitationStatus::ACCEPTED)
            ->sortBy(fn ($inv) => $inv->userPlayer?->name ?? '')
            ->values()
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

    /**
     * @return list<array{kind: string, playerId: int|null, name: string, invitationId: int|null, removeUrl: string|null}>
     */
    private function buildParticipantsLive(int $tournamentId): array
    {
        $invitations = $this->invitationService->getForTournament($tournamentId);
        $tournamentGuests = $this->playerService->getTournamentGuestParticipants($tournamentId);

        return $invitations
            ->filter(fn ($inv) => $inv->status === TournamentInvitationStatus::ACCEPTED)
            ->map(fn ($inv) => [
                'kind' => 'user',
                'playerId' => $inv->userPlayer?->id,
                'name' => $inv->userPlayer?->name ?? '—',
                'invitationId' => $inv->id,
                'removeUrl' => route('tournaments.invitations.remove', [$tournamentId, $inv->id], false),
            ])
            ->merge(
                $tournamentGuests->map(fn ($guest) => [
                    'kind' => 'guest',
                    'playerId' => $guest->id,
                    'name' => $guest->name,
                    'invitationId' => null,
                    'removeUrl' => route('tournaments.participants.guests.remove', $tournamentId, false),
                ])
            )
            ->sortBy('name')
            ->values()
            ->all();
    }
}
