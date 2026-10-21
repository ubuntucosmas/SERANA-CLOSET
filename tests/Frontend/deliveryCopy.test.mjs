import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

// Phase D2: delivery has a price now, shown before payment. Nothing may promise it is included.
function files(dir) {
    return readdirSync(dir).flatMap(name => {
        const path = join(dir, name);
        return statSync(path).isDirectory() ? files(path) : /\.(vue|js|php)$/.test(name) ? [path] : [];
    });
}

test('no screen or email says delivery is included', () => {
    const offenders = [...files('resources/js'), ...files('resources/views'), ...files('app')]
        .filter(path => /delivery (is )?included|no delivery fee added/i.test(readFileSync(path, 'utf8')));
    assert.deepEqual(offenders, []);
});
