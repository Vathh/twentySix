@extends('referee.layout')

@section('title', 'Sędziowanie meczu')

@section('content')
<div
    class="referee-score"
    x-data="refereeScoring(@js([
        'gameType' => $gameType,
        'gameId' => $gameId,
        'channel' => $channel,
        'reverb' => $reverb,
        'gamesUrl' => route('referee.games'),
    ]))"
    x-init="init()"
    @keydown.window="handleWindowKey($event)"
>
    <div class="flex items-center justify-between gap-2 mb-4">
        <button type="button" class="link-back" @click="leave()">← Mecze</button>
        <span
            class="px-2 py-0.5 rounded text-xs border border-border text-text-muted"
            x-text="connection === 'live' ? 'Live' : (connection === 'connecting' ? 'Łączenie…' : 'Offline')"
        ></span>
    </div>

    <div
        x-show="isCancelled"
        x-cloak
        class="mb-4 p-3 rounded-lg border border-danger/40 bg-danger/10 text-center"
    >
        <p class="text-danger font-semibold mb-2">Mecz został anulowany</p>
        <p class="text-text-muted text-sm mb-3">Administrator anulował ten mecz.</p>
        <button type="button" class="btn btn-primary !py-2" @click="goToGames()">OK</button>
    </div>

    <div
        x-show="isFinished"
        x-cloak
        class="mb-4 p-3 rounded-lg border border-success/40 bg-success-muted/30 text-center"
    >
        <p class="text-success-bright font-semibold mb-2">Mecz zakończony</p>
        <button type="button" class="btn btn-primary !py-2" @click="goToGames()">Wróć do listy</button>
    </div>

    <p class="text-danger text-sm mb-3" x-show="error" x-text="error" x-cloak></p>

    <div class="grid grid-cols-2 gap-3 mb-4">
        <div
            class="rounded-xl border p-4 text-center transition"
            :class="turnIndex === 0 ? 'border-accent bg-accent/10' : 'border-border bg-bg-elevated/40'"
        >
            <button
                type="button"
                x-show="canSwitchOpener && turnIndex !== 0"
                x-cloak
                class="text-sm font-semibold text-accent truncate max-w-full underline decoration-dotted underline-offset-2"
                @click="askSwitchOpener(0)"
                x-text="player1?.name ?? 'Gracz 1'"
            ></button>
            <div
                class="text-sm font-semibold text-text truncate"
                x-show="!(canSwitchOpener && turnIndex !== 0)"
                x-text="player1?.name ?? 'Gracz 1'"
            ></div>
            <div class="text-4xl font-bold text-accent my-2 tabular-nums" x-text="remaining(player1)"></div>
            <div class="text-xs text-text-muted">
                <span x-show="isSingleSetFormat()">Legi: <span x-text="matchScore(player1)"></span></span>
                <span x-show="!isSingleSetFormat()">
                    Sety: <span x-text="matchScore(player1)"></span>
                    · Legi: <span x-text="legsInSet(player1)"></span>
                </span>
            </div>
        </div>
        <div
            class="rounded-xl border p-4 text-center transition"
            :class="turnIndex === 1 ? 'border-accent bg-accent/10' : 'border-border bg-bg-elevated/40'"
        >
            <button
                type="button"
                x-show="canSwitchOpener && turnIndex !== 1"
                x-cloak
                class="text-sm font-semibold text-accent truncate max-w-full underline decoration-dotted underline-offset-2"
                @click="askSwitchOpener(1)"
                x-text="player2?.name ?? 'Gracz 2'"
            ></button>
            <div
                class="text-sm font-semibold text-text truncate"
                x-show="!(canSwitchOpener && turnIndex !== 1)"
                x-text="player2?.name ?? 'Gracz 2'"
            ></div>
            <div class="text-4xl font-bold text-accent my-2 tabular-nums" x-text="remaining(player2)"></div>
            <div class="text-xs text-text-muted">
                <span x-show="isSingleSetFormat()">Legi: <span x-text="matchScore(player2)"></span></span>
                <span x-show="!isSingleSetFormat()">
                    Sety: <span x-text="matchScore(player2)"></span>
                    · Legi: <span x-text="legsInSet(player2)"></span>
                </span>
            </div>
        </div>
    </div>

    <p
        class="text-center text-xs text-text-muted -mt-2 mb-3"
        x-show="canSwitchOpener"
        x-cloak
    >Kliknij nazwę drugiego zawodnika, aby zmienić rozpoczynającego.</p>

    <p class="text-center text-sm text-text-secondary mb-3">
        Tura:
        <span class="text-accent font-semibold" x-text="currentPlayer?.name ?? '—'"></span>
    </p>

    <div class="rounded-xl border border-border bg-bg-deep px-4 py-5 mb-4 text-center">
        <div class="text-xs text-text-muted mb-1">Wynik wizyty</div>
        <div class="text-5xl font-bold tabular-nums text-text tracking-wider min-h-[3.5rem]" x-text="input === '' ? '—' : input"></div>
    </div>

    <div class="referee-numpad mb-4" x-show="!isFinished && !isCancelled" x-cloak>
        <template x-for="row in [['1','2','3'],['4','5','6'],['7','8','9'],['C','0','OK']]" :key="row.join('-')">
            <div class="grid grid-cols-3 gap-2 mb-2">
                <template x-for="key in row" :key="key">
                    <button
                        type="button"
                        class="referee-numpad-key"
                        :class="{
                            'referee-numpad-key-accent': key === 'OK',
                            'referee-numpad-key-muted': key === 'C',
                        }"
                        :disabled="busy || openerOpen || !openerChosen"
                        @click="
                            if (key === 'C') clearInput();
                            else if (key === 'OK') submitVisit();
                            else pressDigit(key);
                        "
                        x-text="key"
                    ></button>
                </template>
            </div>
        </template>
    </div>

    <div class="flex gap-2" x-show="!isFinished" x-cloak>
        <button
            type="button"
            class="btn btn-secondary flex-1 !py-3"
            @click="undo()"
            :disabled="busy || openerOpen || !openerChosen"
        >
            Undo
        </button>
        <button
            type="button"
            class="btn btn-secondary flex-1 !py-3"
            @click="backspace()"
            :disabled="busy || openerOpen || !openerChosen || input === ''"
        >
            ⌫
        </button>
    </div>

    {{-- Kto zaczyna (pierwszy leg, jak na tablecie) --}}
    <div
        x-show="openerOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >
        <div class="w-full max-w-sm rounded-xl border border-border bg-bg-deep p-5 text-center">
            <p class="text-lg font-semibold text-accent mb-4">Kto zaczyna mecz?</p>
            <div class="flex flex-col gap-2">
                <template x-for="(player, index) in players" :key="player.playerId ?? index">
                    <button
                        type="button"
                        class="btn btn-primary w-full !py-4"
                        @click="selectOpener(index)"
                        x-text="player.name ?? ('Gracz ' + (index + 1))"
                    ></button>
                </template>
            </div>
        </div>
    </div>

    {{-- Zamiana rozpoczynającego (tylko stan startowy) --}}
    <div
        x-show="switchOpenerOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >
        <div class="w-full max-w-sm rounded-xl border border-border bg-bg-deep p-5 text-center">
            <p class="text-lg font-semibold text-accent mb-2">Zmiana rozpoczynającego</p>
            <p class="text-text-secondary text-sm mb-4">
                Czy przełączyć zawodnika rozpoczynającego mecz na
                <span class="font-bold text-text" x-text="pendingSwitchName"></span>?
            </p>
            <div class="flex gap-2">
                <button type="button" class="btn btn-secondary flex-1" @click="cancelSwitchOpener()">Nie</button>
                <button type="button" class="btn btn-primary flex-1" @click="confirmSwitchOpener()">Tak</button>
            </div>
            <p class="text-xs text-text-muted mt-3">Enter — Tak · Esc — Nie</p>
        </div>
    </div>

    {{-- Potwierdzenie checkout --}}
    <div
        x-show="checkoutOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >
        <div class="w-full max-w-sm rounded-xl border border-border bg-bg-deep p-5 text-center">
            <p class="text-lg font-semibold text-accent mb-2">Checkout?</p>
            <p class="text-text-secondary text-sm mb-4">
                <span x-text="currentPlayer?.name"></span>
                kończy leg wynikiem
                <span class="font-bold text-text" x-text="pendingCheckoutScore"></span>?
            </p>
            <div class="flex gap-2">
                <button type="button" class="btn btn-secondary flex-1" @click="cancelCheckout()">Nie</button>
                <button type="button" class="btn btn-primary flex-1" @click="confirmCheckout()">Tak</button>
            </div>
            <p class="text-xs text-text-muted mt-3">Enter — Tak · Esc — Nie</p>
        </div>
    </div>

    {{-- Liczba lotek checkout --}}
    <div
        x-show="checkoutDartsOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >
        <div class="w-full max-w-sm rounded-xl border border-border bg-bg-deep p-5 text-center">
            <p class="text-lg font-semibold text-accent mb-2">Na ilu lotkach?</p>
            <p class="text-text-secondary text-sm mb-4">Wybierz liczbę lotek checkoutu.</p>
            <div class="grid grid-cols-3 gap-2 mb-3">
                <button type="button" class="btn btn-primary !py-4 text-xl" :disabled="busy" @click="finishCheckout(1)">1</button>
                <button type="button" class="btn btn-primary !py-4 text-xl" :disabled="busy" @click="finishCheckout(2)">2</button>
                <button type="button" class="btn btn-primary !py-4 text-xl" :disabled="busy" @click="finishCheckout(3)">3</button>
            </div>
            <p class="text-xs text-text-muted mb-3">Klawiatura: 1 / 2 / 3</p>
            <button type="button" class="text-sm text-text-muted hover:text-accent" @click="cancelCheckout()" :disabled="busy">Anuluj</button>
        </div>
    </div>

    {{-- Próg przegranej --}}
    <div
        x-show="lossThresholdOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
    >
        <div class="w-full max-w-sm rounded-xl border border-border bg-bg-deep p-5 text-center">
            <p class="text-lg font-semibold text-accent mb-3" x-text="lossThresholdText"></p>
            <button type="button" class="btn btn-primary w-full !py-3" @click="dismissLossThreshold()">OK</button>
        </div>
    </div>

    {{-- Rzut do bulla --}}
    <div
        x-show="bullOffRequired"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
    >
        <div class="w-full max-w-sm rounded-xl border border-border bg-bg-deep p-5 text-center">
            <p class="text-lg font-semibold text-accent mb-2">Rzut do bulla, kto trafił bliżej?</p>
            <div class="flex flex-col gap-2 mb-4">
                <template x-for="(player, index) in players" :key="player.playerId ?? index">
                    <button
                        type="button"
                        class="btn btn-primary w-full !py-4"
                        :disabled="busy"
                        @click="confirmBullOff(index)"
                        x-text="player.name ?? ('Gracz ' + (index + 1))"
                    ></button>
                </template>
            </div>
            <button
                type="button"
                class="btn btn-secondary w-full !py-3"
                :disabled="busy"
                @click="undoFromBullOff()"
            >Cofnij ostatnią kolejkę</button>
        </div>
    </div>
</div>
@endsection
