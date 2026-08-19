@extends('layouts.app')

@section('title', $game->player1->name.' vs '.$game->player2->name)

@section('content')
    @php
        $isAdmin = auth()->check() && $organization->admins->contains('id', auth()->id());
        $overdue = $game->status->value === 'scheduled'
            && $game->deadline_at
            && $game->deadline_at->isPast();
    @endphp

    <div class="container mx-auto py-6 sm:py-8 max-w-4xl text-text">
        <a href="{{ route('league-seasons.show', $season) }}" class="link-back mb-4 inline-block">← Powrót do sezonu</a>

        <h1 class="page-title mb-6">
            {{ $game->player1->name }}
            <span class="text-text-muted font-normal">vs</span>
            {{ $game->player2->name }}
        </h1>

        <div class="bg-bg-elevated rounded-lg p-6 mb-8 border border-border text-center">
            <p class="text-text-muted text-sm mb-1">{{ $format->formatLabel() }}</p>
            @if($game->matchday)
                <p class="text-text-muted text-sm mb-1">
                    Kolejka {{ $game->matchday->round_number }} · {{ $game->matchday->windowLabel() }}
                </p>
            @endif
            <p class="text-text-muted text-sm mb-2">Wynik meczu ({{ $format->scoreUnit() }})</p>
            @if($game->status->value === 'finished')
                <p class="text-4xl font-bold text-text score-num">
                    <span>{{ $game->player1_score }}</span>
                    <span class="text-text-muted mx-3">:</span>
                    <span>{{ $game->player2_score }}</span>
                </p>
                @if($game->walkover_type->value !== 'none')
                    <p class="text-text-muted text-sm mt-2">Walkower{{ $game->walkover_type->value === 'both' ? ' obustronny' : '' }}</p>
                @endif
            @elseif($game->status->value === 'voided')
                <p class="text-text-muted">Mecz anulowany (rezygnacja).</p>
            @elseif($game->status->value === 'lobby')
                <p class="text-warning">Lobby — oczekiwanie na akceptację przeciwnika.</p>
            @elseif($game->status->value === 'in_progress')
                <p class="text-success-bright">Mecz w trakcie (sędziowanie na telefonie).</p>
            @else
                <p class="text-text-muted">Nie rozegrany@if($overdue) · zaległy@endif</p>
                @if($game->deadline_at)
                    <p class="text-text-muted text-xs mt-1">Termin: {{ $game->deadline_at->format('Y-m-d') }}</p>
                @endif
            @endif
        </div>

        @if($canManage && $isAdmin && $game->status->value !== 'voided')
            <div class="bg-bg-deep rounded-lg p-6 mb-8 border border-accent/40">
                <h2 class="text-lg font-semibold text-accent mb-1">Wynik / walkower</h2>
                <p class="text-text-muted text-sm mb-4">
                    Format: {{ $format->formatLabel() }}. Wpisz wynik w {{ $format->scoreUnit() }} (do {{ $format->scoreToWin() }}).
                </p>

                <form method="POST" action="{{ route('league-games.result', $game) }}" class="space-y-4 mb-8">
                    @csrf
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="form-label">{{ $game->player1->name }}</span>
                            <input class="input-field" type="number" min="0" max="15" name="player1_score" value="{{ old('player1_score', $game->player1_score) }}" required>
                        </label>
                        <label class="block">
                            <span class="form-label">{{ $game->player2->name }}</span>
                            <input class="input-field" type="number" min="0" max="15" name="player2_score" value="{{ old('player2_score', $game->player2_score) }}" required>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Zapisz wynik</button>
                </form>

                <form method="POST" action="{{ route('league-games.walkover', $game) }}" class="space-y-3 mb-8">
                    @csrf
                    <p class="form-label text-accent">Walkower</p>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="walkover_type" value="single" checked>
                        Jednostronny — zwycięzca:
                    </label>
                    <select class="input-field" name="winner_id">
                        <option value="{{ $game->player1_id }}">{{ $game->player1->name }}</option>
                        <option value="{{ $game->player2_id }}">{{ $game->player2->name }}</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="walkover_type" value="both">
                        Obustronny (0:0, obie porażki)
                    </label>
                    <button type="submit" class="btn btn-secondary">Zapisz walkower</button>
                </form>

                <form method="POST" action="{{ route('league-games.extend', $game) }}" class="space-y-3">
                    @csrf
                    <label class="block">
                        <span class="form-label">Przedłuż termin</span>
                        <input class="input-field" type="date" name="deadline_at" value="{{ old('deadline_at', optional($game->deadline_at)->format('Y-m-d')) }}" required>
                    </label>
                    <button type="submit" class="btn btn-secondary">Przedłuż</button>
                </form>
            </div>
        @endif

        <x-errors/>
    </div>
@endsection
