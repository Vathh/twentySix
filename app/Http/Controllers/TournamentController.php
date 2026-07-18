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
use App\Services\Tournament\TournamentInvitationService;
use App\Services\Tournament\TournamentService;
use App\Services\User\UserService;
use App\Support\Tournament\TournamentStartRules;
use App\Support\GameScoring\MatchFormat;
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
        private TournamentGuestParticipantService $guestParticipantService,
        private UserService $userService,
        private GetTournamentData $getTournamentGroupResults,
        private LoginCodeService $loginCodeService,
        private GameAuthorizationService $gameAuthorizationService,
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

        $loginCodes = ($canManageTournament && $tournamentDomain->isStarted())
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

        $searchUsers = collect();
        $search = $request->input('search');

        if ($search !== null && trim($search) !== '') {
            $excludeIds = $this->invitationService->getActiveInvitedUserIds($tournamentId);
            $searchUsers = $this->userService->searchForTournamentInvitations($search, $excludeIds);
        }

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

        return view('tournaments.start', [
            'tournament' => $tournament,
            'invitationPipeline' => $invitationPipeline,
            'regulars' => $regulars,
            'searchUsers' => $searchUsers,
            'participants' => $participants,
            'participantCount' => $participantCount,
            'relatedGuests' => $relatedGuests,
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
            'matchFormatStagesByBracket' => $matchFormatStagesByBracket,
            'oldMatchFormats' => old('matchFormats', []),
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
}
