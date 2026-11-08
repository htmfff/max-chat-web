.PHONY: help env up down build restart logs migrate seed import import-save test stan cs worker-logs db-shell frontend-dev e2e clean

help:
	@echo "make up          - build and start all containers"
	@echo "make migrate     - run Doctrine migrations"
	@echo "make seed        - load demo products and admin user"
	@echo "make import      - map samples/import_example.xlsx to the project table"
	@echo "make import-save - import samples/import_example.xlsx into the database"
	@echo "make test        - run PHPUnit tests"
	@echo "make stan        - run PHPStan (level 5)"
	@echo "make cs          - check code style with PHP-CS-Fixer"
	@echo "make logs        - follow application logs"
	@echo "make worker-logs - follow import worker logs"
	@echo "make frontend-dev- run Angular dev server on :4200"
	@echo "make e2e         - run Playwright e2e tests"
	@echo "make down        - stop containers"

env:
	@test -f .env || cp .env.example .env

up: env
	docker compose up -d --build
	@echo "Waiting for the database..."
	@sleep 5
	docker compose exec -T app php bin/migrate.php
	docker compose exec -T app php bin/seed.php
	@echo "Application: http://localhost:$${APP_PORT:-8080}"
	@echo "Swagger UI:  http://localhost:$${APP_PORT:-8080}/api/docs"
	@echo "RabbitMQ:    http://localhost:15672"

down:
	docker compose down

build:
	docker compose build

restart:
	docker compose restart app worker nginx

logs:
	docker compose logs -f app worker

worker-logs:
	docker compose logs -f worker

migrate:
	docker compose exec app php bin/migrate.php

seed:
	docker compose exec app php bin/seed.php

import:
	docker compose exec -T app php bin/import.php samples/import_example.xlsx

import-save:
	docker compose exec -T app php bin/import.php samples/import_example.xlsx --save

test:
	docker compose exec -T app php vendor/bin/phpunit

stan:
	docker compose exec -T app php vendor/bin/phpstan analyse

cs:
	docker compose exec -T app php vendor/bin/php-cs-fixer fix --dry-run --diff

db-shell:
	docker compose exec db mysql -u$${MYSQL_USER:-app} -p$${MYSQL_PASSWORD:-app} $${MYSQL_DATABASE:-product_catalog}

frontend-dev:
	cd frontend && npm start

e2e:
	cd frontend && npx playwright test

clean:
	docker compose down -v
