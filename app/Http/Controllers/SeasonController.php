<?php

namespace App\Http\Controllers;

use App\Domain\LeagueDomain;
use App\Domain\SeasonDomain;
use App\Models\League;
use App\Models\Season;
use App\Services\SeasonService;
use App\Services\UserService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SeasonController extends Controller
{
    public function __construct(
        private SeasonService $seasonService,
        private UserService $userService
    )
    {
    }

    public function index(): Factory|View
    {
        return view('seasons.index');
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

        $season = $this->seasonService->create( $leagueId, $validated['seasonName'], (array)Auth::id(), $validated['startDate'], $validated['endDate']);

        return redirect()
            ->route('leagues.show', ['league' => $leagueId])
            ->with('success', 'Pomyślnie stworzono sezon!');
    }

    public function show(Season $season)
    {
        $seasonDomain = SeasonDomain::fromEloquentWithAdmins($season);

        return view('seasons.show', ['season' => $seasonDomain]);
    }

    public function edit(Season $season)
    {
        //
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
        $season = $this->loadAndAuthorize($seasonId);

        $search = $request->input('search');

        $users = $this->userService->search($season->relatedUsers, $search);

        $relatedUsers = $this->userService->sortByName($season->relatedUsers);

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
            'user_id' => ['required', 'exists:users,id'],
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
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $this->seasonService->removeRelatedUser($seasonId, $validated['user_id']);

        return redirect()
            ->route('seasons.relatedUsers', $seasonId)
            ->with('success', 'Użytkownik usunięty z sezonu');
    }

    public function admins(int $seasonId): Factory|View
    {
        $season = $this->loadAndAuthorize($seasonId);
        $admins = $season->admins;
        $relatedUsers = $this->userService->sortByNameAndRejectAdmins($season->relatedUsers, $season->admins);

        return view('seasons.admins', [
            'season' => $season,
            'admins' => $admins,
            'relatedUsers' => $relatedUsers
        ]);
    }

    public function addAdmin(Request $request, int $seasonId)
    {
        $this->loadAndAuthorize($seasonId);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
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
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $this->seasonService->removeAdmin($seasonId, $validated['user_id']);

        return redirect()
            ->route('seasons.admins', $seasonId)
            ->with('success', 'Uprawnienie administratora usunięto pomyślnie');
    }

    public function loadAndAuthorize(int $seasonId): SeasonDomain
    {
        $season = Season::with('admins')->findOrFail($seasonId);
        $this->authorize('update', $season);

        return SeasonDomain::fromEloquentWithAdmins($season);
    }
}
