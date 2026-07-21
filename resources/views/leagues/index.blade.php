@extends('layouts.app')

@section('title', 'Ligi')

@section('content')

    <div class="max-w-6xl mx-auto px-4 pt-10 pb-28">
        @if($leagues->isEmpty())
            <x-empty-state
                title="Brak lig"
                description="Utwórz pierwszą ligę, aby organizować sezony i turnieje."
            />
        @else
            <div class="index-grid">
                @foreach($leagues as $index => $league)
                    <a href="{{ route('leagues.show', ['league' => $league->id]) }}"
                       class="block"
                       style="--stagger: {{ $index }}">
                        <div class="index-card">
                            <h3 class="text-lg font-semibold text-text leading-snug mb-3">
                                {{ $league->displayTitle() }}
                            </h3>
                            <p class="card-description mb-0">
                                {{ $league->getCardSubtitle() }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @canCreateLeagues
    <a href="{{ route('leagues.create') }}" class="btn-fab">
        Stwórz nową ligę
    </a>
    @endcanCreateLeagues

@endsection
