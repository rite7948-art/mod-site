// Точечно тянет аватарки нескольких людей с большого Discord-сервера через
// селфбота (для карточек High staff на главной). ID передаются JSON-массивом
// первым аргументом. Печатает { id: avatarUrl|null } в stdout и выходит.
//
// Небольшой список (~10-20 id) — логин + адресный fetch, без полного скана
// гильдии (135k участников), поэтому быстро и надёжно.

require('dotenv').config();
const { Client } = require('discord.js-selfbot-v13');

const GUILD_ID = process.env.MODER_GUILD_ID || '531970658633252864';
let ids = [];
try { ids = JSON.parse(process.argv[2] || '[]'); } catch {}

const client = new Client({ checkUpdate: false });

client.on('ready', async () => {
    const out = {};
    try {
        if (!ids.length) { console.log(JSON.stringify(out)); process.exit(0); }
        const guild = client.guilds.cache.get(GUILD_ID) || await client.guilds.fetch(GUILD_ID);
        const fetched = await guild.members.fetch({ user: ids, withPresences: false });
        fetched.forEach(m => {
            out[m.id] = m.user?.displayAvatarURL?.({ size: 128, format: 'png' }) || null;
        });
    } catch (e) {
        console.error('fetch_high_staff_avatars error:', e.message);
    } finally {
        console.log(JSON.stringify(out));
        process.exit(0);
    }
});

client.login(process.env.SELFBOT_TOKEN).catch(e => {
    console.error('login failed:', e.message);
    console.log(JSON.stringify({}));
    process.exit(1);
});
