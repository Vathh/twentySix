@extends('layouts.app')

@section('title', 'Skład ligi')

@section('content')
    <div class="container mx-auto py-6 max-w-6xl px-4" x-data="leagueRosterBoard(@js($board))">
        <a href="{{ route('leagues.show', $league) }}" class="link-back mb-4 inline-block">← {{ $league->name }}</a>
        <h1 class="page-title">Skład szczebli</h1>
        <p class="text-text-muted mb-6">
            Przeciągnij znacznik z puli po prawej na szczebel po lewej. Jeden zawodnik — jeden szczebel.
            @if($locked)
                <strong class="text-accent">Sezon w toku — skład zamrożony.</strong>
            @endif
        </p>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(16rem,0.85fr)] items-start">
            <div class="space-y-4">
                <template x-for="division in divisions" :key="division.id">
                    <div class="card">
                        <h2 class="section-title text-accent mb-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span x-text="division.name"></span>
                            <span class="inline-flex items-center gap-1.5 text-base font-normal text-text-muted" x-show="!locked">
                                <button
                                    type="button"
                                    class="roster-capacity-btn"
                                    title="Zmniejsz pojemność"
                                    :disabled="busy || !canDecrease(division)"
                                    @click.stop="changeCapacity(division, -1)"
                                >−</button>
                                <span>pojemność</span>
                                <button
                                    type="button"
                                    class="roster-capacity-btn"
                                    title="Zwiększ pojemność"
                                    :disabled="busy || !canIncrease(division)"
                                    @click.stop="changeCapacity(division, 1)"
                                >+</button>
                            </span>
                            <span class="text-text-muted text-base font-normal" x-text="'(' + division.players.length + '/' + division.capacity + ')'"></span>
                        </h2>
                        <div
                            class="roster-dropzone"
                            :class="{ 'roster-dropzone-active': dropTarget === ('division-' + division.id), 'opacity-60': busy }"
                            @dragover.prevent="onDragOver($event, { type: 'division', id: division.id })"
                            @dragleave="dropTarget === ('division-' + division.id) ? dropTarget = null : null"
                            @drop.prevent="onDrop($event, { type: 'division', id: division.id })"
                        >
                            <template x-if="division.players.length === 0">
                                <p class="text-text-secondary text-sm" x-text="locked ? 'Brak zawodników.' : 'Upuść tutaj.'"></p>
                            </template>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="player in division.players" :key="player.id">
                                    <div
                                        class="roster-chip"
                                        :class="{ 'roster-chip-guest': player.kind === 'guest', 'cursor-grab': !locked }"
                                        :draggable="!locked"
                                        @dragstart="onDragStart($event, player)"
                                        @dragend="onDragEnd()"
                                    >
                                        <span x-text="player.name"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="space-y-4 lg:sticky lg:top-4">
                <div class="card">
                    <div class="flex items-baseline justify-between gap-2 mb-3">
                        <h2 class="section-title text-accent !mb-0">Powiązani użytkownicy</h2>
                        <a :href="relatedManageUrl" class="text-accent text-sm underline shrink-0">Dodaj do puli</a>
                    </div>
                    <div
                        class="roster-dropzone"
                        :class="{ 'roster-dropzone-active': dropTarget === 'related', 'opacity-60': busy }"
                        @dragover.prevent="onDragOver($event, { type: 'related' })"
                        @dragleave="dropTarget === 'related' ? dropTarget = null : null"
                        @drop.prevent="onDrop($event, { type: 'related' })"
                    >
                        <template x-if="related.length === 0">
                            <p class="text-text-secondary text-sm">Pula pusta.</p>
                        </template>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="player in related" :key="player.id">
                                <div
                                    class="roster-chip"
                                    :class="{ 'cursor-grab': !locked }"
                                    :draggable="!locked"
                                    @dragstart="onDragStart($event, player)"
                                    @dragend="onDragEnd()"
                                >
                                    <span x-text="player.name"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="flex items-baseline justify-between gap-2 mb-3">
                        <h2 class="section-title text-accent !mb-0">Goście</h2>
                        <a :href="guestsManageUrl" class="text-accent text-sm underline shrink-0">Dodaj do puli</a>
                    </div>
                    <div
                        class="roster-dropzone"
                        :class="{ 'roster-dropzone-active': dropTarget === 'guest', 'opacity-60': busy }"
                        @dragover.prevent="onDragOver($event, { type: 'guest' })"
                        @dragleave="dropTarget === 'guest' ? dropTarget = null : null"
                        @drop.prevent="onDrop($event, { type: 'guest' })"
                    >
                        <template x-if="guests.length === 0">
                            <p class="text-text-secondary text-sm">Pula pusta.</p>
                        </template>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="player in guests" :key="player.id">
                                <div
                                    class="roster-chip roster-chip-guest"
                                    :class="{ 'cursor-grab': !locked }"
                                    :draggable="!locked"
                                    @dragstart="onDragStart($event, player)"
                                    @dragend="onDragEnd()"
                                >
                                    <span x-text="player.name"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
