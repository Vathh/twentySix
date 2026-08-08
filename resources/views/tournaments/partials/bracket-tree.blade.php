{{--
  Drzewko drabinki: kolumny tej samej wysokości, sloty flex-1 + items-center
  → mecz rundy N jest w pionie na środku pary meczów rundy N-1.

  @var list<array{key: string, label: string, games: iterable}> $rounds
--}}
@php
    $rounds = $rounds ?? [];
@endphp

@if($rounds !== [])
    <div class="bracket-tree flex items-stretch gap-8 sm:gap-10 overflow-x-auto pb-6">
        @foreach($rounds as $round)
            <div class="bracket-round flex flex-col min-w-[220px]">
                <p class="text-center text-sm text-text-muted mb-2 shrink-0 h-6 leading-6">
                    {{ $round['label'] }}
                </p>
                <div class="bracket-round-slots flex flex-col flex-1">
                    @foreach($round['games'] as $game)
                        <div class="bracket-slot flex flex-1 items-center py-1.5">
                            <div class="w-full">
                                @include('tournaments.partials.bracket-game', ['game' => $game])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
