@extends('layouts.broadcast')

@section('title', $player1->name.' vs '.$player2->name)

@section('content')
    @php
        $liveP1 = $initialState['players'][0] ?? [];
        $liveP2 = $initialState['players'][1] ?? [];
        $liveLeg = $initialState['currentLeg'] ?? null;
        $liveMatchFormat = $initialState['game']['matchFormat'] ?? [];
        $liveIsMultiSet = (int) ($liveMatchFormat['setsToWinMatch'] ?? 1) > 1;
        $liveSetNumber = (int) ($initialState['game']['currentSetNumber'] ?? 1);
        $liveLegNumber = $liveLeg['legNumber'] ?? null;
        $livePrimaryLeft = $liveIsMultiSet
            ? (int) ($liveP1['setsWon'] ?? 0)
            : (int) ($liveP1['legsWonInSet'] ?? $liveP1['legsWon'] ?? 0);
        $livePrimaryRight = $liveIsMultiSet
            ? (int) ($liveP2['setsWon'] ?? 0)
            : (int) ($liveP2['legsWonInSet'] ?? $liveP2['legsWon'] ?? 0);
        $livePrimaryUnit = $liveIsMultiSet ? 'SETY' : 'LEGI';
        $liveLegsLeft = (int) ($liveP1['legsWonInSet'] ?? $liveP1['legsWon'] ?? 0);
        $liveLegsRight = (int) ($liveP2['legsWonInSet'] ?? $liveP2['legsWon'] ?? 0);
        $liveLegLabel = $liveLeg
            ? ($liveIsMultiSet
                ? 'Set '.$liveSetNumber.' · Leg '.$liveLegNumber
                : 'Leg '.$liveLegNumber)
            : '—';
        $liveAvg1 = \App\Support\AverageFormat::display($liveP1['gameAverage'] ?? null);
        $liveAvg2 = \App\Support\AverageFormat::display($liveP2['gameAverage'] ?? null);
        $liveFinished = ($initialState['game']['status'] ?? '') === 'finished';
        $liveRemaining1 = $liveFinished ? '—' : ($liveP1['remaining'] ?? '—');
        $liveRemaining2 = $liveFinished ? '—' : ($liveP2['remaining'] ?? '—');
        $overlayEvent = $subtitle;
        $overlayRound = match ($kind) {
            'group' => 'Faza grupowa'.(! empty($groupNumber) ? ' · Grupa '.$groupNumber : ''),
            'playoff' => \App\Support\Tournament\PlayoffRoundLabel::broadcastLabel((string) ($playoffRound ?? '')),
            default => (string) $label,
        };
    @endphp
    <div
        class="game-overlay-stage"
        x-data="gameLiveViewer(@js([
            'initialState' => $initialState,
            'channel' => $broadcastChannel,
            'stateUrl' => $liveStateUrl,
            'showUrl' => $overlayShowUrl,
            'reverb' => $reverb,
            'redirectOnFinish' => false,
            'previewDemo' => (bool) $overlayPreview,
        ]))"
        x-init="init()"
    >
        <div class="game-overlay-dock">
            <div class="game-overlay-scorebug" aria-live="polite">
                <div
                    class="game-overlay-player is-left"
                    x-bind:class="{ 'is-throwing': isThrowing(0) }"
                >
                    <span class="game-overlay-rail" aria-hidden="true"></span>
                    <div class="game-overlay-copy">
                        <div class="game-overlay-name-row">
                            <p class="game-overlay-name" x-text="player1?.name">{{ $liveP1['name'] ?? $player1->name }}</p>
                            @include('games.partials.overlay-opener-icon', ['index' => 0])
                        </div>
                        <div class="game-overlay-meta">
                            <div class="game-overlay-meta-stats">
                                <span class="game-overlay-avg">
                                    <span class="game-overlay-avg-label">Avg</span>
                                    <span x-text="formatAverage(player1?.gameAverage)">{{ $liveAvg1 }}</span>
                                </span>
                                <span class="game-overlay-darts">
                                    <span class="game-overlay-avg-label">Lotki</span>
                                    <span x-text="dartsInCurrentLeg(player1?.playerId)">{{ $liveP1['dartsThrownInLeg'] ?? 0 }}</span>
                                </span>
                            </div>
                            <span
                                class="game-overlay-visit"
                                x-show="lastVisitLabel(player1?.playerId)"
                                x-bind:class="{
                                    'is-bust': lastVisitIsBust(player1?.playerId),
                                    'is-one-eighty': lastVisitIs180(player1?.playerId),
                                }"
                                x-text="lastVisitLabel(player1?.playerId)"
                                x-cloak
                            ></span>
                        </div>
                    </div>
                    <div class="game-overlay-score-block">
                        <span class="game-overlay-score-caption">Pozostało</span>
                        <p
                            class="game-overlay-remaining"
                            x-text="remainingDisplay(player1, 0)"
                        >{{ $liveRemaining1 }}</p>
                    </div>
                </div>

                <div class="game-overlay-hub">
                    <div class="game-overlay-brand">
                        <img
                            class="game-overlay-wordmark"
                            src="{{ asset('images/napis.svg') }}"
                            alt="twentySix"
                            width="120"
                            height="14"
                        >
                        <img
                            class="game-overlay-logotyp"
                            src="{{ asset('images/logotyp.svg') }}"
                            alt=""
                            width="48"
                            height="30"
                        >
                    </div>
                    <p class="game-overlay-scoreline">
                        <span class="game-overlay-scoreline-num" x-text="overlayPrimaryLeft">{{ $livePrimaryLeft }}</span>
                        <span class="game-overlay-scoreline-unit" x-text="overlayPrimaryUnit">{{ $livePrimaryUnit }}</span>
                        <span class="game-overlay-scoreline-num" x-text="overlayPrimaryRight">{{ $livePrimaryRight }}</span>
                    </p>
                    <p
                        class="game-overlay-scoreline is-secondary"
                        x-show="!isSingleSetFormat()"
                        x-cloak
                    >
                        <span class="game-overlay-scoreline-num" x-text="overlayLegsLeft">{{ $liveLegsLeft }}</span>
                        <span class="game-overlay-scoreline-unit">LEGI</span>
                        <span class="game-overlay-scoreline-num" x-text="overlayLegsRight">{{ $liveLegsRight }}</span>
                    </p>
                    <p class="game-overlay-leg-label" x-show="!isFinished" x-text="overlayLegLabel">{{ $liveLegLabel }}</p>
                    <p class="game-overlay-finished" x-show="isFinished" x-cloak>Koniec</p>
                </div>

                <div
                    class="game-overlay-player is-right{{ $overlayPreview ? ' is-throwing' : '' }}"
                    x-bind:class="{ 'is-throwing': isThrowing(1) }"
                >
                    <div class="game-overlay-score-block">
                        <span class="game-overlay-score-caption">Pozostało</span>
                        <p
                            class="game-overlay-remaining"
                            x-text="remainingDisplay(player2, 1)"
                        >{{ $liveRemaining2 }}</p>
                    </div>
                    <div class="game-overlay-copy">
                        <div class="game-overlay-name-row">
                            @include('games.partials.overlay-opener-icon', ['index' => 1])
                            <p class="game-overlay-name" x-text="player2?.name">{{ $liveP2['name'] ?? $player2->name }}</p>
                        </div>
                        <div class="game-overlay-meta">
                            <div class="game-overlay-meta-stats">
                                <span class="game-overlay-darts">
                                    <span class="game-overlay-avg-label">Lotki</span>
                                    <span x-text="dartsInCurrentLeg(player2?.playerId)">{{ $liveP2['dartsThrownInLeg'] ?? 0 }}</span>
                                </span>
                                <span class="game-overlay-avg">
                                    <span class="game-overlay-avg-label">Avg</span>
                                    <span x-text="formatAverage(player2?.gameAverage)">{{ $liveAvg2 }}</span>
                                </span>
                            </div>
                            <span
                                class="game-overlay-visit"
                                x-show="lastVisitLabel(player2?.playerId)"
                                x-bind:class="{
                                    'is-bust': lastVisitIsBust(player2?.playerId),
                                    'is-one-eighty': lastVisitIs180(player2?.playerId),
                                }"
                                x-text="lastVisitLabel(player2?.playerId)"
                                x-cloak
                            ></span>
                        </div>
                    </div>
                    <span class="game-overlay-rail" aria-hidden="true"></span>
                </div>

                <div class="game-overlay-180" x-show="flash180" x-cloak x-transition.opacity.duration.200ms>180</div>
            </div>
            @if(filled($overlayEvent) || filled($overlayRound))
                <div class="game-overlay-footer">
                    @if(filled($overlayEvent))
                        <span class="game-overlay-event">{{ $overlayEvent }}</span>
                    @endif
                    @if(filled($overlayEvent) && filled($overlayRound))
                        <span class="game-overlay-footer-dot" aria-hidden="true">·</span>
                    @endif
                    @if(filled($overlayRound))
                        <span class="game-overlay-round">{{ $overlayRound }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
