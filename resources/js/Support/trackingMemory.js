// Remembers, on this device only, the phone number a customer last paid or
// tracked with, so a tracking link (receipt email, Your Orders, success
// screen) shows the order straight away instead of asking again. Only the
// number is kept; the order code always comes from the link. "Use a
// different number" on the tracking page forgets it.
const KEY = 'serana_track_phone';
const TTL_MS = 90 * 24 * 60 * 60 * 1000;

export function rememberTrackingPhone(phone) {
    if (!phone) return;
    try { localStorage.setItem(KEY, JSON.stringify({ phone: String(phone).trim(), at: Date.now() })); } catch { /* storage unavailable */ }
}

export function recallTrackingPhone() {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) || 'null');
        if (saved?.phone && Date.now() - saved.at < TTL_MS) return saved.phone;
    } catch { /* storage unavailable */ }
    return '';
}

export function forgetTrackingPhone() {
    try { localStorage.removeItem(KEY); } catch { /* storage unavailable */ }
}
