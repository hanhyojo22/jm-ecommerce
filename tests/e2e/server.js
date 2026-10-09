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

run('npm', ['run', 'build']);
run('php', [
    'artisan',
    'migrate:fresh',
    '--force',
    '--seed',
    '--seeder=Database\\Seeders\\PlaywrightQaSeeder',
]);

const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=4173'], {
    env: process.env,
    stdio: 'inherit',
    shell: process.platform === 'win32',
});

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => server.kill(signal));
}

server.on('exit', (code) => process.exit(code ?? 0));
