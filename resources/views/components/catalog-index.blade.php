@props([
    'items',
    'hasMore',
    'url',
    'kind',
    'heading',
    'lead',
    'placeholder',
    'emptyTitle',
    'emptyDescription',
    'query' => '',
    'summary' => null,
    'page' => 1,
    'sort' => 'activity',
    'status' => '',
    'showStatusFilter' => false,
    'createUrl' => null,
    'createLabel' => null,
])

<div
    class="max-w-6xl mx-auto px-4 pt-2 pb-12"
    x-data="indexLoadMore(@js([
        'items' => $items,
        'hasMore' => $hasMore,
        'url' => $url,
        'query' => $query,
        'summary' => $summary,
        'page' => $page,
        'sort' => $sort,
        'status' => $status,
        'kind' => $kind,
        'emptyTitle' => $emptyTitle,
        'emptyDescription' => $emptyDescription,
    ]))"
>
    <header class="catalog-head">
        <p class="entity-eyebrow">Rozgrywki</p>
        <h1 class="entity-title catalog-title">{{ $heading }}</h1>
        <span class="entity-rule" aria-hidden="true"></span>
        <p class="catalog-lead">{{ $lead }}</p>
    </header>

    <form class="catalog-toolbar" method="get" action="{{ $url }}" role="search" @submit.prevent="search()">
        <div class="catalog-search">
            <label class="sr-only" for="catalog-q-{{ $kind }}">{{ $placeholder }}</label>
            <input
                id="catalog-q-{{ $kind }}"
                type="search"
                name="q"
                class="input-field"
                placeholder="{{ $placeholder }}"
                value="{{ $query }}"
                x-model="query"
                @input.debounce.300ms="search()"
                autocomplete="off"
                enterkeyhint="search"
            >
            <button
                type="button"
                class="catalog-search-clear"
                x-show="query.trim() !== ''"
                x-cloak
                @click="clearSearch()"
                aria-label="Wyczyść wyszukiwanie"
            >×</button>
        </div>
        <div class="catalog-menu" @click.outside="sortOpen = false" @keydown.escape="sortOpen = false">
            <button
                type="button"
                class="catalog-menu-button"
                @click="sortOpen = !sortOpen"
                :aria-expanded="sortOpen ? 'true' : 'false'"
                aria-haspopup="menu"
                aria-controls="catalog-sort-menu-{{ $kind }}"
            >
                <span>Sortowanie</span>
                <svg class="catalog-menu-chevron" :class="sortOpen && 'is-open'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div
                id="catalog-sort-menu-{{ $kind }}"
                class="catalog-menu-panel"
                x-show="sortOpen"
                x-cloak
                role="menu"
                aria-label="Sortowanie"
            >
                <button type="button" class="catalog-menu-item" role="menuitemradio" :aria-checked="sort === 'activity' ? 'true' : 'false'" @click="chooseSort('activity')">
                    <svg class="catalog-menu-check" x-show="sort === 'activity'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="catalog-menu-check" x-show="sort !== 'activity'" aria-hidden="true"></span>
                    Ostatnia aktywność
                </button>
                <button type="button" class="catalog-menu-item" role="menuitemradio" :aria-checked="sort === 'members' ? 'true' : 'false'" @click="chooseSort('members')">
                    <svg class="catalog-menu-check" x-show="sort === 'members'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="catalog-menu-check" x-show="sort !== 'members'" aria-hidden="true"></span>
                    Użytkownicy
                </button>
            </div>
            <noscript>
                <select name="sort" class="catalog-sort" aria-label="Sortowanie">
                    <option value="activity" @selected($sort !== 'members')>Ostatnia aktywność</option>
                    <option value="members" @selected($sort === 'members')>Użytkownicy</option>
                </select>
            </noscript>
        </div>
        @if($showStatusFilter)
            <label class="sr-only" for="catalog-status-{{ $kind }}">Status</label>
            <select
                id="catalog-status-{{ $kind }}"
                name="status"
                class="catalog-sort"
                x-model="status"
                @change="changeStatus()"
            >
                <option value="">Wszystkie statusy</option>
                <option value="planned">Zaplanowane</option>
                <option value="live">W trakcie</option>
                <option value="finished">Zakończone</option>
            </select>
        @endif
        @if($createUrl)
            <a href="{{ $createUrl }}" class="btn btn-primary catalog-create">{{ $createLabel }}</a>
        @endif
    </form>

    <p class="catalog-summary" x-show="searching" x-cloak>Szukam…</p>
    <p class="catalog-summary text-danger-text" x-show="searchError" x-cloak x-text="searchError"></p>
    <p class="catalog-summary" x-show="!searching && summary" x-cloak x-text="summary"></p>

    <template x-if="items.length === 0 && !searching">
        <div class="empty-panel">
            <svg class="empty-panel-icon" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="1.5" opacity="0.35"/>
                <circle cx="32" cy="32" r="18" stroke="currentColor" stroke-width="1.5" opacity="0.5"/>
                <circle cx="32" cy="32" r="8" stroke="currentColor" stroke-width="1.5" opacity="0.7"/>
                <circle cx="32" cy="32" r="2.5" fill="currentColor" opacity="0.8"/>
                <path d="M48 14 L54 8 M50 16 L56 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.55"/>
                <path d="M52 11 L58 16" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" opacity="0.4"/>
            </svg>
            <p class="empty-panel-title" x-text="emptyHeading"></p>
            <p class="empty-panel-desc" x-text="emptyCopy"></p>
        </div>
    </template>

    <div class="index-grid catalog-grid" x-show="items.length > 0" :class="searching && 'opacity-60'">
        <template x-for="(item, index) in items" :key="item.id">
            <a :href="item.url"
               class="block h-full"
               :style="'--stagger: ' + index">
                <div
                    class="place-card"
                    :class="{
                        'place-card--organization': kind === 'organization',
                        'place-card--season': kind === 'season',
                        'place-card--tournament': kind === 'tournament',
                        'is-planned': item.status_variant === 'planned',
                        'is-live': item.status_variant === 'live',
                        'is-finished': item.status_variant === 'finished',
                    }"
                >
                    <p class="catalog-context" x-show="item.context" x-text="item.context"></p>
                    <div class="place-card-head">
                        <span class="place-card-name" x-text="item.name"></span>
                        <span
                            x-show="item.status_label"
                            class="shrink-0 inline-flex items-center"
                            :class="{
                                'badge-planned': item.status_variant === 'planned',
                                'badge-status-live': item.status_variant === 'live',
                                'badge-finished': item.status_variant === 'finished',
                            }"
                        >
                            <span x-show="item.status_variant === 'live'" class="place-live-dot" aria-hidden="true"></span>
                            <span x-text="item.status_label"></span>
                        </span>
                    </div>
                    <div class="catalog-stats" x-show="item.stats && item.stats.length">
                        <template x-for="stat in item.stats" :key="stat.key">
                            <span class="catalog-stat">
                                <svg x-show="stat.key === 'seasons'" class="catalog-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <rect x="4" y="5" width="16" height="15" rx="2"/>
                                    <path d="M8 3v4M16 3v4M4 10h16" stroke-linecap="round"/>
                                </svg>
                                <svg x-show="stat.key === 'tournaments'" class="catalog-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path d="M8 4h8v6a4 4 0 0 1-8 0V4z" stroke-linejoin="round"/>
                                    <path d="M8 6H5.5A2.5 2.5 0 0 0 8 9.5M16 6h2.5A2.5 2.5 0 0 1 16 9.5" stroke-linecap="round"/>
                                    <path d="M12 14v3M9 20h6" stroke-linecap="round"/>
                                </svg>
                                <svg x-show="stat.key === 'members'" class="catalog-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <circle cx="12" cy="8" r="3"/>
                                    <path d="M6.5 19c.8-2.8 2.8-4.2 5.5-4.2s4.7 1.4 5.5 4.2" stroke-linecap="round"/>
                                </svg>
                                <span class="catalog-stat-value" x-text="stat.value"></span>
                                <span class="catalog-stat-label" x-text="stat.label"></span>
                            </span>
                        </template>
                    </div>
                    <p class="catalog-activity" x-show="item.activity" x-text="'aktywność ' + item.activity"></p>
                    <p class="place-card-meta" x-show="item.meta" x-text="item.meta"></p>
                </div>
            </a>
        </template>
    </div>

    <div class="mt-8 flex justify-center" x-show="hasMore && items.length > 0">
        <button type="button"
                @click="loadMore()"
                :disabled="loading"
                class="btn btn-mini disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-text="loading ? 'Ładowanie…' : 'Załaduj więcej'"></span>
        </button>
    </div>
</div>
