{{-- Zakładka Statystyki: kariera z filtrami źródła i okna --}}
@php
    $career = $career ?? ['window' => '90d', 'source' => 'all', 'isSelf' => $isOwnProfile, 'hero' => [], 'series' => ['x01_average' => [], 'double_pct' => []], 'table' => null];
@endphp
<div
    x-data="playerCareerDashboard({
        window: @js($career['window'] ?? '90d'),
        source: @js($career['source'] ?? 'all'),
        isSelf: @js($career['isSelf'] ?? $isOwnProfile),
        hero: @js($career['hero'] ?? []),
        series: @js($career['series'] ?? ['x01_average' => [], 'double_pct' => []]),
        table: @js($career['table'] ?? null),
        fetchUrl: @js(route('players.career', $player)),
    })"
    class="space-y-8"
>
    <div>
        <div class="career-section-head">
            <h2 class="text-xl font-bold text-accent">Kariera</h2>
            <p class="text-xs text-text-muted" x-show="loading">Aktualizuję…</p>
        </div>
        <div
            class="career-filter-anchor"
            x-ref="filterAnchor"
            :style="filterStuck ? { height: filterBarH + 'px' } : null"
        >
            <div
                class="career-filter-bar"
                x-ref="filterBar"
                :class="{ 'is-stuck': filterStuck }"
            >
                <div class="career-filter-controls">
                    <div class="career-seg" role="group" aria-label="Źródło">
                        <template x-for="s in sources()" :key="s.key">
                            <button type="button"
                                    class="career-seg-btn"
                                    :class="{ 'is-on': source === s.key }"
                                    :aria-pressed="(source === s.key).toString()"
                                    @click="setSource(s.key)">
                                <span x-text="s.label"></span>
                            </button>
                        </template>
                    </div>
                    <div class="career-seg" role="group" aria-label="Okres">
                        <template x-for="w in windows" :key="w.key">
                            <button type="button"
                                    class="career-seg-btn"
                                    :class="{ 'is-on': window === w.key }"
                                    :aria-pressed="(window === w.key).toString()"
                                    @click="setWindow(w.key)">
                                <span x-text="w.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="career-hero">
        <article class="career-stat-card career-stat-card--games">
            <p class="overview-summary-kicker">Mecze</p>
            <p class="overview-summary-rate" x-text="hero.games ?? 0"></p>
            <p class="overview-summary-rate-label">w wybranym oknie</p>
        </article>

        <article class="career-stat-card career-stat-card--avg">
            <p class="overview-summary-kicker">Średnia 3 lotki</p>
            <p class="overview-summary-rate" x-text="hero.hasX01 ? formatAverage(hero.x01Average) : '–'"></p>
            <p class="overview-summary-rate-label">X01</p>
            <p class="career-stat-delta"
               x-show="formatDelta(hero.x01AverageDelta)"
               :class="{ 'is-up': hero.x01AverageDelta > 0 }"
               x-text="formatDelta(hero.x01AverageDelta) ? ('vs poprz. okno ' + formatDelta(hero.x01AverageDelta)) : ''"></p>
        </article>

        <article class="career-stat-card career-stat-card--doubles">
            <p class="overview-summary-kicker">Double %</p>
            <p class="overview-summary-rate" x-text="hero.hasDoubles && hero.doublePct != null ? (hero.doublePct + '%') : '–'"></p>
            <p class="overview-summary-rate-label" x-text="hero.doubleLabel || 'śledzone duble'"></p>
            <p class="career-stat-delta"
               x-show="formatDelta(hero.doublePctDelta)"
               :class="{ 'is-up': hero.doublePctDelta > 0 }"
               x-text="formatDelta(hero.doublePctDelta) ? ('vs poprz. okno ' + formatDelta(hero.doublePctDelta)) : ''"></p>
        </article>
    </div>

    <div class="career-charts">
        <article class="career-chart">
            <h3 class="career-chart-title">Trend średniej X01</h3>
            @include('players.partials.career-trend-chart', [
                'chartVar' => 'x01Chart',
                'htmlVar' => 'x01ChartHtml',
                'emptyText' => 'Za mało gier X01 w tym oknie, żeby narysować wykres.',
            ])
        </article>
        <article class="career-chart">
            <h3 class="career-chart-title">Trend double %</h3>
            @include('players.partials.career-trend-chart', [
                'chartVar' => 'doubleChart',
                'htmlVar' => 'doubleChartHtml',
                'emptyText' => 'Brak śledzonych dubli w tym oknie (nie pokazujemy 0%).',
            ])
        </article>
    </div>

    @include('players.partials.career-filter-tables')
</div>
