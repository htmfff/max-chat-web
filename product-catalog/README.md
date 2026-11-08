# Каталог товаров — тестовое задание

Полноценный сервис каталога товаров с импортом из Excel, асинхронной обработкой
через RabbitMQ, JWT-аутентификацией и SPA на Angular.

## Что реализовано

- **Импорт товаров из `.xlsx`** — загрузка файла, валидация расширения/MIME/размера,
  разбор динамических колонок, upsert по `Внешний код`, отчёт об ошибках по строкам.
- **Асинхронная очередь** — задание импорта сохраняется в БД, сообщение уходит в
  RabbitMQ (Symfony Messenger), отдельный worker обновляет прогресс и результат.
- **Список товаров** — серверная пагинация, поиск по названию, фильтр по диапазону
  цен, сортировка, скидка рассчитывается из закупочной цены.
- **Карточка товара** — описание, цены, скидка, все атрибуты и изображения,
  информация о последнем импорте.
- **Аутентификация** — JWT, защищённые маршруты, login-страница, auth-guard,
  HTTP-интерсепторы (токен и обработка 401).
- **NgRx** — store/actions/reducer/effects/selectors для списка товаров,
  компоненты работают через `async pipe`.
- **Docker** — multi-stage сборки backend и frontend, MySQL, RabbitMQ, nginx.
- **Качество кода** — PHPUnit, PHPStan level 5, PHP-CS-Fixer, GitHub Actions,
  Playwright e2e, OpenAPI/Swagger UI.

## Стек

| Слой | Технологии |
| --- | --- |
| Backend | PHP 8.3, Slim 4, Doctrine ORM 3, Doctrine Migrations, PHP-DI |
| Очередь | Symfony Messenger, AMQP (RabbitMQ) |
| Импорт | PhpSpreadsheet, Guzzle (скачивание изображений) |
| Auth | firebase/php-jwt, `password_hash`/`password_verify` |
| Frontend | Angular 22 (standalone), NgRx 22, Angular Material, RxJS |
| Инфраструктура | Docker Compose, MySQL 8.4, RabbitMQ 3.13, nginx |
| Тесты | PHPUnit 11, Playwright |

## Структура проекта

```
product-catalog/
├── backend/
│   ├── bin/                  # import.php, migrate.php, seed.php, worker.php
│   ├── config/               # bootstrap, DI-контейнер, маршруты
│   ├── migrations/           # миграции Doctrine
│   ├── public/               # точка входа index.php, uploads
│   ├── src/
│   │   ├── Application/      # сервисы, сообщения, обработчики
│   │   ├── Domain/           # сущности и доменные исключения
│   │   ├── Http/             # контроллеры, middleware, сериализаторы
│   │   └── Infrastructure/   # Doctrine, XLSX, JWT, rate limiter
│   └── tests/                # интеграционные тесты и фикстуры
├── frontend/
│   ├── e2e/                  # Playwright-сценарии
│   └── src/app/
│       ├── core/             # сервисы, guard, interceptors
│       ├── models/           # модели API
│       ├── pages/            # login, products, product-detail, import
│       └── store/            # NgRx store для товаров
├── nginx/                    # multi-stage сборка frontend + конфиг nginx
├── docs/openapi.yaml         # OpenAPI 3.0
├── samples/import_example.xlsx
├── docker-compose.yml
├── Makefile
└── .env.example
```

### Слои backend

- `Domain` — сущности `Product`, `ProductAttribute`, `ProductImage`, `ImportJob`,
  `User` и исключения (`NotFoundException`, `ValidationException`, ...).
- `Application` — `ProductService` (CRUD), `ProductImportService` (разбор и upsert),
  `DiscountCalculator`, `ImportDispatcher`, обработчик `ImportProductsHandler`.
- `Infrastructure` — репозитории Doctrine, `XlsxParser`/`XlsxDocument`,
  `ImageDownloader`, `UploadedFileValidator`, `JwtService`, `RateLimiter`.
