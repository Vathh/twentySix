{{-- Zakładka Odznaczenia: wysokie podejścia i koło checkoutów --}}
@php
    $checkoutHits = $checkoutHits ?? [];
    $checkoutItems = $checkoutItems ?? [];
    $highVisitItems = $highVisitItems ?? [];
    $qfItems = $qfItems ?? [];
    $finishItems = $finishItems ?? [];
    $careerItems = $careerItems ?? [];
    $unlocked = collect($checkoutItems)->where('timesEarned', '>', 0)->count();
    $total = count($checkoutItems);
@endphp
<div class="mb-6 flex flex-wrap items-center gap-3">
    <p class="text-sm text-text-muted">Liczą się mecze 501 w lidze i turnieju.</p>
    @if(! empty($canCompareBadges))
        <a href="{{ route('players.badges.compare', $player) }}" class="compare-launch ml-auto">
            <span class="compare-launch__marks" aria-hidden="true">
                <svg viewBox="0 0 46 28" class="compare-launch__badges">
                    <path d="M12 3.2 19.2 7.2v8.2L12 19.4 4.8 15.4V7.2Z" />
                    <path d="M28.2 8.4 35.6 12.5v8.4l-7.4 4.2-7.4-4.2v-8.4Z" />
                </svg>
            </span>
            <span class="compare-launch__label">Porównaj odznaczenia</span>
            <svg class="compare-launch__arrow" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M4.5 10h11M11 5.5 15.5 10 11 14.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    @endif
</div>
@if(count($highVisitItems) > 0)
<section class="mb-10">
    <div class="career-section-head">
        <h2 class="text-base font-bold text-accent">Mocny punkciarz</h2>
    </div>
    <div class="grid grid-cols-3 gap-2 sm:gap-4">
        @foreach($highVisitItems as $item)
            @php
                $locked = ($item['levelName'] ?? 'locked') === 'locked';
            @endphp
            <article
                id="badge-visit-{{ $item['key'] }}"
                class="badge-card relative flex flex-col rounded-lg border border-border bg-bg-elevated px-2 py-3 sm:px-4 sm:py-4 text-center"
                x-data="{ help: false }"
                @badge-help-open.window="if ($event.detail !== $el) help = false"
                :class="help && 'is-help-open'"
            >
                <x-badge-help :text="$item['description'] ?? ''" />
                <div class="mx-auto aspect-square w-full max-w-[148px]">
                    <x-badge-artwork
                        :src="$item['image']"
                        :alt="$item['label'].($item['levelLabel'] ? ' — '.$item['levelLabel'] : '')"
                        :locked="$locked"
                    />
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-1 whitespace-nowrap text-xs font-semibold sm:text-sm">
                    <span class="text-text-secondary">Poziom {{ $item['levelNumber'] }}</span>
                    <span class="tabular-nums text-text-muted">{{ $item['progressLabel'] }}</span>
                </div>
                <div
                    class="mt-1 h-1.5 overflow-hidden rounded-full bg-border"
                    role="progressbar"
                    aria-valuenow="{{ $item['progressPercent'] }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="{{ $item['label'] }}: {{ $item['progressLabel'] }}"
                >
                    <div class="h-full rounded-full bg-accent" style="width: {{ (int) $item['progressPercent'] }}%"></div>
                </div>
                @if(! $locked && ! empty($item['lastGame']))
                    <p class="mt-1 text-[11px] leading-snug text-text-muted">
                        {{ collect([$item['lastGame']['opponents'] ?? null, $item['lastGame']['dateFormatted'] ?? null])->filter()->implode(' · ') }}
                    </p>
                @endif
            </article>
        @endforeach
    </div>
</section>
@endif
@if(count($qfItems) > 0)
<section class="mb-10">
    <div class="career-section-head">
        <h2 class="text-base font-bold text-accent">Szybka akcja</h2>
    </div>
    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 sm:gap-4">
        @foreach($qfItems as $item)
            <article
                id="badge-qf-{{ $item['key'] }}"
                class="badge-card relative flex flex-col rounded-lg border border-border bg-bg-elevated px-2 py-3 sm:px-4 sm:py-4 text-center"
                x-data="{ help: false }"
                @badge-help-open.window="if ($event.detail !== $el) help = false"
                :class="help && 'is-help-open'"
            >
                <x-badge-help :text="$item['description'] ?? ''" />
                <div class="mx-auto aspect-square w-full max-w-[148px]">
                    <x-badge-artwork
                        :src="$item['image']"
                        :alt="$item['label'].($item['levelLabel'] ? ' — '.$item['levelLabel'] : '')"
                    />
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-1 whitespace-nowrap text-xs font-semibold sm:text-sm">
                    <span class="text-text-secondary">Poziom {{ $item['levelNumber'] }}</span>
                    <span class="tabular-nums text-text-muted">{{ $item['progressLabel'] }}</span>
                </div>
                <div
                    class="mt-1 h-1.5 overflow-hidden rounded-full bg-border"
                    role="progressbar"
                    aria-valuenow="{{ $item['progressPercent'] }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="{{ $item['label'] }}: {{ $item['progressLabel'] }}"
                >
                    <div class="h-full rounded-full bg-accent" style="width: {{ (int) $item['progressPercent'] }}%"></div>
                </div>
                @if(! empty($item['lastGame']))
                    <p class="mt-1 text-[11px] leading-snug text-text-muted">
                        {{ collect([$item['lastGame']['opponents'] ?? null, $item['lastGame']['dateFormatted'] ?? null])->filter()->implode(' · ') }}
                    </p>
                @endif
            </article>
        @endforeach
    </div>
