<div class="table-wrap mt-10">
    <p class="text-center mb-3 text-text-secondary">Osiągnięcia</p>
    <table class="table-surface">
        <thead>
        <tr>
            <th class="text-left">Zawodnik</th>
            <th class="text-center">180</th>
            <th class="text-center">170+</th>
            <th class="text-center">QF</th>
            <th class="text-center">HF</th>
        </tr>
        </thead>

        <tbody>
        @foreach($achievements as $playerAchievements)
            <tr>
                <td class="font-medium text-text whitespace-nowrap">
                    {{ $playerAchievements['player']->name }}
                </td>
                <td class="text-center">{{ $playerAchievements['max'] ?? 0 }}</td>
                <td class="text-center">{{ $playerAchievements['one_seventy'] ?? 0 }}</td>
                <td class="text-center flex-wrap">
                    @foreach($playerAchievements['qf'] ?? [] as $achievement)
                        <span>{{ $achievement->value }},</span>
                    @endforeach
                </td>
                <td class="text-center flex-wrap">
                    @foreach($playerAchievements['hf'] ?? [] as $achievement)
                        <span>{{ $achievement->value }},</span>
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
