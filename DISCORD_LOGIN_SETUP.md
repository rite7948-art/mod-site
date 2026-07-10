# Вход через Discord — настройка

Сайт получает Discord ID юзера через OAuth, ищет его в `roster.json` (бот заполняет автоматически по ролям на сервере) и пускает с соответствующей site-ролью:

- Discord-роль **Administrator** → site-роль `admin`
- Discord-роль **GLK** → `chief`
- Discord-роль **Curator** → `curator`
- Discord-роль **Master** → `master`
- Нет ни одной → отказ «У тебя нет роли стаффа на сервере»

## Что нужно сделать (3 минуты)

### 1. Discord Developer Portal

1. Открой → <https://discord.com/developers/applications>
2. Выбери **Futurama Mod** (то же приложение, что у бота)
3. Слева → **OAuth2**
4. В блоке **Redirects** → **Add Redirect** → впиши:
   ```
   http://localhost:8090/auth/discord/callback
   ```
   (для Railway позже добавишь ещё `https://your-domain.up.railway.app/auth/discord/callback`)
5. **Save Changes** внизу
6. Сверху скопируй **Client ID** и **Client Secret** (Reset Secret → Copy)

### 2. Открой `.env` рядом с `bot.js`

Добавь / обнови три строки:

```
DISCORD_CLIENT_ID=твой_client_id
DISCORD_CLIENT_SECRET=твой_client_secret
DISCORD_REDIRECT_URI=http://localhost:8090/auth/discord/callback
```

### 3. Перезапусти сервер

```
Ctrl+C → node local-server.js
```

## Как пользоваться

1. Открой <http://localhost:8090/login>
2. Нажми синюю кнопку **«Войти через Discord»**
3. Discord попросит подтверждение → жмёшь Authorize
4. Тебя редиректит обратно на сайт уже залогиненным с твоей ролью

## Условия для входа

Юзер должен:
- Быть на твоём Discord-сервере
- Иметь одну из ролей: **Administrator**, **GLK**, **Curator** или **Master**
- Бот должен быть запущен и хотя бы раз обновить `roster.json` (это происходит на старте + каждые 5 минут + при изменении ролей)

Если кто-то не в роли → сайт откажет.

## Безопасность

- **Client Secret** держи в `.env`, в Git не пушь
- Если случайно засветил Secret — Developer Portal → OAuth2 → **Reset Secret**
- Для деплоя на Railway — `DISCORD_REDIRECT_URI=https://your-domain.up.railway.app/auth/discord/callback` (в Discord-портале добавь оба редиректа)

## Параллельный вход

Старый ручной логин/пароль продолжает работать — кнопка «Войти через Discord» добавлена сверху, форма ниже. Бот выдаёт логины как раньше через `/доступ`.
