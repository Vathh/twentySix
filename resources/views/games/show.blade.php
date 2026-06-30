@extends('layouts.app')

@section('title', 'Mecz — '.$player1->name.' vs '.$player2->name)

@section('content')
    <div class="container mx-auto py-8 max-w-4xl text-text-primary">
        <a href="{{ $backUrl }}" class="text-light-green hover:underline text-sm mb-4 inline-block">← Powrót</a>

        <div class="flex flex-wrap items-center gap-3 mb-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-light-green/20 text-light-green border border-light-green/40">
                {{ $label }}
            </span>
            @if($subtitle)
                <span class="text-text-muted text-sm">{{ $subtitle }}</span>
            @endif
            @if($status === 'in_progress')
                <span class="px-2 py-0.5 rounded text-xs bg-light-orange/20 text-light-orange">Na żywo</span>
                <a href="{{ route('games.live', ['type' => $kind, 'id' => $gameId]) }}"
                   class="px-3 py-1 rounded text-xs font-semibold border border-light-orange text-light-orange hover:bg-light-orange/10 transition">
                    Podgląd live
                </a>
            @elseif($status === 'finished')
                <span class="px-2 py-0.5 rounded text-xs bg-light-green/15 text-light-green border border-light-green/30">Zakończony</span>
            @endif
        </div>

        <h1 class="text-2xl font-bold text-light-green mb-6">
            {{ $player1->name }}
            <span class="text-text-muted font-normal">vs</span>
            {{ $player2->name }}
        </h1>

        <div class="bg-lighter-bg rounded-lg p-6 mb-8 border border-border text-center">
            <p class="text-text-muted text-sm mb-2">Wynik meczu (legi)</p>
            <p class="text-4xl font-bold text-text-primary">
                <span class="{{ (int)$winnerId === (int)$player1->id ? 'text-light-green' : 'text-text-primary' }}">{{ $player1Score }}</span>
                <span class="text-text-muted mx-3">:</span>
                <span class="{{ (int)$winnerId === (int)$player2->id ? 'text-light-green' : 'text-text-primary' }}">{{ $player2Score }}</span>
            </p>
            @if($status !== 'finished')
                <p class="text-text-muted text-xs mt-2">
                    @if($status === 'in_progress')
                        @if($canCorrectResult ?? false)
                            Mecz w trakcie — możesz wymusić wynik końcowy poniżej.
                        @else
                            Mecz w trakcie.
                        @endif
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

        @php
            $p1 = $players[0];
            $p2 = $players[1];
            $fmtAvg = static fn ($v) => $v !== null ? number_format((float) $v, 2, '.', '') : '—';
            $fmtInt = static fn ($v) => $v !== null ? (string) $v : '—';
        @endphp

        <h2 class="text-xl font-semibold text-light-green mb-4">Statystyki meczu</h2>
        <div class="bg-lighter-bg rounded-lg border border-border mb-8 overflow-x-auto">
            <table class="w-full text-sm text-text-primary min-w-[320px]">
                <thead>
                    <tr class="border-b border-border bg-darker-bg/60">
                        <th class="text-left py-3 px-4 text-text-muted font-medium w-2/5"></th>
                        <th class="text-center py-3 px-4 text-light-green font-semibold">{{ $p1['name'] }}</th>
                        <th class="text-center py-3 px-4 text-light-green font-semibold">{{ $p2['name'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border bg-light-orange/10">
                        <td colspan="3" class="py-2 px-4 text-light-orange font-semibold text-xs uppercase tracking-wide">Średnia (3 lotki)</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">Cała gra</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $fmtAvg($p1['matchAverage']) }}</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $fmtAvg($p2['matchAverage']) }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">Najlepszy leg</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $fmtAvg($p1['bestLegAverage']) }}</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $fmtAvg($p2['bestLegAverage']) }}</td>
                    </tr>
                    @if($status === 'in_progress')
                        <tr class="border-b border-border/50">
                            <td class="py-2.5 px-4 text-text-muted">Aktualny leg</td>
                            <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $fmtAvg($p1['currentLegAverage']) }}</td>
                            <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $fmtAvg($p2['currentLegAverage']) }}</td>
                        </tr>
                    @endif

                    <tr class="border-b border-border bg-light-orange/10">
                        <td colspan="3" class="py-2 px-4 text-light-orange font-semibold text-xs uppercase tracking-wide">Osiągi</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">Najlepszy leg (lotki)</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $fmtInt($p1['bestLegThrows']) }}</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $fmtInt($p2['bestLegThrows']) }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">60+</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p1['plus60'] }}</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p2['plus60'] }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">80+</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p1['plus80'] }}</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p2['plus80'] }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">100+</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p1['plus100'] }}</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p2['plus100'] }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">140+</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p1['plus140'] }}</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p2['plus140'] }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">180</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p1['max180'] }}</td>
                        <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p2['max180'] }}</td>
                    </tr>

                    @if($p1['doublePercent'] !== null || $p2['doublePercent'] !== null || $p1['hf']->isNotEmpty() || $p2['hf']->isNotEmpty() || $p1['qf']->isNotEmpty() || $p2['qf']->isNotEmpty())
                        <tr class="border-b border-border bg-light-orange/10">
                            <td colspan="3" class="py-2 px-4 text-light-orange font-semibold text-xs uppercase tracking-wide">Turniej</td>
                        </tr>
                        <tr class="border-b border-border/50">
                            <td class="py-2.5 px-4 text-text-muted">Skuteczność na double (mecz)</td>
                            <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p1['doublePercent'] !== null ? $p1['doublePercent'].'%' : '—' }}</td>
                            <td class="py-2.5 px-4 text-center text-light-white font-medium">{{ $p2['doublePercent'] !== null ? $p2['doublePercent'].'%' : '—' }}</td>
                        </tr>
                        @if($p1['hf']->isNotEmpty() || $p2['hf']->isNotEmpty())
                            <tr class="border-b border-border/50">
                                <td class="py-2.5 px-4 text-text-muted">HF (finish)</td>
                                <td class="py-2.5 px-4 text-center text-light-white font-medium text-xs">{{ $p1['hf']->pluck('value')->filter()->join(', ') ?: '—' }}</td>
                                <td class="py-2.5 px-4 text-center text-light-white font-medium text-xs">{{ $p2['hf']->pluck('value')->filter()->join(', ') ?: '—' }}</td>
                            </tr>
                        @endif
                        @if($p1['qf']->isNotEmpty() || $p2['qf']->isNotEmpty())
                            <tr class="border-b border-border/50">
                                <td class="py-2.5 px-4 text-text-muted">QF (lotki)</td>
                                <td class="py-2.5 px-4 text-center text-light-white font-medium text-xs">{{ $p1['qf']->pluck('value')->filter()->join(', ') ?: '—' }}</td>
                                <td class="py-2.5 px-4 text-center text-light-white font-medium text-xs">{{ $p2['qf']->pluck('value')->filter()->join(', ') ?: '—' }}</td>
                            </tr>
                        @endif
                    @endif
                </tbody>
            </table>
        </div>

        <h2 class="text-xl font-semibold text-light-green mb-4">Legi i wizyty</h2>

        @if($legsDetail->isEmpty())
            <p class="text-text-muted">Brak rozegranych legów — szczegóły pojawią się po rozpoczęciu liczenia z wizytami.</p>
        @else
            @foreach($legsDetail as $block)
                <div class="bg-darker-bg rounded-lg p-4 mb-4 border border-border text-text-primary">
                    <h3 class="font-semibold text-light-orange mb-2">
                        Leg {{ $block['leg']->leg_number }}
                        @if($block['leg']->finished_at)
                            <span class="text-text-primary font-normal">— zwycięzca: {{ $block['leg']->winner_id === $player1->id ? $player1->name : $player2->name }}</span>
                        @else
                            <span class="text-light-green text-sm font-normal">(w trakcie)</span>
                        @endif
                    </h3>

                    @if($block['playerStats']->isNotEmpty())
                        <table class="w-full text-xs mb-3 text-text-primary">
                            <thead>
                            <tr class="text-text-muted">
                                <th class="text-left py-1 font-medium">Gracz</th>
                                <th class="text-center font-medium">Śr. leg</th>
                                <th class="text-center font-medium">Śr. 9 lotek</th>
                                <th class="text-center font-medium">Max wiz.</th>
                                <th class="text-center font-medium">Double</th>
                            </tr>
                            </thead>
                            <tbody class="text-light-white">
                            @foreach($block['playerStats'] as $stat)
                                <tr class="border-b border-border/40">
                                    <td class="py-1.5">{{ $stat->player->name ?? $stat->player_id }}</td>
                                    <td class="text-center py-1.5">{{ $stat->leg_average ?? '—' }}</td>
                                    <td class="text-center py-1.5">{{ $stat->first_nine_average ?? '—' }}</td>
                                    <td class="text-center py-1.5">{{ $stat->highest_visit ?? '—' }}</td>
                                    <td class="text-center py-1.5">
                                        @if($stat->double_tracked && $stat->double_attempts)
                                            {{ $stat->double_successes }}/{{ $stat->double_attempts }}
                                        @else
                                            <span class="text-text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if($block['visits']->isNotEmpty())
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-semibold text-light-green mb-2">{{ $player1->name }}</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-text-primary">
                                        <thead>
                                        <tr class="text-text-muted border-b border-border">
                                            <th class="text-left py-1 font-medium">#</th>
                                            <th class="text-center font-medium">Pkt</th>
                                            <th class="text-center font-medium">Zostało</th>
                                        </tr>
                                        </thead>
                                        <tbody class="text-light-white">
                                        @foreach($block['visits']->where('player_id', $player1->id) as $visit)
                                            <tr class="border-b border-border/50 {{ $visit->bust ? 'opacity-60' : '' }}">
                                                <td class="py-1.5">{{ $visit->visit_number }}</td>
                                                <td class="text-center py-1.5">{{ $visit->bust ? 'Bust' : $visit->score }}</td>
                                                <td class="text-center py-1.5">{{ $visit->remaining_after }}</td>
                                            </tr>
                                        @endforeach
                                        @if($block['visits']->where('player_id', $player1->id)->isEmpty())
                                            <tr>
                                                <td colspan="3" class="py-2 text-text-muted text-center">Brak wizyt</td>
                                            </tr>
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-light-green mb-2">{{ $player2->name }}</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-text-primary">
                                        <thead>
                                        <tr class="text-text-muted border-b border-border">
                                            <th class="text-left py-1 font-medium">#</th>
                                            <th class="text-center font-medium">Pkt</th>
                                            <th class="text-center font-medium">Zostało</th>
                                        </tr>
                                        </thead>
                                        <tbody class="text-light-white">
                                        @foreach($block['visits']->where('player_id', $player2->id) as $visit)
                                            <tr class="border-b border-border/50 {{ $visit->bust ? 'opacity-60' : '' }}">
                                                <td class="py-1.5">{{ $visit->visit_number }}</td>
                                                <td class="text-center py-1.5">{{ $visit->bust ? 'Bust' : $visit->score }}</td>
                                                <td class="text-center py-1.5">{{ $visit->remaining_after }}</td>
                                            </tr>
                                        @endforeach
                                        @if($block['visits']->where('player_id', $player2->id)->isEmpty())
                                            <tr>
                                                <td colspan="3" class="py-2 text-text-muted text-center">Brak wizyt</td>
                                            </tr>
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="text-text-muted text-sm">Brak zapisanych wizyt.</p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
@endsection