- `Http` — Slim-контроллеры, middleware (CORS, auth, rate limit, JSON-ошибки),
  презентеры ответов.

## Быстрый старт (Docker)

Требования: Docker Desktop (или Docker Engine + Compose v2), `make` — опционально.

```bash
cd product-catalog
cp .env.example .env
make up
```

`make up` собирает образы, поднимает MySQL, RabbitMQ, backend, worker и nginx,
затем применяет миграции и загружает демо-данные.

Без `make`:

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec -T app php bin/migrate.php
docker compose exec -T app php bin/seed.php
```

После запуска:

| Что | Адрес |
| --- | --- |
| Приложение | http://localhost:8080 |
| Swagger UI | http://localhost:8080/api/docs |
| OpenAPI | http://localhost:8080/api/openapi.yaml |
| RabbitMQ Management | http://localhost:15672 (`guest` / `guest`) |
| Healthcheck | http://localhost:8080/api/health |

**Демо-доступ:** `admin@example.com` / `admin123`.

Остановить: `make down`. Удалить вместе с данными: `make clean`.

## API

Все маршруты, кроме `/health`, `/auth/login`, `/docs` и `/openapi.yaml`, требуют
заголовок `Authorization: Bearer <token>`.

| Метод | Путь | Назначение |
| --- | --- | --- |
| `GET` | `/api/health` | Проверка API и БД |
| `POST` | `/api/auth/login` | Получение JWT |
| `GET` | `/api/auth/me` | Текущий пользователь |
| `GET` | `/api/products` | Список: `page`, `limit`, `name`, `priceFrom`, `priceTo`, `sort`, `direction` |
| `GET` | `/api/products/{id}` | Карточка товара |
| `POST` | `/api/products` | Создание товара |
| `PUT` | `/api/products/{id}` | Обновление товара |
| `DELETE` | `/api/products/{id}` | Удаление товара |
| `GET` | `/api/imports` | Последние 10 заданий импорта |
| `POST` | `/api/imports` | Загрузка `.xlsx` (`multipart/form-data`, поле `file`) |
| `GET` | `/api/imports/{id}` | Статус, прогресс и ошибки задания |

Пример:

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"admin123"}' | jq -r .token)

curl -s "http://localhost:8080/api/products?page=1&limit=20&name=бермуды&priceFrom=500&priceTo=2000" \
  -H "Authorization: Bearer $TOKEN"

curl -s -X POST http://localhost:8080/api/imports \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@samples/import_example.xlsx"
```

## Формат XLSX

Первая строка — заголовки. Обязательные колонки:

| Колонка | Обязательна | Примечание |
| --- | --- | --- |
| `Наименование` | да | до 500 символов |
| `Внешний код` | да | ключ upsert, до 191 символа |
| `Описание` | нет | текст |
| `Цена: Цена продажи` | да | положительное число |
| `Закупочная цена` | нет | для расчёта скидки |
| `Доп. поле: <название>` | нет | любое количество атрибутов |
| `Доп. поле: Ссылки на фото` | нет | URL через пробел, запятую или `;` |

Скидка считается как `(цена - закупочная цена) / цена * 100`.

Повторный импорт того же `Внешний код` обновляет товар, атрибуты и изображения,
а не создаёт дубликат. Ошибочные строки не прерывают импорт: они попадают в
отчёт с номером строки, внешним кодом и сообщением.

## Скрипт: таблица из задания → таблица проекта

`backend/bin/import.php` берёт исходный `.xlsx` из задания и переводит его в
структуру таблиц проекта (`products` + `product_attributes` + `product_images`).

```bash
# показать таблицу проекта, не записывая в БД
make import
# то же локально:
cd backend && php bin/import.php ../samples/import_example.xlsx

# записать результат в БД
make import-save

# дополнительно выгрузить полную таблицу в CSV
cd backend && php bin/import.php ../samples/import_example.xlsx --csv=var/products.csv
```

