@if($canManageLeague ?? false)
    <div class="bg-bg-deep rounded-lg p-6 mb-8 border border-accent/40">
        <h2 class="text-lg font-semibold text-accent mb-1">Wynik / walkower</h2>
        <p class="text-text-muted text-sm mb-4">
            Format: {{ $formatLabel ?? '501 · do 2 legów' }}.
            Wpisz wynik w {{ $scoreUnit ?? 'legach' }} (do {{ $scoreToWin ?? 2 }}).
        </p>

        <form method="POST" action="{{ route('league-games.result', $gameId) }}" class="space-y-4">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-sm text-text-muted">{{ $player1->name }}</span>
                    <input
                        type="number"
                        name="player1_score"
                        min="0"
                        max="{{ $scoreToWin ?? 2 }}"
                        value="{{ old('player1_score', $player1Score) }}"
                        class="mt-1 w-full rounded border border-border bg-bg px-3 py-2 text-text-secondary"
                        required
                    >
                </label>
                <label class="block">
                    <span class="text-sm text-text-muted">{{ $player2->name }}</span>
                    <input
                        type="number"
                        name="player2_score"
                        min="0"
                        max="{{ $scoreToWin ?? 2 }}"
                        value="{{ old('player2_score', $player2Score) }}"
                        class="mt-1 w-full rounded border border-border bg-bg px-3 py-2 text-text-secondary"
                        required
                    >
                </label>
            </div>
            <button type="submit"
                    class="px-4 py-2 rounded bg-accent text-on-accent font-semibold text-sm hover:bg-accent-hover transition">
                Zapisz wynik
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-border">
            <p class="text-sm text-text-muted mb-3">Walkover:</p>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('league-games.walkover', $gameId) }}">
                    @csrf
                    <input type="hidden" name="walkover_type" value="single">
                    <input type="hidden" name="winner_id" value="{{ $player1->id }}">
                    <button type="submit"
                            onclick="return confirm('Ustawić walkover dla {{ $player1->name }}?')"
                            class="px-3 py-2 rounded border border-accent text-accent text-sm hover:bg-accent/10 transition">
                        Walkover → {{ $player1->name }}
                    </button>
                </form>
                <form method="POST" action="{{ route('league-games.walkover', $gameId) }}">
                    @csrf
                    <input type="hidden" name="walkover_type" value="single">
                    <input type="hidden" name="winner_id" value="{{ $player2->id }}">
                    <button type="submit"
                            onclick="return confirm('Ustawić walkover dla {{ $player2->name }}?')"
                            class="px-3 py-2 rounded border border-accent text-accent text-sm hover:bg-accent/10 transition">
                        Walkover → {{ $player2->name }}
                    </button>
                </form>
                <form method="POST" action="{{ route('league-games.walkover', $gameId) }}">
                    @csrf
                    <input type="hidden" name="walkover_type" value="both">
                    <button type="submit"
                            onclick="return confirm('Ustawić walkover obustronny 0:0?')"
                            class="px-3 py-2 rounded border border-accent text-accent text-sm hover:bg-accent/10 transition">
                        Walkover obustronny (0:0)
                    </button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('league-games.extend', $gameId) }}" class="mt-6 pt-4 border-t border-border space-y-3">
            @csrf
            <label class="block">
                <span class="text-sm text-text-muted">Przedłuż termin</span>
                <input
                    type="date"
                    name="deadline_at"
                    value="{{ old('deadline_at', $leagueDeadline) }}"
                    class="mt-1 w-full rounded border border-border bg-bg px-3 py-2 text-text-secondary"
                    required
                >
            </label>
            <button type="submit"
                    class="px-4 py-2 rounded border border-border text-text-secondary text-sm hover:border-accent/40 hover:text-accent transition">
                Przedłuż
            </button>
        </form>

        <x-errors/>
    </div>
@endif
