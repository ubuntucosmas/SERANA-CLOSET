// A set can share its top or bottom with another bag line. Count each part
// across the whole bag, rather than treating each size combination as stock.
export function canAddPosLine(lines, candidate, quantity = 1) {
    const ids = candidate.variant_ids || [];
    if (!ids.length) return false;
    return ids.every(id => {
        const used = lines.filter(line => line.variant_ids?.includes(id))
            .reduce((sum, line) => sum + Number(line.quantity || 0), 0);
        const limits = [candidate, ...lines.filter(line => line.variant_ids?.includes(id))]
            .map(line => line.variant_limits?.[id] ?? line.available ?? Infinity);
        return used + quantity <= Math.min(...limits);
    });
}
