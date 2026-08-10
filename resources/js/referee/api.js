/**
 * Thin fetch wrapper for tournament referee (Sanctum Bearer).
 */

async function parseJson(res) {
    const text = await res.text();
    try {
        return { data: text ? JSON.parse(text) : null, text };
    } catch {
        return { data: null, text };
    }
}

export class RefereeApiError extends Error {
    /**
     * @param {string} message
     * @param {number} status
     * @param {unknown} [data]
     */
    constructor(message, status, data = null) {
        super(message);
        this.name = 'RefereeApiError';
        this.status = status;
        this.data = data;
    }
}

/**
 * @param {string} url
 * @param {{ method?: string, token?: string|null, body?: unknown }} [opts]
 */
export async function refereeFetch(url, opts = {}) {
    const { method = 'GET', token = null, body } = opts;
    const headers = {
        Accept: 'application/json',
    };
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const res = await fetch(url, {
        method,
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    const { data, text } = await parseJson(res);
    if (!res.ok) {
        const message =
            (data && typeof data === 'object' && data.message) ||
            text ||
            `Błąd HTTP ${res.status}`;
        throw new RefereeApiError(String(message), res.status, data);
    }
    return data;
}

export function newClientVisitId() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export function scoringBaseUrl(type, gameId) {
    const prefix = type === 'playoff' ? 'playoff-games' : 'group-games';
    return `/api/${prefix}/${gameId}`;
}
