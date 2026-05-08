# Product Import / Export (Slim + Angular)

Тестовое задание по импорту/экспорту товаров из `.xlsx` в реляционную БД.

## Технологии

- Backend: `Slim 4`, `Doctrine ORM`
- Frontend: `Angular` (strict mode), `NgRx Store/Effects`
- База данных: `PostgreSQL`
- Очередь: `RabbitMQ`
- Инфраструктура: `Docker Compose`, `Nginx`
- Runtime backend: `PHP-FPM` + внутренний `app_nginx`

## Быстрый старт

1. Подготовьте окружение:
   - `cp backend/.env.example backend/.env`
2. Поднимите проект:
   - `make up`
3. Примените миграции:
   - `make migrate`

Доступ:
- Frontend: `http://localhost:4200`
- API: `http://localhost:8081/api`
- Swagger UI: `http://localhost:8081/api/docs`

## Страницы

- `/import` — загрузка `.xlsx`, запуск импорта, polling статуса и отчета ошибок.
- `/products` — список товаров с серверной пагинацией и фильтрами (`name`, `priceMin`, `priceMax`).
- `/products/:id` — карточка товара с атрибутами и изображениями.

## Основные API endpoint-ы

- `GET /api/health`
- `POST /api/auth/login`
- `POST /api/auth/register`
- `POST /api/auth/refresh`
- `POST /api/auth/logout`
- `POST /api/import`
- `GET /api/import/{taskId}`
- `GET /api/products?page=1&limit=20&name=&priceMin=&priceMax=`
- `GET /api/products/{id}`
- `GET /api/openapi.yaml`
- `GET /api/docs`

OpenAPI файл: `backend/openapi/openapi.yaml`

## Качество и проверки

- PHPStan level 5: `backend/phpstan.neon`
- PHP CS Fixer: `backend/.php-cs-fixer.php`
- CI: `.github/workflows/ci.yml`

Команды:
- Backend tests (docker test image): `make test-backend`
- Backend static analysis: `make phpstan-backend`
- Frontend tests: `cd frontend && npm test -- --watch=false --no-progress`
- Frontend build: `cd frontend && npm run build`

## Режимы импорта

- `FAST_IMPORT_MODE=0` (рекомендуется для проверки ТЗ): импортирует основные поля, атрибуты и изображения.
- `FAST_IMPORT_MODE=1`: ускоренный upsert только основных полей товара, атрибуты и изображения в этом режиме не обрабатываются (это отражается в статусе импорта).

Для строгой проверки требования со скачиванием картинок:
- `IMPORT_SKIP_IMAGES=0`
- `IMPORT_STORE_SOURCE_URL_ONLY=0`
- `FAST_IMPORT_MODE=0`

## Поведение импорта

- Повторный импорт работает как upsert по `external_code`.
- Невалидные строки не останавливают импорт: ошибки накапливаются в отчете задачи.
- Дополнительные поля читаются из колонок с префиксом `Доп. поле`.
- Обработка идет асинхронно через worker `backend/bin/consume-imports.php`.
- Для защищенных endpoint требуется Bearer access token.

## Данные и сиды

- Сид-файл: `backend/fixtures/seed.sql`
- Применить сиды: `make seed`

## Безопасность

- Не коммитьте `backend/.env` в репозиторий.
- При компрометации секретов:
  1. смените `APP_SECRET`;
  2. смените пароли БД/RabbitMQ;
  3. завершите активные сессии (очистите `auth_refresh_tokens`);
  4. пересоздайте `.env` из `backend/.env.example`.
