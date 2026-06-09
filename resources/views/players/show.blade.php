@extends('layouts.app')

@section('title', 'Profil – ' . $player->name)

@section('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('playerProfileData', () => ({
        activeTab: 'overview',
        gameHistory: {
            items: @json($gameHistoryItems),
            hasMore: @json($gameHistoryHasMore),
            page: 1,
            loading: false
        },
        loadMoreGames() {
            if (this.gameHistory.loading || !this.gameHistory.hasMore) return;
            this.gameHistory.loading = true;
            fetch('{{ route('players.games', $player) }}?page=' + (this.gameHistory.page + 1), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                this.gameHistory.items = this.gameHistory.items.concat(data.items);
                this.gameHistory.hasMore = data.has_more;
                this.gameHistory.page++;
            })
            .finally(() => { this.gameHistory.loading = false; });
        },
        typeLabel(type) {
            if (type === 'quick') return 'Szybki mecz';
            if (type === 'group') return 'Grupa';
            if (type === 'playoff') return 'Play-off';
            return type;
        }
    }));
});
</script>
@endsection

@section('content')
    <div class="py-8" x-data="playerProfileData()">
        {{-- Nagłówek profilu --}}
        <div class="bg-lighter-bg rounded-lg p-6 mb-6 border border-border">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-light-green">{{ $player->name }}</h1>
                    @if($player->user_id && $player->user)
                        <p class="text-light-white mt-2">Zarejestrowany od {{ $player->user->created_at->format('d.m.Y') }}</p>
                    @else
                        <p class="text-light-gray mt-2">Gracz gość</p>
                    @endif
                </div>
                @auth
                    @if($canAddFriend)
                        <form action="{{ route('players.add-friend', $player) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="btn btn-mini">Dodaj do znajomych</button>
                        </form>
                    @elseif($isFriend)
                        <span class="text-light-green font-semibold">Znajomy</span>
                    @endif
                @endauth
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-900/50 border border-green-600 rounded text-light-green">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-900/50 border border-red-600 rounded text-light-red">{{ session('error') }}</div>
        @endif

        {{-- Zakładki --}}
        <div class="flex gap-2 mb-6 border-b border-border pb-2">
            <button type="button"
                    @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-light-green/20 text-light-green border-light-green' : 'border-border text-light-white hover:bg-lighter-bg'"
                    class="px-4 py-2 rounded-t border font-medium transition">
                Przegląd
            </button>
            <button type="button"
                    @click="activeTab = 'history'"
                    :class="activeTab === 'history' ? 'bg-light-green/20 text-light-green border-light-green' : 'border-border text-light-white hover:bg-lighter-bg'"
                    class="px-4 py-2 rounded-t border font-medium transition">
                Historia meczów
            </button>
        </div>

        {{-- Zakładka: Przegląd --}}
        <div x-show="activeTab === 'overview'" class="space-y-8">
            {{-- Statystyki: mecze szybkie --}}
            <section>
                <h2 class="text-xl font-bold text-light-orange mb-4">Statystyki – mecze szybkie</h2>
                <div class="bg-lighter-bg rounded-lg p-6 border border-border overflow-x-auto">
                    <table class="w-full text-left text-light-white">
                        <thead>
                            <tr class="border-b border-border">
                                <th class="pb-2 pr-4">Metryka</th>
                                <th class="pb-2">Wartość</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Rozegrane mecze</td><td>{{ $quickStats['games'] }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Średnia (3 lotki)</td><td>{{ $quickStats['avg_three_darts'] ?? '–' }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Najwyższy finish (HF)</td><td>{{ $quickStats['highest_hf'] ?? '–' }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Najszybsza lotka (QF)</td><td>{{ $quickStats['fastest_qf'] !== null ? $quickStats['fastest_qf'] . ' lotek' : '–' }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Ilość 180 (max)</td><td>{{ $quickStats['count_max'] }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Ilość 170+ (bez 180)</td><td>{{ $quickStats['count_170_plus'] }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Ilość finishów 100+ (HF)</td><td>{{ $quickStats['count_hf'] }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Ilość szybkich lotek (QF)</td><td>{{ $quickStats['count_qf'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Statystyki: turnieje --}}
            <section>
                <h2 class="text-xl font-bold text-light-orange mb-4">Statystyki – turnieje</h2>
                <div class="bg-lighter-bg rounded-lg p-6 border border-border overflow-x-auto">
                    <table class="w-full text-left text-light-white">
                        <thead>
                            <tr class="border-b border-border">
                                <th class="pb-2 pr-4">Metryka</th>
                                <th class="pb-2">Wartość</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Rozegrane mecze</td><td>{{ $tournamentStats['games'] }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Średnia (3 lotki)</td><td>{{ $tournamentStats['avg_three_darts'] ?? '–' }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Najwyższy finish (HF)</td><td>{{ $tournamentStats['highest_hf'] ?? '–' }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Najszybsza lotka (QF)</td><td>{{ $tournamentStats['fastest_qf'] !== null ? $tournamentStats['fastest_qf'] . ' lotek' : '–' }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Ilość 180 (max)</td><td>{{ $tournamentStats['count_max'] }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Ilość 170+ (bez 180)</td><td>{{ $tournamentStats['count_170_plus'] }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Ilość finishów 100+ (HF)</td><td>{{ $tournamentStats['count_hf'] }}</td></tr>
                            <tr class="border-b border-border/50"><td class="py-2 pr-4">Ilość szybkich lotek (QF)</td><td>{{ $tournamentStats['count_qf'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- Zakładka: Historia meczów --}}
        <div x-show="activeTab === 'history'" x-cloak>
            <section>
                <h2 class="text-xl font-bold text-light-orange mb-4">Ostatnie mecze</h2>
                <div class="bg-lighter-bg rounded-lg border border-border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-light-white">
                            <thead>
                                <tr class="border-b border-border bg-darker-bg/50">
                                    <th class="px-4 py-3">Data</th>
                                    <th class="px-4 py-3">Typ</th>
                                    <th class="px-4 py-3">Przeciwnik / przeciwnicy</th>
                                    <th class="px-4 py-3">Wynik</th>
                                    <th class="px-4 py-3">Score</th>
                                    <th class="px-4 py-3">Turniej</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(m, i) in gameHistory.items" :key="i">
                                    <tr class="border-b border-border/50 hover:bg-darker-bg/30 transition">
                                        <td class="px-4 py-3" x-text="m.date_formatted"></td>
                                        <td class="px-4 py-3" x-text="typeLabel(m.type)"></td>
                                        <td class="px-4 py-3" x-text="m.opponents"></td>
                                        <td class="px-4 py-3">
                                            <span :class="m.result === 'wygrana' ? 'text-light-green font-semibold' : 'text-light-gray'" x-text="m.result"></span>
                                        </td>
                                        <td class="px-4 py-3" x-text="m.score || '–'"></td>
                                        <td class="px-4 py-3" x-text="m.tournament_name || '–'"></td>
                                    </tr>
                                </template>
                                <tr x-show="gameHistory.items.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-light-gray">Brak meczów w historii.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-border flex justify-center">
                        <button type="button"
                                @click="loadMoreGames()"
                                x-show="gameHistory.hasMore"
                                :disabled="gameHistory.loading"
                                class="btn btn-mini disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-text="gameHistory.loading ? 'Ładowanie…' : 'Załaduj więcej'"></span>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
