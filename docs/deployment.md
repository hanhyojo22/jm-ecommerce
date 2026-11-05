# Render deployment

The existing `render.yaml` deploys the Laravel web/API service, Reverb chat
service, and scheduler from `master`. Staging web/API and chat services deploy
from `staging`. Every service explicitly uses `autoDeployTrigger: commit`.
Render must be connected through your GitHub account for automatic deployments.

In the Render dashboard, sync the existing Blueprint after pushing changes to
`render.yaml`. For services managed individually, set Settings > Auto-Deploy to
On Commit and confirm their linked branch matches the manifest.

Set the required secrets in Render, including a stable `APP_KEY`, hosted PostgreSQL
`DB_URL`, Clerk keys, and the S3-compatible storage credentials listed in
`render.yaml`. Use separate databases, buckets, and Clerk instances for staging
and production. Local `.env` files are excluded from the Docker image and are
not uploaded to Render. Keep the existing `APP_KEY` and database configuration
when updating an existing deployment. Configure webhook signing secrets in
Render when enabling Clerk webhooks or PayMongo payments.

The container builds the website assets and installs production PHP dependencies.
On startup it runs migrations when `RUN_MIGRATIONS=true`, caches Laravel
configuration/routes/views, and starts Apache. The API is served by the same
Laravel application as the website. Queued work currently runs synchronously
(`QUEUE_CONNECTION=sync`), and the Render cron service runs the scheduler hourly.
Uploaded files use the configured external buckets so they survive redeploys.
The existing production web service uses the paid Starter plan; chat and staging
use free plans and can sleep while idle.

After reviewing and committing the intended files, push to the linked branch:

```sh
git push origin master
```

Render rebuilds and deploys the committed backend and website code automatically.
Saving a file locally does not deploy it. Watch the service's Events/Deploys page
and verify `/up` and `/api/v1/categories` after the rollout. Backend changes must
remain compatible with mobile app versions already installed on phones.

The Flutter app defaults to the production Render API and secure Reverb service.
See [the mobile setup](../mobile/README.md) for its Clerk key and run/build commands.
Mobile Dart or asset changes require a new APK/app release; Render does not update
an installed mobile app. The mobile directory is excluded from the backend Docker
image because it is built separately with Flutter.

# VPS deployment

This deployment uses Docker Compose, Caddy-managed TLS, PostgreSQL, Redis, Laravel queue workers, the scheduler, and Reverb. The production Compose file never publishes PostgreSQL, Redis, Apache, or Reverb directly; Caddy is the only public service.

## Prerequisites

- A Linux VPS with Docker Engine and Docker Compose plugin installed.
- A DNS `A` record for the deployment domain pointed to the VPS public IPv4 address. Add an `AAAA` record only when the VPS is reachable over IPv6.
- Firewall rules allowing TCP ports `80` and `443`; keep SSH restricted to trusted addresses.

## First deployment

1. Clone the repository on the VPS and change into its directory.
2. Copy `.env.production.example` to `.env.production`. This file is ignored by Git and must never be committed.
3. Set `DOMAIN`, `CADDY_SITE_ADDRESS`, `APP_URL`, `CLERK_AUTHORIZED_PARTIES`, `REVERB_ALLOWED_ORIGINS`, and `VITE_REVERB_HOST` to the real domain. Set `ACME_EMAIL` to an address that can receive certificate notices. Set the Clerk publishable key, secret key, and webhook signing secret from the production Clerk instance. Keep `TRUSTED_PROXIES=*` only while Caddy is the sole public entry point and the app container cannot be reached directly; otherwise set it to the trusted proxy IP or CIDR.
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

The Render manifest sets `RUN_MIGRATIONS=true`; the container entrypoint runs migrations before starting Apache because Render's free plan does not support a pre-deploy command. For multi-instance deployments, use Render's pre-deploy setting so migrations run exactly once per release, then leave `RUN_MIGRATIONS` unset.
