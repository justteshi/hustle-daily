# Hustle Daily

## Product and implementation plan

Status: approved product direction  
Repository name: `hustle-daily`  
Primary user: one person initially, with multi-user isolation built into the schema  
Initial distribution: private iPhone installation, not an App Store release

## 1. Product goal

Hustle Daily replaces the manual “Таблица Hustle” workflow with an iPhone application that:

- reads activity, workouts, sleep and body measurements from Apple HealthKit;
- records foods, ingredients, dishes and drinks entered in the app;
- calculates consumed calories and macros only from food logged in Hustle Daily;
- shows one live dashboard for the current day;
- supports training-day and rest-day targets;
- asks the user to review and conclude the day at 22:30 local time;
- keeps past days editable and recalculates them after corrections;
- synchronizes the resulting data to a self-hosted backend;
- provides a browser-based administration panel;
- is single-user at launch but does not require a database redesign to add friends later.

## 2. Confirmed product rules

1. The first release is for one user. Every personal table still has a `user_id`, and all API queries are scoped to the authenticated user.
2. The application is privately installed on registered iPhones. It is not submitted to the App Store.
3. The phone schedules a local notification for 22:30 in the user's configured time zone with wording such as “Let’s conclude the day.”
4. A concluded day may be reopened and edited. Any food, health or measurement correction recalculates the daily summary.
5. Foods are brand-specific products. Two brands of milk or skyr are separate foods when their nutrition differs.
6. Quantities support mass, volume, count and food-specific household portions such as tablespoon, teaspoon, scoop, slice and pinch.
7. Dishes and reusable meals can contain multiple ingredients with different units.
8. Food entries have categories such as breakfast, lunch, dinner, snack, pre-workout, post-workout, drinks and cooking ingredients. Categories are user-editable.
9. Target profiles are editable. Initial profiles are Training Day and Rest Day. Each profile can define targets such as calories consumed, protein, steps and sleep.
10. Consumed calories and macros come only from food entered in Hustle Daily. Nutrition records written to HealthKit by other apps are not imported.
11. Workouts are informative components of Active Energy and must never be added to Active Energy a second time.
12. A day’s total burned calories are `resting energy + active energy` when both values are available.
13. The daily energy balance follows the workbook convention: `total burned - consumed`. A positive number is a deficit and a negative number is a surplus.

## 3. Workbook findings and migration implications

The workbook contains three views:

- `Дневен дневник`: date, consumed calories, protein, resting energy, active energy, total burned energy, deficit/surplus, training flag, training calories, steps, sleep, weight and notes.
- `Хранения`: individual food/drink rows with date, meal category, name, quantity description, calories, protein and notes.
- `Статистика`: averages, recent weight, weight change, training-day count, net deficit and charts.

The supplied file currently covers 29 daily records from 23 September 2026 through 21 October 2026 and contains 212 usable food-log rows across 29 dates. It includes 131 distinct free-text food labels and several overlapping category names. This is valuable history, but most rows contain calorie/protein snapshots and prose rather than normalized ingredient definitions.

Migration policy:

- Import daily metrics and notes as legacy records.
- Import every valid food row as a historical consumption with its original calories and protein snapshot.
- Mark historical entries with `source = legacy_excel`.
- Do not invent carbohydrate, fat, fibre or serving conversions that are absent from the workbook.
- Do not automatically convert every free-text label into a reusable normalized food. Put likely reusable products into an admin review queue.
- Normalize equivalent meal-category labels through an explicit mapping while preserving the original text in import metadata.
- Regenerate statistics from database records. Do not migrate spreadsheet charts or cached statistics as authoritative values.
- Make the import idempotent with a file fingerprint and a stable key based on sheet, row and date.

## 4. Technology stack

### Mobile

- Expo and React Native with TypeScript
- Expo Router
- Expo Development Build and EAS Build; Expo Go is not sufficient
- A local Expo native module written in Swift for HealthKit
- TanStack Query for server state
- Zustand for small UI/session state
- React Hook Form and Zod for forms and validation
- Expo SecureStore for API tokens
- Expo Notifications for the 22:30 reminder
- Expo Camera for barcode scanning
- Expo Localization with centralized Bulgarian and English strings

