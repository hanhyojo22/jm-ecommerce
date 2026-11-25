import { spawn, spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

if (
    process.env.APP_ENV !== 'testing'
    || process.env.DB_CONNECTION !== 'sqlite'
    || resolve(process.env.DB_DATABASE ?? '') !== resolve('database/playwright.sqlite')
    || process.env.DB_URL
) {
    console.error('The QA server requires the isolated SQLite test database. Start it through npm run test:e2e.');
    process.exit(1);
}

// A shared cached config can override the explicitly selected test database.
process.env.APP_CONFIG_CACHE = resolve(`bootstrap/cache/config-playwright-${process.pid}.php`);

const run = (command, args) => {
    const result = spawnSync(command, args, {
        env: process.env,
        stdio: 'inherit',
        shell: process.platform === 'win32',
    });

    if (result.status !== 0) {
        process.exit(result.status ?? 1);
    }
};

run('php', ['artisan', 'route:clear', '--no-interaction']);
run('npm', ['run', 'build']);
run('php', ['artisan', 'storage:link', '--no-interaction']);
run('php', [
    'artisan',
    'migrate:fresh',
    '--force',
    '--seed',
    '--seeder=Database\\Seeders\\PlaywrightQaSeeder',
]);

const reverb = spawn('php', ['artisan', 'reverb:start', '--host=127.0.0.1', '--port=4180', '--no-interaction'], {
    env: process.env,
    stdio: 'inherit',
    shell: process.platform === 'win32',
});

const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=4173'], {
    env: process.env,
    stdio: 'inherit',
    shell: process.platform === 'win32',
});

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        server.kill(signal);
        reverb.kill(signal);
    });
}

server.on('exit', (code) => {
    reverb.kill();
    process.exit(code ?? 0);
});
