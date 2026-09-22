const EMPTY_CELL = '-';

function samePlayer(visit, playerId) {
    if (playerId == null || visit?.playerId == null) {
        return false;
    }
    return Number(visit.playerId) === Number(playerId);
}

function isVisitComplete(visit) {
    return Boolean(
        visit?.bust
        || visit?.closedLeg
        || (visit?.dartsInVisit != null && visit.dartsInVisit >= 3),
    );
}

function formatThrown(visit) {
    if (!visit) {
        return EMPTY_CELL;
    }
    if (visit.bust) {
        return 'Bust';
    }
    return String(visit.score ?? 0);
}

function formatRemaining(visit) {
    if (!visit) {
        return EMPTY_CELL;
    }
    if (visit.remainingAfter != null && visit.remainingAfter !== '') {
        return String(visit.remainingAfter);
    }
    if (visit.bust && visit.remainingBefore != null && visit.remainingBefore !== '') {
        return String(visit.remainingBefore);
    }
    return EMPTY_CELL;
}

/**
 * Paruje wizyty H2H w rundy. Najnowsza runda pierwsza.
 * Ta sama kolejność kolumn co tabela na tablecie.
 */
export function buildH2hLegVisitRows(visits, leftPlayerId, rightPlayerId) {
    const rounds = [];
    let current = { left: null, right: null };

    const flush = () => {
        if (current.left || current.right) {
            rounds.push(current);
            current = { left: null, right: null };
        }
    };

    for (const visit of visits ?? []) {
        if (!isVisitComplete(visit)) {
            continue;
        }
        const side = samePlayer(visit, leftPlayerId)
            ? 'left'
            : (samePlayer(visit, rightPlayerId) ? 'right' : null);
        if (!side) {
            continue;
        }
        if (current[side]) {
            flush();
        }
        current[side] = visit;
    }
    flush();

    return [...rounds].reverse().map((round, displayIndex) => {
        const roundFromStart = rounds.length - displayIndex;
        return {
            key: `${round.left?.id ?? 'x'}-${round.right?.id ?? 'x'}-${roundFromStart}`,
            leftThrown: formatThrown(round.left),
            leftRemaining: formatRemaining(round.left),
            darts: roundFromStart * 3,
            rightRemaining: formatRemaining(round.right),
            rightThrown: formatThrown(round.right),
            leftBust: Boolean(round.left?.bust),
            rightBust: Boolean(round.right?.bust),
        };
    });
}