### Backend

- Laravel, using the current stable release that is compatible with the selected packages
- PostgreSQL
- Redis for queues, locks and caching
- Laravel Sanctum for mobile token authentication
- Filament for the administration panel
- OpenAPI documentation generated from the API implementation
- Pest or PHPUnit for automated tests

### Hosting

- Docker Compose
- Containers for web/API, queue worker, scheduler, PostgreSQL, Redis and Caddy
- Caddy for TLS and reverse proxying
- Named volumes for persistent data
- Automated encrypted database backups with a tested restore procedure
- Health checks and restart policies

## 5. Repository structure

```text
hustle-daily/
├── mobile/                 Expo application
│   ├── app/                Expo Router screens
│   ├── src/
│   │   ├── api/
│   │   ├── components/
│   │   ├── db/
│   │   ├── features/
│   │   ├── health/
│   │   ├── i18n/
│   │   └── state/
│   └── modules/
│       └── hustle-healthkit/  Local Swift Expo module
├── backend/                Laravel API and Filament admin
│   ├── app/
│   ├── database/
│   ├── routes/
│   └── tests/
├── infrastructure/
│   ├── caddy/
│   ├── docker/
│   └── backup/
├── docs/
│   ├── architecture.md
│   ├── healthkit-sync.md
│   ├── api.md
│   └── deployment.md
├── docker-compose.yml
├── .env.example
├── Makefile
└── README.md
```

Use English names in code and database objects. Keep user-facing strings in localization files.

## 6. Core data model

Use UUID or ULID primary keys. Store timestamps in UTC. Store the user’s IANA time zone, initially `Europe/Sofia`, and derive `local_date` explicitly.

### Identity and settings

- `users`: identity, email, password hash, active flag and admin flag.
- `user_settings`: `user_id`, time zone, locale, day-conclusion time, notification enabled flag and preferred units.
- `devices`: user, installation UUID, platform, app version, last sync time and optional notification metadata.

There will be one seeded user initially, but no domain table may assume a global singleton.

### Day types and targets

- `day_types`: user, name, colour, active flag and sort order. Seed Training Day and Rest Day.
- `target_profiles`: one profile per day type, effective-from/effective-to dates and optional notes.
- `target_values`: target profile, metric code, target value, unit and comparison rule. Metric codes initially include `consumed_calories`, `protein_g`, `steps`, `sleep_minutes` and optionally `active_calories`.
- `day_assignments`: user, local date, selected day type, selection source (`manual`, `workout_suggestion`, `default`) and override metadata.

The presence of a workout may suggest Training Day, but it must not overwrite a manual choice.

Seed the requested initial values while keeping them editable:

- Training Day: 10,000 steps, 2,000 consumed kcal and 160 g protein.
- Rest Day: no steps target, 3,000 consumed kcal and 120 g protein.

Target comparison rules must be configurable per metric. Protein and steps normally behave as minimums; consumed calories can be displayed as a budget/upper target rather than incorrectly treating “more” as always better.

### Foods, portions and recipes

- `foods`: user owner, name, brand, barcode, description, source, source identifier, verification status, archived flag and timestamps.
- `food_nutrition`: food, basis amount, basis unit (`g`, `ml`, `serving`), calories, protein, carbohydrate, fat, fibre, sugar and salt. Calories/protein/carbohydrate/fat are first-class; optional nutrients may be null.
- `food_portions`: food, label, quantity, resulting base amount and base unit. Examples: “1 scoop = 30 g”, “1 tablespoon = 15 ml”, “1 slice = 36 g”.
- `meal_categories`: user, localized name, icon key, sort order and active flag.
- `recipes`: user, name, description, yield quantity, yield unit and archived flag.
- `recipe_ingredients`: recipe, food, portion or direct base quantity, sort order and optional note.
- `meal_templates`: optional saved combinations such as the recurring breakfast, containing recipe/food items and default quantities.

Household measures are not globally interchangeable. A tablespoon, scoop or pinch must resolve through a food-specific portion or an explicit quantity entered by the user. Never guess a density conversion.

### Food consumption

