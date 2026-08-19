<?php

namespace App\Http\Controllers;

use App\Models\League\League;
use App\Models\Organization\Organization;
use App\Services\League\LeagueService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class LeagueController extends Controller
{
    public function __construct(
        private LeagueService $leagueService,
    ) {
    }

    public function create(Organization $organization): Factory|View
    {
        $organization->loadMissing('admins');
        $this->authorize('createLeague', $organization);

        return view('leagues.create', [
            'organization' => $organization,
            'startingScores' => [101, 201, 301, 401, 501, 601, 701, 801, 901, 1001],
            'defaultDivisions' => [
                ['name' => 'Ekstraklasa', 'capacity' => 8, 'startingScore' => 501, 'legsToWinSet' => 2, 'setsToWinMatch' => 1, 'promoteDirect' => 0, 'promotePlayoff' => 0],
                ['name' => '1. liga', 'capacity' => 8, 'startingScore' => 501, 'legsToWinSet' => 2, 'setsToWinMatch' => 1, 'promoteDirect' => 2, 'promotePlayoff' => 0],
            ],
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $organization->loadMissing('admins');
        $this->authorize('createLeague', $organization);

        $validated = $request->validate([
            'leagueName' => 'required|string|max:80',
            'description' => 'nullable|string|max:500',
            'divisions' => 'required|array|min:1',
            'divisions.*.name' => 'required|string|max:40',
            'divisions.*.capacity' => 'required|integer|min:2|max:16',
            'divisions.*.startingScore' => 'required|integer',
            'divisions.*.legsToWinSet' => 'required|integer|min:1|max:15',
            'divisions.*.setsToWinMatch' => 'required|integer|min:1|max:5',
            'divisions.*.promoteDirect' => 'nullable|integer|min:0|max:8',
            'divisions.*.promotePlayoff' => 'nullable|integer|min:0|max:8',
        ]);

        $league = $this->leagueService->create(
            $organization->id,
            $validated['leagueName'],
            $validated['description'] ?? null,
            $validated['divisions'],
        );

        return redirect()
            ->route('leagues.show', $league)
            ->with('success', 'Utworzono ligę. Ustaw skład szczebli, potem wystartuj sezon.');
    }

    public function show(League $league): Factory|View
    {
        $this->authorize('view', $league);

        return view('leagues.show', $this->leagueService->showData($league->id));
    }

    public function edit(League $league): Factory|View
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $data = $this->leagueService->showData($league->id);

        return view('leagues.edit', [
            ...$data,
            'startingScores' => [101, 201, 301, 401, 501, 601, 701, 801, 901, 1001],
        ]);
    }

    public function update(Request $request, League $league): RedirectResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'leagueName' => 'required|string|max:80',
            'description' => 'nullable|string|max:500',
            'divisions' => 'required|array|min:1',
            'divisions.*.id' => 'nullable|integer',
            'divisions.*.name' => 'required|string|max:40',
            'divisions.*.capacity' => 'required|integer|min:2|max:16',
            'divisions.*.startingScore' => 'required|integer',
            'divisions.*.legsToWinSet' => 'required|integer|min:1|max:15',
            'divisions.*.setsToWinMatch' => 'required|integer|min:1|max:5',
            'divisions.*.promoteDirect' => 'nullable|integer|min:0|max:8',
            'divisions.*.promotePlayoff' => 'nullable|integer|min:0|max:8',
        ]);

        try {
            $this->leagueService->update($league->id, $validated['leagueName'], $validated['description'] ?? null);
            $this->leagueService->updateDivisions($league->id, $validated['divisions']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('leagues.show', $league)
            ->with('success', 'Zapisano ligę.');
    }

    public function roster(League $league): Factory|View
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        return view('leagues.roster', $this->leagueService->rosterData($league->id));
    }

    public function assignPlayer(Request $request, League $league): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'division_id' => 'required|integer',
            'player_id' => 'required|integer',
        ]);

        try {
            $this->leagueService->assignPlayer($league->id, (int) $validated['division_id'], (int) $validated['player_id']);
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Dodano zawodnika do szczebla.');
    }

    public function removePlayer(Request $request, League $league): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'player_id' => 'required|integer',
        ]);

        try {
            $this->leagueService->removePlayer($league->id, (int) $validated['player_id']);
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Usunięto zawodnika ze składu ligi.');
    }

    public function updateDivisionCapacity(Request $request, League $league): JsonResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'division_id' => 'required|integer',
            'capacity' => 'required|integer|min:2|max:16',
        ]);

        try {
            $this->leagueService->updateDivisionCapacity(
                $league->id,
                (int) $validated['division_id'],
                (int) $validated['capacity'],
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json([
            'ok' => true,
            'capacity' => (int) $validated['capacity'],
        ]);
    }

    public function relatedUsers(Request $request, League $league): Factory|View
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        return view('leagues.relatedUsers', $this->leagueService->relatedUsersData(
            $league->id,
            $request->input('search'),
        ));
    }

    public function addRelatedUser(Request $request, League $league): RedirectResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->leagueService->addRelatedUser($league->id, (int) $validated['user_id']);

        return redirect()
            ->route('leagues.relatedUsers', $league)
            ->with('success', 'Użytkownik dodany do puli ligi');
    }

    public function removeRelatedUser(Request $request, League $league): RedirectResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $this->leagueService->removeRelatedUser($league->id, (int) $validated['user_id']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('leagues.relatedUsers', $league)
            ->with('success', 'Użytkownik usunięty z puli ligi');
    }

    public function guests(League $league): Factory|View
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        return view('leagues.guests', $this->leagueService->guestsData($league->id));
    }

    public function addGuest(Request $request, League $league): RedirectResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:20',
                new \App\Rules\UniquePlayerNameInLeague($league->id),
            ],
        ]);

        $this->leagueService->addGuest($league->id, $validated['name']);

        return redirect()
            ->route('leagues.guests', $league)
            ->with('success', 'Pomyślnie dodano gościa');
    }

    public function removeGuest(Request $request, League $league): RedirectResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
        ]);

        try {
            $this->leagueService->removeGuest($league->id, (int) $validated['player_id']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('leagues.guests', $league)
            ->with('success', 'Pomyślnie usunięto gościa');
    }
}
