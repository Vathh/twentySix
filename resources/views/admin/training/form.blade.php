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
            ])->all()
            : [];
    }
    $exerciseOptions = collect(\App\Domain\Training\TrainingExerciseCatalog::all())
        ->map(fn ($limits, $key) => [
            'key' => $key,
            'name' => \App\Domain\Training\TrainingExerciseCatalog::label($key),
            'durationSeconds' => $limits['durationSeconds'],
            'dartCount' => $limits['dartCount'],
        ])->values();
    $allowsBreaks = $kind === \App\Domain\Training\TrainingKind::Plan;
    $allowsLimits = $allowsBreaks;
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
            <label class="block">
                <span class="form-label">Mechanika</span>
                <select class="select-field" name="mechanic">
                    <option value="" @selected(! is_string($selectedMechanic) || $selectedMechanic === '')>Bez mechaniki, tylko ćwiczenia</option>
                    @foreach($challengeMechanics as $value => $label)
                        <option value="{{ $value }}" @selected($selectedMechanic === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <p class="text-text-muted text-xs">Wyzwanie ma mechanikę albo listę ćwiczeń. Czas i liczbę lotek bierze z ćwiczenia.</p>
        @endif

            <div
                class="space-y-3"
                x-data="trainingItems({{ \Illuminate\Support\Js::from($itemRows) }}, {{ \Illuminate\Support\Js::from($exerciseOptions) }}, {{ $allowsBreaks ? 'true' : 'false' }}, {{ $allowsLimits ? 'true' : 'false' }})"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="form-label mb-0">Elementy</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary" @click="addExercise()">Dodaj ćwiczenie</button>
                        <button type="button" class="btn btn-secondary" x-show="allowsBreaks" @click="addBreak()">Dodaj przerwę</button>
                    </div>
                </div>
                <p class="text-text-muted text-xs" x-show="allowsLimits">Przy ćwiczeniu zmieniasz tylko jego czas albo liczbę lotek. Puste pole po wyborze dostaje wartość domyślną.</p>
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
                        @if($allowsLimits)
                            <div class="w-40" x-show="row.kind === 'exercise' && usesDuration(row)">
                                <span class="form-label">Czas (sekundy)</span>
                                <input class="input-field" type="number" min="1" max="14400" :name="`items[${index}][duration_seconds]`" x-model="row.duration_seconds">
                            </div>
                            <div class="w-40" x-show="row.kind === 'exercise' && usesDarts(row)">
                                <span class="form-label">Liczba lotek</span>
                                <input class="input-field" type="number" min="1" max="500" :name="`items[${index}][dart_count]`" x-model="row.dart_count">
                            </div>
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
        const rows = (Array.isArray(initial) ? initial : []).map((row) => ({
            kind: row.kind === 'break' ? 'break' : 'exercise',
            exercise_key: String(row.exercise_key ?? row.exerciseKey ?? ''),
            break_seconds: row.break_seconds ?? row.breakSeconds ?? '',
            duration_seconds: row.duration_seconds ?? row.durationSeconds ?? '',
            dart_count: row.dart_count ?? row.dartCount ?? '',
        }));

        return {
            rows,
            exercises,
            allowsBreaks,
            allowsLimits,
            exerciseByKey(key) {
                return this.exercises.find((exercise) => exercise.key === key) ?? null;
            },
            exerciseLabel(exercise) {
                if (exercise.durationSeconds) {
                    return exercise.name + ' · ' + exercise.durationSeconds + ' s';
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
            applyExercise(row) {
                const exercise = this.exerciseByKey(row.exercise_key);
                if (!exercise) {
                    row.duration_seconds = '';
                    row.dart_count = '';
                    return;
                }
                if (exercise.durationSeconds) {
                    row.duration_seconds = String(exercise.durationSeconds);
                    row.dart_count = '';
                    return;
                }
                row.duration_seconds = '';
                row.dart_count = String(exercise.dartCount ?? '');
            },
            addExercise() {
                this.rows.push({
                    kind: 'exercise',
                    exercise_key: '',
                    break_seconds: '',
                    duration_seconds: '',
                    dart_count: '',
                });
            },
            addBreak() {
                this.rows.push({
                    kind: 'break',
                    exercise_key: '',
                    break_seconds: '',
                    duration_seconds: '',
                    dart_count: '',
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
    }
</script>
@endsection
