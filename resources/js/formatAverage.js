/**
 * Średnia 3-dartowa — zawsze xx.xx, nawet gdy część ułamkowa to zera (72 → "72.00").
 */
export function formatAverage(value, empty = '—') {
    if (value == null || value === '' || value === '-' || value === '–' || value === '—') {
        return empty;
    }
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return empty;
    }

    return n.toFixed(2);
}
