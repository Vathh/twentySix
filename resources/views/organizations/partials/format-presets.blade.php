<div class="mb-6 rounded-lg border border-border bg-bg/40 p-4">
    <p class="text-accent font-semibold text-sm mb-1">{{ $title }}</p>
    <p class="text-text-secondary/70 text-xs mb-4">{{ $lead }}</p>
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
                @foreach($stages as $stage)
                    @php
                        $format = $formats[$stage['value']] ?? [
                            'startingScore' => 501,
                            'legsToWinSet' => 2,
                            'setsToWinMatch' => 1,
                        ];
                    @endphp
                    <tr class="border-b border-border/60 last:border-0">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $stage['label'] }}</td>
                        <td class="py-2 px-2">
                            <select class="select-field w-full min-w-[5rem]" name="{{ $field }}[{{ $stage['value'] }}][startingScore]">
                                @foreach($startingScoreOptions as $score)
                                    <option value="{{ $score }}" @selected((int) ($format['startingScore'] ?? 501) === (int) $score)>{{ $score }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-2 px-2">
                            <select class="select-field w-full min-w-[4rem]" name="{{ $field }}[{{ $stage['value'] }}][legsToWinSet]">
                                @for($n = 1; $n <= 15; $n++)
                                    <option value="{{ $n }}" @selected((int) ($format['legsToWinSet'] ?? 2) === $n)>{{ $n }}</option>
                                @endfor
                            </select>
                        </td>
                        <td class="py-2 pl-2">
                            <select class="select-field w-full min-w-[4rem]" name="{{ $field }}[{{ $stage['value'] }}][setsToWinMatch]">
                                @for($n = 1; $n <= 5; $n++)
                                    <option value="{{ $n }}" @selected((int) ($format['setsToWinMatch'] ?? 1) === $n)>{{ $n }}</option>
                                @endfor
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @foreach($stages as $stage)
        @php
            $format = $formats[$stage['value']] ?? [];
            $dartLimit = $format['dartLimit'] ?? null;
            $lossThreshold = $format['lossThreshold'] ?? null;
        @endphp
        <div class="mt-4 pt-3 border-t border-border/40" x-data="{
            dartOn: {{ $dartLimit ? 'true' : 'false' }},
            dartLimit: {{ $dartLimit ? (int) $dartLimit : 45 }},
            lossOn: {{ $lossThreshold ? 'true' : 'false' }},
            lossThreshold: {{ $lossThreshold ? (int) $lossThreshold : 50 }},
        }">
            <p class="text-sm font-medium text-text mb-2">{{ $stage['label'] }}</p>
            <div class="grid sm:grid-cols-2 gap-3">
                <label class="block">
                    <span class="flex items-center gap-2 text-sm mb-1">
                        <input type="checkbox" x-model="dartOn" @change="if (!dartOn) lossOn = false">
                        Ogranicznik lotek
                    </span>
                    <div class="flex items-center gap-2" x-show="dartOn" x-cloak>
                        <button type="button" class="btn btn-secondary !py-1 !px-3" @click="dartLimit = Math.max(15, dartLimit - 3)">−</button>
                        <input class="input-field text-center w-24" type="number" min="15" max="99" step="3"
                               name="{{ $field }}[{{ $stage['value'] }}][dartLimit]" x-model.number="dartLimit">
                        <button type="button" class="btn btn-secondary !py-1 !px-3" @click="dartLimit = Math.min(99, dartLimit + 3)">+</button>
                    </div>
                    <input type="hidden" name="{{ $field }}[{{ $stage['value'] }}][dartLimit]" value="" x-show="!dartOn">
                </label>
                <label class="block" x-show="dartOn" x-cloak>
                    <span class="flex items-center gap-2 text-sm mb-1">
                        <input type="checkbox" x-model="lossOn">
                        Próg przegranej
                    </span>
                    <input class="input-field" type="number" min="2" max="170"
                           name="{{ $field }}[{{ $stage['value'] }}][lossThreshold]"
                           x-show="lossOn" x-model.number="lossThreshold">
                    <input type="hidden" name="{{ $field }}[{{ $stage['value'] }}][lossThreshold]" value="" x-show="!lossOn">
                </label>
            </div>
        </div>
    @endforeach
</div>
