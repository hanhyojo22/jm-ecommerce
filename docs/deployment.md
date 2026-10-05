# VPS deployment

This deployment uses Docker Compose, Caddy-managed TLS, PostgreSQL, Redis, Laravel queue workers, the scheduler, and Reverb. The production Compose file never publishes PostgreSQL, Redis, Apache, or Reverb directly; Caddy is the only public service.

## Prerequisites

- A Linux VPS with Docker Engine and Docker Compose plugin installed.
- A DNS `A` record for the deployment domain pointed to the VPS public IPv4 address. Add an `AAAA` record only when the VPS is reachable over IPv6.
- Firewall rules allowing TCP ports `80` and `443`; keep SSH restricted to trusted addresses.

## First deployment

1. Clone the repository on the VPS and change into its directory.
2. Copy `.env.production.example` to `.env.production`. This file is ignored by Git and must never be committed.
3. Set `DOMAIN`, `CADDY_SITE_ADDRESS`, `APP_URL`, `CLERK_AUTHORIZED_PARTIES`, `REVERB_ALLOWED_ORIGINS`, and `VITE_REVERB_HOST` to the real domain. Set `ACME_EMAIL` to an address that can receive certificate notices. Set the Clerk publishable key, secret key, and webhook signing secret from the production Clerk instance.
4. Generate unique secrets. Build the image first, then use the command below to generate `APP_KEY` without starting the stack:

   ```sh
   docker compose --env-file .env.production -f docker-compose.production.yml build app
   docker compose --env-file .env.production -f docker-compose.production.yml run --rm --no-deps --entrypoint php app artisan key:generate --show
   ```

   Generate strong, unique values for `POSTGRES_PASSWORD`, `REDIS_PASSWORD`, `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`. Do not reuse the example values or local-development secrets.

5. Run migrations before exposing the new release:

   ```sh
   docker compose --env-file .env.production -f docker-compose.production.yml run --rm app php artisan migrate --force --no-interaction
   ```

6. Start the complete stack:

   ```sh
   docker compose --env-file .env.production -f docker-compose.production.yml up -d
   ```

7. Confirm health and TLS:

   ```sh
   curl --fail --head https://your-domain.example/up
   docker compose --env-file .env.production -f docker-compose.production.yml ps
   ```

## Deploying an update

Pull the intended revision, build it, run migrations, then recreate services:

```sh
git pull --ff-only
docker compose --env-file .env.production -f docker-compose.production.yml build
docker compose --env-file .env.production -f docker-compose.production.yml run --rm app php artisan migrate --force --no-interaction
docker compose --env-file .env.production -f docker-compose.production.yml up -d --remove-orphans
```

Watch rollout logs with `docker compose --env-file .env.production -f docker-compose.production.yml logs --follow app queue reverb caddy`.

## Backups and recovery

Back up PostgreSQL outside the VPS and test restores regularly:

```sh
docker compose --env-file .env.production -f docker-compose.production.yml exec -T database pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" | gzip > jmmart-$(date +%F).sql.gz
```

The `app-storage` named volume contains uploaded product and review images. Include it in the backup plan. Do not delete `postgres-data`, `redis-data`, `app-storage`, or Caddy volumes during ordinary deployments.

## Operations

- View status: `docker compose --env-file .env.production -f docker-compose.production.yml ps`
- View failed jobs: `docker compose --env-file .env.production -f docker-compose.production.yml exec app php artisan queue:failed`
- Restart workers after a code-only change: `docker compose --env-file .env.production -f docker-compose.production.yml restart queue scheduler reverb`
- The application health endpoint is `/up`.

The Render manifest runs migrations before Apache starts because Render's free plan does not support a pre-deploy command. For multi-instance or paid Render deployments, move that command to Render's pre-deploy setting so migrations run exactly once per release.
