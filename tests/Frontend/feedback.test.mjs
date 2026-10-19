import { beforeEach, afterEach, test, mock } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { usePulseStore } from '../../resources/js/Stores/usePulseStore.js';
import { rememberTrackingPhone, recallTrackingPhone, forgetTrackingPhone } from '../../resources/js/Support/trackingMemory.js';

let store;
beforeEach(() => {
    setActivePinia(createPinia());
    mock.timers.enable({ apis: ['setTimeout', 'Date'] });
    store = {};
    Object.defineProperty(globalThis, 'localStorage', { configurable: true, value: {
        getItem: (key) => store[key] ?? null,
        setItem: (key, value) => { store[key] = value; },
        removeItem: (key) => { delete store[key]; },
    } });
});
afterEach(() => mock.timers.reset());

test('a repeated message refreshes the existing toast instead of stacking', () => {
    const pulse = usePulseStore();
    pulse.emit('Choose a size', 'error');
    mock.timers.tick(4000);
    pulse.emit('Choose a size', 'error');
    assert.equal(pulse.notifications.length, 1);
    mock.timers.tick(4000); // 8s after the first, 4s after the refresh
    assert.equal(pulse.notifications.length, 1);
    mock.timers.tick(1001);
    assert.equal(pulse.notifications.length, 0);
});

test('no more than three toasts are visible; the oldest goes first', () => {
    const pulse = usePulseStore();
    ['one', 'two', 'three', 'four'].forEach((m) => pulse.emit(m));
    assert.deepEqual(pulse.notifications.map((n) => n.message), ['two', 'three', 'four']);
});

test('the tracking phone is remembered, expires after 90 days, and can be forgotten', () => {
    rememberTrackingPhone(' 0712345678 ');
    assert.equal(recallTrackingPhone(), '0712345678');
    forgetTrackingPhone();
    assert.equal(recallTrackingPhone(), '');
    rememberTrackingPhone('0712345678');
    mock.timers.tick(91 * 24 * 60 * 60 * 1000);
    assert.equal(recallTrackingPhone(), '');
});
