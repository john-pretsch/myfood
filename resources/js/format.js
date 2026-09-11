const FRACTION_DENOMINATORS = [2, 3, 4, 8];
const TOLERANCE = 0.02;

function gcd(a, b) {
    return b === 0 ? a : gcd(b, a % b);
}

/**
 * Renders a decimal quantity as a mixed number using common cooking
 * fractions (halves, thirds, quarters, eighths), e.g. 0.333 -> "1/3",
 * 1.5 -> "1 1/2". Falls back to a trimmed decimal when no close fraction
 * is found.
 */
export function formatQuantity(value) {
    if (value === null || value === undefined || value === '') return '';

    const num = Number(value);
    if (!Number.isFinite(num)) return value;

    const whole = Math.trunc(num);
    const frac = Math.abs(num - whole);

    if (frac < TOLERANCE) return String(whole);

    let best = null;
    for (const d of FRACTION_DENOMINATORS) {
        const n = Math.round(frac * d);
        if (n === 0 || n === d) continue;
        const error = Math.abs(frac - n / d);
        if (!best || error < best.error) best = { n, d, error };
    }

    if (!best || best.error > TOLERANCE) {
        const rounded = Math.round(num * 100) / 100;
        return String(rounded);
    }

    const divisor = gcd(best.n, best.d);
    const n = best.n / divisor;
    const d = best.d / divisor;
    const fraction = `${n}/${d}`;

    return whole ? `${whole} ${fraction}` : fraction;
}
