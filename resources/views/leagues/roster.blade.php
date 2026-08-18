@extends('layouts.app')

@section('title', 'Skład ligi')

@section('content')
    <div class="container mx-auto py-6 max-w-4xl px-4">
        <a href="{{ route('leagues.show', $league) }}" class="link-back mb-4 inline-block">← {{ $league->name }}</a>
        <h1 class="page-title">Skład szczebli</h1>
        <p class="text-text-muted mb-6">
            Gracze z powiązanych użytkowników i gości organizacji. Jedna osoba — jeden szczebel.
            @if($locked)
                <strong class="text-accent">Sezon w toku — skład zamrożony.</strong>
            @endif
        </p>

        @foreach($divisions as $division)
            <div class="card mb-6">
                <h2 class="section-title text-accent">{{ $division->name }}
                    <span class="text-text-muted text-base font-normal">({{ $division->members->count() }}/{{ $division->capacity }})</span>
                </h2>
                @if($division->members->isEmpty())
                    <p class="text-text-secondary">Brak zawodników.</p>
                @else
                    <div class="flex flex-wrap gap-3">
                        @foreach($division->members as $member)
                            <div class="tile flex items-center justify-center flex-col">
                                <span class="card-title mb-4 text-center">{{ $member->player?->name }}</span>
                                @if(! $locked)
                                    <form method="POST" action="{{ route('leagues.roster.remove', $league) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="player_id" value="{{ $member->player_id }}">
                                        <button type="submit" class="btn-mini-danger">Usuń</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        @if(! $locked)
            <div class="card">
                <h2 class="section-title text-accent">Dodaj zawodnika</h2>
                @if($availablePlayers->isEmpty())
                    <p class="text-text-secondary">Brak wolnych graczy. Dodaj powiązanych użytkowników albo gości w organizacji.</p>
                @else
                    <form method="POST" action="{{ route('leagues.roster.assign', $league) }}" class="grid sm:grid-cols-3 gap-3 items-end">
                        @csrf
                        <label class="block">
                            <span class="form-label">Szczebel</span>
                            <select class="input-field" name="division_id" required>
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}">{{ $division->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="form-label">Gracz</span>
                            <select class="input-field" name="player_id" required>
                                @foreach($availablePlayers as $player)
                                    <option value="{{ $player->id }}">{{ $player->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="btn btn-primary">Dodaj</button>
                    </form>
                @endif
            </div>
        @endif

        <x-errors/>
    </div>
@endsection
