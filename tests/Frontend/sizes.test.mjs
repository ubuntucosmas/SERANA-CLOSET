import { test } from 'node:test';
import assert from 'node:assert/strict';
import { sizesForRow, isSizeSoldOut, sizeNote, rowNotes, fallbackSizes, sizeLabel } from '../../resources/js/Support/sizes.js';

const set = [
    { id: 1, part: 'top', size: 'M', available: 2 },
    { id: 2, part: 'top', size: 'L', available: 0 },
    { id: 3, part: 'bottom', size: 'M', available: 5 },
    { id: 4, part: 'bottom', size: 'L', available: 1 },
];

test('each size row shows only its own part', () => {
    assert.deepEqual(sizesForRow(set, 'top').map(s => s.id), [1, 2]);
    assert.deepEqual(sizesForRow(set, 'bottom').map(s => s.id), [3, 4]);
    // A single piece is chosen in the first (top) row.
    assert.deepEqual(sizesForRow([{ id: 9, part: 'single', size: 'S', available: 1 }], 'top').map(s => s.id), [9]);
});

test('sizes say when they are sold out or nearly gone', () => {
    assert.equal(isSizeSoldOut(set[1]), true);
    assert.equal(isSizeSoldOut(set[0]), false);
    assert.equal(sizeNote(set[1]), 'Sold out in L');
    assert.equal(sizeNote(set[0]), 'Only 2 left in M');
    assert.equal(sizeNote(set[2]), '');
    assert.equal(rowNotes(sizesForRow(set, 'top')), 'Only 2 left in M · Sold out in L');
});

test('made to order sizes have no count and never sell out', () => {
    const free = { id: 5, part: 'single', size: 'XL', available: null };
    assert.equal(isSizeSoldOut(free), false);
    assert.equal(sizeNote(free), '');
});

test('without server sizes the product page falls back to the listed sizes', () => {
    assert.deepEqual(fallbackSizes({ garment_type: 'set', specifications: { sizes: ['M', 'L'] } }).map(s => `${s.part}:${s.size}:${s.available}`),
        ['top:M:null', 'top:L:null', 'bottom:M:null', 'bottom:L:null']);
    assert.deepEqual(fallbackSizes({ garment_type: 'hoodie' }).map(s => s.size), ['S', 'M', 'L', 'XL', 'XXL']);
});

test('size labels read the same as the server, one size included', () => {
    assert.equal(sizeLabel({ top_size: 'M', bottom_size: 'L' }), 'Top M · Bottom L');
    assert.equal(sizeLabel({ bottom_size: 'L' }), 'Size L');
    assert.equal(sizeLabel({ top_size: 'One size' }), 'One size');
    assert.equal(sizeLabel(null), null);
});
