import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

// Material Symbols is loaded as a subset (icon_names in app.blade.php). An icon
// missing from that list renders as its name in large letters, so every icon the
// app names must be in it, and the list must stay alphabetical for Google Fonts.
function files(dir) {
    return readdirSync(dir).flatMap(name => {
        const path = join(dir, name);
        return statSync(path).isDirectory() ? files(path) : /\.(vue|js)$/.test(name) ? [path] : [];
    });
}

test('every icon the app uses is in the loaded icon set', () => {
    const loaded = readFileSync('resources/views/app.blade.php', 'utf8').match(/icon_names=([^"&]*)/)[1].split(',');
    assert.deepEqual([...loaded].sort(), loaded, 'icon_names must be alphabetical');
    const used = new Set();
    for (const file of files('resources/js')) {
        const source = readFileSync(file, 'utf8');
        for (const m of source.matchAll(/material-symbols-outlined[^>]*>\s*([a-z_0-9]+)\s*</g)) used.add(m[1]);
        for (const m of source.matchAll(/icon: ?'([a-z_0-9]+)'/g)) used.add(m[1]);
    }
    assert.deepEqual([...used].filter(name => !loaded.includes(name)).sort(), []);
});
