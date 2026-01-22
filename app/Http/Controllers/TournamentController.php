<?php

namespace App\Http\Controllers;

use App\Domain\Tournament\TournamentDomain;
use App\Models\Season;
use App\Models\Tournament;
use App\Queries\GetTournamentData;
use App\Services\PlayerService;
use App\Services\Tournament\TournamentService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{

    public function __construct(
        private TournamentService $tournamentService,
        private PlayerService     $playerService,
        private GetTournamentData $getTournamentGroupResults,
    )
    {
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
            'date' => 'required|date'
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

    public function start(int $tournamentId)
    {
        $tournament = $this->loadAndAuthorize($tournamentId);
        $players = $this->playerService
                        ->getRelatedPlayers($tournament->season->id)
                        ->sortBy('name');

        return view('tournaments.start', [
            'tournament' => $tournament,
            'players' => $players
        ]);
    }

    public function runTournament(Request $request, int $tournamentId)
    {
        $this->loadAndAuthorize($tournamentId);

        $validated = $request->validate([
            'selectedPlayers' => 'required',
            'groupsCount' => ['required', Rule::in(['2', '4', '8'])]
        ]);

        $selectedPlayersIds = json_decode($request->input('selectedPlayers'), false);
        $groupsCount = $validated['groupsCount'];

        if(empty($selectedPlayersIds)) {
            return back()->with('error', 'Wybrano zbyt mało graczy');
        }

        if(!$this->tournamentService->tryCreateGroupGames($tournamentId, $selectedPlayersIds, $groupsCount)) {
            return back()->with('error', 'Turniej już wystartował');
        }

        return redirect()->route('tournaments.show',
                                        ['tournament' => $tournamentId])
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
