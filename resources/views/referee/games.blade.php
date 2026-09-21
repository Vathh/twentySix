@extends('referee.layout')

@section('title', 'Mecze')

@section('content')
<div
    x-data="refereeGames(@js([
        'scoreUrl' => route('referee.score'),
    ]))"
    x-init="init()"
>
    <div class="flex items-center justify-between gap-3 mb-4">
        <h1 class="text-xl font-semibold text-accent">Mecze turnieju</h1>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="btn btn-secondary !py-2 !px-3 text-sm"
                @click="fetchGames()"
                :disabled="loading"
            >
                Odśwież
            </button>
            <button
                type="button"
                class="btn btn-secondary !py-2 !px-3 text-sm"
                @click="logout()"
            >
                Wyloguj
            </button>
        </div>
    </div>

    <p class="text-danger text-sm mb-3" x-show="error" x-text="error" x-cloak></p>

    <div x-show="loading" class="text-text-muted text-sm py-8 text-center">
        Ładowanie meczów…
    </div>

    <template x-if="!loading && groups.length === 0 && playoffGames.length === 0">
        <p class="text-text-muted text-sm">Brak aktywnych meczów.</p>
    </template>

    <div x-show="!loading && groups.length > 0" class="mb-6" x-cloak>
        <h2 class="text-sm font-semibold text-accent mb-2">Faza grupowa</h2>
        <div class="grid gap-2">
            <template x-for="group in remainingGroups" :key="group.groupNumber">
                <button
                    type="button"
                    class="w-full text-left px-4 py-3 rounded-lg bg-bg-elevated border border-border hover:border-accent/40 transition"
                    @click="openGroup(group.groupNumber)"
                >
                    <div class="font-semibold text-accent">
                        Grupa <span x-text="group.groupNumber"></span>
                    </div>
                    <div
                        class="text-sm text-text-muted mt-1"
                        x-show="groupPlayerNames(group.groupNumber)"
                        x-text="groupPlayerNames(group.groupNumber)"
                    ></div>
                </button>
            </template>
        </div>
    </div>

    <div x-show="!loading && playoffGames.length > 0 && hasSplitPlayoff" class="mb-6" x-cloak>
        <h2 class="text-sm font-semibold text-accent mb-2">Playoff</h2>
        <div class="grid gap-2">
            <template x-for="side in playoffSides" :key="side.id">
                <button
                    type="button"
                    class="w-full text-left px-4 py-3 rounded-lg bg-bg-elevated border border-border hover:border-accent/40 transition"
                    @click="openPlayoffSide(side.id)"
                >
                    <span class="font-semibold text-accent" x-text="side.title"></span>
                </button>
            </template>
        </div>
    </div>

    <div x-show="!loading && playoffGames.length > 0 && !hasSplitPlayoff" x-cloak>
        <h2 class="text-sm font-semibold text-accent mb-2">Playoff</h2>
        <div class="grid gap-2">
            <template x-for="game in playoffGames" :key="'p-'+game.id">
                <button
                    type="button"
                    class="w-full text-left px-4 py-3 rounded-lg bg-bg-elevated border border-border hover:border-accent/40 transition disabled:opacity-50"
                    @click="startGame(game)"
                    :disabled="lockingId != null"
                >
                    <div class="text-xs text-text-muted mb-0.5" x-text="game.roundLabel || game.round || 'Playoff'"></div>
                    <div class="font-semibold text-text" x-text="playerLabel(game)"></div>
                    <div class="text-xs text-accent mt-1" x-show="lockingId === ('playoff-'+game.id)">Blokowanie…</div>
                </button>
            </template>
        </div>
    </div>

    <div
        x-show="selectedGroup != null || selectedPlayoffSide != null"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 p-4"
        @keydown.escape.window="closeGroup()"
        @click.self="closeGroup()"
    >
        <div
            class="w-full rounded-xl border border-border bg-bg-deep shadow-xl p-4"
            :class="isGroupModal ? 'max-w-4xl' : 'max-w-md'"
            @click.stop
        >
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-accent" x-text="modalTitle"></h3>
                <button type="button" class="text-text-muted hover:text-accent text-xl leading-none" @click="closeGroup()" aria-label="Zamknij">✕</button>
            </div>
            <p class="text-danger text-sm mb-3" x-show="error" x-text="error"></p>

            <template x-if="isGroupModal">
                <div>
                    <template x-if="selectedGroupData">
                        <div class="overflow-x-auto max-h-[70vh]">
                            <table class="w-full text-sm table-surface">
                                <thead>
                                    <tr>
                                        <template x-for="col in selectedGroupMatrix.columns" :key="col.key">
                                            <th
                                                class="px-2 py-2 text-text-muted font-semibold uppercase text-xs"
                                                :class="col.key === 'player' ? 'text-left' : 'text-center'"
                                                x-text="col.label"
                                            ></th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <template x-for="row in selectedGroupMatrix.rows" :key="row.key">
                                        <tr>
                                            <td class="px-2 py-2 font-medium text-text whitespace-nowrap" x-text="row.playerName"></td>
                                            <template x-for="cell in row.cells" :key="row.key + '-' + cell.key">
                                                <td class="px-2 py-2 text-center">
                                                <span class="text-text-muted" x-show="cell.diagonal">X</span>
                                                <button
                                                    type="button"
                                                    class="min-w-[2.5rem] min-h-[2.5rem] px-1 inline-flex items-center justify-center disabled:opacity-50"
                                                    :class="cell.sequence ? '' : 'font-bold text-accent hover:underline'"
                                                    x-show="!cell.diagonal && cell.playable"
                                                    @click="startGame(cell.game)"
                                                    :disabled="lockingId != null"
                                                    :title="cell.sequence ? ('Mecz ' + cell.sequence) : null"
                                                >
                                                    <span x-show="cell.game && lockingId === ('group-'+cell.game.id)">…</span>
                                                    <span
                                                        class="sequence-badge"
                                                        x-show="!(cell.game && lockingId === ('group-'+cell.game.id)) && cell.sequence"
                                                        x-text="cell.sequence"
                                                    ></span>
                                                    <span
                                                        x-show="!(cell.game && lockingId === ('group-'+cell.game.id)) && !cell.sequence"
                                                        x-text="cell.text"
                                                    ></span>
                                                </button>
                                                <span
                                                    class="sequence-badge"
                                                    x-show="!cell.diagonal && !cell.playable && cell.sequence"
                                                    x-text="cell.sequence"
                                                    :title="'Mecz ' + cell.sequence"
                                                ></span>
                                                <span
                                                    class="text-text-secondary tabular-nums"
                                                    x-show="!cell.diagonal && !cell.playable && !cell.sequence"
                                                    x-text="cell.text"
                                                ></span>
                                            </td>
                                            </template>
                                            <td class="px-2 py-2 text-center tabular-nums" x-text="row.gamesWon"></td>
                                            <td class="px-2 py-2 text-center tabular-nums" x-text="row.gamesLost"></td>
                                            <td class="px-2 py-2 text-center tabular-nums" x-text="row.matchUnitsDifference"></td>
                                            <td class="px-2 py-2 text-center tabular-nums" x-text="row.points"></td>
                                            <td class="px-2 py-2 text-center tabular-nums" x-text="row.place"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <div
                                class="mt-3 text-sm text-text-secondary leading-relaxed"
                                x-show="groupReferees.length > 0"
                                x-cloak
                            >
                                <span class="text-text-muted">Sędziowie: </span>
                                <template x-for="(slot, index) in groupReferees" :key="slot.id">
                                    <span>
                                        <span
                                            :class="{
                                                'line-through text-text-muted': slot.status === 'finished',
                                                'text-accent font-semibold': slot.status === 'in_progress',
                                            }"
                                            x-text="slot.referee.name"
                                        ></span><span x-text="index < groupReferees.length - 1 ? ', ' : ''"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <p class="text-text-muted text-sm text-center py-6" x-show="!selectedGroupData">
                        Wszystkie mecze w tej grupie zostały już rozegrane.
                    </p>
                </div>
            </template>

            <template x-if="!isGroupModal">
                <div class="grid gap-2 max-h-[60vh] overflow-y-auto">
                    <template x-for="game in gamesInSelectedPlayoffSide" :key="(game.type || 'p')+'-'+game.id">
                        <button
                            type="button"
                            class="w-full text-left px-4 py-3 rounded-lg bg-bg-elevated border border-border hover:border-accent/40 transition disabled:opacity-50"
                            @click="startGame(game)"
                            :disabled="lockingId != null"
                        >
                            <div class="text-xs text-text-muted mb-0.5" x-text="game.roundLabel || game.round || ''"></div>
                            <div class="font-semibold text-text" x-text="playerLabel(game)"></div>
                            <div class="text-xs text-accent mt-1" x-show="lockingId === ((game.type || 'playoff')+'-'+game.id)">Blokowanie…</div>
                        </button>
                    </template>
                    <p class="text-text-muted text-sm" x-show="gamesInSelectedPlayoffSide.length === 0">Brak meczów.</p>
                </div>
            </template>

            <button type="button" class="mt-4 w-full text-center text-accent font-semibold py-2" @click="closeGroup()">
                Zamknij
            </button>
        </div>
    </div>
</div>
@endsection
