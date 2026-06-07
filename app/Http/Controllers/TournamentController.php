<?php

namespace App\Http\Controllers;

use App\Domain\Tournament\TournamentDomain;
use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentStatus;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Queries\GetTournamentData;
use App\Services\Player\PlayerService;
use App\Services\Tournament\TournamentGuestParticipantService;
use App\Services\Tournament\TournamentInvitationService;
use App\Services\Tournament\TournamentService;
use App\Services\User\UserService;
use App\Support\Tournament\TournamentStartRules;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TournamentController extends Controller
{

    public function __construct(
        private TournamentService $tournamentService,
        private PlayerService $playerService,
        private TournamentInvitationService $invitationService,
        private TournamentGuestParticipantService $guestParticipantService,
        private UserService $userService,
        private GetTournamentData $getTournamentGroupResults,
    ) {
    }

    public function index()
    {
        $tournaments = $this->tournamentService->getAll();

        return view('tournaments.index', ['tournaments' => $tournaments]);
    }

    public function create(Request $request): Factory|View
    {
        $seasonId = $request->query('seasonId');

        $this->authorize('update', Season::findOrFail($seasonId));

        return view('tournaments.create', ['seasonId' => $seasonId]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tournamentName' => 'required|string|max:25',
            'date' => 'required|date',
        ]);

        $seasonId = $request->query('seasonId');

        $this->tournamentService->create($seasonId, $validated['tournamentName'], $validated['date']);

        return redirect()
            ->route('seasons.show', ['season' => $seasonId])
            ->with('success', 'Pomyślnie stworzono turniej!');
    }

    public function show(Tournament $tournament)
    {
        $viewModel = $this->getTournamentGroupResults->get($tournament->id);

        return view('tournaments.show', [
            'tournament' => $viewModel->tournament(),
            'season' => $viewModel->season(),
            'groupStandings' => $viewModel->groupStandings(),
            'players' => $viewModel->players(),
            'games' => $viewModel->games(),
            'playoffGames' => $viewModel->playoffGames(),
            'groupNumbers' => $viewModel->groupNumbers(),
            'achievements' => $viewModel->achievements(),
            'results' => $viewModel->results(),
            'tab' => \request()->get('tab', 'results'),
        ]);
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
        $seasonId = $tournament->season->id;

        $invitations = $this->invitationService->getForTournament($tournamentId);
        $invitationByUserId = $invitations->keyBy(fn ($inv) => $inv->userId);

        $regulars = $this->playerService
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
            ->values();

        $searchUsers = collect();
        $search = $request->input('search');

        if ($search !== null && trim($search) !== '') {
            $excludeIds = $this->invitationService->getActiveInvitedUserIds($tournamentId);
            $searchUsers = $this->userService->searchForTournamentInvitations($search, $excludeIds);
        }

        $tournamentGuests = $this->playerService->getTournamentGuestParticipants($tournamentId);
        $tournamentGuestIds = $tournamentGuests->pluck('id');

        $relatedGuests = $this->playerService
            ->getSeasonGuests($seasonId)
            ->map(fn ($guest) => [
                'playerId' => $guest->id,
                'name' => $guest->name,
                'inTournament' => $tournamentGuestIds->contains($guest->id),
            ])
            ->sortBy('name')
            ->values();

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

        return view('tournaments.start', [
            'tournament' => $tournament,
            'invitationPipeline' => $invitationPipeline,
            'regulars' => $regulars,
            'searchUsers' => $searchUsers,
            'participants' => $participants,
            'participantCount' => $participants->count(),
            'relatedGuests' => $relatedGuests,
            'addTab' => $addTab,
            'canManageParticipants' => $tournament->status === TournamentStatus::CREATED,
            'groupCountOptions' => TournamentStartRules::allowedGroupCounts(),
            'advancesByGroupCount' => TournamentStartRules::advancesByGroupCount(),
            'minPlayers' => TournamentStartRules::MIN_PLAYERS,
        ]);
    }

    public function sendInvitation(Request $request, int $tournamentId): RedirectResponse
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
            return back()->with('error', $e->getMessage());
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

    public function cancelInvitation(int $tournamentId, int $invitationId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);

        try {
            $this->invitationService->cancel($tournamentId, $invitationId);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Zaproszenie anulowane');
    }

    public function removeParticipant(int $tournamentId, int $invitationId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);

        try {
            $this->invitationService->removeParticipant($tournamentId, $invitationId);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Uczestnik usunięty z turnieju');
    }

    public function addGuestParticipant(Request $request, int $tournamentId): RedirectResponse
    {
        $tournament = $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'player_id' => 'required|integer|exists:players,id',
        ]);

        try {
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

    public function removeGuestParticipant(Request $request, int $tournamentId): RedirectResponse
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'player_id' => 'required|integer|exists:players,id',
        ]);

        try {
            $this->guestParticipantService->remove($tournamentId, (int) $validated['player_id']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournaments.start', ['tournament' => $tournamentId, 'tab' => 'guests'])
            ->with('success', 'Gość usunięty z turnieju');
    }

    public function runTournament(Request $request, int $tournamentId)
    {
        $tournament = $this->loadAndAuthorize($tournamentId);
        $seasonId = $tournament->season->id;

        $validated = $request->validate([
            'groupsCount' => ['required', 'integer', 'min:2'],
            'advancePerGroup' => ['sometimes', 'integer', 'min:1'],
            'tabletsCount' => ['sometimes', 'integer', 'min:1'],
        ]);

        $playerIds = $this->playerService
            ->getTournamentStartPool($tournamentId, $seasonId)
            ->pluck('id')
            ->all();

        $groupsCount = (int) $validated['groupsCount'];
        $advancePerGroup = isset($validated['advancePerGroup'])
            ? (int) $validated['advancePerGroup']
            : 2;
        $tabletsCount = isset($validated['tabletsCount'])
            ? (int) $validated['tabletsCount']
            : $groupsCount;

        if ($playerIds === []) {
            return back()->with('error', 'Brak uczestników turnieju — dodaj zaakceptowanych zawodników lub gości');
        }

        try {
            if (! $this->tournamentService->tryCreateGroupGames(
                $tournamentId,
                $playerIds,
                $groupsCount,
                $advancePerGroup,
                $tabletsCount,
            )) {
                return back()->with('error', 'Turniej już wystartował');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('tournaments.show', ['tournament' => $tournamentId])
            ->with('success', 'Turniej wystartował!');
    }

    public function loadAndAuthorize(int $tournamentId, array $additionalRelations = []): TournamentDomain
    {
        $allRelations = array_merge($additionalRelations, ['season']);
        $tournament = Tournament::with($allRelations)->findOrFail($tournamentId);
        $this->authorize('update', $tournament->season);

        return TournamentDomain::fromEloquent($tournament, $allRelations);
    }
}
