<?php

namespace App\Http\Controllers;

use App\Domain\Tournament\TournamentDomain;
use App\Domain\Tournament\TournamentStartRules;
use App\Models\Tournament\Tournament;
use App\Queries\GetTournamentData;
use App\Services\GameScoring\GameAuthorizationService;
use App\Services\Tournament\LoginCodeService;
use App\Services\Tournament\TournamentCancelService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use App\Services\Tournament\TournamentGuestParticipantService;
use App\Services\Tournament\TournamentInvitationService;
use App\Services\Tournament\TournamentJoinRequestService;
use App\Services\Tournament\TournamentPlayoffBracketLiveService;
use App\Services\Tournament\TournamentService;
use App\Services\Tournament\TournamentStartPageService;
use App\Services\User\UserService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TournamentController extends Controller
{
    public function __construct(
        private TournamentService $tournamentService,
        private TournamentInvitationService $invitationService,
        private TournamentJoinRequestService $joinRequestService,
        private TournamentGuestParticipantService $guestParticipantService,
        private UserService $userService,
        private GetTournamentData $getTournamentGroupResults,
        private LoginCodeService $loginCodeService,
        private GameAuthorizationService $gameAuthorizationService,
        private TournamentGroupMatrixLiveService $groupMatrixLiveService,
        private TournamentPlayoffBracketLiveService $playoffBracketLiveService,
        private TournamentStartPageService $startPageService,
        private TournamentCancelService $tournamentCancelService,
    ) {}

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
        $this->tournamentService->assertCanCreate($seasonId !== null ? (int) $seasonId : null);

        return view('tournaments.create', ['seasonId' => $seasonId]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournamentName' => 'required|string|max:25',
            'date' => 'required|date',
        ]);

        $seasonId = $request->query('seasonId');
        $resolvedSeasonId = $seasonId !== null ? (int) $seasonId : null;
        $this->tournamentService->assertCanCreate($resolvedSeasonId);

        $tournamentId = $this->tournamentService->create(
            $resolvedSeasonId,
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

        $loginCode = ($canManageTournament && $tournamentDomain->showsTabletLoginCodes())
            ? $this->loginCodeService->getCodeForTournament($tournament->id)
            : null;
        $loginUrl = $loginCode !== null
            ? $this->loginCodeService->loginUrl($loginCode)
            : null;

        return view('tournaments.show', [
            'tournament' => $tournamentDomain,
            'season' => $season,
            'groupStandings' => $viewModel->groupStandings(),
            'players' => $viewModel->players(),
            'games' => $viewModel->games(),
            'playoffGames' => $viewModel->playoffGames(),
            'consolationPlayoffGames' => $viewModel->playoffGames(\App\Enums\BracketSide::Consolation),
            'groupNumbers' => $viewModel->groupNumbers(),
            'groupPlayoffHighlights' => $viewModel->groupPlayoffHighlights(),
            'achievements' => $viewModel->achievements(),
            'results' => $viewModel->results(),
            'tab' => $this->resolveShowTab($tournamentDomain),
            'canManageTournament' => $canManageTournament,
            'loginCode' => $loginCode,
            'loginUrl' => $loginUrl,
        ]);
    }

    public function groupsLive(Tournament $tournament): JsonResponse
    {
        return response()->json(
            $this->groupMatrixLiveService->snapshot($tournament->id),
        );
    }

    public function playoffLive(Tournament $tournament): JsonResponse
    {
        return response()->json(
            $this->playoffBracketLiveService->snapshot($tournament->id),
        );
    }

    public function cancel(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->loadAndAuthorize($tournament->id);

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Hasło jest nieprawidłowe.',
        ]);

        $this->tournamentCancelService->cancel($tournament->id);

        return redirect()
            ->route('tournaments.start', $tournament)
            ->with('success', 'Rozgrywki anulowane. Możesz wystartować turniej od nowa.');
    }

    public function joinRequestsLive(Tournament $tournament): JsonResponse
    {
        $this->loadAndAuthorize($tournament->id);

        return response()->json(array_merge(
            $this->joinRequestService->pendingSnapshot($tournament->id),
            $this->startPageService->rosterLiveSnapshot($tournament->id),
        ));
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

        return view('tournaments.start', $this->startPageService->build($tournament, $tournamentId, $request));
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

        $authId = (int) Auth::id();
        $self = (int) $validated['user_id'] === $authId;
        $message = $self ? 'Dodano Cię do turnieju' : 'Zaproszenie wysłane';

        return $this->respondToAction(
            $request,
            fn () => $this->invitationService->send($tournamentId, (int) $validated['user_id'], $authId),
            $message,
            fn () => $this->invitationActionPayload($tournamentId, $message),
        );
    }

    public function joinSelf(Request $request, int $tournamentId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        return $this->respondToAction(
            $request,
            fn () => $this->invitationService->joinSelf($tournamentId, (int) Auth::id()),
            'Dodano Cię do turnieju',
            fn () => $this->invitationActionPayload($tournamentId, 'Dodano Cię do turnieju'),
        );
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

        return $this->respondToAction(
            $request,
            fn () => $this->invitationService->cancel($tournamentId, $invitationId),
            'Zaproszenie anulowane',
            fn () => $this->invitationActionPayload($tournamentId, 'Zaproszenie anulowane'),
        );
    }

    public function removeParticipant(Request $request, int $tournamentId, int $invitationId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        return $this->respondToAction(
            $request,
            fn () => $this->invitationService->removeParticipant($tournamentId, $invitationId),
            'Uczestnik usunięty z turnieju',
            fn () => $this->joinActionPayload($tournamentId, 'Uczestnik usunięty z turnieju'),
        );
    }

    public function regenerateJoinCode(int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);
        $tournament = $this->tournamentService->getModel($tournamentId);

        try {
            $this->joinRequestService->regenerateJoinCode($tournament);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Wygenerowano nowy kod QR');
    }

    public function regenerateTabletLoginCode(int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $this->loginCodeService->regenerateForTournament($tournamentId);

        return back()->with('success', 'Wygenerowano nowy kod logowania tabletu');
    }

    public function toggleJoinCode(Request $request, int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);
        $tournament = $this->tournamentService->getModel($tournamentId);

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

        return $this->respondToAction(
            $request,
            fn () => $this->joinRequestService->approve($tournamentId, $requestId, (int) Auth::id()),
            'Zawodnik dołączony do turnieju',
            fn () => $this->joinActionPayload($tournamentId, 'Zawodnik dołączony do turnieju'),
        );
    }

    public function rejectJoinRequest(Request $request, int $tournamentId, int $requestId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        return $this->respondToAction(
            $request,
            fn () => $this->joinRequestService->reject($tournamentId, $requestId, (int) Auth::id()),
            'Zgłoszenie odrzucone',
            fn () => $this->joinActionPayload($tournamentId, 'Zgłoszenie odrzucone'),
        );
    }

    public function addGuestParticipant(Request $request, int $tournamentId): RedirectResponse|JsonResponse
    {
        $tournament = $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'player_id' => 'required|integer|exists:players,id',
        ]);

        if ($tournament->season === null) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Ten turniej nie jest powiązany z sezonem — dodawaj uczestników przez wyszukiwanie użytkowników.',
                ], 422);
            }

            return back()->with('error', 'Ten turniej nie jest powiązany z sezonem — dodawaj uczestników przez wyszukiwanie użytkowników.');
        }

        return $this->respondToAction(
            $request,
            fn () => $this->guestParticipantService->addFromRelatedPool(
                $tournamentId,
                (int) $validated['player_id'],
                $tournament->season->id,
            ),
            'Gość dodany do turnieju',
            fn () => $this->joinActionPayload($tournamentId, 'Gość dodany do turnieju'),
        );
    }

    public function createGuestParticipant(Request $request, int $tournamentId): RedirectResponse|JsonResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'name' => 'required|string|max:20',
        ]);

        return $this->respondToAction(
            $request,
            fn () => $this->guestParticipantService->createAndAdd($tournamentId, $validated['name']),
            'Gość dodany do turnieju',
            fn () => $this->joinActionPayload($tournamentId, 'Gość dodany do turnieju'),
        );
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
            'tournamentFormat' => ['required', 'string', 'in:groups_playoff,single_elimination,double_elimination'],
            'groupsCount' => ['required_if:tournamentFormat,groups_playoff', 'nullable', 'integer', 'min:2'],
            'playoffBracketSize' => ['required_if:tournamentFormat,groups_playoff', 'nullable', 'integer', 'min:4'],
            'grandFinalMode' => ['required_if:tournamentFormat,double_elimination', 'nullable', 'string', 'in:single,reset'],
            'hasConsolationBracket' => ['sometimes', 'boolean'],
        ]);

        try {
            $started = $this->tournamentService->runFromWeb(
                $tournamentId,
                $validated,
                $request->all(),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        if (! $started) {
            return back()->with('error', 'Turniej już wystartował');
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

        $this->tournamentService->removeAdmin($tournamentId, (int) $validated['user_id']);

        return redirect()
            ->route('tournaments.admins', $tournamentId)
            ->with('success', 'Uprawnienie administratora usunięto pomyślnie');
    }

    public function loadAndAuthorize(int $tournamentId, array $additionalRelations = []): TournamentDomain
    {
        return $this->tournamentService->loadAndAuthorize($tournamentId, $additionalRelations);
    }

    /**
     * Wspólny try/catch + JSON-vs-redirect dla akcji turniejowych zwracających `RuntimeException` na błąd.
     *
     * @param  callable(): mixed  $action
     * @param  callable(): array<string, mixed>  $jsonPayload
     */
    private function respondToAction(
        Request $request,
        callable $action,
        string $successMessage,
        callable $jsonPayload,
    ): RedirectResponse|JsonResponse {
        try {
            $action();
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($jsonPayload());
        }

        return back()->with('success', $successMessage);
    }

    private function resolveShowTab(TournamentDomain $tournament): string
    {
        $requested = request()->query('tab');
        $allowed = $tournament->allowedShowTabs();

        if (is_string($requested) && in_array($requested, $allowed, true)) {
            return $requested;
        }

        return $tournament->defaultShowTab();
    }

    /**
     * @return array{message: string, requests: list<array<string, mixed>>, participants: list<array<string, mixed>>, participantCount: int, minPlayers: int}
     */
    private function joinActionPayload(int $tournamentId, string $message): array
    {
        $participants = $this->startPageService->buildParticipantsLive($tournamentId);

        return [
            'message' => $message,
            'requests' => $this->joinRequestService->pendingSnapshot($tournamentId)['requests'],
            'participants' => $participants,
            'participantCount' => count($participants),
            'minPlayers' => TournamentStartRules::MIN_PLAYERS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationActionPayload(int $tournamentId, string $message): array
    {
        return array_merge(
            $this->startPageService->rosterLiveSnapshot($tournamentId),
            ['message' => $message],
        );
    }
}
