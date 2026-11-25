import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, unlinkSync, rmdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { after, test } from 'node:test';

const bin = mkdtempSync(join(tmpdir(), 'jmmart-qa-safety-'));
const php = join(bin, process.platform === 'win32' ? 'php.cmd' : 'php');
writeFileSync(php, process.platform === 'win32' ? '@exit /b 73\r\n' : '#!/bin/sh\nexit 73\n', { mode: 0o755 });
after(() => {
    unlinkSync(php);
    rmdirSync(bin);
});

function startServer(overrides = {}) {
    const env = {
        ...process.env,
        PATH: bin,
        APP_ENV: 'testing',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: resolve('database/playwright.sqlite'),
        DB_URL: '',
        ...overrides,
    };

    return spawnSync(process.execPath, ['tests/e2e/server.js'], {
        cwd: resolve('.'),
        env,
        encoding: 'utf8',
        timeout: 5000,
    });
}

for (const [name, overrides] of [
    ['an unconfigured direct launch', { APP_ENV: '', DB_CONNECTION: '', DB_DATABASE: '' }],
    ['a production application environment', { APP_ENV: 'production' }],
    ['a remote Postgres connection', { DB_CONNECTION: 'pgsql', DB_URL: 'postgresql://example.invalid/production' }],
    ['a different SQLite database', { DB_DATABASE: resolve('database/database.sqlite') }],
    ['a connection URL overriding SQLite', { DB_URL: 'postgresql://example.invalid/production' }],
]) {
    test(`QA server refuses ${name} before running Artisan`, () => {
        const result = startServer(overrides);

        assert.equal(result.status, 1, result.stderr);
        assert.match(result.stderr, /isolated SQLite test database/);
    });
}

test('QA server accepts the Playwright database and reaches the stubbed Artisan command', () => {
    const result = startServer();

    assert.equal(result.status, 73, result.stderr);
});
