@extends('layouts.app')

@section('title', 'Mecz — '.$player1->name.' vs '.$player2->name)

@section('content')
    <div class="container mx-auto py-8 max-w-4xl">
        <a href="{{ $backUrl }}" class="text-light-green hover:underline text-sm mb-4 inline-block">← Powrót</a>

        <div class="flex flex-wrap items-center gap-3 mb-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-light-green/20 text-light-green border border-light-green/40">
                {{ $label }}
            </span>
            @if($subtitle)
                <span class="text-text-muted text-sm">{{ $subtitle }}</span>
            @endif
            @if($isLive)
                <span class="px-2 py-0.5 rounded text-xs bg-light-orange/20 text-light-orange">Na żywo</span>
            @endif
            <a href="{{ route('games.live', ['type' => $kind, 'id' => $gameId]) }}"
               class="px-3 py-1 rounded text-xs font-semibold border border-light-orange text-light-orange hover:bg-light-orange/10 transition">
                Podgląd live
            </a>
        </div>

        <h1 class="text-2xl font-bold text-light-green mb-6">
            {{ $player1->name }}
            <span class="text-text-muted font-normal">vs</span>
            {{ $player2->name }}
        </h1>

        <div class="bg-lighter-bg rounded-lg p-6 mb-8 border border-border text-center">
            <p class="text-text-muted text-sm mb-2">Wynik meczu (legi)</p>
            <p class="text-4xl font-bold">
                <span class="{{ (int)$winnerId === (int)$player1->id ? 'text-light-green' : '' }}">{{ $player1Score }}</span>
                <span class="text-text-muted mx-3">:</span>
                <span class="{{ (int)$winnerId === (int)$player2->id ? 'text-light-green' : '' }}">{{ $player2Score }}</span>
            </p>
            @if($status !== 'finished')
                <p class="text-text-muted text-xs mt-2">
                    @if($status === 'in_progress')
                        Mecz w trakcie — możesz wymusić wynik końcowy poniżej.
                    @else
                        Mecz jeszcze nie rozegrany.
                    @endif
                </p>
            @endif
        </div>

        @if($canCorrectResult ?? false)
            <div class="bg-darker-bg rounded-lg p-6 mb-8 border border-light-orange/40">
                <h2 class="text-lg font-semibold text-light-orange mb-1">Korekta wyniku / walkower</h2>
                <p class="text-text-muted text-sm mb-4">
                    BO{{ ($legsToWin ?? 2) * 2 - 1 }} — wpisz wynik w legach (np. 2:0, 2:1). Po zapisie tabele i drabinka przeliczą się automatycznie.
                </p>

                <form method="POST" action="{{ route('games.result.update', ['type' => $kind, 'id' => $gameId]) }}" class="space-y-4">
                    @csrf
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-sm text-text-muted">{{ $player1->name }}</span>
                            <input
                                type="number"
                                name="player1_score"
                                min="0"
                                max="{{ $legsToWin ?? 2 }}"
                                value="{{ old('player1_score', $player1Score) }}"
                                class="mt-1 w-full rounded border border-border bg-dark-bg px-3 py-2 text-light-white"
                                required
                            >
                        </label>
                        <label class="block">
                            <span class="text-sm text-text-muted">{{ $player2->name }}</span>
                            <input
                                type="number"
                                name="player2_score"
                                min="0"
                                max="{{ $legsToWin ?? 2 }}"
                                value="{{ old('player2_score', $player2Score) }}"
                                class="mt-1 w-full rounded border border-border bg-dark-bg px-3 py-2 text-light-white"
                                required
                            >
                        </label>
                    </div>
                    <button type="submit"
                            class="px-4 py-2 rounded bg-light-green text-dark-bg font-semibold text-sm hover:opacity-90 transition">
                        Zapisz wynik
                    </button>
                </form>

                <div class="mt-6 pt-4 border-t border-border">
                    <p class="text-sm text-text-muted mb-3">Walkover (2:0 w legach):</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('games.result.update', ['type' => $kind, 'id' => $gameId]) }}">
                            @csrf
                            <input type="hidden" name="walkover" value="1">
                            <input type="hidden" name="winner_id" value="{{ $player1->id }}">
                            <button type="submit"
                                    onclick="return confirm('Ustawić walkover 2:0 dla {{ $player1->name }}?')"
                                    class="px-3 py-2 rounded border border-light-orange text-light-orange text-sm hover:bg-light-orange/10 transition">
                                Walkover → {{ $player1->name }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('games.result.update', ['type' => $kind, 'id' => $gameId]) }}">
                            @csrf
                            <input type="hidden" name="walkover" value="1">
                            <input type="hidden" name="winner_id" value="{{ $player2->id }}">
                            <button type="submit"
                                    onclick="return confirm('Ustawić walkover 2:0 dla {{ $player2->name }}?')"
                                    class="px-3 py-2 rounded border border-light-orange text-light-orange text-sm hover:bg-light-orange/10 transition">
                                Walkover → {{ $player2->name }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            @foreach($players as $p)
                <div class="bg-lighter-bg rounded-lg p-4 border border-border">
                    <h2 class="text-lg font-semibold text-light-orange mb-3">{{ $p['name'] }}</h2>
                    <dl class="text-sm space-y-1">
                        <div class="flex justify-between">
                            <dt class="text-text-muted">Skuteczność na double (mecz)</dt>
                            <dd>{{ $p['doublePercent'] !== null ? $p['doublePercent'].'%' : '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-text-muted">180</dt>
                            <dd>{{ $p['max'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-text-muted">170+</dt>
                            <dd>{{ $p['oneSeventy'] }}</dd>
                        </div>
                    </dl>
                    @if($p['hf']->isNotEmpty() || $p['qf']->isNotEmpty())
                        <p class="text-xs text-text-muted mt-3">HF: {{ $p['hf']->pluck('value')->filter()->join(', ') ?: '—' }}</p>
                        <p class="text-xs text-text-muted">QF (lotki): {{ $p['qf']->pluck('value')->filter()->join(', ') ?: '—' }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <h2 class="text-xl font-semibold text-light-green mb-4">Legi i wizyty</h2>

        @forelse($legsDetail as $block)
            @php($leg = $block['leg'])
            <div class="bg-darker-bg rounded-lg p-4 mb-4 border border-border">
                <h3 class="font-semibold text-light-orange mb-2">
                    Leg {{ $leg->leg_number }}
                    @if($leg->finished_at)
                        — zwycięzca: {{ $leg->winner_id === $player1->id ? $player1->name : $player2->name }}
                    @else
                        <span class="text-light-green text-sm">(w trakcie)</span>
                    @endif
                </h3>

                @if($block['playerStats']->isNotEmpty())
                    <table class="w-full text-xs mb-3">
                        <thead>
                        <tr class="text-text-muted">
                            <th class="text-left py-1">Gracz</th>
                            <th class="text-center">Śr. leg</th>
                            <th class="text-center">Śr. 9 lotek</th>
                            <th class="text-center">Max wiz.</th>
                            <th class="text-center">Double</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($block['playerStats'] as $stat)
                            <tr>
                                <td class="py-1">{{ $stat->player->name ?? $stat->player_id }}</td>
                                <td class="text-center">{{ $stat->leg_average ?? '—' }}</td>
                                <td class="text-center">{{ $stat->first_nine_average ?? '—' }}</td>
                                <td class="text-center">{{ $stat->highest_visit ?? '—' }}</td>
                                <td class="text-center">
                                    @if($stat->double_tracked && $stat->double_attempts)
                                        {{ $stat->double_successes }}/{{ $stat->double_attempts }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif

                @if($block['visits']->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                            <tr class="text-text-muted border-b border-border">
                                <th class="text-left py-1">#</th>
                                <th class="text-left">Gracz</th>
                                <th class="text-center">Pkt</th>
                                <th class="text-center">Przed</th>
                                <th class="text-center">Po</th>
                                <th class="text-center">Lotki</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($block['visits'] as $visit)
                                <tr class="border-b border-border/50">
                                    <td class="py-1">{{ $visit->visit_number }}</td>
                                    <td>{{ $visit->player_id === $player1->id ? $player1->name : $player2->name }}</td>
                                    <td class="text-center">{{ $visit->score }}</td>
                                    <td class="text-center">{{ $visit->remaining_before }}</td>
                                    <td class="text-center">{{ $visit->remaining_after }}</td>
                                    <td class="text-center">{{ $visit->darts_in_visit }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-text-muted text-sm">Brak zapisanych wizyt.</p>
                @endif
            </div>
        @empty
            <p class="text-text-muted">Brak rozegranych legów — szczegóły pojawią się po rozpoczęciu liczenia z wizytami.</p>
        @endforelse
    </div>
@endsection
