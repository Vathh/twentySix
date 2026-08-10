import {
    clearRefereeSession,
    refereeLoginUrl,
    requireRefereeSessionOrRedirect,
} from './session.js';
import { refereeFetch, RefereeApiError } from './api.js';

const PLAYOFF_ROUND_ORDER = [
    'SIXTEEN',
    'EIGHT',
    'QUARTER',
    'SEMI',
    'THIRD',
    'FINAL',
];

function playoffSortKey(round) {
    const idx = PLAYOFF_ROUND_ORDER.indexOf(round);
    return idx >= 0 ? idx : 999;
}

export function registerRefereeGames(Alpine) {
    Alpine.data('refereeGames', (config) => ({
        session: null,
        games: [],
        loading: true,
        lockingId: null,
        error: '',
        selectedGroup: null,

        init() {
            this.session = requireRefereeSessionOrRedirect();
            if (!this.session) {
                return;
            }
            this.fetchGames();
        },

        get groupGames() {
            return this.games.filter((g) => (g.type || 'group') === 'group');
        },

        get playoffGames() {
            return this.games
                .filter((g) => g.type === 'playoff')
                .slice()
                .sort(
                    (a, b) =>
                        playoffSortKey(a.round) - playoffSortKey(b.round) ||
                        a.id - b.id,
                );
        },

        get groups() {
            const set = new Set(
                this.groupGames
                    .map((g) => g.groupNumber)
                    .filter((n) => n != null),
            );
            return [...set].sort((a, b) => a - b);
        },

        get gamesInSelectedGroup() {
            if (this.selectedGroup == null) {
                return [];
            }
            return this.groupGames.filter(
                (g) => g.groupNumber === this.selectedGroup,
            );
        },

        async handleUnauthorized() {
            clearRefereeSession();
            window.location.replace(refereeLoginUrl());
        },

        async fetchGames() {
            if (!this.session) {
                return;
            }
            this.loading = true;
            this.error = '';
            try {
                const data = await refereeFetch(
                    `/api/game/active?tournamentId=${this.session.tournamentId}`,
                    { token: this.session.token },
                );
                this.games = Array.isArray(data) ? data : [];
            } catch (e) {
                if (e instanceof RefereeApiError && e.status === 401) {
                    await this.handleUnauthorized();
                    return;
                }
                this.error = e.message || 'Nie udało się pobrać listy meczów.';
                this.games = [];
            } finally {
                this.loading = false;
            }
        },

        openGroup(group) {
            this.selectedGroup = group;
        },

        closeGroup() {
            this.selectedGroup = null;
        },

        async startGame(game) {
            if (!this.session || this.lockingId != null) {
                return;
            }
            const type = game.type === 'playoff' ? 'playoff' : 'group';
            this.lockingId = `${type}-${game.id}`;
            this.error = '';
            try {
                await refereeFetch('/api/game/inProgress', {
                    method: 'POST',
                    token: this.session.token,
                    body: { gameId: game.id, type },
                });
                const url = `${config.scoreUrl}?type=${encodeURIComponent(type)}&id=${encodeURIComponent(game.id)}`;
                window.location.assign(url);
            } catch (e) {
                if (e instanceof RefereeApiError && e.status === 401) {
                    await this.handleUnauthorized();
                    return;
                }
                this.error =
                    e.message ||
                    'Nie udało się rozpocząć meczu (może być już sędziowany).';
                await this.fetchGames();
            } finally {
                this.lockingId = null;
            }
        },

        logout() {
            clearRefereeSession();
            window.location.replace(refereeLoginUrl());
        },

        playerLabel(game) {
            const p1 = game.player1?.name ?? 'Gracz 1';
            const p2 = game.player2?.name ?? 'Gracz 2';
            return `${p1} – ${p2}`;
        },
    }));
}
