@extends('layouts.app')

@section('title', 'Turnieje')

@section('content')

    <div
        class="max-w-6xl mx-auto px-4 pt-10 pb-28"
        x-data="indexLoadMore(@js([
            'items' => $items,
            'hasMore' => $hasMore,
            'url' => route('tournaments.index'),
        ]))"
    >
        <template x-if="items.length === 0">
            <div>
                <x-empty-state
                    title="Brak turniejów"
                    description="Gdy pojawią się turnieje w organizacjach lub jednorazowe, zobaczysz je tutaj."
                />
            </div>
        </template>

        <div class="index-grid" x-show="items.length > 0">
            <template x-for="(item, index) in items" :key="item.id">
                <a :href="item.url"
                   class="block"
                   :style="'--stagger: ' + index">
                    <div class="index-card">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <h3 class="text-lg font-semibold text-text leading-snug" x-text="item.title"></h3>
                            <span
                                class="shrink-0"
                                :class="{
                                    'badge-planned': item.status_variant === 'planned',
                                    'badge-status-live': item.status_variant === 'live',
                                    'badge-finished': item.status_variant === 'finished',
                                }"
                                x-text="item.status_label"
                            ></span>
                        </div>
                        <p class="card-description mb-0">
                            <template x-if="!item.subtitle_missing">
                                <span x-text="item.subtitle"></span>
                            </template>
                            <template x-if="item.subtitle_missing">
                                <span class="italic">Data rozgrywek: nie ustawiono</span>
                            </template>
                        </p>
                    </div>
                </a>
            </template>
        </div>

        <div class="mt-8 flex justify-center" x-show="hasMore">
            <button type="button"
                    @click="loadMore()"
                    :disabled="loading"
                    class="btn btn-mini disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-text="loading ? 'Ładowanie…' : 'Załaduj więcej'"></span>
            </button>
        </div>
    </div>

    @canCreateOrganizations
    <a href="{{ route('tournaments.create') }}" class="btn-fab">
        Stwórz nowy turniej
    </a>
    @endcanCreateOrganizations

@endsection
