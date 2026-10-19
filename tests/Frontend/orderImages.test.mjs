import { test } from 'node:test';
import assert from 'node:assert/strict';
import { orderImages } from '../../resources/js/Support/orderImages.js';

test('bespoke order with progress photos is never mutated (Studio freeze regression)', () => {
    const order = { type: 'Gown', inspiration_urls: ['/a.webp', '/b.webp'], progress_snaps: [{ image_url: '/snap.webp' }] };
    const first = orderImages(order);
    const second = orderImages(order);
    assert.deepEqual(order.inspiration_urls, ['/a.webp', '/b.webp']);
    assert.deepEqual(first, ['/a.webp', '/b.webp', '/snap.webp']);
    assert.deepEqual(second, first);
});

test('shop order shows product photos then progress photos, without duplicates', () => {
    const order = { type: 'standard', items_with_urls: [{ image_url: '/p.webp' }, { image_url: '/p.webp' }, { image_url: null }], progress_snaps: [{ image_url: '/s.webp' }] };
    assert.deepEqual(orderImages(order), ['/p.webp', '/s.webp']);
    assert.deepEqual(orderImages(null), []);
});
