import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nameForColour } from '../../resources/js/Support/colourNames.js';

test('picked colours get shopper-friendly names', () => {
    assert.equal(nameForColour('#1f4d2b'), 'Forest Green');
    assert.equal(nameForColour('#000000'), 'Jet Black');
    assert.equal(nameForColour('#ffffff'), 'Snow White');
    assert.equal(nameForColour('#57d5fc'), 'Electric Cyan');
    assert.equal(nameForColour('#800020'), 'Burgundy');
});

test('close shades land on the nearest name, not raw hex', () => {
    assert.equal(nameForColour('#0e1a2d'), 'Deep Navy');
    assert.equal(nameForColour('#c29a6c'), 'Camel');
    assert.match(nameForColour('#ff0000'), /Red|Scarlet/);
});

test('invalid input gives no name', () => {
    assert.equal(nameForColour('red'), '');
    assert.equal(nameForColour(''), '');
    assert.equal(nameForColour(undefined), '');
});
