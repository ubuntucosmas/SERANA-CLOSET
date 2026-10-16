// Photos the Studio shows for an order: product shots (shop) or the client's
// references (bespoke / drop), then any progress photos. Always builds a new
// array: pushing into the order's own reactive list during render re-triggered
// the render forever and froze the Studio on bespoke orders with progress photos.
export function orderImages(order) {
    if (!order) return [];
    const imgs = order.type === 'standard'
        ? (order.items_with_urls || []).map(i => i.image_url).filter(Boolean)
        : [...(order.inspiration_urls || [])];
    if (order.progress_snaps?.length) imgs.push(...order.progress_snaps.map(s => s.image_url).filter(Boolean));
    return [...new Set(imgs)];
}
