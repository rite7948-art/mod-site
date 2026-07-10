# Деплой на Railway

## Шаги

1. **Создай новый сервис** в проекте Railway, где уже крутится футика2 (чтобы делить ту же MySQL).
2. **Тип сервиса** — Empty Service → подключи к этому Git-репозиторию.
3. Railway автоматически найдёт `Dockerfile` и соберёт образ.
4. **Переменные окружения** — в Settings → Variables подвяжи переменные от плагина MySQL:
   - `MYSQLHOST`
   - `MYSQLPORT`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`
   - `MYSQLDATABASE`

   Если у тебя на футика2 уже подключён MySQL plugin, проще всего: **Service → Variables → Add reference** → выбрать MySQL и его переменные.
5. **Volume для `users.json`** (см. `BOT_INTEGRATION.md`, Вариант 1):
   - Settings → Volumes → создай Volume, монти в `/data`.
   - Тот же Volume подмонтируй к сервису бота.
   - В `login.php` поменяй путь: `$users_json = '/data/users.json';`.
6. **Деплой** — push в ветку, которую слушает Railway (обычно `main`). Сайт станет доступен по сгенерированному домену вида `https://*.up.railway.app`.

## Локальная разработка

PHP на Windows ставится через:
- **XAMPP** (самое простое, GUI): https://www.apachefriends.org/
- **Laragon** (легче): https://laragon.org/
- **Portable PHP** (`https://windows.php.net/download/`) + запуск:
  ```
  php -S localhost:8090
  ```

Локально MySQL не обязателен — `db.php` упадёт без ошибки если БД недоступна, но логин работать не будет. Для теста разметки/JS можно временно убрать `require_login()` из `index.php`.

## Что хранится где

| Данные | Где |
|---|---|
| Логины/пароли | `users.json` (бот) + БД `users` (синк при логине) |
| Сессия пользователя | PHP `$_SESSION` (cookie) |
| Отчёты собесов | Сейчас — `localStorage` браузера. Под Railway лучше переехать в `moder_site_reports` (таблица уже создаётся в `db.php`). |
| Варианты колеса фортуны | `localStorage` (личное для каждого) |

## Структура проекта

```
index.php             — основная страница (с auth-обёрткой)
login.php             — форма входа
logout.php            — выход
db.php                — PDO + автосоздание таблиц
auth_helper.php       — require_login, has_role и т.д.
index.css             — стили
Dockerfile            — образ для Railway
server.js             — старый Node static-server (для архивного локального HTML-превью)
index.html            — старая клиентская версия (можно удалить, оставлено как бэкап)
BOT_INTEGRATION.md    — как бот выдаёт логины
DEPLOY.md             — этот файл
```
