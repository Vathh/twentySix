@extends('layouts.app')

@section('title', 'Nowy sezon ligowy')

@section('content')
    <div class="flex justify-center items-center min-h-[70vh] px-4">
        <form class="form-card" method="POST" action="{{ route('league-seasons.store', $league) }}"
              x-data="{
                  mode: '{{ old('calendar_mode', 'matchdays') }}',
                  planning: '{{ old('matchday_planning', 'fixed_length') }}',
                  allowsDraws: '{{ old('allows_draws') ? '1' : '0' }}',
                  deadlineAt: '{{ old('deadline_at') }}'
              }">
            @csrf
            <h1 class="page-title text-center">Nowy sezon ligowy</h1>
            <p class="text-text-muted text-sm text-center mb-6">{{ $league->name }}</p>

            <label class="form-label text-accent" for="seasonName">Nazwa sezonu</label>
            <input class="mb-5 input-field" type="text" id="seasonName" name="seasonName" value="{{ old('seasonName') }}" required maxlength="80">

            <label class="form-label text-accent" for="calendar_mode">Kalendarz</label>
            <select class="mb-5 input-field" id="calendar_mode" name="calendar_mode" x-model="mode">
                @foreach($calendarModes as $calendarMode)
                    <option value="{{ $calendarMode->value }}">{{ $calendarMode->label() }}</option>
                @endforeach
            </select>

            <div x-show="mode === 'matchdays'" x-cloak>
                <p class="form-label text-accent">Jak ustawić kolejki</p>
                <label class="flex items-start gap-2 text-sm mb-2">
                    <input type="radio" name="matchday_planning" value="fixed_length" x-model="planning">
                    <span>
                        <strong>Długość kolejki</strong> — podajesz start i ile trwa jedna kolejka.
                        Datę końca sezonu wyliczymy po starcie (ze składu).
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm mb-5">
                    <input type="radio" name="matchday_planning" value="equal_span" x-model="planning">
                    <span>
                        <strong>Ramy sezonu</strong> — podajesz start i koniec.
                        Długość jednej kolejki wyjdzie z równego podziału tego okresu.
                    </span>
                </label>

                <div x-show="planning === 'fixed_length'" x-cloak>
                    <label class="form-label text-accent" for="matchday_length_days">Długość jednej kolejki</label>
                    <select class="mb-2 input-field" id="matchday_length_days" name="matchday_length_days">
                        @foreach($matchdayLengthOptions as $days => $label)
                            <option value="{{ $days }}" @selected((int) old('matchday_length_days', 7) === $days)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-text-muted text-sm mb-5">
                        W tym oknie zawodnicy mają rozegrać mecze danej kolejki.
                        Liczba kolejek wynika ze składu (np. 8 osób, bez rewanżu = 7 kolejek).
                    </p>
                </div>
            </div>

            <div x-show="mode === 'deadline'" x-cloak>
                <label class="form-label text-accent" for="deadline_at">Termin wszystkich meczów</label>
                <input class="mb-2 input-field" type="date" id="deadline_at" name="deadline_at"
                       x-model="deadlineAt"
                       :required="mode === 'deadline'"
                       value="{{ old('deadline_at') }}">
                <p class="text-text-muted text-sm mb-5">
                    Do tego dnia wszystkie mecze sezonu zasadniczego mają być rozegrane.
                    To będzie też data zakończenia sezonu.
                </p>
            </div>

            <label class="form-label text-accent" for="rounds_each">Spotkania każdy z każdym</label>
            <select class="mb-5 input-field" id="rounds_each" name="rounds_each">
                <option value="1" @selected(old('rounds_each', '1') === '1')>1 (bez rewanżu)</option>
                <option value="2" @selected(old('rounds_each') === '2')>2 (z rewanżem)</option>
            </select>

            <p class="form-label text-accent">Długość meczu (legi, jeden set)</p>
            <label class="flex items-start gap-2 text-sm mb-2">
                <input type="radio" name="allows_draws" value="0" x-model="allowsDraws">
                <span>
                    <strong>Bez remisów</strong> — First to N. Zawsze jest zwycięzca. Tabela: W / P.
                </span>
            </label>
            <label class="flex items-start gap-2 text-sm mb-4">
                <input type="radio" name="allows_draws" value="1" x-model="allowsDraws">
                <span>
                    <strong>Z remisami</strong> — Best of (parzyste). Wynik w stylu 3:3 to po 1 pkt. Tabela: W / R / P i punkty 2/1/0.
                </span>
            </label>

            <div x-show="allowsDraws !== '1'">
                <label class="form-label text-accent" for="win_length_ft">First to (legi)</label>
                <select class="mb-5 input-field" id="win_length_ft" name="win_length" :disabled="allowsDraws === '1'">
                    @foreach(range(1, 15) as $n)
                        <option value="{{ $n }}" @selected((int) old('win_length', 2) === $n)>do {{ $n }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="allowsDraws === '1'" x-cloak>
                <label class="form-label text-accent" for="win_length_bo">Best of (legi)</label>
                <select class="mb-5 input-field" id="win_length_bo" name="win_length" :disabled="allowsDraws !== '1'">
                    @foreach([2, 4, 6, 8, 10, 12, 14, 16] as $n)
                        <option value="{{ $n }}" @selected((int) old('win_length', 6) === $n)>best of {{ $n }}</option>
                    @endforeach
                </select>
            </div>

            <label class="form-label text-accent" for="startDate">Data rozpoczęcia</label>
            <input class="mb-5 input-field" type="date" id="startDate" name="startDate" value="{{ old('startDate') }}" required>

            <div x-show="mode === 'matchdays' && planning === 'equal_span'" x-cloak>
                <label class="form-label text-accent" for="endDate">Data zakończenia</label>
                <input class="mb-5 input-field" type="date" id="endDate" name="endDate" value="{{ old('endDate') }}"
                       :required="mode === 'matchdays' && planning === 'equal_span'">
            </div>

            <div x-show="mode === 'deadline'" x-cloak>
                <p class="form-label text-accent">Data zakończenia</p>
                <p class="text-text mb-5" x-show="deadlineAt" x-cloak>
                    <span x-text="deadlineAt"></span>
                    <span class="text-text-muted text-sm"> — jak termin wszystkich meczów</span>
                </p>
                <p class="text-text-muted text-sm mb-5" x-show="!deadlineAt" x-cloak>
                    Pojawi się tu data z pola „Termin wszystkich meczów”.
                </p>
            </div>

            <p class="text-text-muted text-sm mb-5" x-show="mode === 'matchdays' && planning === 'fixed_length'" x-cloak>
                Koniec sezonu zasadniczego ustawimy automatycznie: start + (liczba kolejek × długość kolejki).
            </p>

            <label class="flex items-center gap-2 mb-6 text-sm">
                <input type="checkbox" name="start_now" value="1" @checked(old('start_now'))>
                Wystartuj od razu (zamrożenie składu i generacja meczów)
            </label>

            <button class="btn btn-primary w-full" type="submit">Zapisz sezon</button>
            <a href="{{ route('leagues.show', $league) }}" class="btn btn-secondary mt-4 text-center">Powrót</a>
            <x-errors/>
        </form>
    </div>
@endsection
