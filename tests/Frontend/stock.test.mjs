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
