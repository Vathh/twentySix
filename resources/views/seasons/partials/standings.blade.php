<div class="table-wrap mt-12">
    <h2 class="section-title">Tabela sezonu</h2>
    <table class="table-surface">
        <thead>
        <tr>
            <th class="w-12 text-center tracking-normal">#</th>
            <th class="text-left">Zawodnik</th>
            <th class="w-14 text-center tracking-normal">Pkt</th>
            <th class="w-14 text-center tracking-normal">180</th>
            <th class="w-14 text-center tracking-normal">170+</th>
            <th class="w-12 text-center tracking-normal">QF</th>
            <th class="w-12 text-center tracking-normal">HF</th>
            <th class="text-center tracking-normal whitespace-nowrap">Best QF</th>
            <th class="text-center tracking-normal whitespace-nowrap">Best HF</th>
        </tr>
        </thead>
        <tbody>
        @forelse($standings as $row)
            <tr>
                <td class="score-num text-center">{{ $row->place }}</td>
                <td class="font-medium text-text whitespace-nowrap">
                    @if($row->user_id)
                        <a href="{{ route('players.show', $row->player_id) }}" class="text-text hover:text-accent hover:underline transition-colors">
                            {{ $row->player_name }}
                        </a>
                    @else
                        {{ $row->player_name }}
                    @endif
                </td>
                <td class="score-num text-center">{{ $row->points }}</td>
                <td class="score-num text-center">{{ $row->count_max }}</td>
                <td class="score-num text-center">{{ $row->count_170_plus }}</td>
                <td class="score-num text-center">{{ $row->count_qf }}</td>
                <td class="score-num text-center">{{ $row->count_hf }}</td>
                <td class="score-num text-center">{{ $row->best_qf !== null ? $row->best_qf.' lotek' : '—' }}</td>
                <td class="score-num text-center">{{ $row->best_hf !== null ? $row->best_hf : '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-3 py-4 text-center text-text-muted">
                    Brak wyników w sezonie — pojawią się po zakończeniu turniejów.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
