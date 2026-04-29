<?php

namespace App\Http\Controllers;

use App\Domain\SeasonDomain;
use App\Enums\AssignableEntityType;
use App\Models\League;
use App\Models\Season;
use App\Rules\UniquePlayerInSeasonAndLeague;
use App\Services\Player\PlayerService;
use App\Services\Season\SeasonService;
use App\Services\User\UserService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SeasonController extends Controller
{
    public function __construct(
        private SeasonService $seasonService,
        private UserService $userService,
        private PlayerService $playerService,
    )
    {
    }

    public function index(): Factory|View
    {
        $seasons = $this->seasonService->getAll();

        return view('seasons.index', ['seasons' => $seasons]);
    }

    public function create(Request $request): Factory|View
    {
        $leagueId = $request->query('leagueId');
        $league = League::with('admins')->findOrFail($leagueId);

        $this->authorize('createSeason', $league);

        return view('seasons.create', ['leagueId' => $leagueId]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
           'seasonName' => 'required|string|max:25|unique:seasons,name',
           'startDate' => 'required|date',
           'endDate' => 'required|date',
        ]);

        $leagueId = $request->query('leagueId');

        $this->seasonService->create( $leagueId, $validated['seasonName'], (array)Auth::id(), $validated['startDate'], $validated['endDate']);

        return redirect()
            ->route('leagues.show', ['league' => $leagueId])
            ->with('success', 'Pomyślnie stworzono sezon!');
    }

    public function show(Season $season)
    {
        $seasonDomain = SeasonDomain::fromEloquent($season, ['admins', 'league', 'tournaments']);

        return view('seasons.show', ['season' => $seasonDomain]);
    }

    public function edit(Season $season)
    {
        $season = SeasonDomain::fromEloquent($season);

        return view('seasons.edit', ['season' => $season]);
    }

    public function update(Request $request, Season $season)
    {
        //
    }

    public function destroy(Season $season)
    {
        //
    }

    public function relatedUsers(Request $request, int $seasonId): Factory|View
    {
        $season = $this->loadAndAuthorize($seasonId, ['relatedUsers']);

        $search = $request->input('search');

        $users = $this->userService->search($season->relatedUsers, $search);

        $relatedUsers = $this->seasonService->getRelatedUsers($seasonId);

        return view('seasons.relatedUsers', [
            'season' => $season,
            'relatedUsers' => $relatedUsers,
            'users' => $users
        ]);
    }

    public function addRelatedUser(Request $request, int $seasonId)
    {
        $this->loadAndAuthorize($seasonId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->seasonService->addRelatedUser($seasonId, $validated['user_id']);

        return redirect()
            ->route('seasons.relatedUsers', $seasonId)
            ->with('success', 'Użytkownik dodany do sezonu');
    }

    public function removeRelatedUser(Request $request, int $seasonId)
    {
        $this->loadAndAuthorize($seasonId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->seasonService->removeRelatedUser($seasonId, $validated['user_id']);

        return redirect()
            ->route('seasons.relatedUsers', $seasonId)
            ->with('success', 'Użytkownik usunięty z sezonu');
    }

    public function admins(int $seasonId): Factory|View
    {
        $season = $this->loadAndAuthorize($seasonId, ['relatedUsers']);
        $admins = $season->admins;
        $relatedUsers = $this->seasonService->getRelatedUsers($seasonId)
                                            ->map(fn($user) => [
                                                    'id' => $user->id,
                                                    'name' => $user->player->name])
                                            ->toArray();
        $filteredRelatedUsers = $this->userService->sortByNameAndRejectAdmins($relatedUsers, $season->admins);

        return view('seasons.admins', [
            'season' => $season,
            'admins' => $admins,
            'relatedUsers' => $filteredRelatedUsers
        ]);
    }

    public function addAdmin(Request $request, int $seasonId)
    {
        $this->loadAndAuthorize($seasonId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->seasonService->addAdmin($seasonId, $validated['user_id']);

        return redirect()
            ->route('seasons.admins', $seasonId)
            ->with('success', 'Uprawnienie administratora nadano pomyślnie');
    }

    public function removeAdmin(Request $request, int $seasonId)
    {
        $this->loadAndAuthorize($seasonId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->seasonService->removeAdmin($seasonId, $validated['user_id']);

        return redirect()
            ->route('seasons.admins', $seasonId)
            ->with('success', 'Uprawnienie administratora usunięto pomyślnie');
    }

    public function guests(int $seasonId): Factory|View
    {
        $season = $this->loadAndAuthorize($seasonId, ['guests']);

        $guests = $this->userService->sortByName($season->guests);

        return view('seasons.guests', [
            'season' => $season,
            'guests' => $guests
        ]);
    }

    /**
     * @throws Throwable
     */
    public function addGuest(Request $request, int $seasonId)
    {
        $season = $this->loadAndAuthorize($seasonId, ['league']);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:20',
                new \App\Rules\UniquePlayerNameInSeason($seasonId, $season->league->id),
            ],
        ]);

        $this->playerService->createGuest($validated['name'], $seasonId, AssignableEntityType::SEASON);

        return redirect()
            ->route('seasons.guests', $seasonId)
            ->with('success', 'Pomyślnie dodano gościa');
    }

    public function removeGuest(Request $request, int $seasonId)
    {
        $this->loadAndAuthorize($seasonId);

        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
        ]);

        $this->playerService->removeGuest($validated['player_id']);

        return redirect()
            ->route('seasons.guests', $seasonId)
            ->with('success', 'Pomyślnie usunięto gościa');
    }

    public function loadAndAuthorize(int $seasonId, array $additionalRelations = []): SeasonDomain
    {
        $allRelations = array_merge($additionalRelations, ['admins']);
        $season = Season::with($allRelations)->findOrFail($seasonId);
        $this->authorize('update', $season);

        return SeasonDomain::fromEloquent($season, $allRelations);
    }
}









