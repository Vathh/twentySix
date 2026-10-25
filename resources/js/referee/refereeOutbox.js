/**
 * Kolejka komend sędziego w przeglądarce (sessionStorage).
 * Ten sam kontrakt co tablet: FIFO, jeden pisarz.
 */

export function refereeOutboxKey(type, gameId) {
    return `scoring-outbox:tournament:${type}:${gameId}`;
}

export function loadRefereeOutbox(key) {
    try {
        const raw = sessionStorage.getItem(key);
        if (!raw) {
            return [];
        }
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function saveRefereeOutbox(key, entries) {
    if (!entries?.length) {
        sessionStorage.removeItem(key);
        return;
    }
    sessionStorage.setItem(key, JSON.stringify(entries));
}

export function clearRefereeOutbox(key) {
    sessionStorage.removeItem(key);
}

export function enqueueRefereeOutbox(key, entry) {
    const next = loadRefereeOutbox(key);
    next.push({
        ...entry,
        createdAt: entry.createdAt ?? Date.now(),
    });
    saveRefereeOutbox(key, next);
    return next;
}

export function dequeueRefereeOutbox(key) {
    const list = loadRefereeOutbox(key);
    const rest = list.slice(1);
    saveRefereeOutbox(key, rest);
    return rest;
}

export function isRetryableRefereeError(error) {
    if (!error) {
        return false;
    }
    if (error.name === 'AbortError' || error instanceof TypeError) {
        return true;
    }
    const status = error.status;
    return status >= 500 || status === 408 || status === 429 || status === 0;
}

export function patchRefereeState(state, entry) {
    if (!state || !entry) {
        return state;
    }
    if (entry.op === 'undoVisit') {
        const visits = state.visits ?? [];
        if (visits.length === 0) {
            return state;
        }
        const removed = visits[visits.length - 1];
        const turn = state.turn ?? { currentPlayerIndex: 0 };
        const backIndex = removed.bust
            ? turn.currentPlayerIndex
            : (Number(turn.currentPlayerIndex ?? 0) === 0 ? 1 : 0);
        return {
            ...state,
            visits: visits.slice(0, -1),
            turn: { ...turn, currentPlayerIndex: backIndex },
            players: (state.players ?? []).map((player) =>
                Number(player.playerId) === Number(removed.playerId)
                    ? { ...player, remaining: removed.remainingBefore ?? player.remaining }
                    : player,
            ),
        };
    }
    if (entry.op === 'closeLeg' && state.currentLeg) {
        return {
            ...state,
            currentLeg: null,
            pendingClosedLegId: state.currentLeg.id,
        };
    }
    if (entry.op !== 'recordVisit' || !entry.payload) {
        return state;
    }
    const payload = entry.payload;
    const remaining = payload.bust ? payload.remainingBefore : payload.remainingAfter;
    const turn = state.turn ?? { currentPlayerIndex: 0, legOpenerIndex: 0 };
    const nextIndex = payload.bust
        ? turn.currentPlayerIndex
        : (Number(turn.currentPlayerIndex ?? 0) === 0 ? 1 : 0);
    return {
        ...state,
        visits: [...(state.visits ?? []), payload],
        turn: { ...turn, currentPlayerIndex: nextIndex },
        players: (state.players ?? []).map((player) =>
            Number(player.playerId) === Number(payload.playerId)
                ? { ...player, remaining }
                : player,
        ),
    };
}

export function replayRefereeEntries(state, entries) {
    return (entries ?? []).reduce(
        (next, entry) => patchRefereeState(next, entry) ?? next,
        state,
    );
}

export function bindRefereeLegId(key, legId) {
    const list = loadRefereeOutbox(key);
    let binding = true;
    const next = list.map((entry) => {
        if (!binding) {
            return entry;
        }
        if (entry.op === 'startLeg') {
            binding = false;
            return entry;
        }
        if (entry.legId == null) {
            return { ...entry, legId };
        }
        return entry;
    });
    saveRefereeOutbox(key, next);
    return next;
}

export function isLegAlreadyOpenMessage(error) {
    return String(error?.message || '').toLowerCase().includes('otwarty');
}

export function commandAlreadyApplied(entry, snapshot, { legAlreadyOpen = false } = {}) {
    if (!entry || !snapshot) {
        return false;
    }
    if (entry.op === 'startLeg' && legAlreadyOpen && snapshot.currentLeg?.id) {
        return true;
    }
    if (entry.op === 'closeLeg') {
        const legs = snapshot.legs ?? [];
        return legs.some((leg) => Number(leg.id) === Number(entry.legId));
    }
    return false;
}

export function rejectionNotice(droppedTail, op = null) {
    if (op === 'undoVisit') {
        return 'Nie udało się cofnąć wizyty. Tablica pokazuje zapisany stan meczu.';
    }
    if (droppedTail > 0) {
        return `Serwer odrzucił wizytę. Cofnięto tablicę do zapisanego stanu. Niezapisane kolejne wpisy: ${droppedTail}. Wpisz je ponownie.`;
    }
    return 'Serwer odrzucił wizytę. Tablica pokazuje zapisany stan meczu.';
}