- `consumptions`: user, local date, eaten-at timestamp, meal category, source type (`food`, `recipe`, `manual`, `legacy_excel`), source entity, quantity, display unit, notes and idempotency key.
- `consumption_items`: immutable ingredient/product snapshots for the consumption, including name, brand, amount, unit and macro totals.
- Store total calorie and macro snapshots on the consumption as well as item snapshots.

Editing a food definition must not silently rewrite old days. A past consumption only changes when the user explicitly edits that entry or chooses a future “recalculate from current food data” tool.

### Health and activity

- `health_metric_snapshots`: user, local date, metric code, value, unit, HealthKit query start/end, captured-at timestamp and source revision.
- `workouts`: user, HealthKit UUID, activity type, start/end, duration, active energy, total energy, distance, average heart rate, source device and metadata.
- `sleep_sessions`: user, HealthKit UUID or deterministic key, stage, start/end, duration and source.
- `body_measurements`: user, type (`weight`, later `body_fat`), value, unit, measured-at, HealthKit UUID and source.
- `health_sync_cursors`: user/device/sample type, anchor or cursor, last success and last error.
- `health_sync_runs`: start/end, requested range, result counts, status and sanitized error details.

### Daily summary

- `daily_summaries`: user, local date, day type, status (`open`, `review_due`, `concluded`, `reopened`), consumed calories/macros, resting energy, active energy, total burned, energy balance, steps, sleep minutes, latest weight, workout count, workout active calories, target results, missing-data flags, concluded-at and last-calculated-at.
- `daily_summary_revisions`: lightweight audit history for conclude/reopen/manual override actions.

The summary is a reproducible read model. Source tables remain authoritative.

## 7. HealthKit integration

### Data to request

- step count;
- active energy burned;
- basal/resting energy burned;
- workouts;
- sleep analysis;
- body mass;
- exercise time if it adds useful information later.

Ask only for read permissions needed by the feature. Explain each permission in plain language before displaying the iOS permission sheet.

### Sync design

The backend cannot read Apple Health directly. The iPhone is the HealthKit gateway.

1. On first authorization, import a configurable history window that covers the workbook period and current data.
2. On foreground/open, query all changed days since the last successful sync, including a small overlap window.
3. Register HealthKit observer/background delivery where supported so a new workout or measurement can trigger an opportunistic sync.
4. Send daily aggregate snapshots for cumulative metrics such as steps and energy. Use HealthKit statistics queries instead of manually summing raw records from multiple devices.
5. Send individual workout, sleep and body-measurement records with stable identifiers.
6. Upsert idempotently. A repeated sync must not create duplicates.
7. Recalculate affected daily summaries after every successful batch.
8. Show the last HealthKit sync time and any missing permissions on the dashboard.

Workout calories are displayed separately but are already included in Active Energy. They are never added again to total burned calories.

For sleep, merge overlapping samples before calculating total sleep. Attribute the night to the local date on which the user wakes up. Document and test this rule around midnight and daylight-saving changes.

### Background limitation

iOS does not promise that application code will run at exactly 22:30. The application therefore schedules a local notification for 22:30, performs a HealthKit sync when the user opens it, and then shows the review screen. Background HealthKit delivery improves freshness but is not treated as an exact scheduler.

At 22:30 the server may mark the day `review_due`, but it must not claim that HealthKit is final. A day becomes `concluded` only after the user reviews it. Late HealthKit changes or manual edits can reopen/recalculate it.

## 8. Main mobile experience

### Today screen

The initial screen contains:

- date and selected day type;
- last HealthKit sync status and a manual Sync button;
- consumed calories versus target;
- protein, carbohydrates and fat versus targets where configured;
- steps versus target;
- resting, active and total burned energy;
- current deficit/surplus;
- sleep duration;
- latest body weight;
- today’s workouts with type, duration and calories;
- recent meal/food entries;
- primary Add Food button;
- Conclude Day action when review is due.

Missing or partial HealthKit information must be shown as missing/partial, not zero.

### Add food flow

1. Choose meal category and time.
2. Search the private food database with debounced local/backend autocomplete.
3. Optionally scan a barcode.
4. Select a food, portion and quantity.
5. Preview calculated macros.
6. Save through the REST API and refresh the dashboard totals.

