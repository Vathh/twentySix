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
        <div class="card mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-text">{{ $player->name }}</h1>
                    @if($player->user_id && $player->user)
                        <p class="text-text-secondary mt-2">Zarejestrowany od {{ $player->user->created_at->format('d.m.Y') }}</p>
                    @else
                        <p class="text-text-muted mt-2">Gracz gość</p>
                    @endif
                </div>
                @auth
                    @if($canInviteFriend)
                        <form action="{{ route('players.add-friend', $player) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="btn btn-mini">Dodaj do znajomych</button>
                        </form>
                    @elseif($isFriend)
                        <span class="text-accent font-semibold">Znajomy</span>
                    @elseif($pendingSentInvitation)
                        <span class="text-accent font-semibold">Zaproszenie wysłane</span>
                    @elseif($pendingReceivedInvitation)
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-accent text-sm">Zaproszenie od tego gracza</span>
                            <form action="{{ route('friends.invitations.accept', $pendingReceivedInvitation->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-mini">Akceptuj</button>
                            </form>
                            <form action="{{ route('friends.invitations.reject', $pendingReceivedInvitation->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-mini border border-accent text-accent bg-transparent hover:bg-accent/10">Odrzuć</button>
                            </form>
                        </div>
                    @endif
                @endauth
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Zakładki --}}
        <div class="flex gap-2 mb-6 border-b border-border pb-2">
            <button type="button"
                    @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-success-muted text-success-bright border-border' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-4 py-2 rounded-t border font-medium transition">
                Przegląd
            </button>
            <button type="button"
                    @click="activeTab = 'history'"
                    :class="activeTab === 'history' ? 'bg-success-muted text-success-bright border-border' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-4 py-2 rounded-t border font-medium transition">
                Historia meczów
            </button>
        </div>

        {{-- Zakładka: Przegląd --}}
        <div x-show="activeTab === 'overview'" class="space-y-8">
            {{-- Statystyki: mecze szybkie --}}
            <section>
                <h2 class="text-xl font-bold text-accent mb-4">Statystyki – mecze szybkie</h2>
                <div class="bg-bg-elevated rounded-lg p-6 border border-border overflow-x-auto">
                    <table class="w-full text-left text-text-secondary">
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
                <h2 class="text-xl font-bold text-accent mb-4">Statystyki – turnieje</h2>
                <div class="bg-bg-elevated rounded-lg p-6 border border-border overflow-x-auto">
                    <table class="w-full text-left text-text-secondary">
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
                <h2 class="text-xl font-bold text-accent mb-4">Ostatnie mecze</h2>
                <div class="bg-bg-elevated rounded-lg border border-border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-text-secondary">
                            <thead>
                                <tr class="border-b border-border bg-bg-deep/50">
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
                                    <tr class="border-b border-border/50 hover:bg-bg-deep/30 transition">
                                        <td class="px-4 py-3" x-text="m.date_formatted"></td>
                                        <td class="px-4 py-3" x-text="typeLabel(m.type)"></td>
                                        <td class="px-4 py-3" x-text="m.opponents"></td>
                                        <td class="px-4 py-3">
                                            <span :class="m.result === 'wygrana' ? 'text-accent font-semibold' : 'text-text-muted'" x-text="m.result"></span>
                                        </td>
                                        <td class="px-4 py-3" x-text="m.score || '–'"></td>
                                        <td class="px-4 py-3" x-text="m.tournament_name || '–'"></td>
                                    </tr>
                                </template>
                                <tr x-show="gameHistory.items.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-text-muted">Brak meczów w historii.</td>
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
