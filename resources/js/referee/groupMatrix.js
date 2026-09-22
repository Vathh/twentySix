import { formatAverage } from '../formatAverage.js';

export function shortPlayerLabel(name) {
    const text = String(name ?? '').trim();
    if (text.length <= 10) {
        return text || '—';
    }
    return `${text.slice(0, 9)}…`;
}

function isFinishedStatus(status) {
    return status === 'finished';
}

function scoreForRow(game, rowPlayerId) {
    const s1 = game.score1 ?? 0;
    const s2 = game.score2 ?? 0;
    if (Number(game.player1?.id) === Number(rowPlayerId)) {
        return `${s1} - ${s2}`;
    }
    return `${s2} - ${s1}`;
}

function averageLabel(game, rowPlayerId) {
    if (!game) {
        return null;
    }
    const isP1 = Number(game.player1?.id) === Number(rowPlayerId);
    const value = isP1 ? game.player1Average : game.player2Average;
    if (value == null || value === '' || Number.isNaN(Number(value))) {
        return null;
    }
    return formatAverage(value);
}

export function matrixCellForPair(game, rowPlayerId, { playableUnfinished = false } = {}) {
    if (!game) {
        return { text: '—', average: null, sequence: null, playable: false, game: null };
    }
    if (isFinishedStatus(game.status)) {
        return { text: scoreForRow(game, rowPlayerId), average: averageLabel(game, rowPlayerId), sequence: null, playable: false, game };
    }
    if (game.status === 'scheduled' && game.sequence != null) {
        return {
            text: String(game.sequence),
            average: null,
            sequence: game.sequence,
            playable: playableUnfinished,
            game,
        };
    }
    if (playableUnfinished) {
        return { text: '—', average: null, sequence: null, playable: true, game };
    }
    if (game.status === 'scheduled') {
        return { text: '—', average: null, sequence: null, playable: false, game };
    }
    return { text: scoreForRow(game, rowPlayerId), average: averageLabel(game, rowPlayerId), sequence: null, playable: false, game };
}

export function groupRefereeSlots(games) {
    return (games ?? [])
        .filter((game) => game.sequence != null && game.referee?.name)
        .slice()
        .sort((a, b) => a.sequence - b.sequence || a.id - b.id);
}

export function buildGroupMatrix(group, { playableUnfinished = false } = {}) {
    const standings = group?.standings ?? [];
    const games = group?.games ?? [];
    const byPair = new Map();
    games.forEach((game) => {
        const a = game.player1?.id;
        const b = game.player2?.id;
        if (a == null || b == null) {
            return;
        }
        byPair.set(`${a}-${b}`, game);
        byPair.set(`${b}-${a}`, game);
    });

    const columns = [
        { key: 'player', label: 'Zawodnik' },
        ...standings.map((row) => ({
            key: `vs_${row.playerId}`,
            label: shortPlayerLabel(row.playerName),
        })),
        { key: 'gamesWon', label: 'W' },
        { key: 'gamesLost', label: 'L' },
        { key: 'matchUnitsDifference', label: 'Wynik' },
        { key: 'points', label: 'Pkt' },
        { key: 'place', label: 'Pozycja' },
    ];

    const rows = standings.map((row) => {
        const next = {
            key: `p-${row.playerId}`,
            playerName: row.playerName,
            playerAverage: row.average == null || row.average === '' || Number.isNaN(Number(row.average))
                ? null
                : formatAverage(row.average),
            gamesWon: row.gamesWon,
            gamesLost: row.gamesLost,
            matchUnitsDifference: row.matchUnitsDifference,
            points: row.points,
            place: row.place,
            cells: [],
        };
        standings.forEach((col) => {
            if (row.playerId === col.playerId) {
                next.cells.push({
                    key: `vs_${col.playerId}`,
                    text: 'X',
                    playable: false,
                    game: null,
                    diagonal: true,
                });
                return;
            }
            const cell = matrixCellForPair(
                byPair.get(`${row.playerId}-${col.playerId}`),
                row.playerId,
                { playableUnfinished },
            );
            next.cells.push({
                key: `vs_${col.playerId}`,
                ...cell,
                diagonal: false,
            });
        });
        return next;
    });

    return { columns, rows };
}

export function playerNamesFromStandings(standings) {
    return (standings ?? [])
        .map((row) => row.playerName)
        .filter((name) => typeof name === 'string' && name.trim() !== '');
}
