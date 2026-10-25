import { test } from 'node:test';
import assert from 'node:assert/strict';
import { availableStock, isSoldOut, lowStockNote } from '../../resources/js/Support/stock.js';

test('a piece held by another checkout reads sold out', () => {
    const held = { in_stock: true, batch_limit: 3, batch_sold: 2, available_stock: 0 };
    assert.equal(isSoldOut(held), true);
    assert.equal(lowStockNote(held), '');
});

test('unlimited pieces are never sold out unless switched off', () => {
    assert.equal(availableStock({ in_stock: true, batch_limit: null }), null);
    assert.equal(isSoldOut({ in_stock: true, batch_limit: null }), false);
    assert.equal(isSoldOut({ in_stock: false, batch_limit: null }), true);
});

test('last units get an honest note', () => {
    assert.equal(lowStockNote({ in_stock: true, batch_limit: 10, batch_sold: 2, available_stock: 2 }), 'Only 2 left');
    assert.equal(lowStockNote({ in_stock: true, batch_limit: 10, batch_sold: 2, available_stock: 8 }), '');
});

test('falls back to sold count when availability was not sent', () => {
    assert.equal(isSoldOut({ in_stock: true, batch_limit: 2, batch_sold: 2 }), true);
    assert.equal(availableStock({ in_stock: true, batch_limit: 5, batch_sold: 1 }), 4);
});

test('availability sent by the server wins over batch numbers (stock per size)', () => {
    // A tracked product after the cutover may carry no batch limit at all.
    assert.equal(availableStock({ in_stock: true, batch_limit: null, track_stock: true, available_stock: 2 }), 2);
    assert.equal(isSoldOut({ in_stock: true, batch_limit: null, track_stock: true, available_stock: 0 }), true);
    assert.equal(lowStockNote({ in_stock: true, batch_limit: null, track_stock: true, available_stock: 1 }), 'Only 1 left');
    // Made to order: null means unlimited, whatever the old batch numbers say.
    assert.equal(availableStock({ in_stock: true, batch_limit: 3, batch_sold: 3, track_stock: false, available_stock: null }), null);
});

test('the Stock screen says why a set is not in the shop', async () => {
    const { shopStatus } = await import('../../resources/js/Support/stock.js');
    const size = (part, s, available) => ({ part, size: s, available, active: true });
    const set = sizes => ({ track_stock: true, garment_type: 'set', sizes });
    assert.match(shopStatus(set([size('top', 'S', 2), size('top', 'M', 3), size('bottom', 'S', 0)])).text, /count at least one bottom size/);
    assert.match(shopStatus(set([size('top', 'S', 0), size('bottom', 'S', 4)])).text, /count at least one top size/);
    assert.equal(shopStatus(set([size('top', 'S', 2), size('top', 'M', 3), size('bottom', 'M', 4)])).text, 'In the shop: up to 4 set(s) can be sold.');
    assert.equal(shopStatus({ track_stock: true, garment_type: 'hoodie', sizes: [size('single', 'M', 0)] }).tone, 'warn');
    assert.equal(shopStatus({ track_stock: false }).tone, 'ok');
});
