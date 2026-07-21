@extends('layouts.app')

@section('title', 'Sezony')

@section('content')

    <div class="max-w-6xl mx-auto px-4 pt-10 pb-28">
        @if($seasons->isEmpty())
            <x-empty-state
                title="Brak sezonów"
                description="Sezony pojawią się po utworzeniu ich w ligach."
            />
        @else
            <div class="index-grid">
                @foreach($seasons as $index => $season)
                    <a href="{{ route('seasons.show', ['season' => $season->id]) }}"
                       class="block"
                       style="--stagger: {{ $index }}">
                        <div class="index-card">
                            <h3 class="text-lg font-semibold text-text leading-snug mb-3">
                                {{ $season->displayTitle() }}
                            </h3>
                            <p class="card-description mb-0">
                                @if($season->getPlayDatesFormatted())
                                    Data rozgrywek: {{ $season->getPlayDatesFormatted() }}
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
    <a href="{{ route('seasons.create') }}" class="btn-fab">
        Stwórz nowy sezon
    </a>
    @endcanCreateLeagues

@endsection
