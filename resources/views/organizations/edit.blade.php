@extends('layouts.app')

@section('title')
    Edycja organizacji {{ $organization->name }}
@endsection

@section('content')

    <div class="flex justify-center items-start min-h-[70vh] px-4 py-8">
        <form class="form-card w-full max-w-3xl"
              action="{{ route('organizations.update', $organization->id) }}"
              method="POST">
            @csrf
            @method('PUT')

            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Edycja organizacji</h1>

                <label class="form-label text-accent" for="organizationName">Nazwa organizacji</label>
                <input
                    class="mb-5 input-field"
                    type="text"
                    id="organizationName"
                    name="organizationName"
                    value="{{ old('organizationName', $organization->name) }}"
                    required
                >

                <label class="form-label text-accent" for="description">Opis organizacji</label>
                <textarea
                    class="input-field mb-2 h-32 resize-none"
                    id="description"
                    name="description"
                    maxlength="500"
                    oninput="updateCounter()"
                >{{ old('description', $organization->description) }}</textarea>

                <div class="text-accent text-sm text-right mb-6">
                    <span id="charCount">0</span>/500
                </div>

                <div class="mb-6 rounded-lg border border-border bg-bg/40 p-4">
                    <p class="text-accent font-semibold text-sm mb-1">Presety formatu gry</p>
                    <p class="text-text-secondary/70 text-xs mb-4">
                        Te wartości wczytają się jako domyślne przy starcie każdego turnieju w tej organizacji.
                        Przy starcie turnieju nadal możesz je nadpisać.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-text-secondary">
                            <thead class="text-accent">
                                <tr class="border-b border-border">
                                    <th class="text-left py-2 pr-3 font-semibold">Etap</th>
                                    <th class="text-left py-2 px-2 font-semibold">Punkty</th>
                                    <th class="text-left py-2 px-2 font-semibold">Legi / set</th>
                                    <th class="text-left py-2 pl-2 font-semibold">Sety / mecz</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($matchFormatStages as $stage)
                                    @php
                                        $format = $matchFormats[$stage['value']] ?? [
                                            'startingScore' => 501,
                                            'legsToWinSet' => 2,
                                            'setsToWinMatch' => 1,
                                        ];
                                    @endphp
                                    <tr class="border-b border-border/60 last:border-0">
                                        <td class="py-2 pr-3 whitespace-nowrap">{{ $stage['label'] }}</td>
                                        <td class="py-2 px-2">
                                            <select
                                                class="select-field w-full min-w-[5rem]"
                                                name="matchFormats[{{ $stage['value'] }}][startingScore]"
                                            >
                                                @foreach($startingScoreOptions as $score)
                                                    <option
                                                        value="{{ $score }}"
                                                        @selected((int) ($format['startingScore'] ?? 501) === (int) $score)
                                                    >{{ $score }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-2 px-2">
                                            <select
                                                class="select-field w-full min-w-[4rem]"
                                                name="matchFormats[{{ $stage['value'] }}][legsToWinSet]"
                                            >
                                                @for($n = 1; $n <= 15; $n++)
                                                    <option
                                                        value="{{ $n }}"
                                                        @selected((int) ($format['legsToWinSet'] ?? 2) === $n)
                                                    >{{ $n }}</option>
                                                @endfor
                                            </select>
                                        </td>
                                        <td class="py-2 pl-2">
                                            <select
                                                class="select-field w-full min-w-[4rem]"
                                                name="matchFormats[{{ $stage['value'] }}][setsToWinMatch]"
                                            >
                                                @for($n = 1; $n <= 5; $n++)
                                                    <option
                                                        value="{{ $n }}"
                                                        @selected((int) ($format['setsToWinMatch'] ?? 1) === $n)
                                                    >{{ $n }}</option>
                                                @endfor
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Zapisz zmiany</button>
                <a href="{{ route('organizations.show', $organization->id) }}" class="btn btn-secondary mt-4 text-center">Powrót</a>

                <x-errors/>
            </div>
        </form>
    </div>

@endsection

@section('scripts')
    <script>
        function updateCounter() {
            const textarea = document.getElementById('description');
            const counter = document.getElementById('charCount');
            counter.textContent = textarea.value.length;
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateCounter();
        });
    </script>
@endsection
