import { spawn, spawnSync } from 'node:child_process';

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
