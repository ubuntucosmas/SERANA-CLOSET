import { test } from 'node:test';
import assert from 'node:assert/strict';
import { registerLabel } from '../../resources/js/Support/register.js';

test('register display names preserve persisted keys and custom names', () => {
    const key = 'Till 1';
    assert.equal(registerLabel(key), 'Register 1');
    assert.equal(key, 'Till 1');
    assert.equal(registerLabel('Nairobi counter'), 'Nairobi counter');
    assert.equal(registerLabel('Tilly'), 'Tilly');
});
