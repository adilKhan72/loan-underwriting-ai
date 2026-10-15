.PHONY: key up down migrate test test-api test-ai

# Generate APP_KEY into ./.env (needed once before `make up`)
key:
	@test -f .env || cp .env.example .env
	@KEY=$$(docker run --rm php:8.4-cli-alpine php -r 'echo "base64:".base64_encode(random_bytes(32));') && \
		sed -i "s|^APP_KEY=.*|APP_KEY=$$KEY|" .env && echo "APP_KEY written to .env"

up:
	docker compose up --build -d

down:
	docker compose down

migrate:
	docker compose exec api php artisan migrate

test: test-api test-ai

test-api:
	docker run --rm -u $$(id -u):$$(id -g) -v "$$PWD/api":/app -w /app composer:2 \
		sh -c 'cp -n .env.example .env; php artisan key:generate -q; vendor/bin/pint --test && php artisan test'

test-ai:
	docker run --rm -e PYTHONDONTWRITEBYTECODE=1 -v "$$PWD/ai-service":/app -w /app python:3.12-slim \
		sh -c 'pip install -q -r requirements.txt && python -m pytest -q -p no:cacheprovider'
