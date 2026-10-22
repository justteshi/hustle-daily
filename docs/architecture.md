# Architecture

`mobile/` is an Expo Router development-build app. It stores only authentication material locally; future server state uses TanStack Query. `backend/` is a Laravel API and Filament admin. PostgreSQL owns application records; Redis backs cache and jobs. `infrastructure/` and root Compose define Caddy, API, worker, scheduler, PostgreSQL, Redis and an opt-in backup placeholder.

All personal records use a `user_id` foreign key and are accessed through user-scoped relationships or policies. Users, settings and devices are implemented. Phase 2 will add day types, targets, foods, portions, recipes and immutable consumption snapshots. HealthKit remains iPhone-only. The API will ingest idempotent snapshots and keep local-date calculations in each user's IANA time zone. Open Food Facts and workbook import will be separate adapters in later milestones.

Nutrition totals will come only from app consumptions. Burned energy will be resting plus active energy when both exist; workout energy must not be added again. Missing health values remain null. Daily summaries will retain revisions and manual-override audit records.
