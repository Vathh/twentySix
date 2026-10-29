@php
    $isRegistered = $player->user_id && $player->user;
    $registeredOn = $isRegistered ? $player->user->created_at->format('d.m.Y') : null;
    $relationChip = null;
    if (! $isOwnProfile) {
        if ($isFriend) {
            $relationChip = 'Znajomy';
        } elseif ($pendingSentInvitation) {
            $relationChip = 'Zaproszenie wysłane';
        } elseif ($pendingReceivedInvitation) {
            $relationChip = 'Zaproszenie od tego gracza';
        }
    }
    // Gablota: tylko zdobyte odznaki z grafiką. Najpierw wysokie podejścia (180 → 100), potem QF (9 → 19), potem finish 100+.
    $showcaseBadge = function (array $item, int $sort, string $anchor): array {
        return [
            'image' => $item['image'],
            'title' => collect([$item['label'] ?? null, $item['levelLabel'] ?? null])->filter()->implode(' — '),
            'sort' => $sort,
            'anchor' => $anchor,
        ];
    };
    $visitBadges = collect($highVisitItems ?? [])
        ->filter(fn ($item) => ($item['levelName'] ?? 'locked') !== 'locked' && filled($item['image'] ?? null))
        ->map(fn ($item) => $showcaseBadge($item, 1000 - (int) ($item['key'] ?? 0), 'badge-visit-'.$item['key']))
        ->sortBy('sort')
        ->values();
    $qfBadges = collect($qfItems ?? [])
        ->filter(fn ($item) => (int) ($item['timesEarned'] ?? 0) > 0 && filled($item['image'] ?? null))
        ->map(fn ($item) => $showcaseBadge($item, 2000 + (int) ($item['key'] ?? 0), 'badge-qf-'.$item['key']))
        ->sortBy('sort')
        ->values();
    $finishBadges = collect($finishItems ?? [])
        ->filter(fn ($item) => ($item['levelName'] ?? 'locked') !== 'locked' && filled($item['image'] ?? null))
        ->map(fn ($item) => $showcaseBadge($item, 3000 + (int) ($item['key'] ?? 0), 'badge-finish-'.$item['key']))
        ->sortBy('sort')
        ->values();
    $careerOrder = ['podium' => 0, 'win_streak' => 1, 'appearances' => 2, 'titles' => 3];
    $careerBadges = collect($careerItems ?? [])
        ->filter(fn ($item) => ($item['levelName'] ?? 'locked') !== 'locked' && filled($item['image'] ?? null))
        ->map(fn ($item) => $showcaseBadge($item, 4000 + ($careerOrder[$item['key'] ?? ''] ?? 9), 'badge-career-'.$item['key']))
        ->sortBy('sort')
        ->values();
    $showcaseBadges = $visitBadges->concat($qfBadges)->concat($finishBadges)->concat($careerBadges)->values();
@endphp
<article class="profile-hero">
    <div class="profile-hero-top">
        <x-player-avatar :player="$player" size="lg" />
        <div class="profile-hero-main">
            <div class="profile-hero-idline">
                <div class="profile-hero-id">
                    <h1 class="profile-hero-name">{{ $player->name }}</h1>
                    <p class="profile-hero-meta">
                        @if($isRegistered)
                            <span>Konto · od {{ $registeredOn }}</span>
                        @else
                            <span class="overview-scope overview-scope--all">Gość</span>
                        @endif
                        @if($relationChip)
                            <span class="overview-scope overview-scope--form">{{ $relationChip }}</span>
                        @endif
                    </p>
                </div>
                @auth
                    <div class="profile-hero-actions">
                        @if($isOwnProfile)
                            <a href="{{ route('players.edit', $player) }}" class="btn btn-mini">Edytuj profil</a>
                        @elseif($canInviteFriend)
                            <form action="{{ route('players.add-friend', $player) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-mini">Dodaj do znajomych</button>
                            </form>
                        @elseif($pendingReceivedInvitation)
                            <form action="{{ route('friends.invitations.accept', $pendingReceivedInvitation->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-mini">Akceptuj</button>
                            </form>
                            <form action="{{ route('friends.invitations.reject', $pendingReceivedInvitation->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-mini border border-accent text-accent bg-transparent hover:bg-accent/10">Odrzuć</button>
                            </form>
                        @endif
                        @if(!empty($feedMute['allowed']))
                            <form method="POST" action="{{ $feedMute['muted']
                                ? route('players.feed-unmute', $player)
                                : route('players.feed-mute', $player) }}">
                                @csrf
                                @if($feedMute['muted'])
                                    @method('DELETE')
                                @endif
                                <button type="submit" class="btn btn-mini">
                                    {{ $feedMute['muted'] ? 'Przywróć na stronie głównej' : 'Wycisz na stronie głównej' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endauth
            </div>

            <div class="profile-hero-highlights">
                <div class="profile-hero-highlight">
                    <span class="profile-hero-highlight-label">Najszybsza lotka</span>
                    <strong class="profile-hero-highlight-value">
                        @if($highlights['fastestQf'] !== null)
                            {{ $highlights['fastestQf'] }}<span class="profile-hero-highlight-unit">lotek</span>
                        @else
                            <span class="profile-hero-highlight-empty">–</span>
                        @endif
                    </strong>
                </div>
                <div class="profile-hero-highlight">
                    <span class="profile-hero-highlight-label">Najwyższy finish</span>
                    <strong class="profile-hero-highlight-value">
                        @if($highlights['highestHf'] !== null)
                            {{ $highlights['highestHf'] }}
                        @else
                            <span class="profile-hero-highlight-empty">–</span>
                        @endif
                    </strong>
                </div>
            </div>

            <section class="profile-hero-badges" aria-label="Odznaczenia">
                <h2 class="profile-hero-badges-label">Odznaczenia</h2>
                @if($showcaseBadges->isEmpty())
                    <p class="profile-hero-badges-empty">Jeszcze brak odznaczeń.</p>
                @else
                    <ul class="profile-hero-badges-row">
                        @foreach($showcaseBadges as $badge)
                            <li>
                                <button
                                    type="button"
                                    class="profile-hero-badge"
                                    title="{{ $badge['title'] }}"
                                    @click="openBadge(@js($badge['anchor']))"
                                >
                                    <x-badge-artwork :src="$badge['image']" :alt="$badge['title']" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>

    @if(filled($player->description))
        <p class="profile-hero-bio">{{ $player->description }}</p>
    @elseif($isOwnProfile)
        <p class="profile-hero-bio profile-hero-bio--empty">
            Nie masz jeszcze opisu. <a href="{{ route('players.edit', $player) }}" class="text-accent hover:underline">Dodaj go w edycji profilu</a>.
        </p>
    @endif

    @if(!empty($liveGames))
        <div class="profile-hero-live">
            @foreach($liveGames as $liveGame)
                <a href="{{ $liveGame['liveUrl'] }}" class="profile-hero-live-link">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold bg-accent/25 text-accent">
                                <span class="inline-block w-1.5 h-1.5 rounded-full bg-accent animate-pulse" aria-hidden="true"></span>
                                Na żywo
                            </span>
                            <span class="text-xs text-text-muted">{{ $liveGame['stageLabel'] }}</span>
                        </div>
                        <p class="text-text font-semibold break-words">
                            Gra teraz vs {{ $liveGame['opponentName'] }}
                            @if($liveGame['tournamentName'])
                                <span class="text-text-secondary font-normal">· {{ $liveGame['tournamentName'] }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="text-accent text-sm font-semibold shrink-0">Podgląd live →</span>
                </a>
            @endforeach
        </div>
    @endif
</article>
