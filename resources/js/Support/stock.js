// One availability rule for every storefront surface. The server sends
// available_stock (batch limit minus sold minus unpaid holds; null = unlimited),
// so a piece held by another shopper's checkout reads sold out here instead
// of failing at payment. batch_sold is the fallback for older payloads.
export function availableStock(product) {
    if (!product || product.batch_limit == null) return null;
    if (product.available_stock != null) return Math.max(0, Number(product.available_stock));
    return Math.max(0, Number(product.batch_limit) - Number(product.batch_sold || 0));
}

export function isSoldOut(product) {
    if (!product || product.in_stock === false) return true;
    const left = availableStock(product);
    return left !== null && left <= 0;
}

// "Only 2 left" for the last few units of a limited batch; '' otherwise.
export function lowStockNote(product, threshold = 3) {
    if (isSoldOut(product)) return '';
    const left = availableStock(product);
    return left !== null && left <= threshold ? `Only ${left} left` : '';
}
