# Интеграция с ботом

Сайт читает учётки **из двух мест** (login.php):
1. `users.json` рядом с PHP-файлами (туда пишет бот) — приоритетный источник
2. Таблица `users` в MySQL (актуальный кэш + первый вход)

При логине, если в `users.json` есть запись для введённого ника — она автоматически переносится в БД (или обновляется). Это значит: **бот должен только корректно вести `users.json`**, всё остальное сайт делает сам.

## Формат `users.json`

```json
{
  "имя_логина": {
    "password": "сгенерированный_пароль",
    "role": "master | curator | chief | admin",
    "discord_id": "123456789012345678"
  }
}
```

См. `users.json.example`.

## Slash-команда для `bot.js` (пример)

```js
// discord.js v14
const { SlashCommandBuilder, PermissionFlagsBits } = require('discord.js');
const fs = require('fs');
const path = require('path');

const USERS_JSON = path.join(__dirname, 'users.json');
const ROLES = ['master', 'curator', 'chief', 'admin'];

function loadUsers() {
    try { return JSON.parse(fs.readFileSync(USERS_JSON, 'utf8')); }
    catch { return {}; }
}
function saveUsers(data) {
    fs.writeFileSync(USERS_JSON, JSON.stringify(data, null, 2), 'utf8');
}
function genPassword(len = 10) {
    const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let s = '';
    for (let i = 0; i < len; i++) s += chars[Math.floor(Math.random() * chars.length)];
    return s;
}

module.exports = {
    data: new SlashCommandBuilder()
        .setName('модерсайт-доступ')
        .setDescription('Выдать доступ к сайту модераторов')
        .addUserOption(o => o.setName('юзер').setDescription('Пользователь').setRequired(true))
        .addStringOption(o => o.setName('логин').setDescription('Логин для входа').setRequired(true))
        .addStringOption(o => o.setName('роль').setDescription('Роль на сайте').setRequired(true)
            .addChoices(...ROLES.map(r => ({ name: r, value: r }))))
        .setDefaultMemberPermissions(PermissionFlagsBits.Administrator),

    async execute(interaction) {
        const user = interaction.options.getUser('юзер');
        const login = interaction.options.getString('логин').trim();
        const role = interaction.options.getString('роль');

        const users = loadUsers();
        const password = genPassword();
        users[login] = {
            password,
            role,
            discord_id: user.id,
        };
        saveUsers(users);

        // ЛС с креденшелами
        try {
            await user.send(
                `**Доступ к сайту модераторов**\n` +
                `Логин: \`${login}\`\n` +
                `Пароль: \`${password}\`\n` +
                `Роль: \`${role}\`\n\n` +
                `URL: https://<твой-railway-домен>/login.php`
            );
            await interaction.reply({ content: `✅ Логин выдан, отправил в ЛС <@${user.id}>`, ephemeral: true });
        } catch {
            await interaction.reply({
                content: `⚠️ Не смог отправить в ЛС. Креденшелы:\n\`\`\`\nЛогин: ${login}\nПароль: ${password}\nРоль: ${role}\n\`\`\``,
                ephemeral: true
            });
        }
    }
};
```

## Где должен лежать `users.json`?

Бот и сайт **в разных контейнерах Railway**, у них нет общей ФС. Варианты:

### Вариант 1 — общий Railway Volume (рекомендую)
1. В Railway создай **Volume** (например, `/data`).
2. Подмонтируй один и тот же Volume к **обоим** сервисам (сайт и бот) на путь `/data`.
3. В коде бота используй `USERS_JSON = '/data/users.json'`.
4. В `login.php` поменяй путь: `$users_json = '/data/users.json';`.

### Вариант 2 — общая БД (без `users.json`)
Если хочешь без файла — пусть бот сразу пишет в таблицу `users` (тот же MySQL). Тогда `login.php` будет читать только из БД, синхронизация с `users.json` не нужна.

```js
// в боте — вместо записи в JSON
await pool.query(
    `INSERT INTO users (username, password, discord_id, role) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role), discord_id = VALUES(discord_id)`,
    [login, password, user.id, role]
);
```

### Вариант 3 — webhook (если сервисы в разных проектах)
Сайт принимает POST с секретным токеном:
```php
// upsert_user.php
if ($_SERVER['HTTP_X_TOKEN'] !== getenv('BOT_SHARED_SECRET')) { http_response_code(403); exit; }
$data = json_decode(file_get_contents('php://input'), true);
// ... upsert в users + users.json
```
Бот вызывает `https://<сайт>/upsert_user.php`.

---

## Если коротко — что менять в `bot.js`

1. Поставить slash-команду из примера выше.
2. Решить, как делиться файлом — самое простое **Вариант 1 (Volume)**.
3. Указать в `users.json` путь к Volume или сразу писать в БД.

После этого: новый модератор в Discord получает ЛС с логином/паролем и заходит на `https://your-railway-domain/login.php`.
