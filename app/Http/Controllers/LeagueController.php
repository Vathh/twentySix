<?php

namespace App\Http\Controllers;

use App\Domain\LeagueDomain;
use App\Models\League;
use App\Models\User;
use App\Services\LeagueService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LeagueController extends Controller
{
    public function __construct(private LeagueService $leagueService){
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
        $leagueDomain = LeagueDomain::fromEloquentWithAdmins($league);

        return view('leagues.show', ['league' => $leagueDomain]);
    }

    public function edit(League $league): Factory|View
    {
        $leagueDomain = LeagueDomain::fromEloquentWithAdmins($league);

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
        $league = $this->loadAndAuthorize($leagueId);

        $search = $request->input('search');

        $users = $this->leagueService->searchUsers($league, $search);

        $relatedUsers = $this->leagueService->getRelatedUsersSortedByName($league);

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
            'user_id' => ['required', 'exists:users,id'],
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
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $this->leagueService->removeRelatedUser($leagueId, $validated['user_id']);

        return redirect()
                    ->route('leagues.relatedUsers', $leagueId)
                    ->with('success', 'Użytkownik usunięty z ligi');
    }

    public function admins(int $leagueId): Factory|View
    {
        $league = $this->loadAndAuthorize($leagueId);
        $admins = $league->admins;
        $relatedUsers = $this->leagueService->getRelatedUsersSortedByNameAndRejectAdmins($league);

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
            'user_id' => ['required', 'exists:users,id'],
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
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $this->leagueService->removeAdmin($leagueId, $validated['user_id']);

        return redirect()
                    ->route('leagues.admins', $leagueId)
                    ->with('success', 'Uprawnienie administratora usunięto pomyślnie');
    }

    public function loadAndAuthorize(int $leagueId): LeagueDomain
    {
        $league = League::with('admins')->findOrFail($leagueId);
        $this->authorize('update', $league);

        return LeagueDomain::fromEloquentWithAdmins($league);
    }
}