If no food exists:

- create a brand-specific food manually;
- enter nutrition per 100 g, per 100 ml or per serving;
- add one or more portion conversions;
- optionally create it from an Open Food Facts barcode result;
- validate that values are non-negative and the basis is complete.

### Dish/recipe flow

- Create a reusable recipe.
- Add multiple branded ingredients with individual quantities and portions.
- Calculate the recipe totals and macros per serving/yield.
- Log a full or partial serving.
- Snapshot the ingredients and macro totals into the consumption record.

### History and editing

- Calendar/list of days with status and headline metrics.
- Day detail reproduces the workbook’s information with structured workouts and foods.
- Reopen a concluded day, edit foods/notes/day type/manual values, then conclude it again.
- Keep a visible “last recalculated” time and an audit entry for important changes.

### Settings

- Profile and API session.
- Time zone and 22:30 notification time.
- HealthKit permissions and sync status.
- Day types and targets.
- Meal categories.
- Units and locale.
- Data export and sign out.

## 9. Open Food Facts and barcode strategy

Open Food Facts is free and open, but its data is community-provided and must be treated as unverified until the user confirms the label. Observe its licence and attribution requirements.

Recommended integration:

- Scan the barcode on-device.
- Ask the Hustle Daily backend for the barcode.
- Return a private/local food if it exists.
- Otherwise, the backend makes one Open Food Facts product lookup, using an identifying User-Agent, and caches the response.
- Show brand, name, image if allowed, nutrition basis and macros for user confirmation.
- Import the confirmed product into the user’s private food database with source attribution and fetch date.
- Let the user correct the imported data without writing back to Open Food Facts in the first release.

Do not use the Open Food Facts search endpoint for each autocomplete keystroke. Its documented search rate limit is too low for search-as-you-type. Normal autocomplete searches only the local Hustle Daily database. Barcode lookups use the product endpoint and are cached.

## 10. REST API outline

All endpoints are under `/api/v1`, authenticated unless explicitly public, and return a consistent error envelope. Mutation endpoints accept an idempotency key.

```text
POST   /auth/login
POST   /auth/logout
GET    /me
GET    /settings
PATCH  /settings

GET    /dashboard/today
GET    /days
GET    /days/{date}
PATCH  /days/{date}
POST   /days/{date}/conclude
POST   /days/{date}/reopen

GET    /day-types
POST   /day-types
PATCH  /day-types/{id}
GET    /targets
PUT    /targets/{dayTypeId}

GET    /meal-categories
POST   /meal-categories
PATCH  /meal-categories/{id}

GET    /foods?query=
POST   /foods
GET    /foods/{id}
PATCH  /foods/{id}
GET    /foods/barcode/{barcode}
POST   /foods/import/open-food-facts/{barcode}

GET    /recipes
POST   /recipes
GET    /recipes/{id}
PATCH  /recipes/{id}

GET    /consumptions?date=
POST   /consumptions
PATCH  /consumptions/{id}
DELETE /consumptions/{id}

POST   /health/sync
GET    /health/status
POST   /health/recalculate/{date}
```

The API must enforce ownership in policies and queries, not merely hide other users’ IDs in the UI.

## 11. Filament administration

Provide resources for:

- users and roles;
- foods, portions and nutrition;
- recipes and ingredients;
- meal categories;
- target profiles;
- daily summaries;
- consumptions;
- workouts and health snapshots as read-only or guarded records;
- import batches and review issues;
- sync runs and failed jobs.

Admin edits to health-derived data should require an explicit manual-override field rather than overwriting the original HealthKit snapshot.

## 12. Mobile data behaviour

- PostgreSQL behind the REST API is the only application database and source of truth.
- The mobile app has no SQLite database or offline write queue in the first release.
- TanStack Query keeps a temporary in-memory cache for responsive navigation and refreshes it from the API.
- SecureStore contains only authentication/session material, not health or food history.
- Adding or editing food requires connectivity to the backend. A failed request remains visible and can be retried; the UI must never pretend an unsaved entry succeeded.
- HealthKit remains on the phone by definition. If the backend is unreachable, the next foreground/background sync can query the affected date range again and upload an idempotent snapshot later.
- HealthKit sync batches and normal food mutations use separate server-side queues so a large health import does not block adding food.

