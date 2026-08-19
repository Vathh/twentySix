<?php

namespace App\Http\Controllers;

use App\Enums\LeagueCalendarMode;
use App\Models\League\League;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Services\League\LeagueSeasonService;
use App\Services\League\LeagueService;
use DomainException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeagueSeasonController extends Controller
{
    public function __construct(
        private LeagueSeasonService $leagueSeasonService,
        private LeagueService $leagueService,
    ) {
    }

    public function create(League $league): Factory|View|RedirectResponse
    {
        $league = $this->leagueService->getForPolicy($league->id);
        $this->authorize('update', $league);

        if ($this->leagueService->hasOpenSeason($league->id)) {
            return redirect()
                ->route('leagues.show', $league)
                ->with('error', 'Ta liga ma już otwarty sezon. Dokończ go albo anuluj, zanim utworzysz nowy.');
        }

        return view('league-seasons.create', [
            'league' => $league,
            'organization' => $league->organization,
            'calendarModes' => LeagueCalendarMode::cases(),
            'matchdayLengthOptions' => [
                7 => 'Tydzień (7 dni)',
                14 => 'Dwa tygodnie (14 dni)',
                21 => 'Trzy tygodnie (21 dni)',
                28 => 'Cztery tygodnie (28 dni)',
            ],
        ]);
    }

    public function store(Request $request, League $league): RedirectResponse
    {
        $this->authorize('update', $this->leagueService->getForPolicy($league->id));

        $validated = $request->validate([
            'seasonName' => 'required|string|max:80',
            'calendar_mode' => ['required', Rule::in(['matchdays', 'deadline'])],
            'matchday_planning' => ['nullable', Rule::in(['fixed_length', 'equal_span'])],
            'rounds_each' => 'required|integer|in:1,2',
            'startDate' => 'required|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'deadline_at' => 'nullable|date',
            'matchday_length_days' => 'nullable|integer|min:1|max:60',
            'start_now' => 'sometimes|boolean',
            'allows_draws' => 'sometimes|boolean',
            'win_length' => 'nullable|integer|min:1|max:16',
        ]);

        try {
            $season = $this->leagueSeasonService->create(
                $league->id,
                $validated['seasonName'],
                $validated['calendar_mode'],
                (int) $validated['rounds_each'],
                $validated['startDate'],
                $validated['endDate'] ?? null,
                $validated['deadline_at'] ?? null,
                $request->boolean('start_now'),
                isset($validated['matchday_length_days']) ? (int) $validated['matchday_length_days'] : null,
                $validated['matchday_planning'] ?? null,
                $request->boolean('allows_draws'),
                (int) ($validated['win_length'] ?? 2),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('league-seasons.show', $season)
            ->with('success', $request->boolean('start_now')
                ? 'Sezon ligowy wystartował — wygenerowano mecze.'
                : 'Zapisano szkic sezonu ligowego.');
    }

    public function show(LeagueSeason $leagueSeason): Factory|View
    {
        $this->authorize('view', $leagueSeason);

        return view('league-seasons.show', $this->leagueSeasonService->showData($leagueSeason->id));
    }

    public function start(LeagueSeason $leagueSeason): RedirectResponse
    {
        $this->authorize('update', $this->leagueSeasonService->getForPolicy($leagueSeason->id));

        try {
            $this->leagueSeasonService->start($leagueSeason->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sezon wystartował — wygenerowano mecze.');
    }

    public function advance(LeagueSeason $leagueSeason): RedirectResponse
    {
        $this->authorize('update', $this->leagueSeasonService->getForPolicy($leagueSeason->id));

        try {
            $message = $this->leagueSeasonService->advance($leagueSeason->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    public function withdraw(Request $request, LeagueSeason $leagueSeason): RedirectResponse
    {
        $this->authorize('update', $this->leagueSeasonService->getForPolicy($leagueSeason->id));

        $validated = $request->validate([
            'player_id' => 'required|integer',
        ]);

        try {
            $this->leagueSeasonService->withdraw($leagueSeason->id, (int) $validated['player_id']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Zawodnik zrezygnował — mecze anulowane, wypada z piramidy.');
    }

    public function cancel(Request $request, LeagueSeason $leagueSeason): RedirectResponse
    {
        $season = $this->leagueSeasonService->getForPolicy($leagueSeason->id);
        $this->authorize('update', $season);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'season_name_confirmation' => ['required', 'string', Rule::in([$season->name])],
        ], [
            'current_password.current_password' => 'Hasło jest nieprawidłowe.',
            'season_name_confirmation.in' => 'Wpisz dokładnie nazwę sezonu, żeby potwierdzić anulowanie.',
        ]);

        try {
            $leagueId = $this->leagueSeasonService->cancel($season->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('leagues.show', $leagueId)
            ->with('success', 'Sezon ligowy anulowany. Skład piramidy wrócił do stanu sprzed startu.');
    }

    public function showGame(LeagueGame $leagueGame): Factory|View
    {
        $this->authorize('view', $leagueGame);

        return view('league-games.show', $this->leagueSeasonService->gameShowData($leagueGame->id));
    }

    public function updateResult(Request $request, LeagueGame $leagueGame): RedirectResponse
    {
        $this->authorize('update', $this->leagueSeasonService->getGameForPolicy($leagueGame->id));

        $validated = $request->validate([
            'player1_score' => 'required|integer|min:0|max:15',
            'player2_score' => 'required|integer|min:0|max:15',
        ]);

        try {
            $this->leagueSeasonService->recordResult(
                $leagueGame->id,
                (int) $validated['player1_score'],
                (int) $validated['player2_score'],
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('league-games.show', $leagueGame)
            ->with('success', 'Zapisano wynik.');
    }

    public function walkover(Request $request, LeagueGame $leagueGame): RedirectResponse
    {
        $this->authorize('update', $this->leagueSeasonService->getGameForPolicy($leagueGame->id));

        $validated = $request->validate([
            'walkover_type' => 'required|in:single,both',
            'winner_id' => 'nullable|integer',
        ]);

        try {
            $this->leagueSeasonService->recordWalkover(
                $leagueGame->id,
                $validated['walkover_type'],
                isset($validated['winner_id']) ? (int) $validated['winner_id'] : null,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('league-games.show', $leagueGame)
            ->with('success', 'Zapisano walkower.');
    }

    public function extend(Request $request, LeagueGame $leagueGame): RedirectResponse
    {
        $this->authorize('update', $this->leagueSeasonService->getGameForPolicy($leagueGame->id));

        $validated = $request->validate([
            'deadline_at' => 'required|date',
        ]);

        try {
            $this->leagueSeasonService->extendGame($leagueGame->id, $validated['deadline_at']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Przedłużono termin meczu.');
    }
}
