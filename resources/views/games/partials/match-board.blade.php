@php
    $p1Wins = $status === 'finished' && (int) $winnerId === (int) $player1->id;
    $p2Wins = $status === 'finished' && (int) $winnerId === (int) $player2->id;
@endphp

<section class="match-board" aria-label="{{ $player1->name }} kontra {{ $player2->name }}">
    <h1 class="sr-only">{{ $player1->name }} vs {{ $player2->name }}</h1>

    @if($player1->user_id)
        <a href="{{ route('players.show', $player1) }}" class="match-player {{ $p1Wins ? 'is-winner' : '' }}">
            <x-player-avatar :player="$player1" />
            <span class="match-player-name">{{ $player1->name }}</span>
        </a>
    @else
        <div class="match-player {{ $p1Wins ? 'is-winner' : '' }}">
            <x-player-avatar :player="$player1" />
            <span class="match-player-name">{{ $player1->name }}</span>
        </div>
    @endif

    <div class="match-board-mid">
        <p class="match-board-score score-num">
            <span class="{{ $p1Wins ? 'text-accent' : 'text-text' }}">{{ $player1Score }}</span>
            <span class="text-text-muted mx-1">:</span>
            <span class="{{ $p2Wins ? 'text-accent' : 'text-text' }}">{{ $player2Score }}</span>
        </p>
        <p class="match-board-format">{{ $formatLabel ?? '501 · do 2 legów' }}</p>
        @if($leagueMatchdayLabel ?? null)
            <p class="match-board-note">{{ $leagueMatchdayLabel }}</p>
        @endif
        @if($leagueWalkoverLabel ?? null)
            <p class="match-board-note">{{ $leagueWalkoverLabel }}</p>
        @endif
        @if($status === 'voided')
            <p class="match-board-note">Mecz anulowany (rezygnacja).</p>
        @elseif($status === 'lobby')
            <p class="match-board-note">Lobby — oczekiwanie na akceptację przeciwnika.</p>
        @elseif($status !== 'finished')
            <p class="match-board-note">
                @if($status === 'in_progress')
                    @if(($canCorrectResult ?? false) || ($canManageLeague ?? false))
                        Mecz w trakcie — możesz wymusić wynik końcowy poniżej.
                    @else
                        Mecz w trakcie.
                    @endif
                @else
                    @if(($canCorrectResult ?? false) || ($canManageLeague ?? false))
                        Mecz jeszcze nie rozegrany — możesz ustawić wynik lub walkover poniżej.
                    @else
                        Mecz jeszcze nie rozegrany.
                    @endif
                    @if($leagueOverdue ?? false)
                        · zaległy
                    @endif
                @endif
            </p>
            @if(($leagueDeadline ?? null) && $status !== 'in_progress')
                <p class="match-board-note">Termin: {{ $leagueDeadline }}</p>
            @endif
        @endif
    </div>

    @if($player2->user_id)
        <a href="{{ route('players.show', $player2) }}" class="match-player {{ $p2Wins ? 'is-winner' : '' }}">
            <x-player-avatar :player="$player2" />
            <span class="match-player-name">{{ $player2->name }}</span>
        </a>
    @else
        <div class="match-player {{ $p2Wins ? 'is-winner' : '' }}">
            <x-player-avatar :player="$player2" />
            <span class="match-player-name">{{ $player2->name }}</span>
        </div>
    @endif
</section>
