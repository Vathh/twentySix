<?php

namespace App\Http\Controllers;

use App\Domain\LeagueDomain;
use App\Domain\SeasonDomain;
use App\Models\League;
use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SeasonController extends Controller
{
    public function __construct(private SeasonService $seasonService)
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
}
