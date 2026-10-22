// One availability rule for every storefront surface. The server sends
// available_stock: units a shopper can still buy, after unpaid holds (null =
// unlimited, made to order). With stock per size it is the server's per-size
// total, and a tracked product may have no batch limit at all, so the server's
// figure wins whenever it is sent; track_stock false means made to order.
// batch_limit/batch_sold are the fallback for older payloads and saved bags.
export function availableStock(product) {
    if (!product) return null;
    if (product.available_stock != null) return Math.max(0, Number(product.available_stock));
    if (product.track_stock === false) return null;
    if (product.batch_limit == null) return null;
    return Math.max(0, Number(product.batch_limit) - Number(product.batch_sold || 0));
}

export function isSoldOut(product) {
    if (!product || product.in_stock === false) return true;
    const left = availableStock(product);
    return left !== null && left <= 0;
}

// "Only 2 left" for the last few units; '' otherwise.
export function lowStockNote(product, threshold = 3) {
    if (isSoldOut(product)) return '';
    const left = availableStock(product);
    return left !== null && left <= threshold ? `Only ${left} left` : '';
}

// Studio → Stock: what the shop will do with a product, in one sentence.
// A set sells only when at least one top size and one bottom size are available.
export function shopStatus(product) {
    if (!product?.track_stock) return { tone: 'ok', text: 'Made to order: always available in the shop.' };
    const active = (product.sizes || []).filter(size => size.active);
    const total = part => active.filter(size => size.part === part).reduce((n, size) => n + Math.max(0, Number(size.available) || 0), 0);
    if (product.garment_type === 'set') {
        const tops = total('top'), bottoms = total('bottom');
        if (!tops && !bottoms) return { tone: 'warn', text: 'Not in the shop yet: count its top and bottom sizes.' };
        if (!bottoms) return { tone: 'warn', text: 'Not in the shop yet: count at least one bottom size. A set needs a top and a bottom.' };
        if (!tops) return { tone: 'warn', text: 'Not in the shop yet: count at least one top size. A set needs a top and a bottom.' };
        return { tone: 'ok', text: `In the shop: up to ${Math.min(tops, bottoms)} set(s) can be sold.` };
    }
    const units = active.reduce((n, size) => n + Math.max(0, Number(size.available) || 0), 0);
    return units ? { tone: 'ok', text: `In the shop: ${units} available.` } : { tone: 'warn', text: 'Sold out in the shop: count or receive stock to sell it.' };
}