## 13. Security and privacy

- HTTPS is mandatory, including on the home server.
- Store passwords with Laravel’s current secure password hasher.
- Keep mobile tokens in SecureStore.
- Never log tokens, raw passwords or full HealthKit payloads.
- Scope every personal record by `user_id` and cover this with authorization tests.
- Rate-limit authentication and Open Food Facts proxy endpoints.
- Use least-privilege HealthKit permissions.
- Provide account data export and deletion before onboarding friends.
- Encrypt backups and periodically test restoration.
- Keep secrets out of the repository and provide a complete `.env.example`.

## 14. Private iPhone distribution

Recommended approach: EAS internal distribution with an Apple Developer Program membership.

1. Register the iPhone UDID.
2. Configure HealthKit entitlements and background delivery in the Expo config/native module.
3. Create a development build for debugging on the physical phone.
4. Create an internal preview `.ipa` using ad hoc provisioning.
5. Install it from the EAS build link/QR code or with Apple tooling.
6. Enable Developer Mode on the iPhone.

A free Apple Personal Team can install development builds, but its provisioning profile expires after seven days and requires frequent rebuilding/reinstallation. That is not a practical daily-driver setup. A paid developer membership allows registered-device/ad hoc distribution without publishing the app in the App Store.

HealthKit background behaviour must be tested on a physical iPhone. The simulator is insufficient for the complete flow.

## 15. Self-hosted deployment

Docker Compose services:

```text
caddy      TLS and reverse proxy
api        Laravel web/API process
worker     Laravel queue worker
scheduler  Laravel scheduler
postgres   persistent database
redis      queue/cache/locks
backup     scheduled PostgreSQL dumps and retention
```

Deployment requirements:

- a stable host name reachable by the iPhone;
- a trusted TLS certificate;
- firewall rules exposing only the proxy;
- database and Redis not exposed publicly;
- migration command as an explicit deployment step;
- health endpoints for API, database and queue state;
- daily backups with local retention and a second encrypted copy;
- documented restore procedure;
- log rotation and disk-space monitoring.

If the server is not publicly exposed, use a secure private network such as Tailscale. Do not solve home access by exposing PostgreSQL or Redis to the internet.

## 16. Delivery phases

### Phase 0 — Decisions and scaffolding

- Create the monorepo and root documentation.
- Pin compatible stable toolchain versions.
- Add formatting, linting and test commands.
- Add Docker Compose and `.env.example`.
- Record architecture decisions for authentication, HealthKit, server-authoritative data and private distribution.

Acceptance: a new developer can clone the repository, start infrastructure and understand the architecture from the README.

### Phase 1 — Backend foundation

- Create Laravel API, PostgreSQL and Redis integration.
- Add users, Sanctum login/logout, user settings and one-user seed/setup command.
- Add Filament and protect `/admin` by an admin role.
- Add API error format, request IDs and base OpenAPI generation.
- Add authorization tests proving cross-user isolation.

Acceptance: Docker starts cleanly; an admin can sign in; the mobile API can authenticate; tests pass.

### Phase 2 — Food vertical slice

- Implement foods, nutrition, portions, categories, consumptions and recipes.
- Implement calorie/macro calculations and immutable snapshots.
- Build mobile login, Today screen shell, food search, create-food and add-consumption flows.
- Add API-backed server state with TanStack Query and clear connectivity/error handling.

Acceptance: while connected to the backend, the user can add the recurring breakfast from different branded ingredients and see correct daily calories and protein. Failed requests are clearly shown and can be retried without creating duplicates.

### Phase 3 — Day types, targets and daily summary

- Implement Training Day and Rest Day.
- Implement editable metric targets and target progress.
- Implement day assignment, summary calculation and history editing.
- Add conclude/reopen states and audit revisions.

Acceptance: targets change with the selected day type, past edits recalculate the correct day, and workout calories are not double-counted.

### Phase 4 — HealthKit

- Build the local Swift Expo module.
- Implement authorization, supported-type checks and foreground sync.
- Add observer/background delivery and sync cursors.
- Implement workout, sleep, energy, steps and weight ingestion.
- Add sync status and permissions UI.

