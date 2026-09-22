import {
    clearRefereeSession,
    refereeLoginUrl,
    requireRefereeSessionOrRedirect,
} from './session.js';
import { refereeFetch, RefereeApiError } from './api.js';
import { buildGroupMatrix, groupRefereeSlots, playerNamesFromStandings } from './groupMatrix.js';

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
        remainingGroups: [],
        loading: true,
        lockingId: null,
        error: '',
        selectedGroup: null,
        selectedPlayoffSide: null,
        groupPane: 'matches',
        groupBoardFull: false,

        init() {
            this.session = requireRefereeSessionOrRedirect();
            if (!this.session) {
                return;
            }
            this.syncGroupBoardMode();
            window.addEventListener('resize', () => this.syncGroupBoardMode());
            this.$watch('selectedGroup', (value) => {
                document.body.style.overflow = value != null ? 'hidden' : '';
            });
            this.fetchGames();
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
            return this.remainingGroups
                .map((g) => g.groupNumber)
                .filter((n) => n != null)
                .sort((a, b) => a - b);
        },

        groupPlayerNames(groupNumber) {
            const group = this.remainingGroups.find((g) => g.groupNumber === groupNumber);
            return playerNamesFromStandings(group?.standings).join(', ');
        },

        get selectedGroupData() {
            if (this.selectedGroup == null) {
                return null;
            }
            return this.remainingGroups.find((g) => g.groupNumber === this.selectedGroup) ?? null;
        },

        get selectedGroupMatrix() {
            if (!this.selectedGroupData) {
                return { columns: [], rows: [] };
            }
            return buildGroupMatrix(this.selectedGroupData, { playableUnfinished: true });
        },

        get groupReferees() {
            return groupRefereeSlots(this.selectedGroupData?.games);
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

        get isGroupModal() {
            return this.selectedGroup != null && this.selectedPlayoffSide == null;
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
                const [active, groups] = await Promise.all([
                    refereeFetch(
                        `/api/game/active?tournamentId=${this.session.tournamentId}`,
                        { token: this.session.token },
                    ),
                    refereeFetch(
                        `/api/game/remaining-groups?tournamentId=${this.session.tournamentId}`,
                        { token: this.session.token },
                    ),
                ]);
                this.games = Array.isArray(active)
                    ? active.filter((g) => g.type === 'playoff')
                    : [];
                this.remainingGroups = Array.isArray(groups) ? groups : [];
            } catch (e) {
                if (e instanceof RefereeApiError && e.status === 401) {
                    await this.handleUnauthorized();
                    return;
                }
                this.error = e.message || 'Nie udało się pobrać listy meczów.';
                this.games = [];
                this.remainingGroups = [];
            } finally {
                this.loading = false;
                this.syncGroupBoardMode();
            }
        },

        groupColumnKind(key) {
            if (key === 'player') {
                return 'group-col-player';
            }
            if (String(key).startsWith('vs_')) {
                return 'group-col-match';
            }
            return 'group-col-standings';
        },

        syncGroupBoardMode() {
            const players = (this.selectedGroupMatrix?.columns ?? []).filter((column) =>
                String(column.key).startsWith('vs_'),
            ).length;
            const needed = 24 + 150 + players * 52 + 250;
            this.groupBoardFull = players > 0 && window.innerWidth >= needed;
        },

        openGroup(group) {
            this.selectedPlayoffSide = null;
            this.groupPane = 'matches';
            this.selectedGroup = group;
            this.syncGroupBoardMode();
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
            if (!this.session || this.lockingId != null || !game) {
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
