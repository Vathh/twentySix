{{-- Przegląd: bilans kariery (T+L+Q, z walkowerami, bez treningu) --}}
@php
    $record = $overview['record'] ?? [];
    $tournament = $record['tournament'] ?? [];
    $league = $record['league'] ?? [];
    $quick = $record['quick'] ?? [];
    $tournamentPlayed = (int) ($tournament['played'] ?? 0);
    $tournamentWins = (int) ($tournament['wins'] ?? 0);
    $leaguePlayed = (int) ($league['played'] ?? 0);
    $leagueWins = (int) ($league['wins'] ?? 0);
    $quickPlayed = (int) ($quick['played'] ?? 0);
    $quickWins = (int) ($quick['wins'] ?? 0);
    $formatWinRate = static function (int $played, int $wins): string {
        if ($played === 0) {
            return '–';
        }

        return number_format(round(100 * $wins / $played, 1), 1, '.', '').'%';
    };
@endphp
<section>
    <div class="flex items-center gap-3">
        <h2 class="text-base font-bold text-accent">Bilans</h2>
        @include('players.partials.overview-scope', ['kind' => 'all'])
    </div>

    <div class="overview-sources mt-2.5">
        <article class="overview-source overview-source--tournament">
            <header class="overview-source-head">
                <span class="overview-source-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="8"/>
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 4v2M12 18v2M4 12h2M18 12h2"/>
                    </svg>
                </span>
                <h3 class="overview-source-title">Turnieje</h3>
            </header>
            <div class="overview-source-stats">
                <div>
                    <p class="overview-source-played">{{ $tournamentPlayed }}</p>
                    <p class="overview-source-played-label">mecze</p>
                </div>
                <div class="overview-source-wl">
                    <p>{{ $tournamentWins }}W · {{ max(0, $tournamentPlayed - $tournamentWins) }}L</p>
                    <p class="overview-source-rate">{{ $formatWinRate($tournamentPlayed, $tournamentWins) }}</p>
                </div>
            </div>
            <div class="overview-honors" aria-label="Podium turniejowe">
                @include('players.partials.overview-honor', ['tone' => 'gold', 'label' => '1. miejsce', 'count' => $tournament['place1'] ?? 0])
                @include('players.partials.overview-honor', ['tone' => 'silver', 'label' => '2. miejsce', 'count' => $tournament['place2'] ?? 0])
                @include('players.partials.overview-honor', ['tone' => 'bronze', 'label' => '3. miejsce', 'count' => $tournament['place3'] ?? 0])
            </div>
        </article>

        <article class="overview-source overview-source--league">
            <header class="overview-source-head">
                <span class="overview-source-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3 19 6.5v6.2c0 4.4-3.1 7.4-7 8.8-3.9-1.4-7-4.4-7-8.8V6.5L12 3z"/>
                        <path d="M12 11v5M10 13h4"/>
                    </svg>
                </span>
                <h3 class="overview-source-title">Liga</h3>
            </header>
            <div class="overview-source-stats">
                <div>
                    <p class="overview-source-played">{{ $leaguePlayed }}</p>
                    <p class="overview-source-played-label">mecze</p>
                </div>
                <div class="overview-source-wl">
                    <p>{{ $leagueWins }}W · {{ max(0, $leaguePlayed - $leagueWins) }}L</p>
                    <p class="overview-source-rate">{{ $formatWinRate($leaguePlayed, $leagueWins) }}</p>
                </div>
            </div>
            <div class="overview-honors" aria-label="Mistrzostwa ligowe">
                @include('players.partials.overview-honor', ['tone' => 'title', 'label' => 'Mistrzostwa', 'count' => $league['titles'] ?? 0])
            </div>
        </article>

        <article class="overview-source overview-source--quick">
            <header class="overview-source-head">
                <span class="overview-source-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M13 2 4 14h7l-1 8 11-12h-7z"/>
                    </svg>
                </span>
                <h3 class="overview-source-title">Szybkie</h3>
            </header>
            <div class="overview-source-stats">
                <div>
                    <p class="overview-source-played">{{ $quickPlayed }}</p>
                    <p class="overview-source-played-label">mecze</p>
                </div>
                <div class="overview-source-wl">
                    <p>{{ $quickWins }}W · {{ max(0, $quickPlayed - $quickWins) }}L</p>
                    <p class="overview-source-rate">{{ $formatWinRate($quickPlayed, $quickWins) }}</p>
                </div>
            </div>
        </article>
    </div>
</section>
