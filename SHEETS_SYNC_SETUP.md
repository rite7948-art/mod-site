# Запись в Google-таблицу из сайта (через Apps Script)

Сайт пишет счёт выговоров (0/3, 1/3, 2/3, 3/3) обратно в таблицу при выдаче / снятии / удалении выговора. Делает это через **Google Apps Script Webhook** — не нужны GCP, OAuth и сервис-аккаунт, всё бесплатно.

## Что нужно сделать (5 минут)

### 1. Открой Apps Script в таблице

1. Открой свою таблицу: <https://docs.google.com/spreadsheets/d/15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU/edit>
2. В меню сверху → **Расширения** → **Apps Script**
3. Откроется редактор с пустым файлом `Code.gs`

### 2. Вставь код

Удали то, что там было, и вставь это (поменяй `TOKEN` на любую длинную строку — это твой секрет):

```js
// === Конфиг ===
const TOKEN = 'ПОМЕНЯЙ_МЕНЯ_НА_РАНДОМНУЮ_СТРОКУ_ХОТЯ_БЫ_32_СИМВОЛА';
const SHEET_GID = 87425732;   // gid вкладки «High staff»
const ID_COL    = 3;          // C — айди
const WARN_COL  = 7;          // G — выговоры

function doPost(e) {
    const respond = (obj) => ContentService
        .createTextOutput(JSON.stringify(obj))
        .setMimeType(ContentService.MimeType.JSON);

    try {
        const data = JSON.parse(e.postData.contents);
        if (data.token !== TOKEN) return respond({ error: 'unauthorized' });

        if (data.action === 'update_warning') {
            const ss = SpreadsheetApp.getActiveSpreadsheet();
            const sheet = ss.getSheets().filter(s => s.getSheetId() === SHEET_GID)[0];
            if (!sheet) return respond({ error: 'sheet not found' });

            const lastRow = sheet.getLastRow();
            const ids = sheet.getRange(1, ID_COL, lastRow, 1).getValues();
            const target = String(data.discord_id || '').trim();
            const count = Math.max(0, Math.min(3, parseInt(data.count, 10) || 0));

            for (let i = 0; i < ids.length; i++) {
                const cell = String(ids[i][0] || '').trim();
                if (cell === target) {
                    sheet.getRange(i + 1, WARN_COL).setValue(count + '/3');
                    return respond({ ok: true, row: i + 1, value: count + '/3' });
                }
            }
            return respond({ error: 'discord_id not found in sheet' });
        }
        return respond({ error: 'unknown action' });
    } catch (err) {
        return respond({ error: String(err) });
    }
}

function doGet() {
    return ContentService.createTextOutput('Webhook alive');
}
```

### 3. Сохрани (Ctrl+S)

Назови проект как хочешь (например `Moder Warnings Webhook`).

### 4. Деплой как Web App

1. Сверху-справа кнопка **Deploy / Развернуть** → **Новое развёртывание** (New deployment)
2. Иконка-шестерёнка слева → **Web app** (Веб-приложение)
3. Параметры:
   - **Описание** — любое
   - **Выполнять от имени** (Execute as) — **«я» / Me** (твой аккаунт, у которого есть права на таблицу)
   - **Кто имеет доступ** (Who has access) — **«Все» / Anyone**
4. **Развернуть** → пройди согласие Google (один раз: «Дополнительно» → «Перейти к проекту»)
5. Скопируй итоговый **Web app URL** — это что-то вроде:
   ```
   https://script.google.com/macros/s/AKfyc.../exec
   ```

### 5. Добавь переменные в .env

Открой `.env` рядом с `bot.js` / `local-server.js` и добавь:

```
SHEETS_WEBHOOK_URL=https://script.google.com/macros/s/AKfyc.../exec
SHEETS_WEBHOOK_TOKEN=та_же_длинная_строка_что_в_Apps_Script
```

### 6. Перезапусти `local-server.js`

В консоли где он запущен — Ctrl+C → `node local-server.js`. Готово.

## Как это работает

- Выдаёшь / снимаешь / удаляешь выговор на сайте
- `local-server.js` POST-ит на webhook URL: `{ token, action, discord_id, count }`
- Apps Script проверяет токен, находит строку с этим discord_id в колонке C, ставит `<count>/3` в колонку G
- В Google-таблице у этого человека счёт обновляется автоматически

## Проверка

После деплоя, в браузере можно открыть `Web app URL` напрямую — должно показать `Webhook alive`. Значит, скрипт развёрнут.

В логах `local-server.js` при выдаче выговора не должно быть ошибки `Sheets sync failed`. Если есть — проверь токен и URL.

## Безопасность

- **Никому не показывай** URL вместе с токеном — кто угодно с ним может писать в таблицу
- Если URL засветился — в Apps Script: `Развёртывания` → `Управление` → удалить старое + сгенерируй новый TOKEN
- Доступ «Anyone» обязателен (внешние POST), но обороняемся секретным токеном
