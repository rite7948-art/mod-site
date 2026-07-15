<?php
// Общие хелперы для send-embed.php / edit-embed.php — сообщение в Discord
// может нести до 10 эмбитов сразу, суммарный текст ограничен 6000 символов.

function embed_channels() {
    return [
        'master'  => '1510992131446018139',
        'curator' => '1510992164392538163',
        'help'    => '1526302909493543092',
    ];
}

function clean_utf8_embed($s) {
    return mb_convert_encoding((string)$s, 'UTF-8', 'UTF-8');
}

// Приводит сырой ввод клиента (массив {title,description,image,color}) к
// массиву настоящих Discord-эмбитов + пишет footer/timestamp на каждый —
// так видно, кто и когда опубликовал/отредактировал, независимо от того,
// сколько эмбитов в сообщении.
// Возвращает [$discordEmbeds, $cleanedForLog, $error] — при ошибке первые
// два элемента будут null.
function build_discord_embeds($rawEmbeds, $footerVerb, $username) {
    if (!is_array($rawEmbeds) || count($rawEmbeds) === 0) {
        return [null, null, 'нужен хотя бы один эмбит'];
    }
    if (count($rawEmbeds) > 10) {
        return [null, null, 'в одном сообщении можно максимум 10 эмбитов'];
    }

    $embeds = [];
    $cleaned = [];
    $totalLen = 0;
    foreach ($rawEmbeds as $raw) {
        $title = mb_substr(clean_utf8_embed(trim((string)($raw['title'] ?? ''))), 0, 256);
        $description = mb_substr(clean_utf8_embed(trim((string)($raw['description'] ?? ''))), 0, 4096);
        $image = trim((string)($raw['image'] ?? ''));
        if ($title === '' && $description === '') {
            return [null, null, 'у каждого эмбита должен быть заголовок или текст'];
        }
        $totalLen += mb_strlen($title) + mb_strlen($description);

        $color = 0xe5352b;
        $colorHex = (string)($raw['color'] ?? '#e5352b');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $colorHex)) {
            $color = hexdec(substr($colorHex, 1));
        }

        $embed = ['color' => $color];
        if ($title !== '') $embed['title'] = $title;
        if ($description !== '') $embed['description'] = $description;
        if ($image !== '' && preg_match('#^https?://#i', $image)) $embed['image'] = ['url' => $image];
        $embed['footer'] = ['text' => $footerVerb . ' ' . $username];
        $embed['timestamp'] = gmdate('c');
        $embeds[] = $embed;

        $cleaned[] = ['title' => $title, 'description' => $description, 'image' => $image, 'color' => $colorHex];
    }

    if ($totalLen > 6000) {
        return [null, null, 'суммарно текст всех эмбитов превышает 6000 символов (' . $totalLen . ')'];
    }

    return [$embeds, $cleaned, null];
}