Acceptance: finishing an Apple Watch workout causes it to appear after background/foreground synchronization; a repeated sync creates no duplicates.

### Phase 5 — Daily conclusion

- Schedule the 22:30 local notification.
- Deep-link it to the review screen.
- Sync HealthKit on open, show missing-data warnings and allow manual corrections.
- Conclude, reopen and reconclude days.

Acceptance: the notification appears at 22:30, opening it produces an up-to-date review, and the application never claims an exact background refresh that iOS did not perform.

### Phase 6 — Barcode and workbook migration

- Add barcode scanning.
- Add cached Open Food Facts product lookup and required attribution.
- Create an idempotent workbook import command and admin review queue.
- Reconcile imported daily calorie/protein totals with the workbook.

Acceptance: known barcodes import into private foods after confirmation; the workbook imports without inventing missing macros; key daily totals reconcile.

### Phase 7 — Hardening and deployment

- Add end-to-end tests for primary flows.
- Validate time zones and daylight-saving boundaries.
- Add backup/restore test, monitoring and deployment documentation.
- Build and install the internal `.ipa` on the registered iPhone.
- Perform a one-week parallel run against the workbook before retiring the spreadsheet.

Acceptance: the installed app handles temporary API failures, server restarts and duplicate HealthKit syncs without corrupting or duplicating data.

## 17. Test strategy

### Backend

- Unit tests for nutrition scaling, recipe totals, daily energy balance and target evaluation.
- Feature tests for every endpoint, validation and authorization.
- Multi-user isolation tests from the beginning.
- Idempotency tests for food writes and HealthKit batches.
- Import reconciliation tests using a sanitized workbook fixture.

### Mobile

- Component tests for target cards, missing states and forms.
- Integration tests for API error handling, retries and cache invalidation.
- Tests for unit/portion calculations and local date assignment.
- Deep-link test for the conclude notification.

### Native HealthKit module

- Swift unit tests for sample mapping, date boundaries and sleep merging.
- Physical-device tests for permission denial, partial permission, new workout delivery, duplicate sync and corrected samples.

### Critical edge cases

- missing resting or active energy;
- watch not worn;
- late workout crossing midnight;
- overlapping sleep samples;
- daylight-saving transition;
- food definition changed after it was consumed;
- deleted/updated HealthKit sample;
- identical sync batch delivered twice;
- backend unavailable while food is added;
- same barcode with an edited private food;
- past concluded day edited later.

## 18. Definition of the first useful release

The first useful release is complete when the private iPhone build can:

- authenticate to the self-hosted backend;
- show today’s food totals, targets and HealthKit metrics;
- add branded foods, portions, recipes and categorized consumptions;
- read steps, energy, workouts, sleep and weight from HealthKit;
- notify at 22:30 and open the day-review flow;
- conclude, reopen and correct a day;
- browse prior days;
- administer foods and records through Filament;
- handle temporary API failures clearly and synchronize HealthKit without duplicates;
- run from Docker on the home server with HTTPS and tested backups.

---

# Ready-to-paste prompt for the PhpStorm AI agent

