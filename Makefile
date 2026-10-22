.PHONY: setup up down up-ephemeral down-ephemeral user-ephemeral migrate user test lint openapi mobile

EPHEMERAL_COMPOSE = docker compose -p hustle-daily-ephemeral -f docker-compose.yml -f docker-compose.ephemeral.yml

setup:
	@test -f .env || (cp .env.example .env && echo 'Fill .env with DB_PASSWORD and APP_KEY; use localhost for APP_DOMAIN in ephemeral mode.')

up:
	docker compose up --build -d

down:
	docker compose down

up-ephemeral:
	$(EPHEMERAL_COMPOSE) up --build -d db redis api worker scheduler
	$(EPHEMERAL_COMPOSE) exec -T api php artisan migrate --force

down-ephemeral:
	$(EPHEMERAL_COMPOSE) down

user-ephemeral:
	$(EPHEMERAL_COMPOSE) exec -it api php artisan hustle:create-user $(EMAIL) --admin

migrate:
	docker compose exec api php artisan migrate --force

user:
	docker compose exec -it api php artisan hustle:create-user $(EMAIL) --admin

test:
	docker compose --profile test run --build --rm test

lint:
	cd backend && vendor/bin/pint --test
	cd mobile && npm run typecheck

openapi:
	docker compose run --rm --no-deps --user root -e HOST_UID=$$(id -u) -e HOST_GID=$$(id -g) -v "$$(pwd)/docs:/app/docs" api sh -c 'php artisan scramble:export --path=/app/docs/openapi.json && chown $$HOST_UID:$$HOST_GID /app/docs/openapi.json'

mobile:
	cd mobile && npx expo start --dev-client
