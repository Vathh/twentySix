@extends('layouts.app')

@section('title', ($definition ? 'Edycja' : 'Nowa treść').' — trening')

@section('content')
@php
    $isEdit = $definition !== null;
    $action = $isEdit
        ? route('admin.training.update', $definition)
        : route('admin.training.store', $kind->slug());
    $selectedDifficulty = old('difficulty', $definition?->difficulty?->value);
    $selectedMechanic = old('mechanic', $definition?->mechanic);
    $selectedMinutes = old('declared_minutes', $definition?->declared_minutes);
    $itemRows = old('items');
    if (! is_array($itemRows)) {
        $itemRows = $definition
            ? $definition->items->map(fn ($item) => [
                'kind' => $item->item_kind->value,
                'exercise_key' => $item->exercise_key,
                'break_seconds' => $item->break_seconds,
                'duration_seconds' => $item->duration_seconds,
                'dart_count' => $item->dart_count,
                'scored' => (bool) $item->scored,
                'variant' => $item->variant,
            ])->all()
            : [];
    }
    $exerciseOptions = collect(\App\Domain\Training\TrainingExerciseCatalog::all())
        ->map(fn ($limits, $key) => [
            'key' => $key,
            'name' => \App\Domain\Training\TrainingExerciseCatalog::label($key),
            'durationSeconds' => $limits['durationSeconds'],
            'dartCount' => $limits['dartCount'],
            'dartCountOptions' => \App\Domain\Training\TrainingExerciseLimits::dartCountSelectOptions($key),
        ])->values();
    $allowsBreaks = $kind === \App\Domain\Training\TrainingKind::Plan;
    $allowsLimits = $kind === \App\Domain\Training\TrainingKind::Plan;
    $allowsEventItems = $kind === \App\Domain\Training\TrainingKind::Event;
    $allowsItemEditor = $allowsBreaks || $allowsEventItems;
