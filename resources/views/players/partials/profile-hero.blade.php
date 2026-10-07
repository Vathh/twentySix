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
@endphp
<article class="profile-hero">
    <div class="profile-hero-top">
        <x-player-avatar :player="$player" size="lg" />
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
            <p class="profile-hero-highlights">
                <span>Najszybsza lotka <strong>{{ $highlights['fastestQf'] !== null ? $highlights['fastestQf'].' lotek' : '–' }}</strong></span>
                <span>Najwyższy finish <strong>{{ $highlights['highestHf'] !== null ? $highlights['highestHf'] : '–' }}</strong></span>
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
            </div>
        @endauth
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
