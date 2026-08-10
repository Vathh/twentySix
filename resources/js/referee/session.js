const STORAGE_KEY = 'twentysix.referee';

/**
 * @returns {{ token: string, tournamentId: number } | null}
 */
export function loadRefereeSession() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return null;
        }
        const data = JSON.parse(raw);
        if (!data?.token || data.tournamentId == null) {
            return null;
        }
        return {
            token: String(data.token),
            tournamentId: Number(data.tournamentId),
        };
    } catch {
        return null;
    }
}

/**
 * @param {{ token: string, tournamentId: number }} session
 */
export function saveRefereeSession(session) {
    sessionStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
            token: session.token,
            tournamentId: Number(session.tournamentId),
        }),
    );
}

export function clearRefereeSession() {
    sessionStorage.removeItem(STORAGE_KEY);
}

export function refereeLoginUrl() {
    return '/referee/login';
}

export function requireRefereeSessionOrRedirect() {
    const session = loadRefereeSession();
    if (!session) {
        window.location.replace(refereeLoginUrl());
        return null;
    }
    return session;
}
