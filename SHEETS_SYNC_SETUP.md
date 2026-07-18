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
const SHEET_GID = 87425732;   // gid вкладки «High staff» (на ней же ниже блок «Список модераторов»)
const ID_COL    = 3;          // C — айди
const WARN_COL  = 7;          // G — выговоры

// Строка с заголовками колонок блока «Список модераторов»
// («Дата | ID | Nick | Дни | Принял | От кого | Отчет | ... | Линк»).
// Если в таблице сдвинутся строки — открой таблицу, найди эту строку
// заголовков и поставь её номер сюда.
const MOD_HEADER_ROW = 39;
const MOD_DATE_COL = 2;     // B — дата
const MOD_ID_COL = 3;       // C — айди
const MOD_NICK_COL = 4;     // D — коренной ник
const MOD_REVIEWER_COL = 6; // F — проводящий («Принял»)

function doPost(e) {
    const respond = (obj) => ContentService
        .createTextOutput(JSON.stringify(obj))
        .setMimeType(ContentService.MimeType.JSON);

    try {
        const data = JSON.parse(e.postData.contents);
        if (data.token !== TOKEN) return respond({ error: 'unauthorized' });

        const ss = SpreadsheetApp.getActiveSpreadsheet();
        const sheet = ss.getSheets().filter(s => s.getSheetId() === SHEET_GID)[0];
        if (!sheet) return respond({ error: 'sheet not found' });

        if (data.action === 'update_warning') {
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

        // Сдал «Собес на модера» на сайте → добавляем строкой ниже
        // последнего заполненного модератора в блоке «Список модераторов».
        if (data.action === 'add_moderator') {
            let row = MOD_HEADER_ROW + 1;
            const maxRow = sheet.getMaxRows();
            while (row <= maxRow && String(sheet.getRange(row, MOD_ID_COL).getValue()).trim() !== '') {
                row++;
            }
            if (row > maxRow) return respond({ error: 'no empty row found below MOD_HEADER_ROW' });

            sheet.getRange(row, MOD_DATE_COL).setValue(data.date || '');
            sheet.getRange(row, MOD_ID_COL).setValue(String(data.discord_id || ''));
            sheet.getRange(row, MOD_NICK_COL).setValue(data.nick || '');
            sheet.getRange(row, MOD_REVIEWER_COL).setValue(data.reviewer || '');
            return respond({ ok: true, row });
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

Второй сценарий — приём в модеры:

- Кандидат сдаёт «Собес на модера» на сайте (балл ≥ 7/10), отправляется отчёт
- `api/send-report.php` POST-ит на тот же webhook: `{ token, action: 'add_moderator', date, discord_id, nick, reviewer }`
- Apps Script ищет первую пустую строку под заголовками блока «Список модераторов» (`MOD_HEADER_ROW`) и заполняет в ней B (дата), C (id), D (коренной ник), F (проводящий)
- Если кандидат не сдал — строка не добавляется, только отчёт в Telegram, как раньше

## Если уже разворачивал раньше — обнови код

Если Apps Script уже был задеплоен под сценарий выговоров (шаги 1-6 выше уже пройдены) и просто добавляешь `add_moderator`:

1. Открой Apps Script таблицы (Расширения → Apps Script), замени содержимое `Code.gs` на новую версию `doPost` из шага 2 выше (константы `MOD_HEADER_ROW` и т.д. — новые, добавь их).
2. Проверь `MOD_HEADER_ROW`: открой таблицу, найди строку с заголовками «Дата | ID | Nick | Дни | Принял | От кого | ...» под баннером «Список модераторов», кликни на номер этой строки слева — впиши его в константу.
3. **Важно**: сохранения кода (Ctrl+S) недостаточно — старый Web App URL продолжит работать по старой версии кода. Нужно **Развернуть → Управление развёртываниями** → у существующего развёртывания нажми на карандаш (редактировать) → в поле «Версия» выбери **«Новая версия»** → **Развернуть**. URL и токен остаются те же, в `.env` менять ничего не нужно.

## Проверка

После деплоя, в браузере можно открыть `Web app URL` напрямую — должно показать `Webhook alive`. Значит, скрипт развёрнут.

В логах `local-server.js` при выдаче выговора не должно быть ошибки `Sheets sync failed`. Если есть — проверь токен и URL.

## Безопасность

- **Никому не показывай** URL вместе с токеном — кто угодно с ним может писать в таблицу
- Если URL засветился — в Apps Script: `Развёртывания` → `Управление` → удалить старое + сгенерируй новый TOKEN
- Доступ «Anyone» обязателен (внешние POST), но обороняемся секретным токеном
