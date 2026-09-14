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
            if (type === 'quick') return 'Szybkie';
            if (type === 'group' || type === 'playoff') return 'Turniej';
            if (type === 'league') return 'Liga';
            if (type === 'training') return 'Trening';
            return type || '–';
        },
        typeTone(type) {
            if (type === 'quick') return 'quick';
            if (type === 'league') return 'league';
            if (type === 'training') return 'training';
            if (type === 'group' || type === 'playoff') return 'tournament';
            return 'muted';
        },
        resultLabel(result) {
            if (result === 'wygrana') return 'Wygrana';
            if (result === 'porażka') return 'Porażka';
            return result || '–';
        },
        formatScore(score) {
            return score ? String(score).replaceAll(' : ', '–') : '';
        },
        historyDate(formatted) {
            return String(formatted || '').split(' ')[0] || '–';
        },
        historyTime(formatted) {
            return String(formatted || '').split(' ')[1] || '';
        },
        gameUrl(m) {
            if (m?.type === 'league' && m?.id) {
                return '{{ url('/league-games') }}/' + m.id;
            }
            if (!m?.id || !['quick', 'group', 'playoff'].includes(m.type)) {
                return null;
            }
            return '{{ url('/games') }}/' + m.type + '/' + m.id;
        },
        openGame(m) {
            const url = this.gameUrl(m);
            if (url) {
                window.location.href = url;
            }
        }
    }));
});
</script>
@endsection

@section('content')
    <div class="py-6 sm:py-8" x-data="playerProfileData()">
        @include('players.partials.profile-hero')

        {{-- Zakładki --}}
        <div class="flex gap-2 mb-6 border-b border-border pb-2 overflow-x-auto">
            <button type="button"
                    @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-success-muted text-success-bright border-border' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-4 py-2 rounded-t border font-medium transition whitespace-nowrap shrink-0">
                Przegląd
            </button>
            <button type="button"
                    @click="activeTab = 'history'"
                    :class="activeTab === 'history' ? 'bg-success-muted text-success-bright border-border' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-4 py-2 rounded-t border font-medium transition whitespace-nowrap shrink-0">
                Historia meczów
            </button>
            <button type="button"
                    @click="activeTab = 'stats'"
                    :class="activeTab === 'stats' ? 'bg-success-muted text-success-bright border-border' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-4 py-2 rounded-t border font-medium transition whitespace-nowrap shrink-0">
                Statystyki
            </button>
            <button type="button"
                    @click="activeTab = 'badges'"
                    :class="activeTab === 'badges' ? 'bg-success-muted text-success-bright border-border' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-4 py-2 rounded-t border font-medium transition whitespace-nowrap shrink-0">
                Odznaczenia
            </button>
        </div>

        {{-- Zakładka: Przegląd --}}
        <div x-show="activeTab === 'overview'" x-cloak class="space-y-8">
            @include('players.partials.stats-tables')
            @include('players.partials.overview-record')
            @include('players.partials.overview-activity')
        </div>

        {{-- Zakładka: Statystyki --}}
        <div x-show="activeTab === 'stats'" x-cloak>
            @include('players.partials.career-dashboard')
        </div>

        {{-- Zakładka: Historia meczów --}}
        <div x-show="activeTab === 'history'" x-cloak>
            @include('players.partials.game-history')
        </div>

        {{-- Zakładka: Odznaczenia --}}
        <div x-show="activeTab === 'badges'" x-cloak>
            @include('players.partials.overview-badges')
        </div>
    </div>
@endsection
