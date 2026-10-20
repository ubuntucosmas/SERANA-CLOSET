// Sizes on the product page (stock per size, Phase D1). The server sends each
// offered size per part with what is left of it (available: null = no count,
// made to order or the old model). A set is chosen in two rows: top and bottom.

const PARTS = { top: ['single', 'top'], bottom: ['bottom'] };
const DEFAULT_SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

export function sizesForRow(sizes, rowKey) {
    return (sizes || []).filter(s => (PARTS[rowKey] || []).includes(s.part));
}

export function isSizeSoldOut(size) {
    return size?.available != null && Number(size.available) <= 0;
}

// "Only 2 left in M" for the last few, "Sold out in L"; '' otherwise.
export function sizeNote(size, threshold = 3) {
    if (!size || size.available == null) return '';
    if (size.available <= 0) return `Sold out in ${size.size}`;
    return size.available <= threshold ? `Only ${size.available} left in ${size.size}` : '';
}

export function rowNotes(sizes) {
    return (sizes || []).map(s => sizeNote(s)).filter(Boolean).join(' · ');
}

// Older payloads (and the old model) without server sizes: the product's own list, no counts.
export function fallbackSizes(product) {
    const list = product?.specifications?.ui?.sizes || product?.specifications?.sizes || DEFAULT_SIZES;
    const type = product?.garment_type;
    const parts = type === 'set' ? ['top', 'bottom'] : type === 'jogger' ? ['bottom'] : ['single'];
    return parts.flatMap(part => list.map(size => ({ id: null, part, size, available: null })));
}