</section>
@endif
@if(count($finishItems) > 0)
<section class="mb-10">
    <div class="career-section-head">
        <h2 class="text-base font-bold text-accent">Inne</h2>
    </div>
    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 sm:gap-4">
        @foreach($finishItems as $item)
            @php
                $times = (int) ($item['timesEarned'] ?? 0);
                $locked = ($item['levelName'] ?? 'locked') === 'locked';
            @endphp
            <article
                id="badge-finish-{{ $item['key'] }}"
                class="badge-card relative flex flex-col rounded-lg border border-border bg-bg-elevated px-2 py-3 sm:px-4 sm:py-4 text-center"
                x-data="{ help: false }"
                @badge-help-open.window="if ($event.detail !== $el) help = false"
                :class="help && 'is-help-open'"
            >
                <x-badge-help :text="$item['description'] ?? ''" />
                <div class="mx-auto aspect-square w-full max-w-[148px]">
                    <x-badge-artwork
                        :src="$item['image']"
                        :alt="$item['label'].($item['levelLabel'] ? ' — '.$item['levelLabel'] : '')"
                        :locked="$locked"
                    />
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-1 whitespace-nowrap text-xs font-semibold sm:text-sm">
                    <span class="text-text-secondary">Poziom {{ $item['levelNumber'] }}</span>
                    <span class="tabular-nums text-text-muted">{{ $item['progressLabel'] }}</span>
                </div>
                <div
                    class="mt-1 h-1.5 overflow-hidden rounded-full bg-border"
                    role="progressbar"
                    aria-valuenow="{{ $item['progressPercent'] }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="{{ $item['label'] }}: {{ $item['progressLabel'] }}"
                >
                    <div class="h-full rounded-full bg-accent" style="width: {{ (int) $item['progressPercent'] }}%"></div>
                </div>
                @if($times > 0 && ! empty($item['lastGame']))
                    <p class="mt-1 text-[11px] leading-snug text-text-muted">
                        {{ collect([$item['lastGame']['opponents'] ?? null, $item['lastGame']['dateFormatted'] ?? null])->filter()->implode(' · ') }}
                    </p>
                @endif
            </article>
        @endforeach
    </div>
</section>
@endif
@if(count($careerItems) > 0)
<section class="mb-10">
    <div class="career-section-head">
        <h2 class="text-base font-bold text-accent">Kariera</h2>
    </div>
    <div class="grid grid-cols-2 gap-2 lg:grid-cols-4 lg:gap-4">
        @foreach($careerItems as $item)
            @php
                $locked = ($item['levelName'] ?? 'locked') === 'locked';
            @endphp
            <article
                id="badge-career-{{ $item['key'] }}"
                class="badge-card relative flex flex-col rounded-lg border border-border bg-bg-elevated px-2 py-3 sm:px-4 sm:py-4 text-center"
                x-data="{ help: false }"
                @badge-help-open.window="if ($event.detail !== $el) help = false"
                :class="help && 'is-help-open'"
            >
                <x-badge-help :text="$item['description'] ?? ''" />
                <div class="mx-auto aspect-square w-full max-w-[148px]">
                    <x-badge-artwork
                        :src="$item['image']"
                        :alt="$item['label'].($item['levelLabel'] ? ' — '.$item['levelLabel'] : '')"
                        :locked="$locked"
                    />
                </div>
                <p class="mt-3 text-sm font-semibold text-text-secondary">{{ $item['label'] }}</p>
                <div class="mt-1 flex items-baseline justify-between gap-1 whitespace-nowrap text-xs font-semibold sm:text-sm">
                    <span class="text-text-secondary">Poziom {{ $item['levelNumber'] }}</span>
                    <span class="tabular-nums text-text-muted">{{ $item['progressLabel'] }}</span>
                </div>
                <div
                    class="mt-1 h-1.5 overflow-hidden rounded-full bg-border"
                    role="progressbar"
                    aria-valuenow="{{ $item['progressPercent'] }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="{{ $item['label'] }}: {{ $item['progressLabel'] }}"
                >
                    <div class="h-full rounded-full bg-accent" style="width: {{ (int) $item['progressPercent'] }}%"></div>
                </div>
                @if(! $locked && ! empty($item['lastGame']['tournamentName']))
                    <p class="mt-1 text-[11px] leading-snug text-text-muted">
                        {{ $item['lastGame']['tournamentName'] }}
                    </p>
                @endif
            </article>
        @endforeach
    </div>
</section>
@endif
<section>
    <div class="career-section-head">
        <h2 class="text-base font-bold text-accent">Checkouty 100+</h2>
        <p class="text-sm text-text-muted">{{ $unlocked }} / {{ $total }} odblokowanych</p>
    </div>
    <x-checkout-wheel :hits="$checkoutHits" :items="$checkoutItems" />
</section>
