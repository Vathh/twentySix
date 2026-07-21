@extends('layouts.app')

@section('title', 'Mecz — '.$player1->name.' vs '.$player2->name)

@section('content')
    <div class="container mx-auto py-8 max-w-4xl text-text">
        <a href="{{ $backUrl }}" class="link-back mb-4 inline-block">← Powrót</a>

        <div class="flex flex-wrap items-center gap-3 mb-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold badge-live">
                {{ $label }}
            </span>
            @if($subtitle)
                <span class="text-text-muted text-sm">{{ $subtitle }}</span>
            @endif
            @if($status === 'in_progress')
                <span class="px-2 py-0.5 rounded text-xs bg-accent/20 text-accent">Na żywo</span>
                <a href="{{ route('games.live', ['type' => $kind, 'id' => $gameId]) }}"
                   class="px-3 py-1 rounded text-xs font-semibold border border-accent text-accent hover:bg-accent/10 transition">
                    Podgląd live
                </a>
            @elseif($status === 'finished')
                <span class="px-2 py-0.5 rounded text-xs bg-success-muted text-success-bright border border-success/30">Zakończony</span>
            @endif
        </div>

        <h1 class="page-title mb-6">
            {{ $player1->name }}
            <span class="text-text-muted font-normal">vs</span>
            {{ $player2->name }}
        </h1>

        <div class="bg-bg-elevated rounded-lg p-6 mb-8 border border-border text-center">
            <p class="text-text-muted text-sm mb-1">{{ $formatLabel ?? '501 · do 2 legów' }}</p>
            <p class="text-text-muted text-sm mb-2">Wynik meczu ({{ $scoreUnit ?? 'legi' }})</p>
            <p class="text-4xl font-bold text-text score-num">
                <span class="{{ (int)$winnerId === (int)$player1->id ? 'text-accent' : 'text-text' }}">{{ $player1Score }}</span>
                <span class="text-text-muted mx-3">:</span>
                <span class="{{ (int)$winnerId === (int)$player2->id ? 'text-accent' : 'text-text' }}">{{ $player2Score }}</span>
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
                        @if($canCorrectResult ?? false)
                            Mecz jeszcze nie rozegrany — możesz ustawić wynik lub walkover poniżej.
                        @else
                            Mecz jeszcze nie rozegrany.
                        @endif
                    @endif
                </p>
            @endif
        </div>

        @if($canCorrectResult ?? false)
            <div class="bg-bg-deep rounded-lg p-6 mb-8 border border-accent/40">
                <h2 class="text-lg font-semibold text-accent mb-1">Korekta wyniku / walkower</h2>
                <p class="text-text-muted text-sm mb-4">
                    Format: {{ $formatLabel ?? '501 · do 2 legów' }}.
                    Wpisz wynik w {{ $scoreUnit ?? 'legach' }} (do {{ $scoreToWin ?? 2 }}).
                    Po zapisie tabele i drabinka przeliczą się automatycznie.
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
                            class="px-4 py-2 rounded bg-success text-on-success font-semibold text-sm hover:opacity-90 transition">
                        Zapisz wynik
                    </button>
                </form>

                <div class="mt-6 pt-4 border-t border-border">
                    <p class="text-sm text-text-muted mb-3">Walkover ({{ $walkoverScoreLine ?? '2:0 legi' }}):</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('games.result.update', ['type' => $kind, 'id' => $gameId]) }}">
                            @csrf
                            <input type="hidden" name="walkover" value="1">
                            <input type="hidden" name="winner_id" value="{{ $player1->id }}">
                            <button type="submit"
                                    onclick="return confirm('Ustawić walkover {{ $walkoverScoreLine ?? '2:0' }} dla {{ $player1->name }}?')"
                                    class="px-3 py-2 rounded border border-accent text-accent text-sm hover:bg-accent/10 transition">
                                Walkover → {{ $player1->name }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('games.result.update', ['type' => $kind, 'id' => $gameId]) }}">
                            @csrf
                            <input type="hidden" name="walkover" value="1">
                            <input type="hidden" name="winner_id" value="{{ $player2->id }}">
                            <button type="submit"
                                    onclick="return confirm('Ustawić walkover {{ $walkoverScoreLine ?? '2:0' }} dla {{ $player2->name }}?')"
                                    class="px-3 py-2 rounded border border-accent text-accent text-sm hover:bg-accent/10 transition">
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

        <h2 class="section-title mb-4">Statystyki meczu</h2>
        <div class="bg-bg-elevated rounded-lg border border-border mb-8 overflow-x-auto">
            <table class="w-full text-sm text-text min-w-[320px]">
                <thead>
                    <tr class="border-b border-border bg-bg-deep/60">
                        <th class="text-left py-3 px-4 text-text-muted font-medium w-2/5"></th>
                        <th class="text-center py-3 px-4 text-accent font-semibold">{{ $p1['name'] }}</th>
                        <th class="text-center py-3 px-4 text-accent font-semibold">{{ $p2['name'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border bg-accent/10">
                        <td colspan="3" class="py-2 px-4 text-accent font-semibold text-xs uppercase tracking-wide">Średnia (3 lotki)</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">Cała gra</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $fmtAvg($p1['matchAverage']) }}</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $fmtAvg($p2['matchAverage']) }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">Najlepszy leg</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $fmtAvg($p1['bestLegAverage']) }}</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $fmtAvg($p2['bestLegAverage']) }}</td>
                    </tr>
                    @if($status === 'in_progress')
                        <tr class="border-b border-border/50">
                            <td class="py-2.5 px-4 text-text-muted">Aktualny leg</td>
                            <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $fmtAvg($p1['currentLegAverage']) }}</td>
                            <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $fmtAvg($p2['currentLegAverage']) }}</td>
                        </tr>
                    @endif

                    <tr class="border-b border-border bg-accent/10">
                        <td colspan="3" class="py-2 px-4 text-accent font-semibold text-xs uppercase tracking-wide">Osiągi</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">Najlepszy leg (lotki)</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $fmtInt($p1['bestLegThrows']) }}</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $fmtInt($p2['bestLegThrows']) }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">60+</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p1['plus60'] }}</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p2['plus60'] }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">80+</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p1['plus80'] }}</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p2['plus80'] }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">100+</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p1['plus100'] }}</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p2['plus100'] }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">140+</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p1['plus140'] }}</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p2['plus140'] }}</td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <td class="py-2.5 px-4 text-text-muted">180</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p1['max180'] }}</td>
                        <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p2['max180'] }}</td>
                    </tr>

                    @if($p1['doublePercent'] !== null || $p2['doublePercent'] !== null || $p1['hf']->isNotEmpty() || $p2['hf']->isNotEmpty() || $p1['qf']->isNotEmpty() || $p2['qf']->isNotEmpty())
                        <tr class="border-b border-border bg-accent/10">
                            <td colspan="3" class="py-2 px-4 text-accent font-semibold text-xs uppercase tracking-wide">Turniej</td>
                        </tr>
                        <tr class="border-b border-border/50">
                            <td class="py-2.5 px-4 text-text-muted">Skuteczność na double (mecz)</td>
                            <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p1['doublePercent'] !== null ? $p1['doublePercent'].'%' : '—' }}</td>
                            <td class="py-2.5 px-4 text-center text-text-secondary font-medium">{{ $p2['doublePercent'] !== null ? $p2['doublePercent'].'%' : '—' }}</td>
                        </tr>
                        @if($p1['hf']->isNotEmpty() || $p2['hf']->isNotEmpty())
                            <tr class="border-b border-border/50">
                                <td class="py-2.5 px-4 text-text-muted">HF (finish)</td>
                                <td class="py-2.5 px-4 text-center text-text-secondary font-medium text-xs">{{ $p1['hf']->pluck('value')->filter()->join(', ') ?: '—' }}</td>
                                <td class="py-2.5 px-4 text-center text-text-secondary font-medium text-xs">{{ $p2['hf']->pluck('value')->filter()->join(', ') ?: '—' }}</td>
                            </tr>
                        @endif
                        @if($p1['qf']->isNotEmpty() || $p2['qf']->isNotEmpty())
                            <tr class="border-b border-border/50">
                                <td class="py-2.5 px-4 text-text-muted">QF (lotki)</td>
                                <td class="py-2.5 px-4 text-center text-text-secondary font-medium text-xs">{{ $p1['qf']->pluck('value')->filter()->join(', ') ?: '—' }}</td>
                                <td class="py-2.5 px-4 text-center text-text-secondary font-medium text-xs">{{ $p2['qf']->pluck('value')->filter()->join(', ') ?: '—' }}</td>
                            </tr>
                        @endif
                    @endif
                </tbody>
            </table>
        </div>

        <h2 class="section-title mb-4">Legi i wizyty</h2>

        @if($legsDetail->isEmpty())
            <p class="text-text-muted">Brak rozegranych legów — szczegóły pojawią się po rozpoczęciu liczenia z wizytami.</p>
        @else
            @php
                $setsList = $legsBySet ?? [];
                $showSetTabs = ($usesSetScore ?? false) && count($setsList) > 0;
                $defaultSetTab = 0;
                $defaultLegTab = 0;
                foreach ($setsList as $setIdx => $set) {
                    foreach ($set['legs'] as $legIdx => $block) {
                        $defaultSetTab = $setIdx;
                        $defaultLegTab = $legIdx;
                        if (! $block['leg']->finished_at) {
                            break 2;
                        }
                    }
                }
            @endphp
            <div
                class="mb-8"
                x-data="{
                    activeSet: {{ $defaultSetTab }},
                    activeLeg: {{ $defaultLegTab }},
                    selectSet(setIndex) {
                        this.activeSet = setIndex;
                        this.activeLeg = 0;
                    }
                }"
            >
                @if($showSetTabs)
                    <div class="flex flex-wrap gap-2 mb-3" role="tablist" aria-label="Sety">
                        @foreach($setsList as $setIdx => $set)
                            <button
                                type="button"
                                role="tab"
                                class="px-4 py-2 rounded-lg text-sm font-semibold border transition"
                                x-bind:class="activeSet === {{ $setIdx }}
                                    ? 'bg-success-muted text-success-bright border-success/50'
                                    : 'bg-bg-deep text-text-muted border-border hover:border-success/40 hover:text-text-secondary'"
                                x-on:click="selectSet({{ $setIdx }})"
                                x-bind:aria-selected="activeSet === {{ $setIdx }}"
                            >
                                Set {{ $set['setNumber'] }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @foreach($setsList as $setIdx => $set)
                    <div x-show="activeSet === {{ $setIdx }}" x-cloak>
                        <div class="flex flex-wrap gap-2 mb-4" role="tablist" aria-label="Legi{{ $showSetTabs ? ' setu '.$set['setNumber'] : '' }}">
                            @foreach($set['legs'] as $legIdx => $block)
                                <button
                                    type="button"
                                    role="tab"
                                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border transition"
                                    x-bind:class="activeLeg === {{ $legIdx }}
                                        ? 'bg-accent/20 text-accent border-accent/50'
                                        : 'bg-bg-deep text-text-muted border-border hover:border-success/40 hover:text-text-secondary'"
                                    x-on:click="activeLeg = {{ $legIdx }}"
                                    x-bind:aria-selected="activeLeg === {{ $legIdx }}"
                                >
                                    Leg {{ $block['legInSetNumber'] }}
                                    @if(! $block['leg']->finished_at)
                                        <span class="opacity-80">·</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        @foreach($set['legs'] as $legIdx => $block)
                            @include('games.partials.leg-detail-panel', [
                                'block' => $block,
                                'player1' => $player1,
                                'player2' => $player2,
                                'showExpression' => 'activeLeg === '.$legIdx,
                            ])
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
