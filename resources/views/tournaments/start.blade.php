@extends('layouts.app')

@section('title', 'Start turnieju')

@section('content')
    <div class="container mx-auto py-4 sm:py-5 max-w-5xl min-w-0">

        <h1 class="page-title mb-4 break-words">
            Start turnieju: {{ $tournament->name }}
        </h1>

        <x-errors/>

        <div class="mb-8 card">
            <h2 class="section-title">Skład</h2>
            <p class="text-text-secondary text-sm mb-3">
                W puli startowej: {{ $participantCount }} / min. {{ $minPlayers }}.
                Zaproszenia i zgłoszenia są na stronie turnieju.
            </p>
            <a href="{{ route('tournaments.show', $tournament->id) }}" class="btn btn-secondary">
                Przejdź do składu
            </a>
        </div>

        {{-- Strefa 3: Start turnieju --}}
        @if($canManageParticipants)
            {{-- JSON poza atrybutem HTML — @json w x-data="..." psuje parser (cudzysłowy). --}}
            @php
                $tournamentStartConfig = [
                    'groupsCount' => $defaultGroupsCount,
                    'playoffBracketSize' => $defaultPlayoffBracketSize,
                    'tournamentFormat' => $defaultTournamentFormat,
                    'grandFinalMode' => (string) old('grandFinalMode', 'reset'),
                    'seBracketSize' => $seBracketSize,
                    'seByeCount' => $seByeCount,
                    'groupCountOptions' => $groupCountOptions,
                    'bracketOptionsByGroupCount' => $bracketOptionsByGroupCount,
                    'startConfigPreview' => $startConfigPreview,
                    'matchFormatStagesByBracket' => $matchFormatStagesByBracket,
                    'matchFormatStagesByBracketSe' => $matchFormatStagesByBracketSe,
                    'matchFormatStagesByBracketDe' => $matchFormatStagesByBracketDe,
                    'startingScoreOptions' => $startingScoreOptions,
                    'defaultMatchFormat' => $defaultMatchFormat,
                    'defaultMatchFormatsByStage' => $defaultMatchFormatsByStage,
                    'hasOrganizationFormatPresets' => $hasOrganizationFormatPresets,
                    'oldMatchFormats' => $oldMatchFormats,
                    'oldConsolationMatchFormats' => old('consolationMatchFormats', []),
                    'hasConsolationBracket' => (bool) old('hasConsolationBracket', false),
                    'hasConsolationThirdPlace' => (bool) old('hasConsolationThirdPlace', false),
                    'minPlayers' => $minPlayers,
                    'minPlayersPerGroup' => $minPlayersPerGroup,
                    'participantCount' => $participantCount,
                ];
            @endphp
            <script type="application/json" id="tournament-start-config">
                @json($tournamentStartConfig)
            </script>
            <div
                x-data="tournamentStartForm()"
                x-init="init(); syncGroupsCount(); syncBracketSelect(); syncMatchFormats()"
                class="mb-8 card"
            >
                <h2 class="section-title text-accent">Start turnieju</h2>

                <p
                    class="text-text-secondary text-sm"
                    x-show="participantCount === 0"
                    x-cloak
                >Wróć do składu turnieju, aby dodać uczestników.</p>
                <p
                    class="text-text-secondary text-sm"
                    x-show="participantCount > 0 && participantCount < minPlayers"
                    x-cloak
                >
                    Potrzeba co najmniej <span x-text="minPlayers"></span> uczestników
                    (obecnie <span x-text="participantCount"></span>).
                </p>
                <form
                    action="{{ route('tournaments.run', $tournament->id) }}"
                    method="POST"
                    class="flex flex-col items-center gap-4"
                    x-show="participantCount >= minPlayers"
                    x-cloak
                    @submit="commitDartLimits($event)"
                >
                        @csrf

                        <div class="w-full max-w-2xl flex flex-col gap-3">
                            <p class="text-accent font-semibold">Rodzaj turnieju</p>
                            <label
                                class="flex items-start gap-3"
                                :class="canUseGroupsPlayoff ? 'cursor-pointer' : 'cursor-help'"
                                :title="groupsPlayoffDisabledReason"
                            >
                                <input type="radio" name="tournamentFormat" value="groups_playoff"
                                       class="mt-1"
                                       x-model="tournamentFormat"
                                       @change="onFormatChange()"
                                       x-bind:disabled="!canUseGroupsPlayoff">
                                <span>
                                    <span class="font-medium text-text-primary">Grupy + drabinka</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5" x-show="canUseGroupsPlayoff">
                                        Faza grupowa, potem playoff
                                    </span>
                                    <span
                                        class="block text-accent/90 text-xs mt-0.5"
                                        x-show="!canUseGroupsPlayoff"
                                        x-cloak
                                        x-text="groupsPlayoffDisabledReason"
                                    ></span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="radio" name="tournamentFormat" value="single_elimination"
                                       class="mt-1"
                                       x-model="tournamentFormat"
                                       @change="onFormatChange()">
                                <span>
                                    <span class="font-medium text-text-primary">Single elimination</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5">
                                        Tylko drabinka (bez grup); wolne losy dopełniają do potęgi 2
                                    </span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="radio" name="tournamentFormat" value="double_elimination"
                                       class="mt-1"
                                       x-model="tournamentFormat"
                                       @change="onFormatChange()">
                                <span>
                                    <span class="font-medium text-text-primary">Double elimination</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5">
                                        Drabinka wygranych + przegranych; dwie porażki = odpadnięcie
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 w-full max-w-2xl"
                             x-show="tournamentFormat === 'groups_playoff'"
                             x-cloak>
                            <div class="flex flex-col">
                                <label for="groupsCount" class="text-accent font-semibold mb-2">Liczba grup</label>
                                <select id="groupsCount" name="groupsCount" class="select-field"
                                        x-ref="groupsSelect"
                                        x-model.number="groupsCount" x-on:change="onGroupsChange()"
                                        x-bind:disabled="tournamentFormat !== 'groups_playoff'">
                                    @foreach ($groupCountOptions as $option)
                                        <option value="{{ $option }}" @selected($defaultGroupsCount === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                <p class="text-text-secondary/70 text-xs mt-2">
                                    Min. {{ $minPlayersPerGroup }} zawodników w grupie
                                </p>
                            </div>
                            <div class="flex flex-col">
                                <label for="playoffBracketSizeSelect" class="text-accent font-semibold mb-2">Etap drabinki</label>
                                <select id="playoffBracketSizeSelect"
                                        x-ref="bracketSelect"
                                        name="playoffBracketSize"
                                        class="select-field"
                                        x-model.number="playoffBracketSize"
                                        x-on:change="onBracketChange()"
                                        x-bind:disabled="tournamentFormat !== 'groups_playoff'">
                                    @foreach ($defaultBracketOptions as $option)
                                        <option value="{{ $option['value'] }}" @selected($defaultPlayoffBracketSize === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                <p class="text-text-secondary/70 text-xs mt-2">
                                    Od tego etapu zaczyna się faza pucharowa
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 w-full max-w-2xl"
                             x-show="tournamentFormat === 'single_elimination' || tournamentFormat === 'double_elimination'"
                             x-cloak>
                            <div class="flex flex-col justify-center rounded-lg border border-border bg-bg/40 p-4">
                                <p class="text-sm text-text-secondary">
                                    <span x-text="participantCount"></span> graczy → drabinka
                                    <span class="text-accent font-semibold" x-text="seBracketSize"></span>
                                    <template x-if="seByeCount > 0">
                                        <span> (<span x-text="seByeCount"></span> wolnych losów)</span>
                                    </template>
                                </p>
                            </div>
                            <div class="flex flex-col gap-3" x-show="tournamentFormat === 'double_elimination'" x-cloak>
                                <div class="flex flex-col">
                                    <label for="grandFinalMode" class="text-accent font-semibold mb-2">Grand Final</label>
                                    <select id="grandFinalMode" name="grandFinalMode" class="select-field"
                                            x-model="grandFinalMode"
                                            x-bind:disabled="tournamentFormat !== 'double_elimination'">
                                        <option value="reset">Reset (do 2 meczów, gdy wygra LB)</option>
                                        <option value="single">Jeden mecz o tytuł</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="w-full max-w-2xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="tournamentFormat === 'groups_playoff' && preview"
                             x-cloak>
                            <p class="text-accent font-semibold text-sm mb-3">Podgląd podziału</p>
                            <template x-for="(advanceCount, index) in (preview?.advances ?? [])" x-bind:key="index">
                                <div class="flex flex-wrap items-baseline justify-between gap-2 py-1 text-sm text-text-secondary border-b border-border/60 last:border-0">
                                    <span>
                                        Grupa <span x-text="index + 1"></span>:
                                        <span x-text="preview.groupSizes[index]"></span> graczy
                                    </span>
                                    <span class="text-accent">
                                        → <span x-text="advanceCount"></span> awansujących
                                    </span>
                                </div>
                            </template>
                        </div>

                        <p class="text-accent text-sm" x-show="tournamentFormat === 'groups_playoff'" x-cloak>
                            Drabinka playoff: <span x-text="$data.playoffBracketSize"></span> graczy awansujących
                        </p>

                        <div class="w-full max-w-2xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="tournamentFormat === 'groups_playoff' && canEnableConsolation"
                             x-cloak>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox"
                                       class="mt-1"
                                       x-model="hasConsolationBracket"
                                       @change="onConsolationToggle()">
                                <span>
                                    <span class="font-medium text-text-primary">Drabinka pocieszenia</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5">
                                        Osobna drabinka dla zawodników, którzy nie awansowali z grup
                                    </span>
                                </span>
                            </label>
                            <input type="hidden" name="hasConsolationBracket"
                                   x-bind:value="hasConsolationBracket ? 1 : 0">
                            <label class="mt-3 flex items-start gap-3 cursor-pointer"
                                   x-show="hasConsolationBracket && consolationBracketSize >= 4"
                                   x-cloak>
                                <input type="checkbox"
                                       class="mt-1"
                                       x-model="hasConsolationThirdPlace">
                                <span>
                                    <span class="font-medium text-text-primary">Mecz o 3. miejsce w pocieszeniu</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5">
                                        Domyślnie wyłączony. Bez tego meczu obaj przegrani półfinału dzielą to samo miejsce.
                                    </span>
                                </span>
                            </label>
                            <input type="hidden" name="hasConsolationThirdPlace"
                                   x-bind:value="hasConsolationBracket && consolationBracketSize >= 4 && hasConsolationThirdPlace ? 1 : 0">
                            <p class="text-sm text-text-secondary mt-3"
                               x-show="hasConsolationBracket"
                               x-cloak>
                                <span x-text="remainingAfterAdvance"></span> nieawansujących → drabinka
                                <span class="text-accent font-semibold" x-text="consolationBracketSize"></span>
                                <template x-if="consolationByeCount > 0">
                                    <span> (<span x-text="consolationByeCount"></span> wolnych losów)</span>
                                </template>
                            </p>
                        </div>

                        <div class="w-full max-w-3xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="activeFormatStages.length"
                             x-cloak>
                            <p class="text-accent font-semibold text-sm mb-1">Format gry per etap</p>
                            <p class="text-text-secondary/70 text-xs mb-4"
                               x-text="hasOrganizationFormatPresets
                                   ? 'Domyślne z presetów organizacji — możesz nadpisać przed startem. Tablet odczyta format z meczu.'
                                   : 'Domyślnie 501 · 1 set · 2 legi. Tablet odczyta format z meczu — bez konfiguracji przy starcie gry.'">
                            </p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-text-secondary">
                                    <thead class="text-accent">
                                        <tr class="border-b border-border">
                                            <th class="text-left py-2 pr-3 font-semibold">Etap</th>
                                            <th class="text-left py-2 px-2 font-semibold">Punkty</th>
                                            <th class="text-left py-2 px-2 font-semibold">Legi / set (pierwszy do)</th>
                                            <th class="text-left py-2 pl-2 font-semibold">Sety / mecz (pierwszy do)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="stage in activeFormatStages" x-bind:key="stage.value">
                                            <tr class="border-b border-border/60 last:border-0">
                                                <td class="py-2 pr-3 whitespace-nowrap" x-text="stage.label"></td>
                                                <td class="py-2 px-2">
                                                    <select class="select-field w-full min-w-[5rem]"
                                                            x-bind:name="'matchFormats[' + stage.value + '][startingScore]'"
                                                            x-model.number="matchFormats[stage.value].startingScore">
                                                        <template x-for="score in startingScoreOptions" x-bind:key="score">
                                                            <option x-bind:value="score" x-text="score"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 px-2">
                                                    <select class="select-field w-full min-w-[4rem]"
                                                            x-bind:name="'matchFormats[' + stage.value + '][legsToWinSet]'"
                                                            x-model.number="matchFormats[stage.value].legsToWinSet">
                                                        <template x-for="n in 15" x-bind:key="n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 pl-2">
                                                    <select class="select-field w-full min-w-[4rem]"
                                                            x-bind:name="'matchFormats[' + stage.value + '][setsToWinMatch]'"
                                                            x-model.number="matchFormats[stage.value].setsToWinMatch">
                                                        <template x-for="n in 5" x-bind:key="n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-5 rounded-lg border border-border bg-bg-elevated/40 p-3">
                                <p class="text-sm font-medium text-text">Dla całego turnieju</p>
                                <p class="text-text-secondary/70 text-xs mt-0.5 mb-3">
                                    Jedno ustawienie trafia do każdego etapu. Inne wartości dla pojedynczych etapów ustawisz poniżej.
                                </p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="flex items-center gap-2 text-sm mb-1">
                                            <input type="checkbox" class="ui-check"
                                                   :checked="globalDart.dartOn"
                                                   @change="setGlobalDartOn('main', $event.target.checked)">
                                            Ogranicznik lotek
                                        </span>
                                        <div class="flex items-center gap-2" x-show="globalDart.dartOn" x-cloak>
                                            <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                    @click="bumpGlobalDart('main', -3)">−</button>
                                            <input class="input-field text-center w-24" type="number" min="15" max="99" step="3"
                                                   x-model.number="globalDart.dartLimit"
                                                   @input="$nextTick(() => onGlobalDartInput('main'))">
                                            <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                    @click="bumpGlobalDart('main', 3)">+</button>
                                        </div>
                                    </label>
                                    <label class="block" x-show="globalDart.dartOn" x-cloak>
                                        <span class="flex items-center gap-2 text-sm mb-1">
                                            <input type="checkbox" class="ui-check"
                                                   :checked="globalDart.lossOn"
                                                   @change="setGlobalLossOn('main', $event.target.checked)">
                                            Próg przegranej
                                        </span>
                                        <input class="input-field" type="number" min="2" max="170"
                                               x-show="globalDart.lossOn"
                                               x-model.number="globalDart.lossThreshold"
                                               @input="$nextTick(() => onGlobalDartInput('main'))">
                                    </label>
                                </div>
                                <p class="text-xs text-accent mt-3" x-show="mainDartLimitsDiffer" x-cloak>
                                    Niektóre etapy mają inne ustawienia niż powyższe.
                                </p>
                            </div>
                            <button type="button"
                                    class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-text-secondary hover:text-accent transition-colors"
                                    @click="stageDartOpen = !stageDartOpen"
                                    :aria-expanded="stageDartOpen">
                                <svg class="h-4 w-4 shrink-0 transition-transform" :class="stageDartOpen && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                </svg>
                                Ustawienia poszczególnych etapów
                            </button>
                            <div x-show="stageDartOpen" x-cloak>
                            <template x-for="stage in activeFormatStages" x-bind:key="'dl-'+stage.value">
                                <div class="mt-4 pt-3 border-t border-border/40" x-show="matchFormats[stage.value]">
                                    <p class="text-sm font-medium text-text mb-2" x-text="stage.label"></p>
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <label class="block">
                                            <span class="flex items-center gap-2 text-sm mb-1">
                                                <input type="checkbox" class="ui-check"
                                                       :checked="matchFormats[stage.value].dartLimit != null"
                                                       @change="
                                                           if ($event.target.checked) {
                                                               matchFormats[stage.value].dartLimit = matchFormats[stage.value].dartLimit || 45;
                                                           } else {
                                                               matchFormats[stage.value].dartLimit = null;
                                                               matchFormats[stage.value].lossThreshold = null;
                                                           }
                                                       ">
                                                Ogranicznik lotek
                                            </span>
                                            <div class="flex items-center gap-2" x-show="matchFormats[stage.value].dartLimit != null" x-cloak>
                                                <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                        @click="matchFormats[stage.value].dartLimit = Math.max(15, (matchFormats[stage.value].dartLimit || 45) - 3)">−</button>
                                            <input class="input-field text-center w-24" type="number" min="15" max="99" step="3"
                                                   x-model.number="matchFormats[stage.value].dartLimit">
                                            <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                    @click="matchFormats[stage.value].dartLimit = Math.min(99, (matchFormats[stage.value].dartLimit || 45) + 3)">+</button>
                                            </div>
                                        </label>
                                        <label class="block" x-show="matchFormats[stage.value].dartLimit != null" x-cloak>
                                            <span class="flex items-center gap-2 text-sm mb-1">
                                                <input type="checkbox" class="ui-check"
                                                       :checked="matchFormats[stage.value].lossThreshold != null"
                                                       @change="matchFormats[stage.value].lossThreshold = $event.target.checked ? (matchFormats[stage.value].lossThreshold || 50) : null">
                                                Próg przegranej
                                            </span>
                                            <input class="input-field" type="number" min="2" max="170"
                                                   x-show="matchFormats[stage.value].lossThreshold != null"
                                                   x-model.number="matchFormats[stage.value].lossThreshold">
                                        </label>
                                    </div>
                                </div>
                            </template>
                            </div>
                        </div>

                        <div class="w-full max-w-3xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="tournamentFormat === 'groups_playoff' && hasConsolationBracket && consolationFormatStages.length"
                             x-cloak>
                            <p class="text-accent font-semibold text-sm mb-1">Format gry — drabinka pocieszenia</p>
                            <p class="text-text-secondary/70 text-xs mb-4">
                                Punkty, legi i sety osobno dla etapów drabinki pocieszenia.
                            </p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-text-secondary">
                                    <thead class="text-accent">
                                        <tr class="border-b border-border">
                                            <th class="text-left py-2 pr-3 font-semibold">Etap</th>
                                            <th class="text-left py-2 px-2 font-semibold">Punkty</th>
                                            <th class="text-left py-2 px-2 font-semibold">Legi / set (pierwszy do)</th>
                                            <th class="text-left py-2 pl-2 font-semibold">Sety / mecz (pierwszy do)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="stage in consolationFormatStages" x-bind:key="'c-'+stage.value">
                                            <tr class="border-b border-border/60 last:border-0">
                                                <td class="py-2 pr-3 whitespace-nowrap" x-text="stage.label"></td>
                                                <td class="py-2 px-2">
                                                    <select class="select-field w-full min-w-[5rem]"
                                                            x-bind:name="'consolationMatchFormats[' + stage.value + '][startingScore]'"
                                                            x-model.number="consolationMatchFormats[stage.value].startingScore">
                                                        <template x-for="score in startingScoreOptions" x-bind:key="'cs-'+score">
                                                            <option x-bind:value="score" x-text="score"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 px-2">
                                                    <select class="select-field w-full min-w-[4rem]"
                                                            x-bind:name="'consolationMatchFormats[' + stage.value + '][legsToWinSet]'"
                                                            x-model.number="consolationMatchFormats[stage.value].legsToWinSet">
                                                        <template x-for="n in 15" x-bind:key="'cl-'+n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 pl-2">
                                                    <select class="select-field w-full min-w-[4rem]"
                                                            x-bind:name="'consolationMatchFormats[' + stage.value + '][setsToWinMatch]'"
                                                            x-model.number="consolationMatchFormats[stage.value].setsToWinMatch">
                                                        <template x-for="n in 5" x-bind:key="'cset-'+n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-5 rounded-lg border border-border bg-bg-elevated/40 p-3">
                                <p class="text-sm font-medium text-text">Dla całej drabinki pocieszenia</p>
                                <p class="text-text-secondary/70 text-xs mt-0.5 mb-3">
                                    Jedno ustawienie trafia do każdego etapu pocieszenia.
                                </p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="flex items-center gap-2 text-sm mb-1">
                                            <input type="checkbox" class="ui-check"
                                                   :checked="consolationGlobalDart.dartOn"
                                                   @change="setGlobalDartOn('consolation', $event.target.checked)">
                                            Ogranicznik lotek
                                        </span>
                                        <div class="flex items-center gap-2" x-show="consolationGlobalDart.dartOn" x-cloak>
                                            <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                    @click="bumpGlobalDart('consolation', -3)">−</button>
                                            <input class="input-field text-center w-24" type="number" min="15" max="99" step="3"
                                                   x-model.number="consolationGlobalDart.dartLimit"
                                                   @input="$nextTick(() => onGlobalDartInput('consolation'))">
                                            <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                    @click="bumpGlobalDart('consolation', 3)">+</button>
                                        </div>
                                    </label>
                                    <label class="block" x-show="consolationGlobalDart.dartOn" x-cloak>
                                        <span class="flex items-center gap-2 text-sm mb-1">
                                            <input type="checkbox" class="ui-check"
                                                   :checked="consolationGlobalDart.lossOn"
                                                   @change="setGlobalLossOn('consolation', $event.target.checked)">
                                            Próg przegranej
                                        </span>
                                        <input class="input-field" type="number" min="2" max="170"
                                               x-show="consolationGlobalDart.lossOn"
                                               x-model.number="consolationGlobalDart.lossThreshold"
                                               @input="$nextTick(() => onGlobalDartInput('consolation'))">
                                    </label>
                                </div>
                                <p class="text-xs text-accent mt-3" x-show="consolationDartLimitsDiffer" x-cloak>
                                    Niektóre etapy pocieszenia mają inne ustawienia niż powyższe.
                                </p>
                            </div>
                            <button type="button"
                                    class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-text-secondary hover:text-accent transition-colors"
                                    @click="consolationStageDartOpen = !consolationStageDartOpen"
                                    :aria-expanded="consolationStageDartOpen">
                                <svg class="h-4 w-4 shrink-0 transition-transform" :class="consolationStageDartOpen && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                </svg>
                                Ustawienia poszczególnych etapów
                            </button>
                            <div x-show="consolationStageDartOpen" x-cloak>
                            <template x-for="stage in consolationFormatStages" x-bind:key="'cdl-'+stage.value">
                                <div class="mt-4 pt-3 border-t border-border/40" x-show="consolationMatchFormats[stage.value]">
                                    <p class="text-sm font-medium text-text mb-2" x-text="stage.label"></p>
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <label class="block">
                                            <span class="flex items-center gap-2 text-sm mb-1">
                                                <input type="checkbox" class="ui-check"
                                                       :checked="consolationMatchFormats[stage.value].dartLimit != null"
                                                       @change="
                                                           if ($event.target.checked) {
                                                               consolationMatchFormats[stage.value].dartLimit = consolationMatchFormats[stage.value].dartLimit || 45;
                                                           } else {
                                                               consolationMatchFormats[stage.value].dartLimit = null;
                                                               consolationMatchFormats[stage.value].lossThreshold = null;
                                                           }
                                                       ">
                                                Ogranicznik lotek
                                            </span>
                                            <div class="flex items-center gap-2" x-show="consolationMatchFormats[stage.value].dartLimit != null" x-cloak>
                                                <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                        @click="consolationMatchFormats[stage.value].dartLimit = Math.max(15, (consolationMatchFormats[stage.value].dartLimit || 45) - 3)">−</button>
                                            <input class="input-field text-center w-24" type="number" min="15" max="99" step="3"
                                                   x-model.number="consolationMatchFormats[stage.value].dartLimit">
                                            <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                    @click="consolationMatchFormats[stage.value].dartLimit = Math.min(99, (consolationMatchFormats[stage.value].dartLimit || 45) + 3)">+</button>
                                            </div>
                                        </label>
                                        <label class="block" x-show="consolationMatchFormats[stage.value].dartLimit != null" x-cloak>
                                            <span class="flex items-center gap-2 text-sm mb-1">
                                                <input type="checkbox" class="ui-check"
                                                       :checked="consolationMatchFormats[stage.value].lossThreshold != null"
                                                       @change="consolationMatchFormats[stage.value].lossThreshold = $event.target.checked ? (consolationMatchFormats[stage.value].lossThreshold || 50) : null">
                                                Próg przegranej
                                            </span>
                                            <input class="input-field" type="number" min="2" max="170"
                                                   x-show="consolationMatchFormats[stage.value].lossThreshold != null"
                                                   x-model.number="consolationMatchFormats[stage.value].lossThreshold">
                                        </label>
                                    </div>
                                </div>
                            </template>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary px-8 py-2"
                                x-bind:disabled="participantCount < minPlayers">
                            Start turnieju
                        </button>
                        <p x-show="participantCount < minPlayers" class="text-accent/80 text-xs">
                            Potrzeba jeszcze <span x-text="minPlayers - participantCount"></span> uczestników
                        </p>
                    </form>
            </div>
        @endif

        <div class="flex justify-center mt-8 pt-2">
            <a href="{{ route('tournaments.show', ['tournament' => $tournament->id]) }}" class="btn btn-primary">
                Powrót
            </a>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script>
        function tournamentStartForm() {
            const el = document.getElementById('tournament-start-config');
            const config = el ? JSON.parse(el.textContent) : {};

            return {
                groupsCount: config.groupsCount ?? 2,
                playoffBracketSize: config.playoffBracketSize ?? 4,
                tournamentFormat: config.tournamentFormat ?? 'groups_playoff',
                grandFinalMode: config.grandFinalMode ?? 'reset',
                seBracketSize: config.seBracketSize ?? 4,
                seByeCount: config.seByeCount ?? 0,
                groupCountOptions: config.groupCountOptions ?? [],
                bracketOptionsByGroupCount: config.bracketOptionsByGroupCount ?? {},
                startConfigPreview: config.startConfigPreview ?? {},
                matchFormatStagesByBracket: config.matchFormatStagesByBracket ?? {},
                matchFormatStagesByBracketSe: config.matchFormatStagesByBracketSe ?? {},
                matchFormatStagesByBracketDe: config.matchFormatStagesByBracketDe ?? {},
                startingScoreOptions: config.startingScoreOptions ?? [],
                defaultMatchFormat: config.defaultMatchFormat ?? {},
                defaultMatchFormatsByStage: config.defaultMatchFormatsByStage ?? {},
                hasOrganizationFormatPresets: !!config.hasOrganizationFormatPresets,
                oldMatchFormats: config.oldMatchFormats ?? {},
                oldConsolationMatchFormats: config.oldConsolationMatchFormats ?? {},
                matchFormats: {},
                consolationMatchFormats: {},
                globalDart: { dartOn: false, dartLimit: 45, lossOn: false, lossThreshold: 50 },
                consolationGlobalDart: { dartOn: false, dartLimit: 45, lossOn: false, lossThreshold: 50 },
                globalDartTouched: false,
                consolationGlobalDartTouched: false,
                stageDartOpen: false,
                consolationStageDartOpen: false,
                stageDartAutoOpened: false,
                consolationStageDartAutoOpened: false,
                hasConsolationBracket: !!config.hasConsolationBracket,
                hasConsolationThirdPlace: !!config.hasConsolationThirdPlace,
                minPlayers: config.minPlayers ?? 4,
                minPlayersPerGroup: config.minPlayersPerGroup ?? 3,
                minGroups: 2,
                participantCount: config.participantCount ?? 0,
                init() {
                    this.onParticipantCountChange(this.participantCount, { preserveUserEdits: false });
                    this.primeConsolationMatchFormats();
                    window.addEventListener('tournament-participant-count', (e) => {
                        const next = Number(e.detail?.participantCount);
                        if (!Number.isNaN(next)) {
                            this.onParticipantCountChange(next);
                        }
                    });
                    // Po zamontowaniu <select> Alpine potrafi nadpisać model pierwszą opcją (101/1/1).
                    this.$nextTick(() => {
                        this.syncMatchFormats({ preserveUserEdits: false });
                        this.syncConsolationMatchFormats({ preserveUserEdits: false });
                    });
                },
                onParticipantCountChange(next, { preserveUserEdits = true } = {}) {
                    this.participantCount = next;
                    this.refreshSeBracket();
                    this.refreshGroupAvailability();
                    this.syncMatchFormats({ preserveUserEdits });
                    this.syncConsolationMatchFormats({ preserveUserEdits });
                    this.$nextTick(() => {
                        this.syncGroupsCount();
                        this.syncBracketSelect();
                    });
                },
                refreshSeBracket() {
                    let power = 1;
                    while (power < this.participantCount) {
                        power *= 2;
                    }
                    this.seBracketSize = Math.max(4, power);
                    this.seByeCount = Math.max(0, this.seBracketSize - this.participantCount);
                },
                groupSizesFor(playerCount, groupsCount) {
                    if (groupsCount < 1 || playerCount < 0) {
                        return [];
                    }
                    const baseSize = Math.floor(playerCount / groupsCount);
                    const remainder = playerCount % groupsCount;
                    const sizes = [];
                    for (let i = 0; i < groupsCount; i++) {
                        sizes.push(baseSize + (i < remainder ? 1 : 0));
                    }
                    return sizes;
                },
                distributeAdvances(groupSizes, bracketSize) {
                    const groupsCount = groupSizes.length;
                    const playerCount = groupSizes.reduce((sum, size) => sum + size, 0);
                    if (groupsCount < 1 || playerCount < 1) {
                        return null;
                    }
                    if (bracketSize < groupsCount || bracketSize > playerCount) {
                        return null;
                    }
                    const advances = [];
                    const remainders = [];
                    for (let i = 0; i < groupsCount; i++) {
                        const exact = (bracketSize * groupSizes[i]) / playerCount;
                        advances[i] = Math.floor(exact);
                        remainders[i] = exact - advances[i];
                    }
                    const toAdd = bracketSize - advances.reduce((sum, n) => sum + n, 0);
                    if (toAdd < 0) {
                        return null;
                    }
                    const indices = Array.from({ length: groupsCount }, (_, i) => i);
                    indices.sort((a, b) => remainders[b] - remainders[a] || a - b);
                    for (let k = 0; k < toAdd; k++) {
                        advances[indices[k]] += 1;
                    }
                    for (let i = 0; i < groupsCount; i++) {
                        if (advances[i] < 1 || advances[i] > groupSizes[i]) {
                            return null;
                        }
                    }
                    if (advances.reduce((sum, n) => sum + n, 0) !== bracketSize) {
                        return null;
                    }
                    return advances;
                },
                bracketOptionLabel(bracketSize) {
                    const stage = ({
                        128: '1/64 finału',
                        64: '1/32 finału',
                        32: '1/16 finału',
                        16: '1/8 finału',
                        8: '1/4 finału',
                        4: '1/2 finału',
                    })[bracketSize] ?? `${bracketSize} graczy`;
                    return `${stage} — ${bracketSize} graczy awansujących`;
                },
                rebuildStartConfig() {
                    const playerCount = this.participantCount;
                    const groupCounts = this.allowedGroupCountsForPlayers(playerCount);
                    this.groupCountOptions = groupCounts;
                    const preview = {};
                    const bracketMap = {};
                    for (const groupsCount of groupCounts) {
                        const sizes = this.groupSizesFor(playerCount, groupsCount);
                        preview[groupsCount] = {};
                        const options = [];
                        for (let bracketSize = 4; bracketSize <= 128; bracketSize *= 2) {
                            if (bracketSize < groupsCount || bracketSize > playerCount) {
                                continue;
                            }
                            const advances = this.distributeAdvances(sizes, bracketSize);
                            if (!advances) {
                                continue;
                            }
                            preview[groupsCount][bracketSize] = {
                                groupSizes: sizes,
                                advances,
                            };
                            options.push({
                                value: bracketSize,
                                label: this.bracketOptionLabel(bracketSize),
                            });
                        }
                        bracketMap[groupsCount] = options;
                    }
                    this.startConfigPreview = preview;
                    this.bracketOptionsByGroupCount = bracketMap;
                },
                allowedGroupCountsForPlayers(playerCount) {
                    const minPer = this.minPlayersPerGroup;
                    const minGroups = this.minGroups;
                    const maxGroups = Math.floor(Math.max(0, playerCount) / minPer);
                    if (maxGroups < minGroups) {
                        return [];
                    }
                    const options = [];
                    for (let groups = minGroups; groups <= maxGroups; groups++) {
                        if (groups <= playerCount && Math.floor(playerCount / groups) >= minPer) {
                            options.push(groups);
                        }
                    }
                    return options;
                },
                refreshGroupAvailability() {
                    this.rebuildStartConfig();
                    if (
                        this.participantCount >= this.minPlayers
                        && this.tournamentFormat === 'groups_playoff'
                        && !this.canUseGroupsPlayoff
                    ) {
                        this.tournamentFormat = 'single_elimination';
                    }
                    this.syncGroupsCount();
                    this.syncBracketSelect();
                },
                get minPlayersForGroups() {
                    return this.minPlayersPerGroup * this.minGroups;
                },
                get canUseGroupsPlayoff() {
                    return this.groupCountOptions.length > 0;
                },
                get groupsPlayoffDisabledReason() {
                    if (this.canUseGroupsPlayoff) {
                        return '';
                    }
                    return `Za mało zawodników do utworzenia grup — potrzeba min. ${this.minPlayersForGroups} graczy `
                        + `(${this.minGroups} grupy po co najmniej ${this.minPlayersPerGroup}). `
                        + `Obecnie: ${this.participantCount}.`;
                },
                get bracketOptions() {
                    const opts = this.bracketOptionsByGroupCount[this.groupsCount]
                        ?? this.bracketOptionsByGroupCount[String(this.groupsCount)]
                        ?? [];
                    return opts.length
                        ? opts
                        : [{ value: 4, label: '1/2 finału — 4 graczy awansujących' }];
                },
                get preview() {
                    const byGroup = this.startConfigPreview[this.groupsCount]
                        ?? this.startConfigPreview[String(this.groupsCount)]
                        ?? {};
                    return byGroup[this.playoffBracketSize]
                        ?? byGroup[String(this.playoffBracketSize)]
                        ?? null;
                },
                get remainingAfterAdvance() {
                    return Math.max(0, this.participantCount - Number(this.playoffBracketSize || 0));
                },
                get canEnableConsolation() {
                    return this.tournamentFormat === 'groups_playoff' && this.remainingAfterAdvance >= 2;
                },
                get consolationBracketSize() {
                    const n = this.remainingAfterAdvance;
                    if (n < 2) {
                        return 0;
                    }
                    let power = 1;
                    while (power < n) {
                        power *= 2;
                    }
                    return power;
                },
                get consolationByeCount() {
                    return Math.max(0, this.consolationBracketSize - this.remainingAfterAdvance);
                },
                get consolationFormatStages() {
                    if (!this.hasConsolationBracket || !this.canEnableConsolation) {
                        return [];
                    }
                    const stages = this.matchFormatStagesByBracketSe[this.consolationBracketSize]
                        ?? this.matchFormatStagesByBracketSe[String(this.consolationBracketSize)]
                        ?? [];
                    if (!this.hasConsolationThirdPlace || this.consolationBracketSize < 4) {
                        return stages.filter((stage) => stage.value !== 'THIRD');
                    }
                    return stages;
                },
                get mainDartLimitsDiffer() {
                    return this.dartLimitsDiffer(this.matchFormats, this.activeFormatStages, this.globalDart);
                },
                get consolationDartLimitsDiffer() {
                    return this.dartLimitsDiffer(this.consolationMatchFormats, this.consolationFormatStages, this.consolationGlobalDart);
                },
                get activeFormatStages() {
                    if (this.tournamentFormat === 'double_elimination') {
                        return this.matchFormatStagesByBracketDe[this.seBracketSize]
                            ?? this.matchFormatStagesByBracketDe[String(this.seBracketSize)]
                            ?? [];
                    }
                    if (this.tournamentFormat === 'single_elimination') {
                        return this.matchFormatStagesByBracketSe[this.seBracketSize]
                            ?? this.matchFormatStagesByBracketSe[String(this.seBracketSize)]
                            ?? [];
                    }
                    return this.matchFormatStagesByBracket[this.playoffBracketSize]
                        ?? this.matchFormatStagesByBracket[String(this.playoffBracketSize)]
                        ?? [];
                },
                dartGlobal(kind) {
                    return kind === 'consolation' ? this.consolationGlobalDart : this.globalDart;
                },
                optionalNumber(value) {
                    if (value == null || value === '') {
                        return null;
                    }
                    const n = Number(value);
                    return Number.isFinite(n) ? n : null;
                },
                dartLimitsDiffer(formats, stages, global) {
                    const limit = global.dartOn ? this.optionalNumber(global.dartLimit) : null;
                    const loss = global.dartOn && global.lossOn ? this.optionalNumber(global.lossThreshold) : null;
                    return stages.some((stage) => {
                        const row = formats[stage.value];
                        if (!row) {
                            return false;
                        }
                        return this.optionalNumber(row.dartLimit) !== limit
                            || this.optionalNumber(row.lossThreshold) !== loss;
                    });
                },
                writeGlobalInto(kind) {
                    const global = this.dartGlobal(kind);
                    const stages = kind === 'consolation' ? this.consolationFormatStages : this.activeFormatStages;
                    const formats = kind === 'consolation' ? this.consolationMatchFormats : this.matchFormats;
                    let limit = null;
                    if (global.dartOn) {
                        limit = this.optionalNumber(global.dartLimit);
                        if (limit == null) {
                            return;
                        }
                    }
                    let loss = null;
                    if (global.dartOn && global.lossOn) {
                        loss = this.optionalNumber(global.lossThreshold);
                        if (loss == null) {
                            return;
                        }
                    }
                    for (const stage of stages) {
                        if (!formats[stage.value]) {
                            continue;
                        }
                        formats[stage.value].dartLimit = limit;
                        formats[stage.value].lossThreshold = loss;
                    }
                },
                touchDartGlobal(kind) {
                    if (kind === 'consolation') {
                        this.consolationGlobalDartTouched = true;
                    } else {
                        this.globalDartTouched = true;
                    }
                },
                setGlobalDartOn(kind, on) {
                    const global = this.dartGlobal(kind);
                    global.dartOn = on;
                    if (!on) {
                        global.lossOn = false;
                    } else if (this.optionalNumber(global.dartLimit) == null) {
                        global.dartLimit = 45;
                    }
                    this.touchDartGlobal(kind);
                    this.writeGlobalInto(kind);
                },
                setGlobalLossOn(kind, on) {
                    const global = this.dartGlobal(kind);
                    global.lossOn = on;
                    if (on && this.optionalNumber(global.lossThreshold) == null) {
                        global.lossThreshold = 50;
                    }
                    this.touchDartGlobal(kind);
                    this.writeGlobalInto(kind);
                },
                bumpGlobalDart(kind, delta) {
                    const global = this.dartGlobal(kind);
                    global.dartOn = true;
                    const current = this.optionalNumber(global.dartLimit) ?? 45;
                    global.dartLimit = Math.min(99, Math.max(15, current + delta));
                    this.touchDartGlobal(kind);
                    this.writeGlobalInto(kind);
                },
                onGlobalDartInput(kind) {
                    this.touchDartGlobal(kind);
                    this.writeGlobalInto(kind);
                },
                commitDartLimits(event) {
                    this.flushGlobalDart('main');
                    this.flushGlobalDart('consolation');
                    const form = event.target;
                    form.querySelectorAll('input[data-format-dart]').forEach((node) => node.remove());
                    this.appendDartFields(form, 'matchFormats', this.activeFormatStages, this.matchFormats);
                    if (this.hasConsolationBracket) {
                        this.appendDartFields(
                            form,
                            'consolationMatchFormats',
                            this.consolationFormatStages,
                            this.consolationMatchFormats,
                        );
                    }
                },
                flushGlobalDart(kind) {
                    const global = this.dartGlobal(kind);
                    if (!global.dartOn) {
                        return;
                    }
                    const stages = kind === 'consolation' ? this.consolationFormatStages : this.activeFormatStages;
                    const formats = kind === 'consolation' ? this.consolationMatchFormats : this.matchFormats;
                    const allEmpty = stages.every((stage) => this.optionalNumber(formats[stage.value]?.dartLimit) == null);
                    if (allEmpty || !this.dartLimitsDiffer(formats, stages, global)) {
                        this.writeGlobalInto(kind);
                    }
                },
                appendDartFields(form, prefix, stages, formats) {
                    for (const stage of stages) {
                        const row = formats[stage.value];
                        if (!row) {
                            continue;
                        }
                        const limit = this.optionalNumber(row.dartLimit);
                        const loss = limit == null ? null : this.optionalNumber(row.lossThreshold);
                        this.appendHiddenField(form, prefix + '[' + stage.value + '][dartLimit]', limit);
                        this.appendHiddenField(form, prefix + '[' + stage.value + '][lossThreshold]', loss);
                    }
                },
                appendHiddenField(form, name, value) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value == null ? '' : String(value);
                    input.dataset.formatDart = '1';
                    form.appendChild(input);
                },
                seedGlobalDart(kind) {
                    const stages = kind === 'consolation' ? this.consolationFormatStages : this.activeFormatStages;
                    const formats = kind === 'consolation' ? this.consolationMatchFormats : this.matchFormats;
                    const global = this.dartGlobal(kind);
                    if (!stages.length) {
                        return;
                    }
                    const limits = stages.map((stage) => this.optionalNumber(formats[stage.value]?.dartLimit));
                    const losses = stages.map((stage) => this.optionalNumber(formats[stage.value]?.lossThreshold));
                    const same = (values) => values.every((value) => value === values[0]);
                    const uniformLimit = same(limits);
                    const uniformLoss = same(losses);
                    if (uniformLimit && limits[0] != null) {
                        global.dartOn = true;
                        global.dartLimit = limits[0];
                    } else {
                        global.dartOn = false;
                    }
                    if (global.dartOn && uniformLoss && losses[0] != null) {
                        global.lossOn = true;
                        global.lossThreshold = losses[0];
                    } else {
                        global.lossOn = false;
                    }
                    if (uniformLimit && uniformLoss) {
                        return;
                    }
                    if (kind === 'consolation') {
                        if (!this.consolationStageDartAutoOpened) {
                            this.consolationStageDartOpen = true;
                            this.consolationStageDartAutoOpened = true;
                        }
                    } else if (!this.stageDartAutoOpened) {
                        this.stageDartOpen = true;
                        this.stageDartAutoOpened = true;
                    }
                },
                syncMatchFormats({ preserveUserEdits = true } = {}) {
                    const stages = this.activeFormatStages;
                    const next = {};
                    for (const stage of stages) {
                        next[stage.value] = {
                            ...this.defaultMatchFormat,
                            ...(this.defaultMatchFormatsByStage[stage.value] ?? {}),
                            ...(this.oldMatchFormats[stage.value] ?? {}),
                            ...(preserveUserEdits ? (this.matchFormats[stage.value] ?? {}) : {}),
                        };
                    }
                    this.matchFormats = next;
                    if (this.globalDartTouched) {
                        this.writeGlobalInto('main');
                    } else {
                        this.seedGlobalDart('main');
                    }
                },
                primeConsolationMatchFormats() {
                    const next = { ...this.consolationMatchFormats };
                    for (const size of [2, 4, 8, 16, 32, 64, 128]) {
                        const stages = this.matchFormatStagesByBracketSe[size]
                            ?? this.matchFormatStagesByBracketSe[String(size)]
                            ?? [];
                        for (const stage of stages) {
                            next[stage.value] = {
                                ...this.defaultMatchFormat,
                                ...(this.defaultMatchFormatsByStage[stage.value] ?? {}),
                                ...(this.oldConsolationMatchFormats[stage.value] ?? {}),
                                ...(this.consolationMatchFormats[stage.value] ?? {}),
                            };
                        }
                    }
                    this.consolationMatchFormats = next;
                },
                syncConsolationMatchFormats({ preserveUserEdits = true } = {}) {
                    if (!this.canEnableConsolation) {
                        this.hasConsolationBracket = false;
                    }
                    const stages = this.consolationFormatStages;
                    const next = { ...this.consolationMatchFormats };
                    for (const stage of stages) {
                        next[stage.value] = {
                            ...this.defaultMatchFormat,
                            ...(this.defaultMatchFormatsByStage[stage.value] ?? {}),
                            ...(this.oldConsolationMatchFormats[stage.value] ?? {}),
                            ...(preserveUserEdits ? (this.consolationMatchFormats[stage.value] ?? {}) : {}),
                        };
                    }
                    this.consolationMatchFormats = next;
                    if (this.consolationGlobalDartTouched) {
                        this.writeGlobalInto('consolation');
                    } else {
                        this.seedGlobalDart('consolation');
                    }
                },
                syncGroupsCount() {
                    const sel = this.$refs.groupsSelect;
                    if (sel) {
                        sel.replaceChildren();
                        for (const option of this.groupCountOptions) {
                            const elOpt = document.createElement('option');
                            elOpt.value = String(option);
                            elOpt.textContent = String(option);
                            sel.appendChild(elOpt);
                        }
                    }
                    if (!this.groupCountOptions.includes(this.groupsCount)) {
                        this.groupsCount = this.groupCountOptions[0] ?? 2;
                    }
                    if (sel) {
                        sel.value = String(this.groupsCount);
                    }
                },
                syncBracketSelect() {
                    const opts = this.bracketOptions;
                    const sel = this.$refs.bracketSelect;
                    if (!sel) {
                        return;
                    }
                    sel.replaceChildren();
                    for (const option of opts) {
                        const elOpt = document.createElement('option');
                        elOpt.value = String(option.value);
                        elOpt.textContent = option.label;
                        sel.appendChild(elOpt);
                    }
                    if (!opts.some((option) => Number(option.value) === Number(this.playoffBracketSize))) {
                        this.playoffBracketSize = opts[0]?.value ?? 4;
                    }
                    sel.value = String(this.playoffBracketSize);
                },
                onFormatChange() {
                    this.syncMatchFormats({ preserveUserEdits: false });
                    this.syncConsolationMatchFormats({ preserveUserEdits: false });
                },
                onGroupsChange() {
                    this.syncBracketSelect();
                    this.syncMatchFormats();
                    this.syncConsolationMatchFormats();
                },
                onBracketChange() {
                    this.syncMatchFormats();
                    this.syncConsolationMatchFormats();
                },
                onConsolationToggle() {
                    if (!this.hasConsolationBracket) {
                        this.hasConsolationThirdPlace = false;
                    }
                    this.syncConsolationMatchFormats();
                },
            };
        }
    </script>
@endsection
