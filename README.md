## Study-on.billing — Billing API (Symfony 7)

### Описание
- REST API для регистрации, аутентификации (JWT), баланса, курсов, оплат и истории транзакций.
- Документация Swagger доступна по `/api/v1/doc`.

### Архитектура (docker-compose)
- Контейнеры: `nginx` (имя контейнера: `billing.study-on.local`), `php`, `postgres`, `mailhog` (для писем).
- Порт приложения задаётся `NGINX_PORT` (пример: `8081`).

### Быстрый старт
1) Настройте переменные окружения (файл `.env` в корне проекта или экспорт):
```env
NGINX_PORT=8081
MAILER_DSN=smtp://mailhog:1025
```

2) Запуск и подготовка:
```bash
make up
docker-compose exec php composer install
make migrate
make fixtload
```

3) Проверка:
- Swagger: `http://localhost:8081/api/v1/doc`
- MailHog (почта): `http://localhost:8025`
- Подключение к БД локально: Postgres проброшен на `127.0.0.1:5434`.

### Эндпоинты (v1)
Авторизация и пользователи:
- `POST /api/v1/register` — регистрация.
- `POST /api/v1/auth` — логин (Lexik JWT json_login).
- `POST /api/v1/token/refresh` — обновление токена (gesdinet/jwt-refresh-token-bundle).
- `GET  /api/v1/users/current` — текущий пользователь (JWT).

Курсы:
- `GET  /api/v1/courses` — список курсов.
- `GET  /api/v1/courses/{code}` — получение курса по коду.
- `POST /api/v1/courses` — создать (ROLE_SUPER_ADMIN).
- `POST /api/v1/courses/{code}` — редактировать (ROLE_SUPER_ADMIN).
- `POST /api/v1/courses/{code}/pay` — оплата курса с баланса (JWT).

Баланс и платежи:
- `POST /api/v1/deposit` — пополнение баланса, тело: `{ "amount": number }` (JWT).

Транзакции:
- `GET  /api/v1/transactions` — история пользователя (JWT), фильтры:
  - `filter[type]=payment|deposit`
  - `filter[course_code]=<код>`
  - `filter[skip_expired]=true|false`

### Почта и команды (уведомления/отчёты)
MailHog уже описан выше. Команды:
```bash
docker-compose exec php bin/console payment:ending:notification  # уведомления об истекающих арендах
docker-compose exec php bin/console payment:report               # месячный отчёт по оплатам
```
Cron‑примеры:
```bash
0 9  * * * docker-compose exec php bin/console payment:ending:notification
0 10 1 * * docker-compose exec php bin/console payment:report
```
Подробнее см. `README_COMMANDS.md` и `TESTING_COMMANDS.md`.

### Безопасность
- `config/packages/security.yaml`:
  - Публичные: `/api/v1/register`, `/api/v1/auth`, `/api/v1/doc`, `/api/v1/token/refresh`, `GET /api/v1/courses*`.
  - Остальные `/api` — только с JWT.

### Команды разработчика
```bash
make up         # поднять контейнеры
make down       # остановить контейнеры
make migrate    # применить миграции Doctrine
make fixtload   # загрузить фикстуры
make phpunit    # запустить тесты
```

### Интеграция с порталом
- Портал `study-on` обращается к этому сервису.
- Рекомендуемый адрес для контейнеров в одной сети: `http://billing.study-on.local` (имя контейнера nginx).

