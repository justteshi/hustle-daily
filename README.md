# Hustle Daily

Hustle Daily is a self-hosted health and nutrition tracker for multiple users. The desktop web app is the current focus; an iPhone app is being developed alongside it. Each account has its own foods, recipes, meal logs, and settings.

> **Current stage:** Food logging works on the web. Day targets, full daily summaries, HealthKit synchronization, and the 22:30 review flow are later milestones. See [implementation_plan.md](implementation_plan.md) for the roadmap.

## What you can use now

| Area | Available today |
| --- | --- |
| Web app (`/`) | Sign in, view today's food calories and protein, search and edit branded foods and portions, create and edit reusable recipes, log a recipe or individual ingredients, browse food history, and correct a past meal. |
| Administration (`/admin`) | Admin-only management of users, foods, portions, recipes, categories, and consumption corrections. Corrections have an audit trail. |
| iPhone app | Early development-build screens for sign-in, food search and creation, and meal logging. Not yet feature-complete. |
| API (`/api/v1`) | Authenticated food, recipe, category, consumption, and today's food-total endpoints. |

Food and recipe changes do **not** rewrite meals already logged: each log keeps a nutrition and ingredient snapshot. Consumed nutrition comes only from Hustle Daily food logs, not Apple Health. Every user's records are isolated by account.

## Run locally with persistent storage

You need Docker with Compose. Node.js 24 and npm are only needed if you want to run the web or mobile development servers outside Docker. PostgreSQL and Redis run in containers—nothing is installed as a database server on your machine.

1. Copy the example environment file:

   ```bash
   make setup
   ```

2. Edit the **repository-root** `.env`. Set a strong `DB_PASSWORD`, an `APP_KEY`, and the HTTPS hostname in `APP_DOMAIN` and `APP_URL`. For local-only use, `APP_DOMAIN=localhost` and `APP_URL=https://localhost` are suitable. A key in `backend/.env` does not configure Docker Compose.

   If you need to generate an app key, run:

   ```bash
   docker compose build api
   docker compose run --rm --no-deps api php artisan key:generate --show
   ```

   Paste the generated value into `APP_KEY` in the root `.env`. Never commit `.env` or share the key.

3. Start the stack and apply migrations:

   ```bash
   make up
   make migrate
   ```

4. Create the first administrator. The command prompts for a name and password; no credentials are hardcoded:

   ```bash
   make user EMAIL=you@example.com
   ```

Open `https://localhost/` for the web app and `https://localhost/admin` for Filament when using the local hostname. Trust Caddy's local development certificate on your machine; do not disable certificate verification. For access from another device, configure a reachable hostname and trusted HTTPS certificate first.

The default Compose stack stores PostgreSQL, Redis, and Caddy data in **persistent named volumes**. `make down` stops containers without removing them. Do not run `docker compose down -v` unless you intentionally want to erase those volumes. PostgreSQL and Redis ports are not published to the host.

### Create another user

There is no public registration yet. Create a regular account through the CLI:

```bash
docker compose exec -it api php artisan hustle:create-user friend@example.com
```

Only accounts created with the admin option can access `/admin`. Multiple users share the application but cannot access one another's personal data.

## Working with the web app

- Create each branded product under **Foods**, with its nutrition basis and any explicit portions (for example, one scoop = 30 g). The app never guesses a mass-to-volume conversion.
- Combine those foods into a reusable **Ready meal** if you eat the same combination often.
- From **Today**, log either a ready meal or individual ingredients. If you forgot the milk, edit the existing log instead of deleting and recreating it.
- Use **History** to revisit food logs for previous dates. Corrections are audited; old meal snapshots remain unchanged when the underlying food definition is edited.

History currently shows food entries, not full daily health summaries. Food and settings changes require a connection to the backend; there is no offline write queue.

## Development and verification

| Command | Purpose |
| --- | --- |
| `make up` | Build and start the persistent Docker stack. |
| `make down` | Stop it while keeping named volumes. |
| `make migrate` | Apply backend database migrations. |
| `make test` | Run backend tests in a separate test container/database. |
| `make lint` | Check PHP formatting and TypeScript types. |
| `make openapi` | Regenerate [docs/openapi.json](docs/openapi.json) from Laravel routes. |
| `docker compose ps` | Check API, database, Redis, worker, and scheduler health. |

Before running `make test` for the first time, create its separate database (adjust the username if you changed `DB_USERNAME`):

```bash
docker compose exec -T db createdb -U hustle_daily -O hustle_daily hustle_daily_test
```

For web development with hot reload:

```bash
cd web
npm ci
npm run dev
```

Vite proxies `/api` and `/session` to `VITE_DEV_BACKEND_URL` (default `https://localhost`) and verifies TLS. Trust the local Caddy certificate rather than turning off verification. The production web bundle is built into the Caddy image by `make up`.

For the iPhone development build:

```bash
cd mobile
npm ci
npx expo start --dev-client
```

Set `EXPO_PUBLIC_API_BASE_URL` to your reachable HTTPS API base URL, such as `https://your-domain/api/v1`. Expo Go is not supported. A physical-device HealthKit module and EAS ad hoc distribution are planned for later phases; no Apple identifiers or signing credentials are included in this repository.

### Disposable alternative

`make up-ephemeral` starts a separate Compose project with memory-only PostgreSQL and Redis storage for disposable testing. Stop it with `make down-ephemeral`. **Do not use this mode for data you want to keep.**

## API, security, and further reading

The web app uses a server-side session cookie and CSRF protection. The mobile API uses Sanctum bearer tokens. There is no public registration. API mutations require an `Idempotency-Key` header; errors use a consistent `error` object and responses include `X-Request-ID`. See the generated [OpenAPI specification](docs/openapi.json) for endpoint and request details.

Filament is for administration, not the everyday user-facing web workflow. Its consumption editor can correct notes/category or replace ingredients with an explicit reason; before/after values are kept in read-only revisions. Health-derived data is not editable as an ordinary food log.

Read the [architecture overview](docs/architecture.md) and [implementation plan](implementation_plan.md) for domain rules, current gaps, and upcoming phases. Backups are not yet a tested production workflow; do not treat the placeholder backup service as a complete backup solution.
