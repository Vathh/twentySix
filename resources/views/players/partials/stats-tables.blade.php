{{-- Przegląd: forma — ostatnie 30 dni + highlights 90 dni --}}
@php
    $record = $overview['record'] ?? [];
    $overall = $record['overall'] ?? [];
    $overallPlayed = (int) ($overall['played'] ?? 0);
    $overallWins = (int) ($overall['wins'] ?? 0);
    $overallLosses = max(0, $overallPlayed - $overallWins);
    $winRatePct = $overallPlayed > 0 ? round(100 * $overallWins / $overallPlayed, 1) : 0;
    $activity = $overview['activity'] ?? [];
    $gamesLast30 = (int) ($activity['gamesLast30'] ?? 0);
    $currentStreak = (int) ($activity['currentStreak'] ?? 0);
    $lastActivityOn = $activity['lastActivityOn'] ?? '–';
    $overviewQuick = $overviewSplit['quick'] ?? [];
    $overviewTournament = $overviewSplit['tournament'] ?? [];
    $formValue = static function (array $stats, string $key): string {
        if ($key === 'fastest_qf') {
            return $stats['fastest_qf'] !== null ? $stats['fastest_qf'].' lotek' : '–';
        }
        if ($key === 'avg_three_darts') {
            return \App\Support\AverageFormat::display($stats[$key] ?? null, '–');
        }
        if ($key === 'highest_hf') {
            return $stats[$key] !== null ? (string) $stats[$key] : '–';
        }

        return (string) ($stats[$key] ?? 0);
    };
    $formCards = [
        [
            'key' => 'tournament',
            'title' => 'Turnieje',
            'tone' => 'tournament',
            'stats' => $overviewTournament,
            'icon' => 'board',
        ],
        [
            'key' => 'quick',
            'title' => 'Szybkie',
            'tone' => 'quick',
            'stats' => $overviewQuick,
            'icon' => 'bolt',
        ],
    ];
    $formHighlights = [
        ['Najwyższy checkout', 'highest_hf'],
        ['Najszybsza lotka', 'fastest_qf'],
        ['180', 'count_max'],
        ['170+', 'count_170_plus'],
        ['Checkout 100+', 'count_hf'],
        ['Szybkie lotki', 'count_qf'],
    ];
@endphp
<section>
    <h2 class="text-base font-bold text-accent">Forma</h2>

    <div class="overview-pair mt-2.5">
        <div class="overview-summary" style="--win-pct: {{ $winRatePct }}%;">
            <div class="overview-card-head">
                <p class="overview-summary-kicker">Wyniki</p>
                @include('players.partials.overview-scope', ['kind' => 'recent'])
            </div>
            <div class="overview-summary-main">
                <div>
                    <p class="overview-summary-rate">{{ $overall['winRate'] ?? '–' }}</p>
                    <p class="overview-summary-rate-label">Win rate</p>
                </div>
                <p class="overview-summary-counts">
                    <span><strong>{{ $overallWins }}</strong> wygranych</span>
                    <span class="overview-summary-dot" aria-hidden="true">·</span>
                    <span><strong>{{ $overallLosses }}</strong> porażek</span>
                    <span class="overview-summary-dot" aria-hidden="true">·</span>
                    <span><strong>{{ $overallPlayed }}</strong> rozegranych</span>
                </p>
            </div>
            <div class="overview-summary-bar" role="img" aria-label="Win rate {{ $overall['winRate'] ?? '–' }}">
                <span class="overview-summary-bar-fill"></span>
            </div>
        </div>

        <div class="overview-recent">
            <div class="overview-card-head">
                <p class="overview-summary-kicker">Obecność</p>
                @include('players.partials.overview-scope', ['kind' => 'recent'])
            </div>
            <p class="overview-summary-rate">{{ $gamesLast30 }}</p>
            <p class="overview-summary-rate-label">mecze</p>
            <dl class="overview-recent-meta">
                <div>
                    <dt>Ostatnia aktywność</dt>
                    <dd>{{ $lastActivityOn }}</dd>
                </div>
                <div>
                    <dt>Aktualna seria</dt>
                    <dd>{{ $currentStreak }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="overview-pair">
        @foreach($formCards as $card)
            @php $stats = $card['stats']; @endphp
            <article class="overview-form overview-form--{{ $card['tone'] }}">
                <header class="overview-form-head">
                    <div class="overview-form-sources">
                        @if($card['key'] === 'tournament')
                            <span class="overview-form-source">
                                <span class="overview-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="8"/>
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M12 4v2M12 18v2M4 12h2M18 12h2"/>
                                    </svg>
                                </span>
                                <span>Turnieje</span>
                            </span>
                            <span class="overview-form-source overview-form-source--league">
                                <span class="overview-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 3 19 6.5v6.2c0 4.4-3.1 7.4-7 8.8-3.9-1.4-7-4.4-7-8.8V6.5L12 3z"/>
                                        <path d="M12 11v5M10 13h4"/>
                                    </svg>
                                </span>
                                <span>Liga</span>
                            </span>
                        @else
                            <span class="overview-form-source">
                                <span class="overview-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M13 2 4 14h7l-1 8 11-12h-7z"/>
                                    </svg>
                                </span>
                                <span>Szybkie</span>
                            </span>
                        @endif
                    </div>
                    @include('players.partials.overview-scope', ['kind' => 'form'])
                </header>
                <div class="overview-form-hero">
                    <div>
                        <p class="overview-summary-rate">{{ $formValue($stats, 'avg_three_darts') }}</p>
                        <p class="overview-summary-rate-label">średnia 3 lotki</p>
                    </div>
                    <p class="overview-form-games">
                        <strong>{{ $formValue($stats, 'games') }}</strong>
                        meczów
                    </p>
                </div>
                <dl class="overview-form-grid">
                    @foreach($formHighlights as [$statLabel, $statKey])
                        <div class="overview-form-stat">
                            <dt>{{ $statLabel }}</dt>
                            <dd>{{ $formValue($stats, $statKey) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>
        @endforeach
    </div>
</section>
