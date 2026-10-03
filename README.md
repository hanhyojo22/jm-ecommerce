# JMMart

JMMart is a local-first multi-vendor marketplace MVP built as a Laravel modular monolith. Customers can buy from independent sellers; approved sellers manage their own stores; platform administrators retain marketplace-wide visibility and moderation control.

## Stack

- Laravel 13, PHP 8.4, Blade, Livewire, Tailwind, and Alpine where needed
- PostgreSQL 17
- Laravel queues and notifications (database-backed during the initial local setup)
- Local public filesystem storage and the Laravel log mailer

## Local setup

1. Install PHP 8.3+, Composer, PostgreSQL 17+, Node.js, and Redis.
2. Create a PostgreSQL database and user, then copy `.env.example` to `.env` and set the `DB_*` values.
3. Run `php artisan key:generate`, `php artisan migrate --seed`, and `php artisan storage:link`.
4. Run `npm install`, then `npm run dev`.
5. Start the application with `php artisan serve`, the queue worker with `php artisan queue:work`, and Reverb with `php artisan reverb:start`. Keep `npm run dev` running while developing frontend assets.

For this workstation, PostgreSQL is running locally at `127.0.0.1:5432` and the `jmmart` database is ready. Local verification emails are written to `storage/logs/laravel.log`.

## Architecture

See [docs/architecture.md](docs/architecture.md) for module boundaries, authorization, tenant isolation, and order integrity. See [docs/database.md](docs/database.md) for the schema plan.

## Production VPS deployment

Docker-based VPS deployment, TLS, workers, scheduler, Reverb, backups, and production environment setup are documented in [docs/deployment.md](docs/deployment.md).

## MVP decisions

- Brand: **JMMart**
- Seller products: publish automatically after seller approval
- Shipping: free (PHP 0.00)
- Payment method: Cash on Delivery
