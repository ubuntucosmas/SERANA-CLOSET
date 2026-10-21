import { test } from 'node:test';
import assert from 'node:assert/strict';
import { sectionsFor, studioSection } from '../../resources/js/Support/studioSections.js';

test('the owner sees Staff and Stock; a manager sees Stock but not Staff', () => {
    const owner = sectionsFor(['staff.manage', 'stock.manage']).map(s => s.id);
    const manager = sectionsFor(['stock.manage']).map(s => s.id);
    assert.ok(owner.includes('staff') && owner.includes('stock'));
    assert.ok(manager.includes('stock') && !manager.includes('staff'));
});

test('sections without an ability show to every Studio user', () => {
    const none = sectionsFor([]).map(s => s.id);
    assert.deepEqual(none.filter(id => ['staff', 'stock'].includes(id)), []);
    assert.ok(['home', 'orders', 'products', 'settings'].every(id => none.includes(id)));
});

test('the Staff section explains itself', () => {
    assert.equal(studioSection('staff').label, 'Staff');
});

test('Pre-orders show only while Drops are on', () => {
    assert.ok(!sectionsFor([], { drops_enabled: false }).some(s => s.id === 'drops'));
    assert.ok(sectionsFor([], { drops_enabled: true }).some(s => s.id === 'drops'));
});

test('the till history is for those who review tills', () => {
    assert.ok(sectionsFor(['till.review']).some(s => s.id === 'tills'));
    assert.ok(!sectionsFor([]).some(s => s.id === 'tills'));
});