@endphp
<div class="max-w-3xl mx-auto px-4 pt-6 pb-12">
    <a href="{{ $isEdit ? route('admin.training.show', $definition) : route('admin.training.list', $kind->slug()) }}" class="text-text-secondary text-sm hover:text-accent">← Wróć</a>
    <h1 class="text-xl sm:text-2xl font-semibold text-accent mt-1 mb-6">
        {{ $isEdit ? 'Edycja: '.$definition->name : 'Nowe: '.$kind->singular() }}
    </h1>

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="card p-5 space-y-4">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <label class="block">
            <span class="form-label">Nazwa</span>
            <input class="input-field" type="text" name="name" value="{{ old('name', $definition->name ?? '') }}" maxlength="120" required>
        </label>

        <label class="block">
            <span class="form-label">Opis</span>
            <textarea class="input-field h-32 resize-y" name="description" maxlength="5000">{{ old('description', $definition->description ?? '') }}</textarea>
        </label>

        <label class="block">
            <span class="form-label">Grafika</span>
            <input class="input-field" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <span class="text-text-muted text-xs">JPEG, PNG albo WebP, do 2 MB. Bez pliku zostają inicjały nazwy.</span>
        </label>
        @if($isEdit && $definition->imageUrl())
            <label class="flex items-center gap-2 text-sm text-text">
                <input type="checkbox" name="remove_image" value="1" @checked(old('remove_image'))>
                <span>Usuń grafikę</span>
            </label>
        @endif

        @if($kind === \App\Domain\Training\TrainingKind::Plan)
            <label class="block">
                <span class="form-label">Poziom trudności</span>
                <select class="select-field" name="difficulty" required>
                    <option value="" disabled @selected($selectedDifficulty === null || $selectedDifficulty === '')>Wybierz</option>
                    @foreach(\App\Domain\Training\TrainingDifficulty::cases() as $level)
                        <option value="{{ $level->value }}" @selected($selectedDifficulty === $level->value)>{{ $level->label() }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if($kind === \App\Domain\Training\TrainingKind::Plan)
            <label class="block">
                <span class="form-label">Deklarowany czas</span>
                <select class="select-field" name="declared_minutes" required>
                    <option value="" disabled @selected($selectedMinutes === null || $selectedMinutes === '')>Wybierz</option>
                    @foreach([30, 60, 90, 120] as $minutes)
                        <option value="{{ $minutes }}" @selected((string) $selectedMinutes === (string) $minutes)>{{ $minutes }} min</option>
                    @endforeach
                </select>
            </label>
            <p class="text-text-muted text-xs">Deklarowany czas wybierasz z listy. Nie jest liczony z elementów.</p>
        @endif

        @if($kind === \App\Domain\Training\TrainingKind::Challenge)
            @php
                $thresholds = $definition?->badge_thresholds ?? [];
                if (! is_array($thresholds)) {
                    $thresholds = [];
                }
                $isCatalogChallenge = is_string($selectedMechanic)
                    && \App\Domain\Training\TrainingChallengeCatalog::exists($selectedMechanic);
            @endphp
            @if($isCatalogChallenge)
                <input type="hidden" name="mechanic" value="{{ $selectedMechanic }}">
                <p class="text-text-muted text-sm">
                    Gotowe wyzwanie:
                    <span class="text-text font-medium">{{ \App\Domain\Training\TrainingChallengeCatalog::name($selectedMechanic) }}</span>.
                    Nazwy i zasad nie zmieniasz — tylko progi odznaki przed pierwszą aktywacją.
                </p>
            @endif
            <div class="space-y-2">
                <span class="form-label">Progi odznaki (wynik)</span>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach([
                        'iron' => 'Żelazo',
                        'bronze' => 'Brąz',
                        'gold' => 'Złoto',
                        'platinum' => 'Platyna',
                        'diamond' => 'Diament',
                    ] as $level => $label)
                        <label class="block">
                            <span class="form-label">{{ $label }}</span>
                            <input
                                class="input-field"
                                type="number"
                                min="0"
                                max="3190"
                                name="badge_{{ $level }}"
                                value="{{ old('badge_'.$level, $thresholds[$level] ?? '') }}"
                                required
                            >
                        </label>
                    @endforeach
                </div>
                <p class="text-text-muted text-xs">Pięć rosnących progów. Odznaki nalicza numer 13.</p>
            </div>
        @endif

        @if($kind === \App\Domain\Training\TrainingKind::Event)
            @php
                $startsAt = old('starts_at', optional($definition?->starts_at)->format('Y-m-d\TH:i'));
                $endsAt = old('ends_at', optional($definition?->ends_at)->format('Y-m-d\TH:i'));
                $windowMode = old('window_mode', $definition?->window_mode ?? 'calendar_week');
                $attemptsPerWindow = old('attempts_per_window', $definition?->attempts_per_window ?? 1);
                $windowAnchor = old('window_anchor_date', optional($definition?->window_anchor_date)->format('Y-m-d'));
                $timeoutHours = old('attempt_timeout_hours', $definition?->attempt_timeout_hours ?? 4);
            @endphp
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="form-label">Start</span>
                    <input class="input-field" type="datetime-local" name="starts_at" value="{{ $startsAt }}" required>
                </label>
                <label class="block">
                    <span class="form-label">Koniec</span>
                    <input class="input-field" type="datetime-local" name="ends_at" value="{{ $endsAt }}" required>
                </label>
            </div>
            <label class="block">
                <span class="form-label">Prób w oknie</span>
                <input class="input-field" type="number" min="1" max="20" name="attempts_per_window" value="{{ $attemptsPerWindow }}" required>
            </label>
            <label class="block">
                <span class="form-label">Tryb okna</span>
                <select class="select-field" name="window_mode" required>
                    @foreach(\App\Domain\Training\TrainingEventWindow::modes() as $mode)
                        <option value="{{ $mode }}" @selected($windowMode === $mode)>{{ \App\Domain\Training\TrainingEventWindow::label($mode) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="form-label">Data kotwicy (gdy tryb od wskazanej daty)</span>
                <input class="input-field" type="date" name="window_anchor_date" value="{{ $windowAnchor }}">
            </label>
            <label class="block">
                <span class="form-label">Limit otwartej próby (godziny)</span>
                <input class="input-field" type="number" min="1" max="72" name="attempt_timeout_hours" value="{{ $timeoutHours }}" required>
                <span class="text-text-muted text-xs">Po tym czasie niedokończona próba wygasa i zużywa slot. Crash/sieć nie voidują — gracz wznawia tę samą próbę.</span>
            </label>
        @endif

            <div
                class="space-y-3"
                x-show="{{ $allowsItemEditor ? 'true' : 'false' }}"
                x-data="trainingItems({{ \Illuminate\Support\Js::from($itemRows) }}, {{ \Illuminate\Support\Js::from($exerciseOptions) }}, {{ $allowsBreaks ? 'true' : 'false' }}, {{ ($allowsLimits || $allowsEventItems) ? 'true' : 'false' }})"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="form-label mb-0">Elementy</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary" @click="addExercise()">Dodaj ćwiczenie</button>
                        <button type="button" class="btn btn-secondary" x-show="allowsBreaks" @click="addBreak()">Dodaj przerwę</button>
                    </div>
                </div>
                <p class="text-text-muted text-xs" x-show="allowsLimits">Przy ćwiczeniu zmieniasz tylko czas albo liczbę lotek (select), checkbox Punktowane i — przy Scoringu z presją — wariant. Dla 9/18 lotek wybór 9 albo 18 oznacza lotki na etap. Puste pole czasu dostaje wartość domyślną.</p>
                <template x-for="(row, index) in rows" :key="index">
                    <div class="flex flex-wrap items-end gap-2 border border-border/60 rounded-lg p-3">
                        <input type="hidden" :name="`items[${index}][kind]`" :value="row.kind">
                        <div class="flex-1 min-w-[12rem]" x-show="row.kind === 'exercise'">
                            <span class="form-label">Ćwiczenie</span>
                            <select class="select-field" :name="`items[${index}][exercise_key]`" x-model="row.exercise_key" @change="applyExercise(row)">
                                <option value="">Wybierz</option>
                                <template x-for="exercise in exercises" :key="exercise.key">
                                    <option :value="exercise.key" x-text="exerciseLabel(exercise)"></option>
                                </template>
                            </select>
                        </div>
                        @if($allowsLimits || $allowsEventItems)
                            @if($allowsLimits)
                                <div class="w-40" x-show="row.kind === 'exercise' && usesDuration(row)">
                                    <span class="form-label">Czas (sekundy)</span>
                                    <input class="input-field" type="number" min="1" max="14400" :name="`items[${index}][duration_seconds]`" x-model="row.duration_seconds">
                                </div>
                                <div class="w-40" x-show="row.kind === 'exercise' && usesDarts(row)">
                                    <span class="form-label">Liczba lotek</span>
                                    <select class="select-field" :name="`items[${index}][dart_count]`" x-model="row.dart_count">
                                        <template x-for="option in dartCountOptions(row)" :key="option.value">
                                            <option :value="String(option.value)" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </div>
                            @endif
                            <div class="w-40" x-show="row.kind === 'exercise' && usesVariant(row)">
                                <span class="form-label">Wariant</span>
                                <select class="select-field" :name="`items[${index}][variant]`" x-model="row.variant">
                                    <template x-for="option in pressureVariants" :key="option">
                                        <option :value="option" x-text="option"></option>
                                    </template>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-text pb-2" x-show="row.kind === 'exercise'">
                                <input type="hidden" :name="`items[${index}][scored]`" :value="row.scored ? 1 : 0">
                                <input type="checkbox" x-model="row.scored">
                                <span>Punktowane</span>
                            </label>
                        @endif
                        <div class="flex-1 min-w-[8rem]" x-show="row.kind === 'break'">
                            <span class="form-label">Przerwa (sekundy)</span>
                            <input class="input-field" type="number" min="1" max="7200" :name="`items[${index}][break_seconds]`" x-model="row.break_seconds">
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-secondary" @click="move(index, -1)">W górę</button>
                            <button type="button" class="btn btn-secondary" @click="move(index, 1)">W dół</button>
                            <button type="button" class="btn btn-secondary" @click="remove(index)">Usuń</button>
                        </div>
                    </div>
                </template>
            </div>

        <x-errors/>

        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="btn btn-primary">Zapisz</button>
            <a href="{{ $isEdit ? route('admin.training.show', $definition) : route('admin.training.list', $kind->slug()) }}" class="btn btn-secondary">Anuluj</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    function trainingItems(initial, exercises, allowsBreaks, allowsLimits) {
        const pressureVariants = ['20', '19', '18', '20/19/18'];
        const rows = (Array.isArray(initial) ? initial : []).map((row) => ({
            kind: row.kind === 'break' ? 'break' : 'exercise',
            exercise_key: String(row.exercise_key ?? row.exerciseKey ?? ''),
            break_seconds: row.break_seconds ?? row.breakSeconds ?? '',
            duration_seconds: row.duration_seconds ?? row.durationSeconds ?? '',
            dart_count: row.dart_count ?? row.dartCount ?? '',
            scored: row.scored === false || row.scored === 0 || row.scored === '0' ? false : true,
            variant: row.variant && pressureVariants.includes(String(row.variant))
                ? String(row.variant)
                : '20',
        }));

        const state = {
            rows,
            exercises,
            allowsBreaks,
            allowsLimits,
            pressureVariants,
            exerciseByKey(key) {
                return this.exercises.find((exercise) => exercise.key === key) ?? null;
            },
            exerciseLabel(exercise) {
                if (exercise.durationSeconds) {
                    return exercise.name + ' · ' + exercise.durationSeconds + ' s';
                }
                const options = Array.isArray(exercise.dartCountOptions) ? exercise.dartCountOptions : [];
                const match = options.find((option) => Number(option.value) === Number(exercise.dartCount));
                if (match) {
                    return exercise.name + ' · ' + match.label;
                }
                return exercise.name + ' · ' + exercise.dartCount + ' lotek';
            },
            usesDuration(row) {
                const exercise = this.exerciseByKey(row.exercise_key);
                return Boolean(exercise && exercise.durationSeconds);
            },
            usesDarts(row) {
                const exercise = this.exerciseByKey(row.exercise_key);
                return Boolean(exercise && exercise.dartCount);
            },
            dartCountOptions(row) {
                const exercise = this.exerciseByKey(row.exercise_key);
                if (!exercise || !Array.isArray(exercise.dartCountOptions) || exercise.dartCountOptions.length === 0) {
                    return exercise?.dartCount
                        ? [{ value: Number(exercise.dartCount), label: exercise.dartCount + ' lotek' }]
                        : [];
                }
                return exercise.dartCountOptions;
            },
            normalizeDartCount(row) {
                const options = this.dartCountOptions(row);
                if (options.length === 0) {
                    row.dart_count = '';
                    return;
                }
                const current = Number(row.dart_count);
                if (options.some((option) => Number(option.value) === current)) {
                    row.dart_count = String(current);
                    return;
                }
                row.dart_count = String(options[0].value);
            },
            usesVariant(row) {
                return row.exercise_key === 'scoring_under_pressure';
            },
            applyExercise(row) {
                const exercise = this.exerciseByKey(row.exercise_key);
                if (!exercise) {
                    row.duration_seconds = '';
                    row.dart_count = '';
                    row.variant = '20';
                    return;
                }
                if (exercise.key === 'scoring_under_pressure' && !row.variant) {
                    row.variant = '20';
                }
                if (exercise.durationSeconds) {
                    row.duration_seconds = String(exercise.durationSeconds);
                    row.dart_count = '';
                    return;
                }
                row.duration_seconds = '';
                row.dart_count = String(exercise.dartCount ?? '');
                this.normalizeDartCount(row);
            },
            addExercise() {
                this.rows.push({
                    kind: 'exercise',
                    exercise_key: '',
                    break_seconds: '',
                    duration_seconds: '',
                    dart_count: '',
                    scored: true,
                    variant: '20',
                });
            },
            addBreak() {
                this.rows.push({
                    kind: 'break',
                    exercise_key: '',
                    break_seconds: '',
                    duration_seconds: '',
                    dart_count: '',
                    scored: true,
                    variant: '20',
                });
            },
            remove(index) {
                this.rows.splice(index, 1);
            },
            move(index, delta) {
                const target = index + delta;
                if (target < 0 || target >= this.rows.length) {
                    return;
                }
                const row = this.rows[index];
                this.rows.splice(index, 1);
                this.rows.splice(target, 0, row);
            },
        };

        state.rows.forEach((row) => {
            if (row.kind === 'exercise' && row.exercise_key) {
                state.normalizeDartCount(row);
            }
        });

        return state;
    }
</script>
@endsection
