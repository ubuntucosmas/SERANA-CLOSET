import { test } from 'node:test';
import assert from 'node:assert/strict';
import { canAddPosLine } from '../../resources/js/Support/posStock.js';

test('different set combinations cannot oversell their shared top', () => {
    const first = { variant_ids: [1, 2], variant_limits: { 1: 2, 2: 9 }, quantity: 2 };
    const second = { variant_ids: [1, 3], variant_limits: { 1: 2, 3: 9 } };
    assert.equal(canAddPosLine([first], second), false);
    assert.equal(canAddPosLine([{ ...first, quantity: 1 }], second), true);
});

test('each part retains its own capacity rather than the set minimum', () => {
    const first = { variant_ids: [1, 2], variant_limits: { 1: 8, 2: 1 }, quantity: 1, available: 1 };
    const second = { variant_ids: [1, 3], variant_limits: { 1: 8, 3: 7 }, available: 7 };
    assert.equal(canAddPosLine([first], second), true);
    assert.equal(canAddPosLine([first], first), false);
});

test('untracked pieces are not assigned an artificial stock cap', () => {
    assert.equal(canAddPosLine([], { variant_ids: [4], variant_limits: { 4: Infinity } }), true);
    assert.equal(canAddPosLine([], { variant_ids: [4], variant_limits: { 4: 0 } }), false);
});
