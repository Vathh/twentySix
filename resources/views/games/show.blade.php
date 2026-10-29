@extends('layouts.app')

@section('title', 'Mecz — '.$player1->name.' vs '.$player2->name)

@section('content')
    <div class="container mx-auto py-4 sm:py-5 max-w-4xl text-text px-0 sm:px-0">
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
            @elseif($status === 'voided')
                <span class="px-2 py-0.5 rounded text-xs bg-bg-deep text-text-muted border border-border">Anulowany</span>
            @elseif($status === 'lobby')
                <span class="px-2 py-0.5 rounded text-xs bg-warning/15 text-warning border border-warning/30">Lobby</span>
            @endif
        </div>

        @if($status === 'in_progress')
            <div class="mb-4">
                @include('games.partials.overlay-obs-link')
            </div>
        @endif

        @include('games.partials.match-board')

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
                            class="px-4 py-2 rounded bg-accent text-on-accent font-semibold text-sm hover:bg-accent-hover transition">
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

        @include('games.partials.league-manage')

        @include('games.partials.cancel-game', [
            'canCancelGame' => $canCancelGame ?? false,
            'cancelAction' => $kind === 'league'
                ? route('league-games.cancel', $gameId)
                : route('games.cancel', ['type' => $kind, 'id' => $gameId]),
        ])

        @php
            $p1 = $players[0];
            $p2 = $players[1];
            $fmtAvg = static fn ($v) => \App\Support\AverageFormat::display($v);
            $fmtInt = static fn ($v) => $v !== null ? (string) $v : '—';
        @endphp

        @php
            $statGroups = [
                [
                    'title' => 'Średnia (3 lotki)',
                    'rows' => array_values(array_filter([
                        ['Cała gra', $fmtAvg($p1['matchAverage']), $fmtAvg($p2['matchAverage'])],
                        ['Pierwsze 9 lotek', $fmtAvg($p1['matchFirstNineAverage'] ?? null), $fmtAvg($p2['matchFirstNineAverage'] ?? null)],
                        ['Najlepszy leg', $fmtAvg($p1['bestLegAverage']), $fmtAvg($p2['bestLegAverage'])],
                        $status === 'in_progress'
                            ? ['Aktualny leg', $fmtAvg($p1['currentLegAverage']), $fmtAvg($p2['currentLegAverage'])]
                            : null,
                    ])),
                ],
                [
                    'title' => 'Osiągi',
                    'rows' => [
                        ['Najlepszy leg (lotki)', $fmtInt($p1['bestLegThrows']), $fmtInt($p2['bestLegThrows'])],
                        ['60+', $p1['plus60'], $p2['plus60']],
                        ['80+', $p1['plus80'], $p2['plus80']],
                        ['100+', $p1['plus100'], $p2['plus100']],
                        ['140+', $p1['plus140'], $p2['plus140']],
                        ['180', $p1['max180'], $p2['max180']],
                        ['HF (finish)', $p1['hf'] !== [] ? implode(', ', $p1['hf']) : '—', $p2['hf'] !== [] ? implode(', ', $p2['hf']) : '—'],
                        ['QF (lotki)', $p1['qf'] !== [] ? implode(', ', $p1['qf']) : '—', $p2['qf'] !== [] ? implode(', ', $p2['qf']) : '—'],
                    ],
                ],
            ];
        @endphp

        <h2 class="section-title mb-4">Statystyki meczu</h2>
        <div class="match-stats mb-8">
            <div class="match-stats-row match-stats-head">
                <span>{{ $p1['name'] }}</span>
                <span></span>
                <span>{{ $p2['name'] }}</span>
            </div>
            @foreach($statGroups as $group)
                <p class="match-stats-group">{{ $group['title'] }}</p>
                @foreach($group['rows'] as $row)
                    <div class="match-stats-row">
                        <span class="match-stats-value score-num">{{ $row[1] }}</span>
                        <span class="match-stats-label">{{ $row[0] }}</span>
                        <span class="match-stats-value score-num">{{ $row[2] }}</span>
                    </div>
                @endforeach
            @endforeach
        </div>

        <h2 class="section-title mb-4">Legi i wizyty</h2>

        @if($legsDetail->isEmpty())
            <p class="text-text-muted">
                @if($status === 'finished')
                    Brak rozegranych legów.
                @else
                    Brak rozegranych legów — szczegóły pojawią się po rozpoczęciu liczenia z wizytami.
                @endif
            </p>
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
                                    ? 'bg-accent/20 text-accent border-accent/50'
                                    : 'bg-bg-deep text-text-muted border-border hover:border-accent/40 hover:text-text-secondary'"
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
                                        : 'bg-bg-deep text-text-muted border-border hover:border-accent/40 hover:text-text-secondary'"
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
