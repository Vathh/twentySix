{{--
  Drzewko drabinki: kolumny tej samej wysokości, sloty flex-1 + items-center
  → mecz rundy N jest w pionie na środku pary meczów rundy N-1.
  Łączniki: data-feed="pair" (2→1) albo "single" (1→1, np. LB).

  @var list<array{key: string, label: string, games: iterable}> $rounds
--}}
@php
    $rounds = $rounds ?? [];
@endphp

@if($rounds !== [])
    <div class="bracket-tree">
        @foreach($rounds as $index => $round)
            @php
                $thisCount = count($round['games']);
                $nextCount = isset($rounds[$index + 1]) ? count($rounds[$index + 1]['games']) : 0;
                $feed = 'none';
                if ($nextCount > 0) {
                    if ($thisCount === $nextCount) {
                        $feed = 'single';
                    } elseif ($nextCount > 0 && $thisCount === $nextCount * 2) {
                        $feed = 'pair';
                    } elseif ($thisCount > $nextCount) {
                        $feed = 'pair';
                    } else {
                        $feed = 'single';
                    }
                }
            @endphp
            <div class="bracket-round" data-feed="{{ $feed }}">
                <p class="bracket-round-label">
                    {{ $round['label'] }}
                </p>
                <div class="bracket-round-slots">
                    @foreach($round['games'] as $game)
                        <div class="bracket-slot">
                            <div class="bracket-slot-body">
                                @include('tournaments.partials.bracket-game', ['game' => $game])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
