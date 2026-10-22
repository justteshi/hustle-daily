# Hustle Daily

Private iPhone health and nutrition tracker. This repository currently implements Phase 0 and the backend foundation of Phase 1. HealthKit, nutrition logging, dashboard, notifications and private iOS signing come later.

## Local setup

Requirements: Docker Compose, Node 24, npm, and an iPhone development environment for the mobile app. The API uses PostgreSQL and Redis in Docker. Copy `.env.example` to `.env`, set a real HTTPS hostname and a strong `DB_PASSWORD`. Build the API with `docker compose build api`, then run `docker compose run --rm --no-deps api php artisan key:generate --show` and paste the generated value into `APP_KEY` in `.env`. Do not commit `.env`.

Run `make up`, then `make migrate`. Create the first admin with `make user EMAIL=you@example.com`; the command prompts for a name and password. `/admin` is the Filament panel. A regular user can be created with `docker compose exec -it api php artisan hustle:create-user user@example.com`.

For a disposable local backend, run `make up-ephemeral` after creating `.env` and its key. You may set `APP_DOMAIN=localhost` and `APP_URL=https://localhost` for this mode because Caddy is not started. This starts PostgreSQL and Redis with memory-only storage under a separate Compose project; it does not mount the persistent volumes from the regular stack. Migrations run automatically. Check services with `docker compose -p hustle-daily-ephemeral -f docker-compose.yml -f docker-compose.ephemeral.yml ps`. Create a disposable admin with `make user-ephemeral EMAIL=you@example.com`. Stop with `make down-ephemeral`; all data in this mode disappears. It starts the API, worker and scheduler without Caddy, so it is useful for local checks only. Access from an iPhone still requires a real HTTPS hostname and trusted certificate. The older `hustle-daily_postgres_data` and `hustle-daily_redis_data` test volumes are not used by this mode.

The mobile project reads `EXPO_PUBLIC_API_BASE_URL` at build time (for example `https://your-domain/api/v1`). Run `cd mobile && npm ci && npx expo start --dev-client`. Expo Go is not supported. An iOS development build and registered device signing are needed; the physical-device HealthKit module and EAS ad hoc distribution are later milestones. No Apple identifier is included here.

## Development checks

Create a dedicated test database once with `docker compose exec -T db createdb -U hustle_daily -O hustle_daily hustle_daily_test` (adjust the username if changed). Run `make test`, `make lint`, and `make openapi`. `docs/openapi.json` is generated from Laravel routes by Scramble. `docker compose ps` shows API, database, Redis, worker and scheduler health. Docker services do not expose PostgreSQL or Redis ports. Caddy obtains HTTPS certificates for the configured hostname.

API endpoints: `POST /api/v1/auth/login` accepts email, password and device_name; `GET /api/v1/me` and `POST /api/v1/auth/logout` require a bearer token. Login errors use an `error` object with code, message, details and request_id. Responses include `X-Request-ID`.

The database is server authoritative. There is no public registration or offline write queue. See [architecture](docs/architecture.md) and the ADRs for the planned domain boundaries.
