<?php

namespace App\Http\Controllers;

use App\Domain\LeagueDomain;
use App\Enums\AssignableEntityType;
use App\Models\League;
use App\Models\User;
use App\Services\LeagueService;
use App\Services\LeagueStatsService;
use App\Services\PlayerService;
use App\Services\UserService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeagueController extends Controller
{
    public function __construct(
        private LeagueService $leagueService,
        private LeagueStatsService $leagueStatsService,
        private UserService $userService,
        private PlayerService $playerService
    ) {
        $this->authorizeResource(League::class, 'league');
    }

    public function index(): Factory|View
    {
        $leagues = $this->leagueService->getAll();

        return view('leagues.index', ['leagues' => $leagues]);
    }

    public function create(): Factory|View
    {
        return view('leagues.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'leagueName' => 'required|string|max:255|unique:leagues,name',
            'description' => 'string|max:500',
        ]);

        $this->leagueService->create($validated['leagueName'], $validated['description'], Auth::id());

        return redirect()
                    ->route('leagues.index')
                    ->with('success', 'Pomyślnie stworzono ligę!');
    }

    public function show(League $league): Factory|View
    {
        $leagueDomain = LeagueDomain::fromEloquent($league, ['admins', 'seasons']);
        $seasons = collect($leagueDomain->seasons)
            ->sortByDesc(fn($season) => $season->updatedAt)
            ->values();

        $standings = $this->leagueStatsService->getTop40($league->id);

        return view('leagues.show', [
            'league' => $leagueDomain,
            'seasons' => $seasons,
            'standings' => $standings,
        ]);
    }

    public function edit(League $league): Factory|View
    {
        $leagueDomain = LeagueDomain::fromEloquent($league, ['admins']);

        return view('leagues.edit', ['league' => $leagueDomain]);
    }

    public function update(Request $request, League $league)
    {
        $validated = $request->validate([
            'leagueName' => 'required|string|max:255',
            'description' => 'required|string|max:500',
        ]);

        $this->leagueService->update(
            $league->id,
            $validated['leagueName'],
            $validated['description']
        );

        return redirect()
            ->route('leagues.show', $league->id)
            ->with('success', 'Pomyślnie zaktualizowano ligę');
    }

    public function relatedUsers(Request $request, int $leagueId): Factory|View
    {
        $league = $this->loadAndAuthorize($leagueId, ['relatedUsers']);

        $search = $request->input('search');

        $users = $this->userService->search($league->relatedUsers, $search);

        $relatedUsers = $this->userService->sortByName($league->relatedUsers);

        return view('leagues.relatedUsers', [
            'league' => $league,
            'relatedUsers' => $relatedUsers,
            'users' => $users
        ]);
    }

    public function addRelatedUser(Request $request, int $leagueId)
    {
        $this->loadAndAuthorize($leagueId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->leagueService->addRelatedUser($leagueId, $validated['user_id']);

        return redirect()
                    ->route('leagues.relatedUsers', $leagueId)
                    ->with('success', 'Użytkownik dodany do ligi');
    }

    public function removeRelatedUser(Request $request, int $leagueId)
    {
        $this->loadAndAuthorize($leagueId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->leagueService->removeRelatedUser($leagueId, $validated['user_id']);

        return redirect()
                    ->route('leagues.relatedUsers', $leagueId)
                    ->with('success', 'Użytkownik usunięty z ligi');
    }

    public function admins(int $leagueId): Factory|View
    {
        $league = $this->loadAndAuthorize($leagueId, ['relatedUsers']);
        $admins = $league->admins;
        $relatedUsers = $this->userService->sortByNameAndRejectAdmins($league->relatedUsers, $league->admins);

        return view('leagues.admins', [
            'league' => $league,
            'admins' => $admins,
            'relatedUsers' => $relatedUsers
        ]);
    }

    public function addAdmin(Request $request, int $leagueId)
    {
        $this->loadAndAuthorize($leagueId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->leagueService->addAdmin($leagueId, $validated['user_id']);

        return redirect()
                    ->route('leagues.admins', $leagueId)
                    ->with('success', 'Uprawnienie administratora nadano pomyślnie');
    }

    public function removeAdmin(Request $request, int $leagueId)
    {
        $this->loadAndAuthorize($leagueId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->leagueService->removeAdmin($leagueId, $validated['user_id']);

        return redirect()
                    ->route('leagues.admins', $leagueId)
                    ->with('success', 'Uprawnienie administratora usunięto pomyślnie');
    }

    public function guests(int $leagueId): Factory|View
    {
        $league = $this->loadAndAuthorize($leagueId, ['guests']);

        $guests = $this->userService->sortByName($league->guests);

        return view('leagues.guests', [
            'league' => $league,
            'guests' => $guests
        ]);
    }

    public function addGuest(Request $request, int $leagueId)
    {
        $this->loadAndAuthorize($leagueId);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:20',
                new \App\Rules\UniquePlayerNameInLeague($leagueId),
            ],
        ]);

        $this->playerService->createGuest($validated['name'], $leagueId, AssignableEntityType::LEAGUE);

        return redirect()
                    ->route('leagues.guests', $leagueId)
                    ->with('success', 'Pomyślnie dodano gościa');
    }

    public function removeGuest(Request $request, int $leagueId)
    {
        $this->loadAndAuthorize($leagueId);

        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
        ]);

        $this->playerService->removeGuest($validated['player_id']);

        return redirect()
            ->route('leagues.guests', $leagueId)
            ->with('success', 'Pomyślnie usunięto gościa');
    }

    public function loadAndAuthorize(int $leagueId, array $additionalRelations = []): LeagueDomain
    {
        $allRelations = array_merge($additionalRelations, ['admins']);
        $league = League::with($allRelations)->findOrFail($leagueId);
        $this->authorize('update', $league);

        return LeagueDomain::fromEloquent($league, $allRelations);
    }
}