```text
You are implementing a new project named Hustle Daily in the current PhpStorm workspace. Treat this as production software, but implement it incrementally and keep every completed phase runnable and tested.

PRODUCT
Hustle Daily is a private iPhone health and nutrition tracker that replaces a Bulgarian Excel workbook. It combines food logged in the app with activity, workouts, sleep and weight read from Apple HealthKit. It shows a live daily dashboard, supports Training Day and Rest Day targets, and prompts the user at 22:30 local time to review and conclude the day. Past days remain editable.

The first release has one real user, but all personal database records and API operations must be scoped by user_id so friends can be added later. Do not build public registration yet. The application will be installed privately on registered iPhones and will not be submitted to the App Store.

APPROVED STACK
- Mobile: Expo, React Native, TypeScript, Expo Router, Expo Development Build, TanStack Query, Zustand, React Hook Form, Zod, SecureStore, Notifications, Camera and Localization.
- Apple integration: a local Expo native module written in Swift using HealthKit. Expo Go is not a target.
- Backend: Laravel, PostgreSQL, Redis, Sanctum, Filament and generated OpenAPI documentation.
- Hosting: Docker Compose with Caddy, API, queue worker, scheduler, PostgreSQL, Redis and backup services.
- Use current stable mutually compatible releases and commit lockfiles. Do not select prerelease dependencies without a documented reason.

REPOSITORY STRUCTURE
Create:
- mobile/
- backend/
- infrastructure/
- docs/
- docker-compose.yml
- .env.example
- Makefile
- README.md

Use English names in source code and database objects. Centralize user-facing text and prepare Bulgarian and English locale files.

NON-NEGOTIABLE DOMAIN RULES
1. Consumed calories and macros come only from foods logged in Hustle Daily, never from HealthKit nutrition entries.
2. Foods are brand-specific. Different milk/skyr brands are separate foods when nutrition differs.
3. Nutrition supports calories, protein, carbohydrates, fat and optional fibre/sugar/salt. A nutrition basis may be per 100 g, per 100 ml or per serving.
4. Quantities support grams, millilitres, count and food-specific portions such as tablespoon, teaspoon, scoop, slice and pinch. Never invent density conversions. Store explicit per-food conversions.
5. Recipes/dishes contain multiple branded ingredients. Log entries store immutable macro and ingredient snapshots so editing a food later does not rewrite history.
6. Meal categories are configurable and initially include breakfast, lunch, dinner, snack, pre-workout, post-workout, drinks and cooking ingredients.
7. Day types are configurable and initially include Training Day and Rest Day. Targets are editable per day type and may include consumed calories, protein, steps and sleep.
8. A workout may suggest Training Day but cannot replace a manual day-type selection.
9. Workout energy is already included in HealthKit Active Energy. Never add workout calories to Active Energy again.
10. Total burned energy is resting energy plus active energy when both exist. Energy balance is total burned minus consumed; positive means deficit and negative means surplus.
11. Missing or partial HealthKit data must not be represented as zero.
12. A day can be open, review_due, concluded or reopened. Editing source data recalculates it. Keep an audit record for conclude/reopen/manual overrides.
13. Store timestamps in UTC and use the user’s IANA time zone, initially Europe/Sofia, for local dates.

HEALTHKIT DESIGN
The iPhone is the only HealthKit gateway; the backend cannot pull Apple Health data. The native module must eventually read stepCount, activeEnergyBurned, basal/resting energy, workouts, sleepAnalysis and bodyMass. Use HealthKit statistics queries for cumulative daily metrics to avoid double-counting multiple devices. Send daily metric snapshots plus individual workouts, sleep sessions and body measurements. Use stable HealthKit identifiers and idempotent upserts. Merge overlapping sleep samples and attribute sleep to the local wake-up date.

iOS does not guarantee code execution at exactly 22:30. Schedule a local notification saying “Let’s conclude the day.” Opening it must deep-link to the review screen, trigger a HealthKit sync, show missing-data warnings, and allow confirmation. Background HealthKit delivery is opportunistic freshness, not an exact scheduler.

CORE BACKEND ENTITIES
Implement or document migrations for:
- users, user_settings, devices
- day_types, target_profiles, target_values, day_assignments
- foods, food_nutrition, food_portions
- meal_categories
- recipes, recipe_ingredients, meal_templates
- consumptions, consumption_items with immutable macro snapshots
- health_metric_snapshots, workouts, sleep_sessions, body_measurements
- health_sync_cursors, health_sync_runs
- daily_summaries, daily_summary_revisions

Use UUIDs or ULIDs. Enforce ownership with Laravel policies and query scoping. Add explicit cross-user authorization tests even though only one account is used initially.

API
Use /api/v1 and a consistent JSON error envelope. Mutations accept idempotency keys. The planned resources are auth/me/settings, dashboard/today, days/conclude/reopen, day-types, targets, meal-categories, foods/search/barcode, recipes, consumptions and health sync/status/recalculate. Generate OpenAPI documentation from the implementation.

ADMIN
Install Filament at /admin. Add an admin role and resources for users, foods, nutrition/portions, recipes, categories, targets, daily summaries, consumptions, import batches and sync runs. Treat raw health records as read-only unless an explicit manual override is created.

MOBILE DATA MODEL
PostgreSQL behind the REST API is the single application database and source of truth. Do not add SQLite, an offline write queue or local replication in the first release. Use TanStack Query for temporary server-state caching and SecureStore only for authentication/session material. Food and settings mutations require backend connectivity. Show failed requests clearly and allow a safe retry. HealthKit sync remains idempotent so an affected date range can be queried and uploaded again after a temporary API failure.

OPEN FOOD FACTS
Do not implement this in the first milestone, but keep a clean adapter boundary. Future barcode lookup checks the private database first, then calls the Open Food Facts product endpoint through the backend, identifies the app with a User-Agent, caches the response and asks the user to confirm imported nutrition. Local food autocomplete must not call Open Food Facts on each keystroke.

WORKBOOK MIGRATION
Do not implement this in the first milestone, but reserve an import architecture. The supplied workbook has sheets Дневен дневник, Хранения and Статистика. Historical food rows contain date/category/name/quantity prose/calories/protein/notes. They must be imported as legacy immutable consumptions without inventing missing carbs or fat. Statistics are regenerated, not imported as authoritative results. The future command must be idempotent and create an admin review queue for reusable foods/categories.

SECURITY AND OPERATIONS
- HTTPS is mandatory.
- Keep secrets out of Git and provide .env.example.
- Do not log passwords, tokens or full health payloads.
- Do not expose PostgreSQL or Redis publicly.
- Add health checks, queue/scheduler services and backup placeholders.
- Use least-privilege containers where practical.

YOUR FIRST IMPLEMENTATION RUN
Implement Phase 0 and Phase 1 only. Do not attempt HealthKit or the entire product in this first run.

Phase 0:
1. Inspect the existing workspace and preserve any user work.
2. Create the monorepo structure and a concise architecture document.
3. Scaffold the Expo TypeScript app with Expo Router and development-build configuration, but only create a simple localized placeholder screen.
4. Scaffold Laravel and root Docker Compose.
5. Add root commands/documentation for setup, linting and tests.
6. Add architectural decision records for HealthKit gateway, immutable nutrition snapshots, server-authoritative data and private iOS distribution.

Phase 1:
1. Configure Laravel with PostgreSQL, Redis, queues and scheduler.
2. Implement users, user settings and device records.
3. Implement Sanctum login, logout and GET /api/v1/me. Do not add public registration.
4. Provide a safe CLI command or documented setup method to create the first admin/user; do not hardcode credentials.
5. Install/configure Filament and protect admin access with an explicit admin flag/role.
6. Add a consistent API response/error format, request validation and request IDs.
7. Add generated OpenAPI documentation for the implemented endpoints.
8. Add tests for successful authentication, invalid credentials, logout and cross-user isolation.
9. Add Docker health checks and verify API, database, Redis, worker and scheduler startup.

QUALITY RULES
- Read repository instructions before editing.
- Prefer clear framework conventions over unnecessary abstractions.
- Keep controllers thin and domain logic testable.
- Do not fabricate secrets, Apple identifiers, domains or production credentials.
- Do not weaken TLS, authentication or certificate validation for convenience.
- Run formatters, linters and tests after implementation.
- If a dependency or command is unavailable, report the exact blocker and leave the repository in a coherent state.
- Do not commit or push unless explicitly asked.

PHASE 0/1 ACCEPTANCE CRITERIA
- A fresh clone has documented setup steps.
- Docker Compose starts the backend dependencies and application services.
- Database migrations complete successfully.
- The first user can be created without a hardcoded password.
- The API can log in, return the authenticated user and log out.
- An admin can access Filament while a non-admin cannot.
- Automated tests include authorization isolation and pass.
- The Expo placeholder starts as a development-build-ready project and reads its API base URL from configuration.
- README clearly explains that physical-device HealthKit work and EAS ad hoc signing come in later phases.

Begin by auditing the workspace and reporting any existing files or constraints. Then implement Phase 0 and Phase 1, run the verification commands, and finish with: files created/changed, commands run, tests/results, remaining risks, and the exact recommended next milestone.
```