Вывод в консоль: `№`, `Внешний код`, `Наименование`, `Цена`, `Закупка`,
`Скидка`, `Атрибуты`, `Фото`. CSV содержит полный набор полей проекта:
`external_code`, `name`, `description`, `price`, `purchase_price`, `discount`,
`attributes`, `image_urls`. Файл `samples/import_example.xlsx` — это ровно тот
пример, который приложен к заданию.

## Как работает импорт

1. `POST /api/imports` проверяет файл (`.xlsx`, MIME, ZIP-сигнатура, размер) и
   сохраняет его в `var/uploads`.
2. Создаётся `ImportJob` со статусом `queued`, в RabbitMQ отправляется
   `ImportMessage` с ID задания.
3. Worker (`php bin/worker.php`) забирает сообщение, парсит файл, скачивает
   изображения, делает upsert в транзакции и обновляет прогресс.
4. Frontend опрашивает `GET /api/imports/{id}` раз в секунду и показывает
   прогресс, итог и таблицу ошибок.
5. На `POST /api/imports` действует rate limit: по умолчанию 5 запросов за 60
   секунд с одного IP (`IMPORT_RATE_LIMIT`, `IMPORT_RATE_WINDOW`).

## Разработка frontend

Angular dev server проксирует `/api` и `/uploads` на `http://localhost:8080`
(см. `frontend/proxy.conf.json`), поэтому backend должен быть запущен.

```bash
cd frontend
npm install
npm start          # http://localhost:4200
npm run build      # production-сборка в dist/frontend/browser
```

## Тесты и качество кода

```bash
# Backend (в контейнере)
make test          # PHPUnit
make stan          # PHPStan level 5
make cs            # PHP-CS-Fixer, dry-run

# Frontend
cd frontend
npm run build
npx playwright install chromium   # один раз
npx playwright test               # нужен запущенный стек на :8080
```

E2E-тесты проверяют неудачный и удачный login, страницу товаров, импорт
`samples/import_example.xlsx` и открытие карточки товара. Базовый URL можно
переопределить: `E2E_BASE_URL=http://localhost:8080 npx playwright test`.

### Что покрыто PHPUnit

- CRUD товара: создание, чтение, обновление, удаление.
- Запрет дубликата `externalCode` и неположительной цены.
- Импорт реального `samples/import_example.xlsx`: 40 товаров, атрибуты,
  изображения, повторный импорт без дублей.
- Импорт файла с некорректными строками: валидные строки сохраняются,
  ошибочные попадают в отчёт, импорт не прерывается.

## Переменные окружения

Основные параметры находятся в `.env.example`:

| Переменная | Назначение |
| --- | --- |
| `DATABASE_URL` | DSN MySQL |
| `JWT_SECRET`, `JWT_TTL` | подпись и время жизни токена |
| `MESSENGER_TRANSPORT_DSN` | `amqp://...` для RabbitMQ |
| `IMPORT_MAX_FILE_SIZE` | лимит размера файла в байтах |
| `IMPORT_RATE_LIMIT`, `IMPORT_RATE_WINDOW` | rate limit импорта |
| `CORS_ALLOW_ORIGIN` | origin frontend dev server |
| `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` | демо-пользователь |

## CI

`.github/workflows/ci.yml` выполняет:

- backend: `composer install`, PHPStan, PHP-CS-Fixer, PHPUnit;
- frontend: `npm ci`, production-сборку;
- сборку Docker-образов backend и nginx/frontend.

## Примечания

- Миграция написана под MySQL 8.4 (используется в `docker-compose.yml`).
- Для локального запуска backend без Docker нужны PHP 8.3+ с расширениями
  `pdo_mysql`, `intl`, `zip`, `mbstring`, `fileinfo`, `amqp` и MySQL; проще
  использовать Docker Compose. Если расширения `amqp` нет, а нужны только
  тесты/статический анализ, ставьте зависимости с
  `composer install --ignore-platform-req=ext-amqp`.
- Worker требует расширение `amqp` и DSN `amqp://...`. В тестах используется
  SQLite in-memory и синхронный сценарий импорта без брокера.
