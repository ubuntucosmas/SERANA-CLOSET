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
