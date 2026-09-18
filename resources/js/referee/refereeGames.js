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
        selectedPlayoffSide: null,

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

        get mainPlayoffGames() {
            return this.playoffGames.filter((g) => g.bracketSide !== 'consolation');
        },

        get consolationPlayoffGames() {
            return this.playoffGames.filter((g) => g.bracketSide === 'consolation');
        },

        get hasSplitPlayoff() {
            return this.mainPlayoffGames.length > 0 && this.consolationPlayoffGames.length > 0;
        },

        get playoffSides() {
            if (!this.hasSplitPlayoff) {
                return [];
            }
            return [
                { id: 'main', title: 'Drabinka główna' },
                { id: 'consolation', title: 'Drabinka pocieszenia' },
            ];
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

        get gamesInSelectedPlayoffSide() {
            if (this.selectedPlayoffSide === 'consolation') {
                return this.consolationPlayoffGames;
            }
            if (this.selectedPlayoffSide === 'main') {
                return this.mainPlayoffGames;
            }
            return [];
        },

        get modalGames() {
            return this.selectedPlayoffSide != null
                ? this.gamesInSelectedPlayoffSide
                : this.gamesInSelectedGroup;
        },

        get modalTitle() {
            if (this.selectedPlayoffSide === 'consolation') {
                return 'Drabinka pocieszenia';
            }
            if (this.selectedPlayoffSide === 'main') {
                return 'Drabinka główna';
            }
            if (this.selectedGroup != null) {
                return `Grupa ${this.selectedGroup}`;
            }
            return 'Wybierz mecz';
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
            this.selectedPlayoffSide = null;
            this.selectedGroup = group;
        },

        openPlayoffSide(side) {
            this.selectedGroup = null;
            this.selectedPlayoffSide = side;
        },

        closeGroup() {
            this.selectedGroup = null;
            this.selectedPlayoffSide = null;
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
