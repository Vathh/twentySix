@extends('layouts.app')

@section('title', 'Turnieje')

@section('content')

    <div class="max-w-6xl mx-auto px-4 pt-10 pb-28">
        @if($tournaments->isEmpty())
            <x-empty-state
                title="Brak turniejów"
                description="Gdy pojawią się turnieje w ligach lub jednorazowe, zobaczysz je tutaj."
            />
        @else
            <div class="index-grid">
                @foreach($tournaments as $index => $tournament)
                    <a href="{{ route('tournaments.show', ['tournament' => $tournament->id]) }}"
                       class="block"
                       style="--stagger: {{ $index }}">
                        <div class="index-card">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <h3 class="text-lg font-semibold text-text leading-snug">
                                    {{ $tournament->displayTitle() }}
                                </h3>
                                @php $variant = $tournament->status->badgeVariant(); @endphp
                                <span @class([
                                    'shrink-0',
                                    'badge-planned' => $variant === 'planned',
                                    'badge-status-live' => $variant === 'live',
                                    'badge-finished' => $variant === 'finished',
                                ])>
                                    {{ $tournament->status->label() }}
                                </span>
                            </div>
                            <p class="card-description mb-0">
                                @if($tournament->getPlayDateFormatted())
                                    Data rozgrywek: {{ $tournament->getPlayDateFormatted() }}
                                @else
                                    <span class="italic">Data rozgrywek: nie ustawiono</span>
                                @endif
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @canCreateLeagues
    <a href="{{ route('tournaments.create') }}" class="btn-fab">
        Stwórz nowy turniej
    </a>
    @endcanCreateLeagues

@endsection
