@extends('layouts.app')

@section('title', 'Format rund')

@section('content')
    <div class="page-head">
        <div>
            <p class="text-text-secondary text-sm mb-1">
                <a href="{{ route('tournaments.show', $tournament->id) }}" class="hover:text-accent">{{ $tournament->name }}</a>
            </p>
            <h1 class="page-title">Format rund</h1>
        </div>
        <a href="{{ route('tournaments.show', $tournament->id) }}" class="btn btn-secondary">Wróć do turnieju</a>
    </div>

    <p class="text-text-secondary text-sm mb-6 max-w-2xl">
        Możesz skrócić lub wydłużyć szczebel, z którego żaden mecz jeszcze nie ruszył.
        Dotyczy to oczekujących meczów tej rundy. Trwająca runda zostaje bez zmian.
    </p>

    @foreach($sides as $block)
        <form method="POST" action="{{ route('tournaments.playoff-formats.update', $tournament->id) }}" class="card mb-6 max-w-2xl">
            @csrf
            <input type="hidden" name="bracketSide" value="{{ $block['side']->value }}">
            <h2 class="section-title text-accent mb-4">{{ $block['label'] }}</h2>
            @if($block['stages'] === [])
                <p class="text-text-secondary text-sm">Brak zapisanych szczebli.</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach($block['stages'] as $stage)
                        <div class="grid grid-cols-1 sm:grid-cols-[1.4fr_1fr_1fr] gap-2 items-end">
                            <div>
                                <p class="text-sm font-medium text-text">{{ $stage['label'] }}</p>
                                @if($stage['reason'])
                                    <p class="text-xs text-text-secondary">{{ $stage['reason'] }}</p>
                                @endif
                            </div>
                            <label class="block text-xs text-text-secondary">
                                Legi do wygranej
                                <input
                                    class="input-field mt-1"
                                    type="number"
                                    min="1"
                                    max="15"
                                    name="stages[{{ $stage['stage'] }}][legsToWinSet]"
                                    value="{{ $stage['legsToWinSet'] }}"
                                    @disabled(! $stage['editable'])
                                >
                            </label>
                            <label class="block text-xs text-text-secondary">
                                Sety do wygranej
                                <input
                                    class="input-field mt-1"
                                    type="number"
                                    min="1"
                                    max="5"
                                    name="stages[{{ $stage['stage'] }}][setsToWinMatch]"
                                    value="{{ $stage['setsToWinMatch'] }}"
                                    @disabled(! $stage['editable'])
                                >
                            </label>
                        </div>
                    @endforeach
                </div>
                <button class="btn btn-primary mt-4" type="submit">Zapisz {{ $block['label'] }}</button>
            @endif
        </form>
    @endforeach

    <x-errors/>
@endsection
