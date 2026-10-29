@extends('layouts.app')

@section('title', 'Profil – ' . $player->name)

@section('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('playerProfileData', () => ({
        activeTab: 'overview',
        feed: {
            extra: [],
            nextCursor: @json($profileFeed['nextCursor'] ?? null),
            loading: false,
            error: '',
            paused: false
        },
        init() {
            this.$nextTick(() => {
                if (typeof window.observeFeedEnd !== 'function') return;
                window.observeFeedEnd(this.$root.querySelector('[data-feed-end]'), () => this.loadOlderFeed());
            });
            this.$watch('activeTab', (tab) => {
                if (tab !== 'feed') return;
                this.$nextTick(() => {
                    const node = this.$root.querySelector('[data-feed-end]');
                    if (!node) return;
                    const rect = node.getBoundingClientRect();
                    if (rect.top <= window.innerHeight + 320) this.loadOlderFeed();
                });
            });
        },
        retryOlderFeed() {
            this.feed.paused = false;
            this.feed.error = '';
            this.loadOlderFeed();
        },
        loadOlderFeed() {
            if (this.feed.loading || this.feed.paused || !this.feed.nextCursor) return;
            this.feed.loading = true;
            this.feed.error = '';
            fetch('{{ route('players.feed', $player) }}?cursor=' + encodeURIComponent(this.feed.nextCursor), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => {
                if (!r.ok) throw new Error('feed');
                return r.json();
            })
            .then(data => {
                this.feed.extra = this.feed.extra.concat(data.items || []);
                this.feed.nextCursor = data.nextCursor || null;
            })
            .catch(() => {
                this.feed.error = 'Nie udało się wczytać starszych wpisów.';
                this.feed.paused = true;
            })
            .finally(() => { this.feed.loading = false; });
        },
        feedAgo(iso) {
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return '';
            const minutes = Math.round((Date.now() - date.getTime()) / 60000);
            if (minutes < 1) return 'przed chwilą';
            if (minutes < 60) return minutes + ' min temu';
            const hours = Math.round(minutes / 60);
            if (hours < 24) return hours + ' godz. temu';
            const days = Math.round(hours / 24);
            if (days === 1) return 'wczoraj';
            if (days < 30) return days + ' dni temu';
            return date.toLocaleDateString('pl-PL');
        },
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
        },
        openBadge(anchor) {
            this.activeTab = 'badges';
            const reveal = () => {
                const el = document.getElementById(anchor);
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.remove('is-jumped');
                void el.offsetWidth;
                el.classList.add('is-jumped');
            };
            this.$nextTick(() => requestAnimationFrame(() => requestAnimationFrame(reveal)));
        }
    }));
});
</script>
@endsection

@section('content')
    <div class="py-3 sm:py-4" x-data="playerProfileData()">
        @include('players.partials.profile-hero')

        {{-- Zakładki --}}
        <div class="profile-tabs">
            <button type="button"
                    @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-accent/20 text-accent border-accent/50' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-2.5 py-1 rounded-t border text-xs font-medium transition whitespace-nowrap shrink-0">
                Podsumowanie
            </button>
            <button type="button"
                    @click="activeTab = 'feed'"
                    :class="activeTab === 'feed' ? 'bg-accent/20 text-accent border-accent/50' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-2.5 py-1 rounded-t border text-xs font-medium transition whitespace-nowrap shrink-0">
                Tablica
            </button>
            <button type="button"
                    @click="activeTab = 'history'"
                    :class="activeTab === 'history' ? 'bg-accent/20 text-accent border-accent/50' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-2.5 py-1 rounded-t border text-xs font-medium transition whitespace-nowrap shrink-0">
                Historia meczów
            </button>
            <button type="button"
                    @click="activeTab = 'stats'"
                    :class="activeTab === 'stats' ? 'bg-accent/20 text-accent border-accent/50' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-2.5 py-1 rounded-t border text-xs font-medium transition whitespace-nowrap shrink-0">
                Statystyki
            </button>
            <button type="button"
                    @click="activeTab = 'badges'"
                    :class="activeTab === 'badges' ? 'bg-accent/20 text-accent border-accent/50' : 'border-border text-text-secondary hover:bg-bg-elevated'"
                    class="px-2.5 py-1 rounded-t border text-xs font-medium transition whitespace-nowrap shrink-0">
                Odznaczenia
            </button>
        </div>

        {{-- Zakładka: Podsumowanie --}}
        <div x-show="activeTab === 'overview'" x-cloak class="space-y-4">
            @include('players.partials.stats-tables')
            @include('players.partials.overview-record')
            @include('players.partials.overview-activity')
        </div>

        {{-- Zakładka: Tablica --}}
        <div x-show="activeTab === 'feed'" x-cloak>
            @include('players.partials.profile-feed')
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
