// Names a picked colour the way a shopper would read it ("Forest Green",
// not "#1f4d2b"). Finds the closest entry in a fashion-oriented palette by
// perceptual distance (CIE76 in Lab space), so near-blacks become "Jet Black"
// and soft browns "Camel" rather than whatever is nearest in raw RGB.
const PALETTE = [
    ['Jet Black', '#0a0a0a'], ['Onyx', '#1c1c1e'], ['Charcoal', '#36454f'], ['Graphite', '#474a51'],
    ['Slate Grey', '#708090'], ['Steel Grey', '#71797e'], ['Ash Grey', '#b2beb5'], ['Heather Grey', '#9a9a9a'],
    ['Silver', '#c0c0c0'], ['Light Grey', '#d3d3d3'], ['Pearl', '#eae0c8'], ['Optic White', '#f8f8f8'],
    ['Snow White', '#fffafa'], ['Ivory', '#fffff0'], ['Off White', '#f5f2e8'], ['Cream', '#f3e5ab'],
    ['Bone', '#e3dac9'], ['Ecru', '#c2b280'], ['Oatmeal', '#d8cbb0'], ['Sand', '#cbb994'],
    ['Desert Sand', '#e2cba3'], ['Beige', '#d9c4a1'], ['Stone', '#a89f91'], ['Taupe', '#8b8589'],
    ['Khaki', '#c3b091'], ['Camel', '#c19a6b'], ['Tan', '#d2b48c'], ['Caramel', '#a9713b'],
    ['Cognac', '#9a463d'], ['Rust', '#b7410e'], ['Terracotta', '#e2725b'], ['Cinnamon', '#7b3f00'],
    ['Mocha', '#6f4e37'], ['Chocolate', '#4b2e1e'], ['Espresso', '#3b2a20'], ['Coffee', '#5b3a29'],
    ['Chestnut', '#954535'], ['Mahogany', '#6c2e1f'], ['Burgundy', '#800020'], ['Wine', '#722f37'],
    ['Maroon', '#5c1a1b'], ['Oxblood', '#4a0000'], ['Cherry Red', '#b0102a'], ['Crimson', '#c8102e'],
    ['Scarlet', '#e62020'], ['Fire Red', '#d0201b'], ['Brick Red', '#a23b2c'], ['Coral', '#ff7f50'],
    ['Salmon', '#fa8072'], ['Peach', '#ffcba4'], ['Apricot', '#fbceb1'], ['Tangerine', '#f28500'],
    ['Burnt Orange', '#cc5500'], ['Orange', '#ff8c00'], ['Mustard', '#d4a017'], ['Ochre', '#cc7722'],
    ['Gold', '#d4af37'], ['Honey', '#e3a857'], ['Sunflower', '#ffc512'], ['Lemon', '#fff44f'],
    ['Butter Yellow', '#fff1a8'], ['Canary Yellow', '#ffef00'], ['Lime', '#a4c639'], ['Chartreuse', '#b4d000'],
    ['Pistachio', '#93c572'], ['Sage', '#9caf88'], ['Mint', '#98d7c2'], ['Seafoam', '#9fe2bf'],
    ['Olive', '#708238'], ['Moss Green', '#5c6b3a'], ['Army Green', '#4b5320'], ['Khaki Green', '#728639'],
    ['Emerald', '#009b77'], ['Kelly Green', '#4cbb17'], ['Grass Green', '#3f9b0b'], ['Bottle Green', '#006a4e'],
    ['Forest Green', '#1f4d2b'], ['Hunter Green', '#355e3b'], ['Pine Green', '#01796f'], ['Deep Green', '#0b3d2e'],
    ['Jade', '#00a86b'], ['Teal', '#008080'], ['Deep Teal', '#004d4d'], ['Petrol Blue', '#005f6a'],
    ['Turquoise', '#30d5c8'], ['Aqua', '#00c4cc'], ['Electric Cyan', '#57d5fc'], ['Sky Blue', '#87ceeb'],
    ['Baby Blue', '#a1caf1'], ['Powder Blue', '#b0e0e6'], ['Ice Blue', '#d6ecf3'], ['Denim Blue', '#1560bd'],
    ['Cornflower', '#6495ed'], ['Azure', '#007fff'], ['Royal Blue', '#4169e1'], ['Cobalt', '#0047ab'],
    ['Sapphire', '#0f52ba'], ['Electric Blue', '#2c75ff'], ['Steel Blue', '#4682b4'], ['Dusty Blue', '#6d8ea0'],
    ['Navy', '#1f2a44'], ['Deep Navy', '#0f172a'], ['Midnight Blue', '#191970'], ['Ink Blue', '#1c2841'],
    ['Indigo', '#3f00ff'], ['Periwinkle', '#c3cde6'], ['Lavender', '#b57edc'], ['Lilac', '#c8a2c8'],
    ['Mauve', '#b784a7'], ['Violet', '#8f00ff'], ['Purple', '#6a0dad'], ['Royal Purple', '#5b2c83'],
    ['Plum', '#673147'], ['Aubergine', '#3b0918'], ['Grape', '#4e2a5a'], ['Orchid', '#da70d6'],
    ['Magenta', '#d4007a'], ['Fuchsia', '#e0218a'], ['Hot Pink', '#ff4fa3'], ['Bubblegum', '#ffc1cc'],
    ['Blush', '#de8f9c'], ['Dusty Rose', '#c48b8b'], ['Rose', '#e8a0a8'], ['Baby Pink', '#f4c2c2'],
    ['Pastel Pink', '#ffd1dc'], ['Raspberry', '#b3446c'], ['Berry', '#8e3a59'],
];

function hexToRgb(hex) {
    const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
    if (!m) return null;
    const n = parseInt(m[1], 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function rgbToLab([r, g, b]) {
    const lin = (c) => { c /= 255; return c > 0.04045 ? ((c + 0.055) / 1.055) ** 2.4 : c / 12.92; };
    const [R, G, B] = [lin(r), lin(g), lin(b)];
    const f = (t) => (t > 0.008856 ? Math.cbrt(t) : 7.787 * t + 16 / 116);
    const x = f((R * 0.4124 + G * 0.3576 + B * 0.1805) / 0.95047);
    const y = f(R * 0.2126 + G * 0.7152 + B * 0.0722);
    const z = f((R * 0.0193 + G * 0.1192 + B * 0.9505) / 1.08883);
    return [116 * y - 16, 500 * (x - y), 200 * (y - z)];
}

const LAB = PALETTE.map(([name, hex]) => [name, rgbToLab(hexToRgb(hex))]);

/** Closest shopper-friendly name for a #rrggbb colour, or '' if the hex is invalid. */
export function nameForColour(hex) {
    const rgb = hexToRgb(hex);
    if (!rgb) return '';
    const [L, a, b] = rgbToLab(rgb);
    let best = '', bestDist = Infinity;
    for (const [name, [L2, a2, b2]] of LAB) {
        const d = (L - L2) ** 2 + (a - a2) ** 2 + (b - b2) ** 2;
        if (d < bestDist) { bestDist = d; best = name; }
    }
    return best;
}
