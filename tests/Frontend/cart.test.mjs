import { beforeEach, afterEach, test } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { useCartStore } from '../../resources/js/Stores/useCartStore.js';

let saved;
beforeEach(() => {
    setActivePinia(createPinia());
    saved = undefined;
    globalThis.window = {};
    Object.defineProperty(globalThis, 'localStorage', { configurable: true, value: {
        getItem: () => saved ?? null,
        setItem: (_key, value) => { saved = value; },
    } });
});
afterEach(async () => { await nextTick(); });
const product = { id: 1, slug: 'jacket', name: 'Jacket', price: 5000, batch_limit: 3, garment_type: 'hoodie' };

test('different sizes can be changed and removed independently', () => {
    const cart = useCartStore();
    cart.addItem({ ...product, options: { top_size: 'M' } });
    cart.addItem({ ...product, options: { top_size: 'L' } });
    assert.equal(cart.items.length, 2);
    cart.updateQuantity(cart.items[0].line_key, 1);
    assert.equal(cart.items[0].quantity, 2);
    assert.equal(cart.items[1].quantity, 1);
    cart.removeItem(cart.items[0].line_key);
    assert.equal(cart.items[0].options.top_size, 'L');
    assert.equal(cart.totalPrice, 5000);
});

test('adding and incrementing enforce a combined product limit across sizes', () => {
    const cart = useCartStore();
    cart.addItem({ ...product, options: { top_size: 'M' } });
    cart.addItem({ ...product, options: { top_size: 'L' } });
    cart.updateQuantity(cart.items[0].line_key, 1);
    cart.updateQuantity(cart.items[1].line_key, 1);
    assert.equal(cart.addItem({ ...product, options: { top_size: 'S' } }), false);
    assert.equal(cart.totalItems, 3);
});

test('option property ordering does not create duplicate lines', () => {
    const cart = useCartStore();
    cart.addItem({ ...product, options: { top_size: 'M', bottom_size: 'L' } });
    cart.addItem({ ...product, options: { bottom_size: 'L', top_size: 'M' } });
    assert.equal(cart.items.length, 1);
    assert.equal(cart.items[0].quantity, 2);
});

test('unavailable products and zero stock cannot be added', () => {
    const cart = useCartStore();
    assert.equal(cart.addItem({ ...product, in_stock: false }), false);
    assert.equal(cart.addItem({ ...product, batch_limit: 0 }), false);
    assert.equal(cart.totalItems, 0);
});

test('corrupt stored bag does not prevent the app from opening', () => {
    saved = '{invalid';
    assert.deepEqual(useCartStore().items, []);
});

test('valid old bags receive variant keys and persist subsequent changes', async () => {
    saved = JSON.stringify([{ ...product, quantity: 1, options: { top_size: 'M' } }]);
    const cart = useCartStore();
    assert.equal(typeof cart.items[0].line_key, 'string');
    cart.updateQuantity(cart.items[0].line_key, 1);
    await nextTick();
    assert.equal(JSON.parse(saved)[0].quantity, 2);
});

test('canIncrease reports when the combined stock cap is reached', () => {
    const cart = useCartStore();
    cart.addItem({ ...product, options: { top_size: 'M' } });
    cart.addItem({ ...product, options: { top_size: 'L' } });
    const [m, l] = cart.items.map(i => i.line_key);
    assert.equal(cart.remainingFor(m), 1);
    assert.equal(cart.canIncrease(l), true);
    cart.updateQuantity(l, 1);
    assert.equal(cart.canIncrease(m), false, 'cap is shared across sizes');
    assert.equal(cart.remainingFor(m), 0);
    cart.removeItem(l);
    assert.equal(cart.remainingFor(m), 2, 'removing a size frees its units');
});

test('unlimited products can always be increased', () => {
    const cart = useCartStore();
    cart.addItem({ ...product, batch_limit: null, options: { top_size: 'M' } });
    assert.equal(cart.canIncrease(cart.items[0].line_key), true);
});
