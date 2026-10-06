@if($achievements->isEmpty())
    <x-empty-state
        class="mt-10"
        title="Brak osiągnięć"
        description="180, wysokie wizyty i szybkie zamknięcia pojawią się po rozegranych meczach."
    />
@else
    <div class="table-wrap mt-8">
        <table class="table-surface achievement-table table-striped">
            <thead>
            <tr>
                <th class="sticky-player">Zawodnik</th>
                <th>180</th>
                <th>170+</th>
                <th>QF</th>
                <th>HF</th>
            </tr>
            </thead>
            <tbody>
            @foreach($achievements as $playerAchievements)
                <tr>
                    <td class="font-medium text-text whitespace-nowrap sticky-player">
                        {{ $playerAchievements['player']->name }}
                    </td>
                    <td>
                        <span @class(['score-num', 'text-text-muted' => ($playerAchievements['max'] ?? 0) === 0])>{{ $playerAchievements['max'] ?? 0 }}</span>
                    </td>
                    <td>
                        <span @class(['score-num', 'text-text-muted' => ($playerAchievements['one_seventy'] ?? 0) === 0])>{{ $playerAchievements['one_seventy'] ?? 0 }}</span>
                    </td>
                    <td>
                        <x-achievement-marks :items="$playerAchievements['qf'] ?? []" order="asc" />
                    </td>
                    <td>
                        <x-achievement-marks :items="$playerAchievements['hf'] ?? []" order="desc" />
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
