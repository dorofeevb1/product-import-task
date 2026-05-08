up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f

migrate:
	docker compose exec app php vendor/bin/doctrine-migrations migrate --configuration=config/doctrine-migrations.php --no-interaction

seed:
	docker compose exec -T db psql -U app -d products < backend/fixtures/seed.sql

test-backend:
	docker build -f backend/Dockerfile --target test -t product-backend-test ./backend
	docker run --rm -e DB_DRIVER=pdo_sqlite -e DB_PATH=:memory: product-backend-test vendor/bin/phpunit --testdox

phpstan-backend:
	docker build -f backend/Dockerfile --target test -t product-backend-test ./backend
	docker run --rm -e DB_DRIVER=pdo_sqlite -e DB_PATH=:memory: product-backend-test vendor/bin/phpstan analyse --memory-limit=512M

test-frontend:
	docker compose exec frontend sh -lc "npm test"
