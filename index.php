<?php
require_once __DIR__ . '/auth_helper.php';
require_login();
$me = current_user();
$me['role_name'] = role_display_name($me['role']);

// Аватарка из Discord через внутренний sync-сервис (если настроен и успел
// ответить быстро) — иначе просто буква, как раньше.
$me['avatar_url'] = null;
$syncServiceUrl = getenv('SYNC_SERVICE_URL') ?: '';
$syncToken = getenv('INTERNAL_SYNC_TOKEN') ?: '';
if ($syncServiceUrl && $syncToken && !empty($me['discord_id'])) {
    $ch = curl_init(rtrim($syncServiceUrl, '/') . '/high-staff/avatar?id=' . urlencode($me['discord_id']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Internal-Token: ' . $syncToken]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    $resp = curl_exec($ch);
    curl_close($ch);
    if ($resp !== false) {
        $data = json_decode($resp, true);
        if (!empty($data['avatar'])) $me['avatar_url'] = $data['avatar'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Futurama Moders | Главная</title>
    <link rel="icon" type="image/png" href="/logo.webp">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js" defer></script>
</head>
<body>
    <div class="dashboard-container">
        <button class="mobile-nav-toggle" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

        <aside class="sidebar" id="mainSidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="/logo.webp" alt="" class="logo-img">
                    <span>Futurama <span>Moderator</span></span>
                </div>
                <div class="sidebar-user-card">
                    <?php if ($me['avatar_url']): ?>
                        <img class="avatar-circle" src="<?= htmlspecialchars($me['avatar_url']) ?>" style="object-fit:cover;" alt="">
                    <?php else: ?>
                        <div class="avatar-circle"><?= htmlspecialchars(mb_strtoupper(mb_substr($me['username'] ?: '?', 0, 1))) ?></div>
                    <?php endif; ?>
                    <div style="overflow:hidden;">
                        <div class="u-name"><?= htmlspecialchars($me['username']) ?></div>
                        <div class="u-role role-<?= htmlspecialchars($me['role']) ?>"><?= htmlspecialchars($me['role_name']) ?></div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-label">Основное</div>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a class="nav-link" data-tab="profile"><i class="fas fa-user-circle"></i> <span>Профиль</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" data-tab="home"><i class="fas fa-house"></i> <span>Главная</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://docs.google.com/spreadsheets/d/15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU/edit?gid=87425732#gid=87425732" target="_blank" rel="noopener">
                                <i class="fas fa-table-list"></i>
                                <span>Таблица</span>
                                <i class="fas fa-arrow-up-right-from-square" style="margin-left:auto;font-size:0.7rem;opacity:0.5;"></i>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="warnings"><i class="fas fa-triangle-exclamation"></i> <span>Выговоры</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="archive"><i class="fas fa-box-archive"></i> <span>Архив</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="sync"><i class="fas fa-rotate"></i> <span>Сверка таблицы</span></a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Собеседования</div>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a class="nav-link" data-tab="interview-moder"><i class="fas fa-user-check"></i> <span>Собес на модера</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="interview-master"><i class="fas fa-chalkboard-user"></i> <span>Собес на мастера</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="reatt-moders"><i class="fas fa-file-pen"></i> <span>Переаттестация модеров</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="reatt-masters"><i class="fas fa-file-signature"></i> <span>Переаттестация мастеров</span></a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Памятки</div>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a class="nav-link" data-tab="memo-masters"><i class="fas fa-clipboard-list"></i> <span>Памятка для мастеров</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="memo-curators"><i class="fas fa-shield-halved"></i> <span>Памятка для кураторов</span></a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Активности</div>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a class="nav-link" data-tab="events"><i class="fas fa-bullhorn"></i> <span>Ивенты</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="embeds"><i class="fas fa-rectangle-list"></i> <span>Эмбиты</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="giveaways"><i class="fas fa-gift"></i> <span>Розыгрыши</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="wheel"><i class="fas fa-dharmachakra"></i> <span>Колесо фортуны</span></a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Отчёты</div>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a class="nav-link" data-tab="reports"><i class="fas fa-file-lines"></i> <span>Отчётность</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="tokens"><i class="fas fa-coins"></i> <span>Токены</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab="voice-activity"><i class="fas fa-microphone"></i> <span>Активность</span></a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Level up</div>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a class="nav-link" data-tab="levelup"><i class="fas fa-arrow-up-right-dots"></i> <span>Level up</span></a>
                        </li>
                    </ul>
                </div>

            </nav>

            <a class="sidebar-logout" href="logout.php" onclick="return confirm('Выйти из аккаунта?');">
                <i class="fas fa-right-from-bracket"></i> Выйти
            </a>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1 id="pageTitle">Главная</h1>
                    <p id="pageSubtitle">Ваш состав</p>
                </div>
                <div class="header-actions"></div>
            </header>

            <div class="page-body">
                <!-- Профиль -->
                <section class="tab-page" id="tab-profile">
                    <div class="profile-container">
                        <div class="save-bar" id="profSaveBar" style="display:none;">
                            <div style="color:#34d399;font-weight:600;"><i class="fas fa-circle-info"></i> Несохранённые изменения</div>
                            <div style="display:flex;gap:10px;">
                                <button class="btn-profile-action" id="profDiscardBtn">Сбросить</button>
                                <button class="btn-confirm-save" id="profSaveBtn">Сохранить</button>
                            </div>
                        </div>

                        <div class="profile-header-card" id="profHeader">
                            <div class="u-avatar-wrap">
                                <img id="profAvatar" class="u-avatar-img" alt="">
                            </div>
                            <div class="u-info">
                                <h1 id="profName" class="u-name-text"></h1>
                                <div class="u-discord-id"><i class="fab fa-discord"></i> <span id="profDiscordId"></span></div>
                                <span id="profRole" class="u-badge"></span>
                            </div>
                            <div style="z-index:2;align-self:flex-start;">
                                <button class="btn-profile-action" id="profEditBtn">
                                    <i class="fas fa-pen-nib"></i> Оформить профиль
                                </button>
                            </div>
                        </div>

                        <div class="u-stats-grid">
                            <div class="u-stat-card">
                                <span class="u-stat-label">Собесов на модера</span>
                                <span class="u-stat-value" id="statModerTotal">0</span>
                            </div>
                            <div class="u-stat-card">
                                <span class="u-stat-label">Собесов на мастера</span>
                                <span class="u-stat-value" id="statMasterTotal">0</span>
                            </div>
                        </div>

                        <div class="u-about-box">
                            <h3>Обо мне</h3>
                            <div class="u-about-text" id="profAbout">Пользователь ещё не заполнил информацию о себе.</div>
                        </div>
                    </div>

                    <div id="profEditModal" class="modal">
                        <div class="modal-content">
                            <h2 style="font-size:1.4rem;margin-bottom:1.5rem;">Изменить профиль</h2>

                            <label class="form-label">Баннер</label>
                            <div class="banner-zone" id="profBannerZone" tabindex="0">
                                <i class="fas fa-image" id="profBannerIcon"></i>
                                <span id="profBannerHint">Клик — выбрать файл · Ctrl+V — вставить из буфера</span>
                                <button type="button" class="banner-clear" id="profBannerClear" style="display:none;" title="Убрать баннер"><i class="fas fa-xmark"></i></button>
                            </div>
                            <input type="file" id="profBannerFile" accept="image/*" style="display:none;">
                            <input type="text" id="profBannerInput" placeholder="…или URL картинки" class="form-control">

                            <label class="form-label">Обо мне</label>
                            <textarea id="profAboutInput" class="form-control" rows="5" style="resize:none;"></textarea>

                            <div style="display:flex;gap:1rem;">
                                <button class="btn-profile-action" id="profModalCancel" style="flex:1;">Отмена</button>
                                <button class="btn-confirm-save" id="profModalApply" style="flex:1;">Применить</button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Главная -->
                <section class="tab-page active" id="tab-home">
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-users" style="color: var(--accent); font-size:1.3rem;"></i>
                                <h3>Состав вышки</h3>
                            </div>
                            <span class="status-badge" id="wyshkaStatus">Загрузка…</span>
                        </div>
                        <div id="wyshkaContainer">
                            <div style="display:flex;justify-content:center;padding:3rem;">
                                <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--accent);"></i>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- Собес на модера -->
                <section class="tab-page" id="tab-interview-moder">
                    <div class="info-banner">
                        <div class="ib-icon"><i class="fas fa-circle-info"></i></div>
                        <div class="ib-text">
                            Перед началом собеседования обязательно спрашивайте, <b>сколько лет пользователю</b>. Кандидат может выбрать <b>1 вариант из 3</b>.
                            <span class="q-pass"><i class="fas fa-check-circle"></i>Проходной балл 7/10</span>
                        </div>
                    </div>

                    <div class="candidate-card">
                        <div class="cc-field">
                            <label>Коренной ник</label>
                            <input id="candNick" placeholder="" autocomplete="off">
                        </div>
                        <div class="cc-field">
                            <label>ID</label>
                            <input id="candId" placeholder="Discord ID" autocomplete="off">
                        </div>
                        <div class="cc-field">
                            <label>Проверяющий</label>
                            <input id="candReviewer" placeholder="" autocomplete="off">
                        </div>
                        <div class="cc-score">
                            <div class="cc-score-label">Балл</div>
                            <div class="cc-score-value" id="candScore">0 / 10</div>
                        </div>
                        <button class="cc-reset" id="resetRatings" type="button" title="Сбросить оценки этого варианта">
                            <i class="fas fa-arrows-rotate"></i>
                        </button>
                    </div>

                    <div class="cc-actions">
                        <button class="cc-submit" id="submitReport" type="button">
                            <i class="fas fa-paper-plane"></i> Отправить отчёт
                        </button>
                        <span class="cc-tg-status" id="reportTgStatus"></span>
                    </div>

                    <div class="variant-tabs">
                        <button class="variant-btn active" data-variant="1">Вариант 1</button>
                        <button class="variant-btn" data-variant="2">Вариант 2</button>
                        <button class="variant-btn" data-variant="3">Вариант 3</button>
                    </div>

                    <div class="questions-list" id="questionsList"></div>

                    <div class="reports-section">
                        <div class="reports-header">
                            <h3><i class="fas fa-clipboard-check"></i> История отчётов</h3>
                            <span class="reports-count" id="reportsCount">0</span>
                        </div>
                        <div class="reports-list" id="reportsList"></div>
                    </div>
                </section>

                <!-- Собес на мастера -->
                <section class="tab-page" id="tab-interview-master">
                    <div class="info-banner">
                        <div class="ib-icon"><i class="fas fa-circle-info"></i></div>
                        <div class="ib-text">
                            Собеседование на мастера — доступно <b>кураторам и выше</b>. Всего <b>35 вопросов</b>.
                            <span class="q-pass"><i class="fas fa-check-circle"></i>Проходной балл 25/35</span>
                        </div>
                    </div>

                    <div class="candidate-card">
                        <div class="cc-field">
                            <label>Коренной ник</label>
                            <input id="mCandNick" placeholder="" autocomplete="off">
                        </div>
                        <div class="cc-field">
                            <label>ID</label>
                            <input id="mCandId" placeholder="Discord ID" autocomplete="off">
                        </div>
                        <div class="cc-field">
                            <label>Проверяющий</label>
                            <input id="mCandReviewer" placeholder="" autocomplete="off">
                        </div>
                        <div class="cc-score">
                            <div class="cc-score-label">Балл</div>
                            <div class="cc-score-value" id="mCandScore">0 / 35</div>
                        </div>
                        <button class="cc-reset" id="mResetRatings" type="button" title="Сбросить оценки">
                            <i class="fas fa-arrows-rotate"></i>
                        </button>
                    </div>

                    <div class="cc-actions">
                        <button class="cc-submit" id="mSubmitReport" type="button">
                            <i class="fas fa-paper-plane"></i> Отправить отчёт
                        </button>
                        <span class="cc-tg-status" id="mReportTgStatus"></span>
                    </div>

                    <div class="questions-list" id="mastersInterviewList"></div>

                    <div class="reports-section">
                        <div class="reports-header">
                            <h3><i class="fas fa-clipboard-check"></i> История отчётов</h3>
                            <span class="reports-count" id="mReportsCount">0</span>
                        </div>
                        <div class="reports-list" id="mReportsList"></div>
                    </div>
                </section>

                <!-- Памятка для мастеров -->
                <section class="tab-page" id="tab-memo-masters">
                    <div class="memo-card">
                        <div class="memo-title">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Памятка для мастеров</span>
                        </div>
                        <ol class="memo-list">
                            <li><span>Принять / отклонить заявку.</span></li>
                            <li><span>При выборе смены объяснить про ПТ с 1–30 руму.</span></li>
                            <li><span>Уши не выключать; если отходит — выходит с войса.</span></li>
                            <li><span>Удалить заявку.</span></li>
                            <li><span>Рассказать про команды <code>/action</code> и <code>/voice</code> (спросить, всё ли ясно).</span></li>
                            <li><span>Есть 3 степени наказания: устное предупреждение, мут или бан — в зависимости от ситуации.</span></li>
                            <li><span>Рассказать про канал репортов (СТАФФ / ЧАТ / СПОНСОРОВ — не трогать).</span></li>
                            <li><span>Бан-пруфы / мут-пруфы скидываются в обязательном порядке.</span></li>
                            <li><span>Канал «Информация» на локалке — правила ветки + шпора.</span></li>
                            <li><span>Отпуска.</span></li>
                            <li><span>Ознакомить с категорией «Основное».</span></li>
                            <li><span>1-й день — ознакомительный, к работе приступать со следующего дня.</span></li>
                            <li><span>Обязательно вести откат в мод-войсах.</span></li>
                            <li><span>Персоналу (стафф) наказание не выдаётся — пишется репорт при нарушении правил.</span></li>
                            <li>
                                <span>Проговаривать модерам инфу: если они находятся в войсе меньше:
                                    <ul>
                                        <li>4 часов в неделю — снятие;</li>
                                        <li>7 часов в неделю — выговор 14 дней;</li>
                                        <li>10 часов в неделю — выговор 7 дней;</li>
                                        <li>12 часов в неделю — устник 7 дней.</li>
                                    </ul>
                                </span>
                            </li>
                            <li><span>Брать на ветку от 3 дней на сервере.</span></li>
                        </ol>
                    </div>
                </section>

                <!-- Памятка для кураторов -->
                <section class="tab-page" id="tab-memo-curators">
                    <div class="memo-card">
                        <div class="memo-title">
                            <i class="fas fa-shield-halved"></i>
                            <span>Гайд: как не вьебать рольку куратора на сервере Futurama</span>
                        </div>
                        <p class="memo-intro">
                            Этот гайд предназначен для кураторов сервера Futurama. Он поможет грамотно разбирать
                            репорты на модераторов и мастеров, проводить наборы и контролировать активность ветки.
                        </p>

                        <div class="memo-section">
                            <div class="memo-section-title"><i class="fas fa-gavel"></i> Разбор репортов</div>
                            <p>
                                Куратор рассматривает репорты на мастеров и модераторов. Процесс аналогичен описанному
                                в гайде мастера: собираются доказательства, уточняется ситуация, после чего выносится
                                решение по наказанию — <b>устное предупреждение</b> или <b>выговор</b>.
                            </p>
                        </div>

                        <div class="memo-section">
                            <div class="memo-section-title"><i class="fas fa-user-plus"></i> Наборы</div>
                            <p>
                                Кураторы имеют право проводить наборы модераторов — подробная инструкция в гайде мастера.
                            </p>
                            <p style="margin-top: 0.6rem;">Также куратор может набирать <b>мастеров</b>. Для этого необходимо:</p>
                            <ul class="memo-list memo-list-bullets">
                                <li><span>Согласовать кандидата с <b>администратором ветки</b> и <b>главным куратором</b>.</span></li>
                                <li><span>Кандидат должен быть <b>не младше 16 лет</b>.</span></li>
                                <li><span>Кандидат должен пройти <b>собеседование на мастера</b>. Ссылку на вопросы выдаёт админ ветки.</span></li>
                            </ul>
                        </div>

                        <div class="memo-section">
                            <div class="memo-section-title"><i class="fas fa-clock"></i> Подсчёт часов ветки</div>
                            <p>
                                Каждый <b>понедельник</b> куратор обязан подсчитать часы голосовой активности всех
                                модераторов ветки.
                            </p>
                            <ol class="memo-list">
                                <li><span>Команда: <code>/voice группа:Moderator target:ID</code></span></li>
                                <li><span>Выберите в выпадающем списке <b>прошлую неделю</b> и зафиксируйте количество часов.</span></li>
                                <li><span>Впишите значение в графу <b>«Часы»</b> в таблице.</span></li>
                            </ol>
                        </div>

                        <div class="memo-section">
                            <div class="memo-section-title"><i class="fas fa-user-secret"></i> Проверка на double staff</div>
                            <p>
                                Также по <b>понедельникам</b> куратор обязан проверять модераторов на двойной стаффинг.
                            </p>
                            <ol class="memo-list">
                                <li><span>Зайдите в чат: <a href="https://discord.com/channels/531970658633252864/1277270855575142611" target="_blank" rel="noopener" class="memo-link">discord.com/channels/.../1277270855575142611</a></span></li>
                                <li><span>Введите команду: <code>!checkguilds ID</code></span></li>
                            </ol>
                            <p style="margin-top: 0.6rem;">Допустимы сервера-партнёры:</p>
                            <div class="memo-tags">
                                <span class="memo-tag">Elysium</span>
                                <span class="memo-tag">Hatory</span>
                                <span class="memo-tag">Darkness</span>
                                <span class="memo-tag">Solisaid</span>
                                <span class="memo-tag">Stockholm</span>
                                <span class="memo-tag">Aletheia</span>
                            </div>
                        </div>

                        <div class="memo-section">
                            <div class="memo-section-title"><i class="fas fa-ghost"></i> Проверка таблицы на мёртвые души</div>
                            <p>
                                Каждую неделю проверяйте таблицу на наличие неактивных модераторов, которые были
                                удалены с сервера, но остались в таблице. При нахождении таких пользователей — удалите
                                их из таблицы вручную.
                            </p>
                            <p class="memo-warn">
                                <i class="fas fa-triangle-exclamation"></i>
                                Не забывайте удалять их и из <b>смены</b>.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Переаттестация для мастеров -->
                <section class="tab-page" id="tab-reatt-masters">
                    <div class="info-banner">
                        <div class="ib-icon"><i class="fas fa-question-circle"></i></div>
                        <div class="ib-text">Вопросы для переаттестации мастеров. Нажмите «Показать ответ», чтобы увидеть правильный вариант.</div>
                    </div>

                    <div class="questions-list" id="mastersReattList"></div>
                </section>

                <!-- Переаттестация для модеров -->
                <section class="tab-page" id="tab-reatt-moders">
                    <div class="info-banner">
                        <div class="ib-icon"><i class="fas fa-question-circle"></i></div>
                        <div class="ib-text">Вопросы для переаттестации модеров. Нажмите «Показать ответ», чтобы увидеть правильный вариант.</div>
                    </div>

                    <div class="questions-list" id="modersReattList"></div>
                </section>

                <!-- Ивенты -->
                <section class="tab-page" id="tab-events">
                    <!-- Выбор типа -->
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-bullhorn" style="color:var(--accent);font-size:1.3rem;"></i>
                                <h3>Создать ивент</h3>
                            </div>
                        </div>
                        <div class="event-types-grid" id="eventTypesGrid"></div>
                    </div>

                    <!-- Список созданных -->
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-list" style="color:var(--accent);font-size:1.3rem;"></i>
                                <h3>Проведённые ивенты</h3>
                                <span class="status-badge" id="eventsCount">0</span>
                            </div>
                        </div>
                        <div id="eventsList"></div>
                    </div>
                </section>

                <!-- Эмбиты -->
                <section class="tab-page" id="tab-embeds">
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-rectangle-list" style="color:var(--accent);font-size:1.3rem;"></i>
                                <h3>Эмбит для инфо-канала</h3>
                            </div>
                        </div>

                        <label class="form-label">Изменить существующее сообщение</label>
                        <div class="embed-link-row">
                            <input type="text" id="embedLinkInput" class="form-control" placeholder="Вставь ссылку на сообщение в инфо-канале">
                            <button class="btn-profile-action" id="embedLinkLoadBtn" type="button">Загрузить</button>
                        </div>
                        <div id="embedEditingBanner" class="embed-editing-banner" style="display:none;">
                            <span><i class="fas fa-pen"></i> Редактируешь существующее сообщение</span>
                            <a id="embedEditingLink" href="#" target="_blank" rel="noopener">Открыть в Discord ↗</a>
                            <button id="embedEditingCancel" type="button" title="Отменить редактирование"><i class="fas fa-xmark"></i></button>
                        </div>

                        <label class="form-label">Канал</label>
                        <div class="embed-channel-row">
                            <label class="embed-channel-opt">
                                <input type="radio" name="embedChannel" value="master" checked>
                                <span>Инфо для мастеров</span>
                            </label>
                            <label class="embed-channel-opt">
                                <input type="radio" name="embedChannel" value="curator">
                                <span>Инфо для кураторов</span>
                            </label>
                            <label class="embed-channel-opt">
                                <input type="radio" name="embedChannel" value="help">
                                <span>Как пользоваться сайтом</span>
                            </label>
                        </div>

                        <div id="embedCardsContainer"></div>

                        <div class="embed-add-row">
                            <button class="btn-profile-action" id="embedAddCardBtn" type="button">
                                <i class="fas fa-plus"></i> Добавить эмбит
                            </button>
                            <span class="embed-char-counter" id="embedTotalCounter">0/6000</span>
                        </div>

                        <div style="display:flex;justify-content:flex-end;margin-top:1.5rem;">
                            <button class="btn-confirm-save" id="embedSendBtn" type="button">
                                <i class="fab fa-discord"></i> <span id="embedSendBtnLabel">Отправить</span>
                            </button>
                        </div>
                        <div id="embedStatusMsg" style="margin-top:0.75rem;font-size:0.85rem;"></div>

                        <label class="form-label" style="margin-top:1.75rem;">Последние отправленные</label>
                        <div id="embedHistoryList" class="embed-history-list">
                            <div class="reports-empty">Загрузка…</div>
                        </div>
                    </div>
                </section>

                <!-- Модалка ивента -->
                <div id="eventModal" class="modal">
                    <div class="modal-content" style="max-width:680px;">
                        <h2 id="eventModalTitle" style="font-size:1.4rem;margin-bottom:1rem;">Ивент</h2>
                        <div id="eventPrizeBox" class="event-prize-box"></div>

                        <div class="event-meta-row">
                            <div style="flex:1;">
                                <label class="form-label">Дата</label>
                                <input type="date" id="evDate" class="form-control">
                            </div>
                            <div style="flex:1;">
                                <label class="form-label">Время</label>
                                <input type="time" id="evTime" class="form-control">
                            </div>
                            <div style="flex:1.4;">
                                <label class="form-label">Проводит</label>
                                <input type="text" id="evOrganizer" class="form-control" placeholder="Ник">
                            </div>
                        </div>

                        <!-- 5v5 формат -->
                        <div id="evTeam5v5" style="display:none;">
                            <div class="event-teams-row">
                                <div class="event-team">
                                    <div class="event-team-head">
                                        <span class="event-team-label">Команда 1</span>
                                        <label class="event-winner-radio">
                                            <input type="radio" name="evWinner" value="1"> Победила
                                        </label>
                                    </div>
                                    <div class="event-slots" id="evTeam1Slots"></div>
                                </div>
                                <div class="event-team">
                                    <div class="event-team-head">
                                        <span class="event-team-label">Команда 2</span>
                                        <label class="event-winner-radio">
                                            <input type="radio" name="evWinner" value="2"> Победила
                                        </label>
                                    </div>
                                    <div class="event-slots" id="evTeam2Slots"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Podium формат -->
                        <div id="evPodium" style="display:none;">
                            <label class="form-label">Призовые места</label>
                            <div class="event-podium">
                                <div class="event-place place-1">
                                    <span class="place-num">1</span>
                                    <input type="text" id="evFirst" class="form-control" placeholder="Ник победителя" style="margin:0;">
                                </div>
                                <div class="event-place place-2">
                                    <span class="place-num">2</span>
                                    <input type="text" id="evSecond" class="form-control" placeholder="Ник" style="margin:0;">
                                </div>
                                <div class="event-place place-3">
                                    <span class="place-num">3</span>
                                    <input type="text" id="evThird" class="form-control" placeholder="Ник" style="margin:0;">
                                </div>
                            </div>
                            <label class="form-label" style="margin-top:1rem;">Прочие участники (через запятую, опц.)</label>
                            <input type="text" id="evParticipants" class="form-control" placeholder="ник1, ник2, ник3...">
                        </div>

                        <label class="form-label">Заметки (опц.)</label>
                        <textarea id="evNotes" class="form-control" rows="2" style="resize:none;" placeholder="Любые комментарии"></textarea>

                        <div style="display:flex;gap:1rem;margin-top:1rem;">
                            <button class="btn-profile-action" id="evCancel" style="flex:1;">Отмена</button>
                            <button class="btn-confirm-save" id="evSubmit" style="flex:1;"><i class="fas fa-floppy-disk"></i> Сохранить</button>
                        </div>
                    </div>
                </div>

                <!-- Розыгрыши -->
                <section class="tab-page" id="tab-giveaways">
                    <div class="card">
                        <div class="card-header"><h3>Розыгрыши</h3></div>
                        <div class="placeholder">
                            <i class="fas fa-gift"></i>
                            <h2>Розыгрыши</h2>
                        </div>
                    </div>
                </section>

                <!-- Колесо фортуны -->
                <section class="tab-page" id="tab-wheel">
                    <div class="wheel-container">
                        <div class="wheel-box card">
                            <div class="wheel-wrapper">
                                <div class="wheel-pointer"></div>
                                <canvas id="wheel-canvas" width="500" height="500"></canvas>
                            </div>

                            <button id="spin-button" class="btn-spin" type="button">
                                <i class="fas fa-rotate"></i> КРУТИТЬ КОЛЕСО
                            </button>

                            <div id="winner-box" class="winner-display">
                                <div class="winner-label">Победитель</div>
                                <div id="winner-name" class="winner-name">—</div>
                            </div>
                        </div>

                        <div class="controls-box">
                            <div class="controls-header">
                                <span><i class="fas fa-list-ul"></i> Варианты</span>
                                <button class="wheel-add-btn" id="wheelAddBtn" type="button" title="Добавить вариант"><i class="fas fa-plus"></i></button>
                            </div>

                            <div id="options-list" class="wheel-options"></div>

                            <div class="wheel-footer">
                                <button class="wheel-reset" id="wheelResetBtn" type="button"><i class="fas fa-rotate-left"></i> Сбросить</button>
                                <select id="wheelSpeed" class="wheel-speed-select" title="Скорость кручения">
                                    <option value="1">Очень медленно</option>
                                    <option value="2">Медленно</option>
                                    <option value="3" selected>Норма</option>
                                    <option value="4">Быстро</option>
                                    <option value="5">Очень быстро</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Выговоры -->
                <section class="tab-page" id="tab-warnings">
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-triangle-exclamation" style="color:var(--accent);font-size:1.3rem;"></i>
                                <h3>Выговоры — кураторы и мастера</h3>
                            </div>
                            <span class="status-badge" id="warnStatus">Загрузка…</span>
                        </div>
                        <div id="warnContainer">
                            <div style="display:flex;justify-content:center;padding:3rem;">
                                <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--accent);"></i>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Архив выговоров -->
                <section class="tab-page" id="tab-archive">
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-box-archive" style="color:var(--accent);font-size:1.3rem;"></i>
                                <h3>Архив выговоров</h3>
                                <span class="status-badge" id="warnArchiveCount">0</span>
                            </div>
                            <div class="reports-filter" id="archiveFilter" style="margin-bottom:0;">
                                <button class="rf-btn active" data-filter="all" type="button">Все</button>
                                <button class="rf-btn" data-filter="active" type="button">Активные</button>
                                <button class="rf-btn" data-filter="justified" type="button">Сняты</button>
                                <button class="rf-btn" data-filter="expired" type="button">Истекли</button>
                            </div>
                        </div>
                        <div id="warnArchiveList"></div>
                    </div>
                </section>

                <!-- Сверка таблицы -->
                <section class="tab-page" id="tab-sync">
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-rotate" style="color: var(--accent); font-size:1.3rem;"></i>
                                <h3>Сверка модеров</h3>
                            </div>
                            <button id="btnStartSync" class="btn-confirm-save" type="button">
                                <i class="fas fa-play"></i> Начать проверку
                            </button>
                        </div>
                        <div class="sync-loader" id="syncLoader">
                            <p id="syncLoaderStatus">Подключение…</p>
                        </div>
                    </div>

                    <div class="sync-results" id="syncResults">
                        <!-- Дубликаты в таблице -->
                        <div class="sync-card sync-card-orange sync-card-wide" id="syncCardDuplicates" style="display:none;">
                            <div class="sync-card-header">
                                <div class="sync-card-title"><i class="fas fa-clone"></i> Дубликаты в таблице</div>
                                <span class="sync-count" id="syncCountDuplicates">0</span>
                            </div>
                            <div class="sync-list" id="syncListDuplicates"></div>
                        </div>

                        <!-- Лишние в Discord (нет в таблице) -->
                        <div class="sync-card sync-card-red">
                            <div class="sync-card-header">
                                <div class="sync-card-title"><i class="fas fa-triangle-exclamation"></i> Нет в гугл таблице</div>
                                <span class="sync-count" id="syncCountExtra">0</span>
                            </div>
                            <div class="sync-list" id="syncListExtra"></div>
                        </div>

                        <!-- Убрать из таблицы (нет в Discord) -->
                        <div class="sync-card sync-card-yellow">
                            <div class="sync-card-header">
                                <div class="sync-card-title"><i class="fas fa-user-minus"></i> Убрать из гугл таблицы</div>
                                <span class="sync-count" id="syncCountMissing">0</span>
                            </div>
                            <div class="sync-list" id="syncListMissing"></div>
                        </div>
                    </div>
                </section>

                <!-- Модалка выдачи -->
                <div id="warnIssueModal" class="modal">
                    <div class="modal-content" style="max-width: 560px;">
                        <h2 style="font-size:1.4rem;margin-bottom:1.25rem;">Выдать выговор</h2>

                        <label class="form-label">Кому</label>
                        <div id="warnTargetInfo" class="warn-target-info"></div>

                        <label class="form-label">Причина</label>
                        <textarea id="warnReasonInput" class="form-control" rows="3" placeholder="Например: Неадекватное поведение" style="resize:none;"></textarea>

                        <label class="form-label">Срок</label>
                        <div class="warn-duration-row">
                            <button type="button" class="warn-dur-btn active" data-days="7">7 дней</button>
                            <button type="button" class="warn-dur-btn" data-days="14">14 дней</button>
                            <button type="button" class="warn-dur-btn" data-days="30">30 дней</button>
                        </div>

                        <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                            <button class="btn-profile-action" id="warnIssueCancel" style="flex:1;">Отмена</button>
                            <button class="btn-confirm-save" id="warnIssueSubmit" style="flex:1;">Выдать</button>
                        </div>
                    </div>
                </div>

                <!-- Модалка снятия -->
                <div id="warnJustifyModal" class="modal">
                    <div class="modal-content" style="max-width:500px;">
                        <h2 style="font-size:1.3rem;margin-bottom:1rem;">Снять выговор</h2>
                        <div id="warnJustifyTarget" style="background:rgba(255,255,255,0.04);padding:0.7rem 1rem;border-radius:10px;margin-bottom:1.25rem;font-size:0.9rem;"></div>
                        <label class="form-label">Причина снятия (необязательно)</label>
                        <textarea id="warnJustifyReason" class="form-control" rows="3" placeholder="Например: ошибочно выдан" style="resize:none;"></textarea>
                        <div style="display:flex;gap:1rem;">
                            <button class="btn-profile-action" id="warnJustifyCancel" style="flex:1;">Отмена</button>
                            <button class="btn-confirm-save" id="warnJustifySubmit" style="flex:1;">Снять</button>
                        </div>
                    </div>
                </div>

                <!-- Отчётность -->
                <section class="tab-page" id="tab-reports">
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-file-lines" style="color: var(--accent); font-size:1.3rem;"></i>
                                <h3>Все отчёты собеседований</h3>
                            </div>
                            <span class="reports-count" id="allReportsCount">0</span>
                        </div>
                        <div class="reports-filter" id="reportsFilter">
                            <button class="rf-btn active" data-filter="all" type="button">Все</button>
                            <button class="rf-btn" data-filter="moder" type="button">Собес на модера</button>
                            <button class="rf-btn" data-filter="master" type="button">Собес на мастера</button>
                            <button class="rf-btn" data-filter="events" type="button">Ивенты</button>
                        </div>
                        <div class="reports-list" id="allReportsList"></div>
                    </div>
                </section>

                <!-- Токены -->
                <section class="tab-page" id="tab-tokens">
                    <div class="card">
                        <div class="card-header"><h3>Токены</h3></div>
                        <div class="placeholder">
                            <i class="fas fa-coins"></i>
                            <h2>Токены</h2>
                        </div>
                    </div>
                </section>

                <!-- Активность в голосовых -->
                <section class="tab-page" id="tab-voice-activity">
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-circle" style="color:var(--ok);font-size:0.7rem;"></i>
                                <h3>Сейчас в комнатах</h3>
                            </div>
                            <span class="status-badge" id="voiceOnlineCount">0</span>
                        </div>
                        <div id="voiceOnlineList"></div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-microphone" style="color:var(--accent);font-size:1.3rem;"></i>
                                <h3>Активность в голосовых комнатах</h3>
                            </div>
                            <button class="btn-confirm-save" id="voiceActivityRefreshBtn" type="button">
                                <i class="fas fa-rotate"></i> Обновить
                            </button>
                        </div>
                        <div class="va-status" id="voiceActivityStatus"></div>
                        <div id="voiceActivityList"></div>
                    </div>
                </section>

                <!-- Level up -->
                <section class="tab-page" id="tab-levelup">
                    <div class="card">
                        <div class="card-header">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-arrow-up-right-dots" style="color:var(--accent);font-size:1.3rem;"></i>
                                <h3>Level up — мастера</h3>
                            </div>
                            <span class="status-badge" id="lvlStatus">Загрузка…</span>
                        </div>
                        <div id="lvlMastersGrid"></div>
                    </div>
                </section>

                <!-- Модалка выдачи балла -->
                <div id="lvlAddModal" class="modal">
                    <div class="modal-content" style="max-width:500px;">
                        <h2 style="font-size:1.3rem;margin-bottom:1rem;">Выдать балл</h2>
                        <div id="lvlAddTarget" class="warn-target-info"></div>
                        <label class="form-label">За что (обязательно)</label>
                        <textarea id="lvlAddReason" class="form-control" rows="3" placeholder="Например: успешно провёл переаттестацию модера" style="resize:none;"></textarea>
                        <div style="display:flex;gap:1rem;">
                            <button class="btn-profile-action" id="lvlAddCancel" style="flex:1;">Отмена</button>
                            <button class="btn-confirm-save" id="lvlAddSubmit" style="flex:1;"><i class="fas fa-plus"></i> Выдать</button>
                        </div>
                    </div>
                </div>

                <!-- Модалка истории -->
                <div id="lvlHistoryModal" class="modal">
                    <div class="modal-content" style="max-width:620px;">
                        <h2 id="lvlHistoryTitle" style="font-size:1.3rem;margin-bottom:1rem;">История баллов</h2>
                        <div id="lvlHistoryList" style="max-height:60vh;overflow-y:auto;"></div>
                        <div style="margin-top:1rem;">
                            <button class="btn-profile-action" id="lvlHistoryClose" style="width:100%;">Закрыть</button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // --- Серверный пользователь ---
        const CURRENT_USER = <?= json_encode($me, JSON_UNESCAPED_UNICODE) ?>;
        const ROLES = {
            admin:   { level: 4 },
            asst:    { level: 4 },
            chief:   { level: 3 },
            curator: { level: 2 },
            master:  { level: 1 }
        };
        // Минимальный уровень роли для доступа к вкладке
        const TAB_ACCESS = {
            'interview-master': 2,  // Curator+
            'reatt-masters':    2,  // Curator+
            'memo-curators':    2,  // Curator+
            'wheel':            2,  // Curator+
            'sync':             1,  // все
            'embeds':           2,  // Curator+
            'voice-activity':   2   // Curator+
        };
        function currentRoleLevel() {
            return (ROLES[CURRENT_USER.role] || {}).level || 0;
        }
        function canAccess(tab) {
            return currentRoleLevel() >= (TAB_ACCESS[tab] || 1);
        }
        function applyRole() {
            // Карточку юзера уже отрендерил PHP. Здесь только скрываем недоступные вкладки.
            document.querySelectorAll('.nav-link[data-tab]').forEach(link => {
                const li = link.closest('.nav-item');
                if (!li) return;
                li.style.display = canAccess(link.dataset.tab) ? '' : 'none';
            });
            document.querySelectorAll('.nav-section').forEach(sec => {
                const visible = Array.from(sec.querySelectorAll('.nav-item')).some(i => i.style.display !== 'none');
                sec.style.display = visible ? '' : 'none';
            });
            const active = document.querySelector('.nav-link.active');
            if (active && !canAccess(active.dataset.tab) && typeof switchTab === 'function') {
                switchTab('home');
            }
        }

        const titles = {
            'profile': ['Профиль', 'Ваша информация'],
            'home': ['Главная', 'Ваш состав'],
            'interview-moder': ['Собес на модера', 'Доступно мастерам'],
            'events': ['Ивенты', 'Управление ивентами'],
            'giveaways': ['Розыгрыши', 'Розыгрыши и призы'],
            'warnings': ['Выговоры', 'Дисциплинарные меры'],
            'archive': ['Архив', 'Снятые и истёкшие выговоры'],
            'sync': ['Сверка таблицы', 'Синхронизация Google-таблицы и Discord'],
            'wheel': ['Колесо фортуны', 'Испытайте удачу'],
            'interview-master': ['Собес на мастера', 'Кураторам и выше'],
            'memo-masters': ['Памятка для мастеров', 'Чек-лист для мастеров'],
            'memo-curators': ['Памятка для кураторов', 'Гайд для кураторов'],
            'reatt-masters': ['Переаттестация для мастеров', 'Вопросы для мастеров'],
            'reatt-moders': ['Переаттестация для модеров', 'Вопросы для модеров'],
            'reports': ['Отчётность', 'Все отчёты собеседований'],
            'tokens': ['Токены', 'Учёт токенов'],
            'levelup': ['Level up', ''],
            'embeds': ['Эмбиты', 'Публикация в инфо-каналы'],
            'voice-activity': ['Активность', '']
        };

        const links = document.querySelectorAll('.nav-link[data-tab]');
        const pages = document.querySelectorAll('.tab-page');
        const sidebar = document.getElementById('mainSidebar');
        const menuBtn = document.getElementById('mobileMenuBtn');

        function switchTab(tab) {
            if (typeof canAccess === 'function' && !canAccess(tab)) return;
            links.forEach(l => l.classList.toggle('active', l.dataset.tab === tab));
            pages.forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
            const [title, sub] = titles[tab] || ['', ''];
            document.getElementById('pageTitle').textContent = title;
            document.getElementById('pageSubtitle').textContent = sub;
            if (window.innerWidth <= 1024) sidebar.classList.remove('open');
        }

        links.forEach(l => l.addEventListener('click', () => switchTab(l.dataset.tab)));

        // Применить роль текущего пользователя к UI (после того как switchTab объявлен)
        applyRole();

        if (menuBtn) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('open');
                const icon = menuBtn.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 1024 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== menuBtn) {
                    sidebar.classList.remove('open');
                    menuBtn.querySelector('i').classList.replace('fa-times', 'fa-bars');
                }
            });
        }

        // --- Собес на модера: вопросы ---
        const interviewData = {
            1: [
                { q: 'Что будете делать, если человек утверждает о неполноценности этнической группы?', a: 'пред / бан' },
                { q: 'Что будете делать, если человек сидит с вебкой и бьёт ни с того ни с сего по столу, разбивая вещи?', a: 'пред / бан (шок-контент)' },
                { q: 'Чем отличается Community Guidelines от Terms of Service?', a: 'ToS — это соглашение между пользователем и Discord.\nGD — это руководство по безопасному и корректному поведению в Discord.' },
                { q: 'Человек заходит в войс с девушками и говорит: «Пизде слово не давали».', a: 'пред / мут (банворды)' },
                { q: 'Человек запивает лекарства алкоголем на вебке.', a: 'пред / бан (нанесение вреда здоровью)' },
                { q: 'Человек стримит казино, ваши действия?', a: 'Если на реальные деньги или материальные ценности — пред / бан.\nЕсли на виртуальные монеты (не деньги) — ничего, но пользователю должно быть 18+ и без рекламы на демке.' },
                { q: 'Человек в войсе начинает демонстрировать кракен-ссылку.', a: 'пред / бан — такие ссылки запрещены.' },
                { q: 'Человек заходит в войс и говорит, что он из Северной Кореи.', a: 'бан — страна эмбарго.' },
                { q: 'У человека ник 4/20 и он говорит, что это час, когда он родился, и откровенно смеётся.', a: 'пред / бан (завуалированная запретка — день рождения Гитлера)' },
                { q: 'Что будете делать, если человек в не-мод-войсе включает порнуху?', a: 'пред / бан — запрещено такое стримить (порнографический контент).' }
            ],
            2: [
                { q: 'Человек заходит в войс и стримит игру 16+, на вопрос сколько ему лет отвечает 15. Ваши действия?', a: 'пред / бан' },
                { q: 'Человек заходит в войс и в шутку говорит, что ему 12 лет. Ваши действия?', a: 'бан на год (по GD запрещено даже в шутку говорить, что тебе 12 лет).' },
                { q: 'Человек демонстрирует гифки с непонятными жидкостями и человеческими выделениями. Ваши действия?', a: 'пред / бан — тошнотворный контент.' },
                { q: 'Человек заходит в войс и говорит, что его роль стоит 1488 монет. Ваши действия?', a: 'пред / бан' },
                { q: 'Человек курит косяк на вебке, но не говорит, что там. Ваши действия?', a: 'Ничего, если не пропагандирует и не призывает к действию.' },
                { q: 'Человек просит купить у него какую-то услугу в войсе. Ваши действия?', a: 'пред / бан — коммерческая деятельность запрещена без согласования стафф-администрации.' },
                { q: 'Человек заходит в войс без стафф-ролей (или спонсорок) и на демке читает чаты стаффа. Ваши действия?', a: 'Бан — модификации Discord запрещены.' },
                { q: 'Человек на демке показывает новости и кадры с аварии, но крови там нет. Ваши действия?', a: 'пред / бан — шок-контент.' },
                { q: 'Человек заходит в войс, называет участника по имени и начинает имитировать звуки выстрелов, крики и плач.', a: 'пред / бан — расценивается как угроза и запугивание.' },
                { q: 'Человек заходит в войс, на фоне слышно пылесос, его просят выключить микрофон или включить шумоподавление, но он не слушает. Ваши действия?', a: 'пред / мут — правило 3.1.' }
            ],
            3: [
                { q: 'Человек отправляет фото девушки с интернета, где она фотографируется в нижнем белье перед зеркалом. Ваши действия?', a: 'Ничего.' },
                { q: 'Человек сидит на вебке и нюхает клей, говоря, что он приятно пахнет. Ваши действия?', a: 'пред / бан — нанесение вреда здоровью.' },
                { q: 'Человек в мод-руме на вебку начинает запивать лекарственные препараты алкоголем. Ваши действия?', a: 'пред / бан — вред здоровью.' },
                { q: 'Человек на вебке оскорбляет и бьёт своего питомца за то, что тот сходил в туалет на кровать. Ваши действия?', a: 'пред / бан — за нанесение вреда здоровью питомца.' },
                { q: 'Пользователь заходит в войс, но у него очень громкий микрофон. Ваши действия?', a: 'пред / мут — правило 3.1.' },
                { q: 'В голосовой канал заходит новый участник, молчит 5 минут, потом резко выходит. Никаких других действий. Ваши действия?', a: 'Ничего.' },
                { q: 'Человек сидит в войсе, стримит доту и показывает функции snitch-changer-а. Ваши действия?', a: 'пред / бан — запрещены читы и модификации.' },
                { q: 'Человек в войсе включает вебку и рассказывает, как его побили, показывая кровь на футболке. Ваши действия?', a: 'пред / бан — тошнотворный контент.' },
                { q: 'Человек заходит к девушке, которой 16 лет, и начинает предлагать «оплодотвориться», не реагируя на её предупреждения. Ваши действия?', a: 'пред / бан.' },
                { q: 'Человек говорит, что ветка модеров плохая, оскорбляя её. Ваши действия?', a: 'пред / бан — за деструктив.' }
            ]
        };

        function renderQuestionsTo(containerId, items) {
            const list = document.getElementById(containerId);
            if (!list) return;
            list.innerHTML = items.map((it, i) => `
                <div class="q-card">
                    <div class="q-num">${i + 1}</div>
                    <div class="q-body">
                        <div class="q-text">${it.q}</div>
                        <button class="q-toggle" type="button">
                            <i class="fas fa-eye"></i> <span>Показать ответ</span>
                        </button>
                        <div class="q-answer">${it.a}</div>
                    </div>
                </div>
            `).join('');
            list.querySelectorAll('.q-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const card = btn.closest('.q-card');
                    const revealed = card.classList.toggle('revealed');
                    btn.querySelector('span').textContent = revealed ? 'Скрыть ответ' : 'Показать ответ';
                    btn.querySelector('i').className = revealed ? 'fas fa-eye-slash' : 'fas fa-eye';
                });
            });
        }

        // --- Универсальный собес (поля + оценки + отчёты) ---
        const RATING_POINTS = { '+': 1, '+-': 0.5, '-': 0 };

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
        function formatDate(d) {
            const dd = String(d.getDate()).padStart(2, '0');
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            return `${dd}.${mm}.${d.getFullYear()}`;
        }

        function createInterview(cfg) {
            let currentVariant = cfg.hasVariants ? '1' : 'single';

            const getItems = () => cfg.hasVariants ? (cfg.data[currentVariant] || []) : cfg.data;
            const loadRatings = () => {
                try { return JSON.parse(localStorage.getItem(cfg.ratingStorage + currentVariant) || '{}'); }
                catch { return {}; }
            };
            const saveRatings = r => localStorage.setItem(cfg.ratingStorage + currentVariant, JSON.stringify(r));
            const loadReports = () => {
                try { return JSON.parse(localStorage.getItem(cfg.reportsStorage) || '[]'); }
                catch { return []; }
            };
            const saveReports = arr => localStorage.setItem(cfg.reportsStorage, JSON.stringify(arr));

            function updateScore() {
                const r = loadRatings();
                let pts = 0, rated = 0;
                Object.values(r).forEach(v => { if (v) { pts += RATING_POINTS[v]; rated++; } });
                const el = document.getElementById(cfg.scoreId);
                if (!el) return;
                el.textContent = (pts % 1 === 0 ? pts : pts.toFixed(1)) + ' / ' + cfg.maxScore;
                el.classList.remove('pass', 'fail', 'partial');
                if (pts >= cfg.passingScore) el.classList.add('pass');
                else if (rated >= cfg.maxScore) el.classList.add('fail');
                else if (rated > 0) el.classList.add('partial');
            }

            function render(variant) {
                if (variant !== undefined) currentVariant = String(variant);
                const list = document.getElementById(cfg.listId);
                if (!list) return;
                const items = getItems();
                const ratings = loadRatings();
                list.innerHTML = items.map((it, i) => {
                    const r = ratings[i];
                    const ratedCls = r ? ' rated rated-' + (r === '+' ? 'plus' : r === '+-' ? 'mid' : 'minus') : '';
                    return `
                    <div class="q-card${ratedCls}" data-idx="${i}">
                        <div class="q-num">${i + 1}</div>
                        <div class="q-body">
                            <div class="q-text">${it.q}</div>
                            <div class="q-actions">
                                <button class="q-toggle" type="button">
                                    <i class="fas fa-eye"></i> <span>Показать ответ</span>
                                </button>
                                <div class="q-rate">
                                    <button class="r-plus${r === '+' ? ' active' : ''}" data-r="+" title="Верный ответ" type="button">+</button>
                                    <button class="r-mid${r === '+-' ? ' active' : ''}" data-r="+-" title="Частично" type="button">+−</button>
                                    <button class="r-minus${r === '-' ? ' active' : ''}" data-r="-" title="Неверный" type="button">−</button>
                                </div>
                            </div>
                            <div class="q-answer">${it.a}</div>
                        </div>
                    </div>`;
                }).join('');

                list.querySelectorAll('.q-toggle').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const card = btn.closest('.q-card');
                        const revealed = card.classList.toggle('revealed');
                        btn.querySelector('span').textContent = revealed ? 'Скрыть ответ' : 'Показать ответ';
                        btn.querySelector('i').className = revealed ? 'fas fa-eye-slash' : 'fas fa-eye';
                    });
                });

                list.querySelectorAll('.q-rate button').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const card = btn.closest('.q-card');
                        const idx = card.dataset.idx;
                        const r = btn.dataset.r;
                        const all = loadRatings();
                        all[idx] = all[idx] === r ? null : r;
                        if (!all[idx]) delete all[idx];
                        saveRatings(all);
                        card.querySelectorAll('.q-rate button').forEach(b => b.classList.toggle('active', b.dataset.r === all[idx]));
                        card.classList.remove('rated', 'rated-plus', 'rated-mid', 'rated-minus');
                        if (all[idx]) {
                            card.classList.add('rated');
                            card.classList.add('rated-' + (all[idx] === '+' ? 'plus' : all[idx] === '+-' ? 'mid' : 'minus'));
                        }
                        updateScore();
                    });
                });

                updateScore();
            }

            function renderReports() {
                const reports = loadReports();
                const list = document.getElementById(cfg.reportsListId);
                const count = document.getElementById(cfg.reportsCountId);
                if (!list) return;
                if (count) count.textContent = reports.length;
                if (reports.length === 0) {
                    list.innerHTML = '<div class="reports-empty">Пока нет отправленных отчётов.</div>';
                    return;
                }
                list.innerHTML = reports.slice().reverse().map((r, idx) => {
                    const realIdx = reports.length - 1 - idx;
                    const passed = r.score >= cfg.passingScore;
                    return `
                        <div class="report-card ${passed ? 'r-passed' : 'r-failed'}">
                            <div class="report-row">
                                <div class="report-main">
                                    <div class="report-nick">${escapeHtml(r.nick || '—')}</div>
                                    <div class="report-id">ID: ${escapeHtml(r.id || '—')}</div>
                                </div>
                                <div class="report-score ${passed ? 'pass' : 'fail'}">
                                    ${r.score} / ${cfg.maxScore}
                                    <span class="report-verdict">${passed ? 'Сдал' : 'Не сдал'}</span>
                                </div>
                                <button class="report-del" data-i="${realIdx}" title="Удалить"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="report-info">
                                <span><i class="fas fa-calendar"></i> ${r.date}</span>
                                <span><i class="fas fa-user-shield"></i> ${escapeHtml(r.reviewer || '—')}</span>
                                ${cfg.hasVariants ? `<span><i class="fas fa-layer-group"></i> Вариант ${r.variant}</span>` : ''}
                            </div>
                        </div>`;
                }).join('');
                list.querySelectorAll('.report-del').forEach(b => {
                    b.addEventListener('click', () => {
                        if (!confirm('Удалить отчёт?')) return;
                        const all = loadReports();
                        all.splice(+b.dataset.i, 1);
                        saveReports(all);
                        renderReports();
                        if (typeof renderAllReports === 'function') renderAllReports();
                    });
                });
            }

            // Поля кандидата — persistence. «Проверяющий» по умолчанию — текущий
            // пользователь (можно вручную поменять, если собес ведёт не он).
            [cfg.nickId, cfg.idId, cfg.reviewerId].forEach(elId => {
                const el = document.getElementById(elId);
                if (!el) return;
                const fallback = elId === cfg.reviewerId ? (CURRENT_USER.username || '') : '';
                el.value = localStorage.getItem(elId) || fallback;
                el.addEventListener('input', () => localStorage.setItem(elId, el.value));
            });

            // Сброс оценок
            const resetBtn = document.getElementById(cfg.resetId);
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (!confirm('Сбросить все оценки?')) return;
                    localStorage.removeItem(cfg.ratingStorage + currentVariant);
                    render();
                });
            }

            // Отправка отчёта — сохраняется локально и параллельно уходит в
            // Telegram-группу через бота, чтобы отчёт видела вся вышка, а не
            // только браузер того, кто его отправил.
            const submitBtn = document.getElementById(cfg.submitId);
            const tgStatus = cfg.reportTgStatusId ? document.getElementById(cfg.reportTgStatusId) : null;
            if (submitBtn) {
                submitBtn.addEventListener('click', async () => {
                    const nick = document.getElementById(cfg.nickId).value.trim();
                    const id = document.getElementById(cfg.idId).value.trim();
                    const reviewer = document.getElementById(cfg.reviewerId).value.trim();
                    if (!nick || !id) {
                        alert('Заполните «Коренной ник» и «ID» кандидата.');
                        return;
                    }
                    const ratings = loadRatings();
                    let pts = 0;
                    Object.values(ratings).forEach(v => { if (v) pts += RATING_POINTS[v]; });
                    const score = pts % 1 === 0 ? pts : +pts.toFixed(1);
                    const now = new Date();
                    const report = {
                        nick, id, reviewer,
                        variant: currentVariant,
                        score, date: formatDate(now),
                        ts: now.getTime(),
                        ratings: { ...ratings }
                    };
                    const all = loadReports();
                    all.push(report);
                    saveReports(all);
                    renderReports();
                    if (typeof renderAllReports === 'function') renderAllReports();
                    [cfg.nickId, cfg.idId].forEach(k => {
                        const el = document.getElementById(k);
                        el.value = '';
                        localStorage.removeItem(k);
                    });
                    localStorage.removeItem(cfg.ratingStorage + currentVariant);
                    render();
                    document.getElementById(cfg.nickId).focus();

                    if (tgStatus && cfg.type) {
                        tgStatus.textContent = 'Отправляю в Telegram…';
                        tgStatus.style.color = 'var(--text-secondary)';
                        try {
                            const r = await fetch('/api/send-report.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    type: cfg.type, nick, id, reviewer,
                                    score, maxScore: cfg.maxScore,
                                    passed: score >= cfg.passingScore,
                                    variant: cfg.hasVariants ? report.variant : '',
                                    ratings: report.ratings
                                })
                            });
                            const data = await r.json();
                            if (!r.ok || data.error) throw new Error(data.error || ('HTTP ' + r.status));
                            tgStatus.textContent = 'Отправлено в Telegram';
                            tgStatus.style.color = 'var(--ok)';
                        } catch (e) {
                            tgStatus.textContent = 'Не ушло в Telegram: ' + e.message;
                            tgStatus.style.color = 'var(--bad)';
                        }
                    }
                });
            }

            return { render, renderReports, getVariant: () => currentVariant };
        }

        // Собес на модера
        const moderInterview = createInterview({
            data: interviewData, hasVariants: true,
            maxScore: 10, passingScore: 7,
            listId: 'questionsList', scoreId: 'candScore',
            nickId: 'candNick', idId: 'candId', reviewerId: 'candReviewer',
            submitId: 'submitReport', resetId: 'resetRatings',
            reportsListId: 'reportsList', reportsCountId: 'reportsCount',
            ratingStorage: 'rate_v', reportsStorage: 'moder_reports',
            type: 'moder', reportTgStatusId: 'reportTgStatus'
        });
        document.querySelectorAll('.variant-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.variant-btn').forEach(b => b.classList.toggle('active', b === btn));
                moderInterview.render(btn.dataset.variant);
            });
        });
        moderInterview.render('1');
        moderInterview.renderReports();

        // --- Переаттестация для модеров ---
        const reattModersData = [
            { q: 'Человек на вебке бегает, прыгает по всей комнате и бьётся головой об подушку, выкрикивая непонятные звуки. Ваши действия?', a: 'Неадекватное поведение — правило 3.2. Пред / мут.' },
            { q: 'Назови и охарактеризуй 3 вида запрещённого контента.', a: 'Шок-контент, порно-контент (одобряем), тошнотворный контент.' },
            { q: 'У пользователя в профиле ссылка на его ТГК. Ваши действия?', a: 'Ничего.' },
            { q: 'Человек в мод-руме сидит с вебкой и едет за рулём, разговаривая с другими пользователями.', a: 'Пред / бан — опасное вождение.' },
            { q: 'Один из пользователей усомнился, что его собеседнику есть 13 лет, и просит показать документы. Ваши действия?', a: 'Запрос документов — деанон. Пред, при настойчивости — вплоть до бана.' },
            { q: 'В мод-руме человек представляется Артёмом, через пару минут — Алиной, использует войсмод, изменяя голос на женский, и убеждает всех, что он девушка. Ваши действия?', a: 'Пред / бан — ввод в заблуждение.' },
            { q: 'В мод-руме пользователь взял курительную трубку, забил её табаком и зажёг. Ваши действия?', a: 'Ничего.' },
            { q: 'Человек в мод-руме рассказывает, как абузить правила сервера и избегать наказаний. Ваши действия?', a: 'Пред / бан — правило 1.2.' },
            { q: 'Пользователи завуалированно кидают банворды в ходе обсуждений. Ваши действия?', a: 'Пред / мут — банворды.' },
            { q: 'Человек стримит фильм в руме на троих для себя и друга — проскакивают сцены с малым количеством крови и насилия. Ваши действия?', a: 'Пред / бан — шок-контент. Можно попросить перейти в руму 1×1 или приватку.' },
            { q: 'Человек на вебке включает подсветку в комнате, которая динамично резко загорается под музыку на фоне. Ваши действия?', a: 'Пред / бан — шок-контент (эпилепсия).' },
            { q: 'В мод-руму зашёл человек с ником «Я люблю Милф». Что будете делать?', a: 'Ничего.' },
            { q: 'В руму заходят 3 человека. Один говорит «Смотри, что умею» — после этого у всех начинает лагать Discord, повышается пинг, выкидывает с румы. Ваши действия?', a: 'Бан — правило 1.2.' },
            { q: 'Человек на вебке показывает, как поджигает мелкие предметы. Ваши действия?', a: 'Шок-контент — пред / бан.' },
            { q: 'Зашёл человек с ником «Вызывайте 911». Ваши действия?', a: 'Ничего.' },
            { q: 'Пользователь решил поиграть в казино на демонстрации. Ваши действия?', a: 'Можно, если пользователю 18+, игра не на материальные ценности / деньги и без рекламы. Иначе — пред / бан.' },
            { q: 'У пользователя в «Обо мне» ссылка на его Twitch-аккаунт без спойлера. Ваши действия?', a: 'Ничего.' },
            { q: 'Какие правила действуют в личных румах и какие — в немод-румах?', a: 'Немод-румы: 1.2, 2.2, 2.8, Политика сервера + ToS + GD.\nЛичные / приватные: 1.2, 2.2, 2.8, ToS + GD.' },
            { q: 'Человек на вебку показывает бездомного и смеётся над ним. Ваши действия?', a: 'Если просто смеётся — ничего. Если унижает — лёгкий оск, правило 3.2.' },
            { q: 'В мод-руме один пользователь просит другого перейти по ссылке из ЛС — там оказывается ссылка на его сервер в Discord. Первый кидает жалобу на второго за рекламу. Ваши действия?', a: 'Бан за рекламу.' }
        ];

        // --- Собес на мастера ---
        const mastersInterviewData = [
            { q: 'Что такое фишинг и SCAM? В чём их сходство и различия?', a: 'Фишинг — интернет-мошенничество с целью получить пароль и логин пользователя.\nSCAM — интернет-мошенничество с целью обогащения преступника.' },
            { q: 'Зашёл человек и включил гимн через soundpad. Ваши действия?', a: 'Пред / мут — правила 3.1 + 1.9.' },
            { q: 'Пользователь в мод-руме стримит официальные новости BBC, где ведущий рассказывает о террористических актах в Крыму. Ваши действия?', a: 'Шок-контент — пред / бан.' },
            { q: 'Человек в мод-руме говорит, что ветка Саппортов лучше ветки Модераторов: там дружнее коллектив и интереснее, лучше идти в саппорты. Ваши действия?', a: 'Пред / мут — правило 1.7.' },
            { q: 'Чем отличается терроризм от экстремизма? Какое понятие шире и включает в себя второе?', a: 'Экстремизм — широкое понятие: радикальные взгляды и действия, направленные на подрыв основ государственного строя, нарушение прав и свобод граждан, насильственное изменение власти, разжигание ненависти и вражды.\nТерроризм — более узкое, одно из проявлений экстремизма. Использование насилия, угроз и устрашения для достижения политических, религиозных или идеологических целей. Основной инструмент — акты насилия (взрывы, захваты заложников, убийства), направленные на создание страха среди населения.' },
            { q: 'Тебе скидывают в личные сообщения фото голой девушки, которая согласна на то, чтобы ты её увидел. Твои действия?', a: 'Ничего.' },
            { q: 'Человек говорит в руме, что его роль стоит 514 монет. Ваши действия?', a: 'Ничего.' },
            { q: 'Человек играет в игру с ограничением 16+.', a: 'Ничего.' },
            { q: 'Человек подаёт жалобы на пользователя, но не дожидается начала разбора и подаёт ещё пару жалоб на него же. Ваши действия?', a: 'Правило 2.6 — бан до 30 дней.' },
            { q: 'Пользователь говорит, что не видит ничего плохого в доксинге и иногда сам прибегал к подобному. Ваши действия?', a: 'Бан.' },
            { q: 'Человек говорит, что земля плоская.', a: 'Ввод в заблуждение — пред / бан.' },
            { q: 'Человек использует на демонстрации экрана скинчейнджер в Доте. Ваши действия?', a: 'Пред / бан — по GD запрещены читы и модификации игр не от официальных источников.' },
            { q: 'Человек зашёл в руму и сказал, чтобы девочка на втором слоте помолчала, потому что ему не нравится её голос — «потому что она женщина». Ваши действия?', a: 'Пред / мут — сексизм.' },
            { q: 'Человек запрашивает у другого пользователя фотографии, нормализующие сексуализированное насилие над детьми, но другой отказывается скидывать. Ваши действия и почему?', a: 'Нельзя запрашивать. Пред / бан.' },
            { q: 'К какой классификации относится утверждение о неполноценности этнической группы?', a: 'Мягкий язык вражды.' },

            { q: 'Пользователь отправил ссылку на сайт с мемами. Ссылка была под спойлером. На сайте нет рекламы, но есть политические картинки и пара мемов с запрещённой символикой. Какие правила нарушены и какое наказание?', a: 'Сразу два правила: 2.2 (размещение сторонних ресурсов без согласования) и 1.9 (политический контент + запрещённая символика). Наказание: варн / бан навсегда без амнистии.' },
            { q: 'Пользователь во время мута за флуд зашёл с твинка и в приватной комнате использовал SoundPad с громкими звуками. Нарушает ли он правила, если приватки не модерируются?', a: 'Нарушает. Использование твинка для обхода наказания — правила 3.1 + 2.8. SoundPad в приватке сам по себе не нарушение, но факт присутствия на твинке во время активного наказания уже фиксируется.' },
            { q: 'Если пользователь написал в чат «иди кури», имея в виду обычные сигареты (не наркотики), это нарушение?', a: 'Зависит от контекста. Если фраза сказана без контекста — человек не говорил, что курит или уходит покурить — это нарушение, правило 1.6, бан. Если пользователь сам сказал, что курит / уходит покурить, и ему в ответ говорят «иди кури» — это не нарушение.' },
            { q: 'Пользователь отправил каждому подряд по одному упоминанию через каждые 10 минут. Это считается флудом / спамом?', a: 'Это не флуд, но по правилу 2.7 «беспричинное многократное упоминание участников и ролей» — отдельный пункт. Мут на 4 часа.' },
            { q: 'Участник написал: «Ты меня уважаешь, или мне к твоим фоткам обратиться?» — имел в виду мем, фоток у него нет. Нарушение?', a: 'Да. Даже косвенные / двусмысленные угрозы доксом (правило 1.4) — нарушение, независимо от реальности угрозы.' },
            { q: 'Пользователь трижды за день получил мут, но в разных категориях (чат, голос, оффтоп). Считается ли это как три мута подряд?', a: 'Да. «Получение трёх мутов в течение одного дня» — неважно за что и где именно. Автоматом — варн.' },
            { q: 'Игрок поставил ник «Sanya_FSB» и баннер с картинкой из фильма про войну. В чате ничего политического не пишет. Нарушение?', a: 'Да. По правилу 1.9 запрещено политическое / провокационное содержимое профиля (ник + баннер), даже если в чате молчит.' },
            { q: 'Админ замьютил пользователя на 2 часа вместо 4 «по доброте». Соответствует правилам?', a: 'Нет. Сроки прописаны чётко («Мут — 4 часа»). Уменьшать регламентированные сроки нельзя — администрация может наказывать на своё усмотрение только при дополнительных обстоятельствах.' },
            { q: 'Пользователь сказал в голосовом чате: «Вы такие дауны». В личной приватной комнате — это нарушение или нет?', a: 'Нет. По правилу 1.10 неадекватное поведение / оскорбления не действует в приватных / немодерируемых комнатах.' },
            { q: 'Администрация выдала бан на 1 день «для профилактики», хотя в правилах указано 30 дней / навсегда. Это легально?', a: 'Да. В примечаниях: «администрация может наказывать пользователей на своё усмотрение без объяснения причин» — перекрывает стандартные сроки. Плюс формулировка «до 30 дней» — это диапазон: выдать можно хоть 2 дня, не обязательно максимум.' },
            { q: 'Участник включил вебку и сидит без микрофона. На камере виден флаг страны. Нарушает ли он правила?', a: 'Да. По правилу 1.9 запрещено использование флагов стран и политической символики — даже на вебке, а не только в чате.' },
            { q: 'Пользователь транслирует с вебки, у него на фоне алкогольные напитки. Сам ничего не пьёт и не рекламирует. Нарушение?', a: 'Да. По правилу 1.6 запрещена пропаганда алкоголя; видимость алкоголя на фоне расценивается как косвенная демонстрация.' },
            { q: 'Пользователь на вебке включил ролик с изображением сцен охоты (убийство животных). Это нарушение?', a: 'Да. По правилу 2.3 запрещён шокирующий / тошнотворный контент. Демонстрация убийства животных подпадает сюда, даже если «документальное видео».' },
            { q: 'Участник на вебке показал игрушечный пистолет и сказал «вот сейчас я вас всех завалю» в шутку. Нарушение?', a: 'Да. По GD — даже косвенные / завуалированные угрозы. Плюс может трактоваться как провокационный контент (правило 1.4).' },
            { q: 'Пользователь в голосовом канале без вебки говорит: «Я сегодня утопил свою кошку, ха-ха». Никто не видел, правда это или нет. Нарушение?', a: 'Да. Подпадает под шокирующий / тошнотворный контент (правило 2.3), даже если это ложь.' },
            { q: 'Участник во время стрима с вебкой сидит в маске и издаёт нечленораздельные крики. Это нарушение?', a: 'Да. По правилу 3.2 — неадекватное поведение (крики, провокации), даже если это «шутка».' },
            { q: 'Пользователь демонстрирует на вебке шокирующий контент (например, видео аварии), но делает это в приватной комнате. Нарушение?', a: 'Да. В отличие от мата, оскорблений и флуда — шокирующий контент (правило 2.3) запрещён в любом месте, включая приватные комнаты.' },
            { q: 'Участник включает вебку и просто сидит голым по пояс. Нарушение, если ничего неприличного не показывает?', a: 'Да. Может быть классифицировано как сексуальный / провокационный контент (правило 2.3). Даже «по пояс» — повод для жалобы.' },
            { q: 'Пользователь на вебке показал домашнего питомца, но в шутку сказал «сейчас я его придушу». Самого насилия не было. Нарушение?', a: 'Да. Нарушение правил GD (косвенные угрозы) + правило 2.3 (пропаганда насилия, шокирующий контент). «Шутка» трактуется как нарушение.' },
            { q: 'Во время вебки пользователь показывает запрещённую символику на одежде (например, свастику), но сам ничего не говорит. Это нарушение?', a: 'Да. По правилу 1.6 прямо запрещена демонстрация запрещённой символики — независимо от того, говорил ли участник что-то.' }
        ];

        // Собес на мастера
        const masterInterview = createInterview({
            data: mastersInterviewData, hasVariants: false,
            maxScore: 35, passingScore: 25,
            listId: 'mastersInterviewList', scoreId: 'mCandScore',
            nickId: 'mCandNick', idId: 'mCandId', reviewerId: 'mCandReviewer',
            submitId: 'mSubmitReport', resetId: 'mResetRatings',
            reportsListId: 'mReportsList', reportsCountId: 'mReportsCount',
            ratingStorage: 'm_rate_', reportsStorage: 'master_reports',
            type: 'master', reportTgStatusId: 'mReportTgStatus'
        });
        masterInterview.render();
        masterInterview.renderReports();

        // Переаттестация для модеров — просто список с раскрытием ответов
        renderQuestionsTo('modersReattList', reattModersData);

        // --- Переаттестация для мастеров ---
        const reattMastersData = [
            { q: 'Девушка сидит в войсе, к ней заходит отец. На вебке происходит жестокое обращение к девушке — он выгоняет её и продолжает спокойный диалог с остальными. Было ли нарушение, если да — какое?', a: 'Пред / бан.' },
            { q: 'Человек крутит кейсы на кейс-батле и говорит, что он из Китая. Что будете делать и почему?', a: 'Пред / бан (страна-эмбарго).' },
            { q: 'Человек играет в шахматы и говорит, что он из Афганистана. Ваши действия?', a: 'Бан (страна-эмбарго).' },
            { q: 'Человек создал роль и выставил её на сервере за 1488 монет. Что будете делать?', a: 'Пред / бан (запрещённая символика в стоимости).' },
            { q: 'Человек скинул ссылку на подозрительный сайт. Ваши действия?', a: 'Пред / бан.' },
            { q: 'Юзеру нужна помощь и он просит вас помочь. Ваши действия?', a: 'Помогу.' },
            { q: 'Зашёл человек и начал обвинять модера за то, что его забанили за нарушение, а других не банят за такие же нарушения. Ваши действия?', a: 'Бан по правилу 2.8.' },
            { q: 'Человек зашёл в войс, аккаунт создан в 2018 году, и говорит, что купил его. Ваши действия?', a: 'Бан.' },
            { q: 'У пользователя стоит аватарка, где ребёнок с табличкой и надписью «I love crack». Ваши действия?', a: 'Бан.' },
            { q: 'У человека в нике «200% позитивный». Ваши действия?', a: 'Пред / бан (200% — отсылка к превосходству белой расы).' },
            { q: 'Человеку 15 лет, он зашёл в войс и его начали буллить за возраст. Какой термин подходит к ситуации?', a: 'Эйджизм — бан.' },
            { q: 'Пользователь транслирует на демке контент с АП (защита авторского права). Что будете делать и почему?', a: 'Бан, если автор против.' },
            { q: 'Что значит 514?', a: 'Код смерти в Китае.' },
            { q: 'Что значит 1109?', a: 'Теракт 11 сентября в Америке (башни-близнецы).' },
            { q: 'Что значит 100%?', a: 'Превосходство белой расы.' },
            { q: 'Что значит 1488?', a: 'Превосходство белой расы.' },
            { q: 'Что значит 14 и 88?', a: '88 — «Heil Hitler!» (H — 8-я буква латинского алфавита). 14 — «14 слов» о превосходстве белой расы.' },
            { q: 'Что значит 4:20?', a: 'Три запретки одновременно: время покурить траву, день рождения Гитлера, «синий кит».' },
            { q: 'Назови 3 страны-эмбарго.', a: 'Северная Корея, Куба, Континентальный Китай.' },
            { q: 'Назови 5 видов запрещённого контента.', a: '18+ контент, тошнотворный, шок-контент, пиратский контент, высмеивающий.' }
        ];

        renderQuestionsTo('mastersReattList', reattMastersData);

        // === Ивенты ===
        function fmtEventDate(d) {
            if (!d) return '';
            const m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
            return m ? `${m[3]}.${m[2]}.${m[1]}` : d;
        }
        const EVENT_TYPES = [
            {
                type: 'cs2', label: '5×5 CS2 среди модеров',
                icon: 'fa-crosshairs', color: '#fbbf24',
                format: 'team5v5',
                prize: 'Призы: 50 000 монет на команду + 1 Discord Nitro / Украшение'
            },
            {
                type: 'dota2', label: '5×5 Dota 2 среди модеров',
                icon: 'fa-gamepad', color: '#ef4444',
                format: 'team5v5',
                prize: 'Призы: 50 000 монет на команду + 1 Discord Nitro / Украшение'
            },
            {
                type: 'valorant', label: '5×5 Valorant среди модеров',
                icon: 'fa-bullseye', color: '#f43f5e',
                format: 'team5v5',
                prize: 'Призы: 50 000 монет на команду + 1 Discord Nitro / Украшение'
            },
            {
                type: 'roblox', label: 'Roblox (Рандомайзер)',
                icon: 'fa-cube', color: '#10b981',
                format: 'podium',
                prize: '1 место — Discord Nitro / Украшение · 2 место — 30 000 монет · 3 место — 20 000 монет'
            }
        ];
        const canCreateEvent = currentRoleLevel() >= 2; // curator+
        const canDeleteEvent = currentRoleLevel() >= 3; // chief+
        let currentEventType = null;

        // Тайлы для выбора типа ивента
        function renderEventTypes() {
            const grid = document.getElementById('eventTypesGrid');
            grid.innerHTML = EVENT_TYPES.map(t => `
                <button class="event-type-tile ${canCreateEvent ? '' : 'is-disabled'}" data-type="${t.type}"
                        ${canCreateEvent ? '' : 'disabled'} type="button">
                    <div class="ett-icon" style="color:${t.color};"><i class="fas ${t.icon}"></i></div>
                    <div class="ett-label">${escapeHtml(t.label)}</div>
                    <div class="ett-prize">${escapeHtml(t.prize)}</div>
                </button>
            `).join('');
            grid.querySelectorAll('.event-type-tile').forEach(b => {
                b.addEventListener('click', () => openEventModal(b.dataset.type));
            });
        }

        function buildTeamSlots(containerId, prefix) {
            const c = document.getElementById(containerId);
            c.innerHTML = '';
            for (let i = 1; i <= 5; i++) {
                const inp = document.createElement('input');
                inp.type = 'text';
                inp.className = 'form-control';
                inp.id = prefix + i;
                inp.placeholder = 'Игрок ' + i + ' (ник или ID)';
                inp.style.margin = '0 0 6px 0';
                c.appendChild(inp);
            }
        }

        function openEventModal(typeKey) {
            const t = EVENT_TYPES.find(x => x.type === typeKey);
            if (!t) return;
            currentEventType = t;
            document.getElementById('eventModalTitle').textContent = t.label;
            const pb = document.getElementById('eventPrizeBox');
            pb.innerHTML = `<i class="fas fa-trophy" style="color:${t.color};"></i> ${escapeHtml(t.prize)}`;

            // Дата/время — текущее по умолчанию
            const now = new Date();
            const yyyy = now.getFullYear(), mm = String(now.getMonth() + 1).padStart(2, '0'), dd = String(now.getDate()).padStart(2, '0');
            document.getElementById('evDate').value = `${yyyy}-${mm}-${dd}`;
            document.getElementById('evTime').value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
            document.getElementById('evOrganizer').value = CURRENT_USER.username || '';
            document.getElementById('evNotes').value = '';

            // Формат
            if (t.format === 'team5v5') {
                document.getElementById('evTeam5v5').style.display = '';
                document.getElementById('evPodium').style.display = 'none';
                buildTeamSlots('evTeam1Slots', 'evT1_');
                buildTeamSlots('evTeam2Slots', 'evT2_');
                document.querySelectorAll('input[name="evWinner"]').forEach(r => r.checked = false);
            } else {
                document.getElementById('evTeam5v5').style.display = 'none';
                document.getElementById('evPodium').style.display = '';
                document.getElementById('evFirst').value = '';
                document.getElementById('evSecond').value = '';
                document.getElementById('evThird').value = '';
                document.getElementById('evParticipants').value = '';
            }

            document.getElementById('eventModal').classList.add('active');
        }
        function closeEventModal() {
            document.getElementById('eventModal').classList.remove('active');
            currentEventType = null;
        }
        document.getElementById('evCancel').addEventListener('click', closeEventModal);
        document.getElementById('eventModal').addEventListener('click', e => {
            if (e.target.id === 'eventModal') closeEventModal();
        });

        document.getElementById('evSubmit').addEventListener('click', async () => {
            if (!currentEventType) return;
            const t = currentEventType;
            const body = {
                type: t.type,
                type_label: t.label,
                format: t.format,
                date: document.getElementById('evDate').value,
                time: document.getElementById('evTime').value,
                organizer: document.getElementById('evOrganizer').value.trim(),
                prize_text: t.prize,
                notes: document.getElementById('evNotes').value.trim()
            };
            if (!body.organizer) { alert('Кто проводит?'); return; }

            if (t.format === 'team5v5') {
                const team1 = [];
                const team2 = [];
                for (let i = 1; i <= 5; i++) {
                    const v1 = document.getElementById('evT1_' + i).value.trim();
                    const v2 = document.getElementById('evT2_' + i).value.trim();
                    if (v1) team1.push(v1);
                    if (v2) team2.push(v2);
                }
                if (team1.length === 0 || team2.length === 0) { alert('Заполни обе команды'); return; }
                const winnerEl = document.querySelector('input[name="evWinner"]:checked');
                body.team1 = team1;
                body.team2 = team2;
                body.winner = winnerEl ? +winnerEl.value : null;
            } else {
                body.first = document.getElementById('evFirst').value.trim();
                body.second = document.getElementById('evSecond').value.trim();
                body.third = document.getElementById('evThird').value.trim();
                body.participants = document.getElementById('evParticipants').value
                    .split(',').map(s => s.trim()).filter(Boolean);
                if (!body.first) { alert('Укажи победителя'); return; }
            }

            const r = await fetch('/api/events', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await r.json();
            if (!r.ok) { alert('Ошибка: ' + (data.error || r.status)); return; }
            closeEventModal();
            renderEventsList();
            if (typeof renderAllReports === 'function') renderAllReports();
        });

        // Список проведённых ивентов
        async function fetchEvents() {
            try {
                const r = await fetch('/api/events');
                if (!r.ok) return [];
                const d = await r.json();
                return d.items || [];
            } catch { return []; }
        }
        function renderEventCard(e) {
            const teamHtml = e.format === 'team5v5' ? `
                <div class="event-teams-display">
                    <div class="event-team-display ${e.winner === 1 ? 'is-winner' : ''}">
                        <div class="etd-head">Команда 1 ${e.winner === 1 ? '<span class="etd-win-tag">Победа</span>' : ''}</div>
                        <ul>${(e.team1 || []).map(p => `<li>${escapeHtml(p)}</li>`).join('')}</ul>
                    </div>
                    <div class="event-team-display ${e.winner === 2 ? 'is-winner' : ''}">
                        <div class="etd-head">Команда 2 ${e.winner === 2 ? '<span class="etd-win-tag">Победа</span>' : ''}</div>
                        <ul>${(e.team2 || []).map(p => `<li>${escapeHtml(p)}</li>`).join('')}</ul>
                    </div>
                </div>` : `
                <div class="event-podium-display">
                    ${e.first  ? `<div class="evp-line evp-1"><b>1</b> ${escapeHtml(e.first)}</div>` : ''}
                    ${e.second ? `<div class="evp-line evp-2"><b>2</b> ${escapeHtml(e.second)}</div>` : ''}
                    ${e.third  ? `<div class="evp-line evp-3"><b>3</b> ${escapeHtml(e.third)}</div>` : ''}
                    ${(e.participants && e.participants.length) ? `<div class="evp-others">Участники: ${e.participants.map(escapeHtml).join(', ')}</div>` : ''}
                </div>`;
            const dt = fmtEventDate(e.date) + (e.time ? ' · ' + e.time : '');
            const typeInfo = EVENT_TYPES.find(t => t.type === e.type) || {};
            return `
                <div class="event-card">
                    <div class="event-card-head">
                        <div class="event-card-title">
                            <i class="fas ${typeInfo.icon || 'fa-bullhorn'}" style="color:${typeInfo.color || 'var(--accent)'};"></i>
                            <span>${escapeHtml(e.type_label || '—')}</span>
                        </div>
                        ${canDeleteEvent ? `<button class="report-del" data-id="${e.id}" title="Удалить"><i class="fas fa-times"></i></button>` : ''}
                    </div>
                    <div class="event-card-meta">
                        <span><i class="fas fa-calendar"></i> ${escapeHtml(dt) || '—'}</span>
                        <span><i class="fas fa-user-shield"></i> ${escapeHtml(e.organizer || '—')}</span>
                    </div>
                    <div class="event-card-prize">${escapeHtml(e.prize_text || '')}</div>
                    ${teamHtml}
                    ${e.notes ? `<div class="event-card-notes">${escapeHtml(e.notes)}</div>` : ''}
                </div>`;
        }
        async function renderEventsList() {
            const list = document.getElementById('eventsList');
            const count = document.getElementById('eventsCount');
            const items = await fetchEvents();
            count.textContent = items.length;
            if (items.length === 0) {
                list.innerHTML = '<div class="reports-empty">Ивентов пока нет.</div>';
                return;
            }
            list.innerHTML = items.slice().reverse().map(renderEventCard).join('');
            list.querySelectorAll('.report-del').forEach(b => {
                b.addEventListener('click', async () => {
                    if (!confirm('Удалить ивент?')) return;
                    await fetch('/api/events/delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: +b.dataset.id })
                    });
                    renderEventsList();
                    if (typeof renderAllReports === 'function') renderAllReports();
                });
            });
        }

        renderEventTypes();
        renderEventsList();

        // === Эмбиты ===
        (function initEmbeds() {
            const btn = document.getElementById('embedSendBtn');
            if (!btn) return;
            const btnLabel = document.getElementById('embedSendBtnLabel');
            const statusMsg = document.getElementById('embedStatusMsg');
            const historyList = document.getElementById('embedHistoryList');
            const linkInput = document.getElementById('embedLinkInput');
            const linkLoadBtn = document.getElementById('embedLinkLoadBtn');
            const editingBanner = document.getElementById('embedEditingBanner');
            const editingLink = document.getElementById('embedEditingLink');
            const editingCancelBtn = document.getElementById('embedEditingCancel');
            const cardsContainer = document.getElementById('embedCardsContainer');
            const addCardBtn = document.getElementById('embedAddCardBtn');
            const totalCounterEl = document.getElementById('embedTotalCounter');

            const EMBED_GUILD_ID = '531970658633252864';
            const EMBED_CHANNEL_IDS = { master: '1510992131446018139', curator: '1510992164392538163', help: '1526302909493543092' };
            const CHANNEL_KEY_BY_ID = Object.fromEntries(Object.entries(EMBED_CHANNEL_IDS).map(([k, v]) => [v, k]));
            const CHANNEL_LABELS = { master: 'Мастера', curator: 'Кураторы', help: 'Как пользоваться сайтом' };
            const PRESET_COLORS = ['#e5352b', '#fbbf24', '#34d399', '#9fb4cc', '#d99cb8', '#5865f2'];
            const MAX_EMBEDS = 10;
            let editing = null; // { channelId, messageId }

            function setChannelRadio(channelKey) {
                const radio = document.querySelector(`input[name="embedChannel"][value="${channelKey in EMBED_CHANNEL_IDS ? channelKey : 'master'}"]`);
                if (radio) radio.checked = true;
            }

            function updateCounter(el, len, max) {
                el.textContent = len + '/' + max;
                el.classList.toggle('is-near-limit', len > max * 0.9);
            }

            function collectEmbeds() {
                return [...cardsContainer.querySelectorAll('.embed-card')].map(c => ({
                    title: c.querySelector('[data-role="title"]').value.trim(),
                    description: c.querySelector('[data-role="description"]').value.trim(),
                    image: c.querySelector('[data-role="image"]').value.trim(),
                    color: c.querySelector('[data-role="color"]').value,
                }));
            }

            function updateTotalCounter() {
                const total = collectEmbeds().reduce((sum, e) => sum + e.title.length + e.description.length, 0);
                updateCounter(totalCounterEl, total, 6000);
            }

            function renumberCards() {
                const cards = [...cardsContainer.querySelectorAll('.embed-card')];
                cards.forEach((c, i) => {
                    c.querySelector('.embed-card-num').textContent = 'Эмбит ' + (i + 1);
                    c.querySelector('.embed-card-remove').style.display = cards.length > 1 ? '' : 'none';
                });
                addCardBtn.disabled = cards.length >= MAX_EMBEDS;
            }

            function createCard(data) {
                data = data || {};
                const el = document.createElement('div');
                el.className = 'embed-card';
                el.innerHTML = `
                    <div class="embed-card-head">
                        <span class="embed-card-num"></span>
                        <button class="embed-card-remove" type="button" title="Удалить эмбит"><i class="fas fa-trash"></i></button>
                    </div>
                    <div class="embed-label-row">
                        <label class="form-label">Заголовок</label>
                        <span class="embed-char-counter" data-role="titleCount">0/256</span>
                    </div>
                    <input type="text" class="form-control" data-role="title" maxlength="256" placeholder="Заголовок эмбита">
                    <div class="embed-label-row">
                        <label class="form-label">Текст</label>
                        <span class="embed-char-counter" data-role="descCount">0/4096</span>
                    </div>
                    <textarea class="form-control" data-role="description" rows="4" maxlength="4096" style="resize:vertical;" placeholder="Текст эмбита"></textarea>
                    <label class="form-label">Картинка (URL, необязательно)</label>
                    <input type="text" class="form-control" data-role="image" placeholder="https://...">
                    <label class="form-label">Цвет полоски</label>
                    <div class="embed-color-row">
                        <div class="embed-color-swatches" data-role="swatches"></div>
                        <input type="color" class="embed-color-picker" data-role="color" value="#e5352b">
                    </div>
                    <label class="form-label" style="margin-top:0.5rem;">Так будет выглядеть в Discord</label>
                    <div class="discord-embed-preview" data-role="preview">
                        <div class="discord-embed-bar" data-role="previewBar"></div>
                        <div class="discord-embed-body">
                            <div class="discord-embed-title" data-role="previewTitle"></div>
                            <div class="discord-embed-desc" data-role="previewDesc"></div>
                            <img class="discord-embed-image" data-role="previewImage" style="display:none;" alt="">
                        </div>
                    </div>`;

                const titleInput = el.querySelector('[data-role="title"]');
                const descInput = el.querySelector('[data-role="description"]');
                const imageInput = el.querySelector('[data-role="image"]');
                const colorPicker = el.querySelector('[data-role="color"]');
                const swatchesBox = el.querySelector('[data-role="swatches"]');
                const titleCount = el.querySelector('[data-role="titleCount"]');
                const descCount = el.querySelector('[data-role="descCount"]');
                const previewBar = el.querySelector('[data-role="previewBar"]');
                const previewTitle = el.querySelector('[data-role="previewTitle"]');
                const previewDesc = el.querySelector('[data-role="previewDesc"]');
                const previewImage = el.querySelector('[data-role="previewImage"]');

                titleInput.value = data.title || '';
                descInput.value = data.description || '';
                imageInput.value = data.image || '';
                colorPicker.value = data.color || '#e5352b';

                swatchesBox.innerHTML = PRESET_COLORS.map(c =>
                    `<button type="button" class="embed-swatch" data-color="${c}" style="background:${c};"></button>`
                ).join('');
                swatchesBox.querySelectorAll('.embed-swatch').forEach(sw => {
                    sw.addEventListener('click', () => { colorPicker.value = sw.dataset.color; refreshCard(); });
                });

                function refreshCard() {
                    previewBar.style.background = colorPicker.value;
                    previewTitle.textContent = titleInput.value || 'Заголовок';
                    previewTitle.style.opacity = titleInput.value ? '1' : '0.4';
                    previewDesc.textContent = descInput.value || 'Текст эмбита появится здесь…';
                    previewDesc.style.opacity = descInput.value ? '1' : '0.4';
                    const img = imageInput.value.trim();
                    if (img) { previewImage.src = img; previewImage.style.display = 'block'; }
                    else previewImage.style.display = 'none';
                    updateCounter(titleCount, titleInput.value.length, 256);
                    updateCounter(descCount, descInput.value.length, 4096);
                    updateTotalCounter();
                }
                [titleInput, descInput, imageInput, colorPicker].forEach(inp => inp.addEventListener('input', refreshCard));
                refreshCard();

                el.querySelector('.embed-card-remove').addEventListener('click', () => {
                    el.remove();
                    renumberCards();
                    updateTotalCounter();
                });

                return el;
            }

            function addCard(data) {
                cardsContainer.appendChild(createCard(data));
                renumberCards();
                updateTotalCounter();
            }
            function loadCards(list) {
                cardsContainer.innerHTML = '';
                (list && list.length ? list : [{}]).forEach(addCard);
            }
            addCardBtn.addEventListener('click', () => addCard());
            loadCards();

            function enterEditMode(channelKey, channelId, messageId, embeds) {
                editing = { channelId, messageId };
                loadCards(embeds);
                setChannelRadio(channelKey);
                btnLabel.textContent = 'Сохранить изменения';
                editingLink.href = `https://discord.com/channels/${EMBED_GUILD_ID}/${channelId}/${messageId}`;
                editingBanner.style.display = 'flex';
                statusMsg.textContent = '';
            }
            function exitEditMode() {
                editing = null;
                btnLabel.textContent = 'Отправить';
                editingBanner.style.display = 'none';
                linkInput.value = '';
            }
            editingCancelBtn.addEventListener('click', () => {
                exitEditMode();
                loadCards();
            });

            linkLoadBtn.addEventListener('click', async () => {
                const m = linkInput.value.trim().match(/discord\.com\/channels\/\d+\/(\d+)\/(\d+)/);
                if (!m) {
                    statusMsg.textContent = 'Это не похоже на ссылку на сообщение Discord.';
                    statusMsg.style.color = 'var(--bad)';
                    return;
                }
                const [, channelId, messageId] = m;
                const channelKey = CHANNEL_KEY_BY_ID[channelId];
                if (!channelKey) {
                    statusMsg.textContent = 'Эта ссылка не на инфо-канал (мастера/кураторы).';
                    statusMsg.style.color = 'var(--bad)';
                    return;
                }
                linkLoadBtn.disabled = true;
                statusMsg.textContent = 'Загружаю сообщение…';
                statusMsg.style.color = 'var(--text-secondary)';
                try {
                    const r = await fetch(`/api/edit-embed.php?channel_id=${channelId}&message_id=${messageId}`);
                    const data = await r.json();
                    if (!r.ok || data.error) throw new Error(data.error || ('HTTP ' + r.status));
                    enterEditMode(channelKey, channelId, messageId, data.embeds);
                } catch (e) {
                    statusMsg.textContent = 'Ошибка: ' + e.message;
                    statusMsg.style.color = 'var(--bad)';
                } finally {
                    linkLoadBtn.disabled = false;
                }
            });

            // --- История последних отправленных — с возможностью повторить как шаблон ---
            let historyItems = [];

            function itemEmbeds(item) {
                return (item.embeds && item.embeds.length) ? item.embeds
                    : [{ title: item.title, description: item.description, image: item.image, color: item.color }];
            }

            function historyItemHTML(item, idx) {
                const embeds = itemEmbeds(item);
                const first = embeds[0] || {};
                const label = CHANNEL_LABELS[item.channel] || item.channel;
                const when = item.created_at ? new Date(item.created_at).toLocaleString('ru-RU') : '';
                const editedNote = item.edited_at ? ` <span title="Отредактировано">(ред. ${escapeHtml(item.edited_by || '')})</span>` : '';
                const countBadge = embeds.length > 1 ? ` <span class="embed-history-count">+${embeds.length - 1}</span>` : '';
                const editBtn = item.message_id ? `
                        <button class="embed-history-repeat" data-idx="${idx}" data-action="edit" type="button" title="Изменить это сообщение">
                            <i class="fas fa-pen"></i>
                        </button>` : '';
                return `
                    <div class="embed-history-item">
                        <div class="embed-history-dot" style="background:${escapeHtml(first.color || '#e5352b')};"></div>
                        <div class="embed-history-main">
                            <div class="embed-history-title">${escapeHtml(first.title || first.description || '(без текста)')}${countBadge}</div>
                            <div class="embed-history-meta">
                                <span class="warn-cat-${item.channel in CHANNEL_LABELS ? item.channel : 'master'}">${escapeHtml(label)}</span>
                                <span>${escapeHtml(item.sent_by || '—')}</span>
                                <span>${escapeHtml(when)}${editedNote}</span>
                            </div>
                        </div>
                        ${editBtn}
                        <button class="embed-history-repeat" data-idx="${idx}" data-action="repeat" type="button" title="Повторить как шаблон (новое сообщение)">
                            <i class="fas fa-rotate-left"></i>
                        </button>
                    </div>`;
            }

            async function fetchEmbedHistory() {
                try {
                    const r = await fetch('/api/embeds-log.php');
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    const data = await r.json();
                    historyItems = data.items || [];
                    if (historyItems.length === 0) {
                        historyList.innerHTML = '<div class="reports-empty">Пока ничего не отправляли.</div>';
                        return;
                    }
                    historyList.innerHTML = historyItems.map(historyItemHTML).join('');
                    historyList.querySelectorAll('.embed-history-repeat').forEach(b => {
                        b.addEventListener('click', () => {
                            const item = historyItems[+b.dataset.idx];
                            if (!item) return;
                            const embeds = itemEmbeds(item);
                            if (b.dataset.action === 'edit' && item.message_id && item.channel_id) {
                                enterEditMode(item.channel, item.channel_id, item.message_id, embeds);
                                return;
                            }
                            exitEditMode();
                            loadCards(embeds);
                            setChannelRadio(item.channel);
                            statusMsg.textContent = 'Заполнено из истории — проверь текст и отправляй.';
                            statusMsg.style.color = 'var(--text-secondary)';
                        });
                    });
                } catch (e) {
                    historyList.innerHTML = '<div class="reports-empty">Не удалось загрузить историю.</div>';
                }
            }
            fetchEmbedHistory();

            btn.addEventListener('click', async () => {
                const embeds = collectEmbeds().filter(e => e.title || e.description);
                if (embeds.length === 0) {
                    statusMsg.textContent = 'Заполни хотя бы один эмбит (заголовок или текст).';
                    statusMsg.style.color = 'var(--bad)';
                    return;
                }
                const channel = document.querySelector('input[name="embedChannel"]:checked').value;
                btn.disabled = true;
                statusMsg.textContent = editing ? 'Сохраняю…' : 'Отправляю…';
                statusMsg.style.color = 'var(--text-secondary)';
                try {
                    const url = editing ? '/api/edit-embed.php' : '/api/send-embed.php';
                    const payload = editing
                        ? { channel_id: editing.channelId, message_id: editing.messageId, embeds }
                        : { channel, embeds };
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await r.json();
                    if (!r.ok || data.error) throw new Error(data.error || ('HTTP ' + r.status));
                    statusMsg.textContent = editing ? 'Изменено ✓' : 'Отправлено ✓';
                    statusMsg.style.color = 'var(--ok)';
                    exitEditMode();
                    loadCards();
                    fetchEmbedHistory();
                } catch (e) {
                    statusMsg.textContent = 'Ошибка: ' + e.message;
                    statusMsg.style.color = 'var(--bad)';
                } finally {
                    btn.disabled = false;
                }
            });
        })();

        // === Активность в голосовых ===
        (function initVoiceActivity() {
            const list = document.getElementById('voiceActivityList');
            const statusEl = document.getElementById('voiceActivityStatus');
            const refreshBtn = document.getElementById('voiceActivityRefreshBtn');
            const onlineList = document.getElementById('voiceOnlineList');
            const onlineCount = document.getElementById('voiceOnlineCount');
            if (!list) return;

            function formatDuration(seconds) {
                if (!seconds) return '—';
                const h = Math.floor(seconds / 3600);
                const m = Math.floor((seconds % 3600) / 60);
                if (h === 0) return m + 'м';
                return h + 'ч ' + m + 'м';
            }
            function formatSince(sinceMs) {
                const seconds = Math.max(0, Math.round((Date.now() - sinceMs) / 1000));
                return formatDuration(seconds) || '0м';
            }
            function initial(nick) {
                return escapeHtml((nick || '?').trim().charAt(0) || '?');
            }

            function renderOnline(online) {
                onlineCount.textContent = online.length;
                if (online.length === 0) {
                    onlineList.innerHTML = '<div class="reports-empty">Сейчас никого нет в комнатах.</div>';
                    return;
                }
                onlineList.innerHTML = online.map(o => `
                    <div class="va-online-row">
                        <div class="va-avatar">${initial(o.nick)}</div>
                        <div class="va-online-main">
                            <span class="va-online-nick">${escapeHtml(o.nick)}</span>
                            <span class="va-online-channel">${escapeHtml(o.channel_name || '')}</span>
                        </div>
                        <span class="va-online-since">${formatSince(o.since)}</span>
                    </div>`).join('');
            }

            function renderLeaderboard(board) {
                if (board.length === 0) {
                    list.innerHTML = '<div class="reports-empty">Пока нет данных активности.</div>';
                    return;
                }
                const todayIdx = (new Date().getDay() + 6) % 7;
                list.innerHTML = board.map((row, i) => {
                    const days = row.days || [];
                    const maxSec = Math.max(1, ...days.map(d => d.seconds));
                    const daysHtml = days.map((d, di) => `
                        <div class="va-week-cell${di === todayIdx ? ' is-today' : ''}">
                            <span class="va-week-day">${d.label}</span>
                            <div class="va-week-bar"><div class="va-week-bar-fill${d.seconds ? ' has-time' : ''}" style="height:${d.seconds ? Math.max(10, Math.round(d.seconds / maxSec * 100)) : 4}%"></div></div>
                            <span class="va-week-hours${d.seconds ? '' : ' is-zero'}">${formatDuration(d.seconds)}</span>
                        </div>`).join('');
                    return `
                        <div class="va-person">
                            <div class="va-person-top">
                                <div class="va-avatar">${initial(row.nick)}</div>
                                <div class="va-person-info">
                                    <div class="va-person-nick">${escapeHtml(row.nick)}</div>
                                    <div class="va-person-rank">#${i + 1}</div>
                                </div>
                                <div class="va-person-totals">
                                    <div class="va-total">
                                        <span class="va-total-label">Неделя</span>
                                        <span class="va-total-value${row.week_seconds ? '' : ' is-zero'}">${formatDuration(row.week_seconds)}</span>
                                    </div>
                                    <div class="va-total">
                                        <span class="va-total-label">Месяц</span>
                                        <span class="va-total-value${row.month_seconds ? '' : ' is-zero'}">${formatDuration(row.month_seconds)}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="va-week-grid">${daysHtml}</div>
                        </div>`;
                }).join('');
            }

            async function loadVoiceActivity() {
                statusEl.textContent = 'Загрузка…';
                refreshBtn.disabled = true;
                try {
                    const r = await fetch('/api/voice-activity.php');
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    const data = await r.json();
                    renderOnline(data.online || []);
                    renderLeaderboard(data.leaderboard || []);
                    statusEl.textContent = data.synced_at
                        ? 'Обновлено: ' + new Date(data.synced_at).toLocaleString('ru-RU')
                        : '';
                    if (data.sync_error) statusEl.textContent += ' — ошибка синка: ' + data.sync_error;
                } catch (e) {
                    list.innerHTML = '<div class="reports-empty">Не удалось загрузить активность.</div>';
                    onlineList.innerHTML = '';
                    statusEl.textContent = '';
                } finally {
                    refreshBtn.disabled = false;
                }
            }
            refreshBtn.addEventListener('click', loadVoiceActivity);
            loadVoiceActivity();
        })();

        // === Level up ===
        const LVL_MAX = 10;
        const canGiveLvlPoint = currentRoleLevel() >= 2; // curator+
        const canDeleteLvlPoint = currentRoleLevel() >= 3; // chief+
        let lvlCurrentTarget = null;
        let lvlAllPoints = [];

        async function fetchLevelup() {
            try {
                const r = await fetch('/api/levelup');
                if (!r.ok) return [];
                const d = await r.json();
                return d.items || [];
            } catch { return []; }
        }
        async function fetchMastersFromRoster() {
            try {
                const r = await fetch('/api/roster');
                if (!r.ok) return [];
                const d = await r.json();
                return (d.roster && d.roster.master) || [];
            } catch { return []; }
        }

        // Мастера из Google Sheets (секция «Master» того же листа, что и выговоры)
        // URL зашит прямо здесь, чтобы не зависеть от порядка объявления const'ов ниже.
        const SHEET_MASTERS_URL = 'https://docs.google.com/spreadsheets/d/15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU/export?format=csv&gid=87425732';
        async function fetchMastersFromSheet() {
            const res = await fetch(SHEET_MASTERS_URL);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const text = await res.text();
            if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                throw new Error('Таблица закрыта');
            }
            const rows = parseCSV(text);
            const ENTER_KEYS = ['Master'];
            const STOP_KEYS  = ['Administrator', 'Administrative Assistant', 'Аdministrative Аssistant',
                                'Curator', 'Moderators', 'Список модераторов', 'High staff'];
            function detect(row) {
                for (const c of row) {
                    const t = (c || '').trim();
                    if (ENTER_KEYS.includes(t)) return t;
                    if (STOP_KEYS.includes(t))  return '_STOP_';
                }
                return null;
            }
            let current = null;
            const masters = [];
            for (const row of rows) {
                if (!row || !row.length) continue;
                const sec = detect(row);
                if (sec === '_STOP_') { current = null; continue; }
                if (sec) { current = sec; continue; }
                if (current !== 'Master') continue;
                const id   = (row[2] || '').trim();
                const nick = (row[3] || '').trim();
                const name = (row[5] || '').trim();
                if (!/^\d{15,22}$/.test(id)) continue;
                masters.push({ id, username: nick, name: name || nick, avatar: null });
            }
            return masters;
        }

        // Объединяет мастеров из таблицы + аватарки из /api/high-staff (если есть совпадение по discord_id)
        async function fetchMastersForLevelup() {
            const [sheetMasters, rosterResp] = await Promise.all([
                fetchMastersFromSheet().catch(() => []),
                fetch('/api/high-staff.php').then(r => r.ok ? r.json() : { roster: {} }).catch(() => ({ roster: {} }))
            ]);
            const allRoster = []
                .concat(rosterResp.roster?.admin   || [])
                .concat(rosterResp.roster?.asst    || [])
                .concat(rosterResp.roster?.chief   || [])
                .concat(rosterResp.roster?.curator || [])
                .concat(rosterResp.roster?.master  || []);
            const avatarById = {};
            allRoster.forEach(m => { if (m.id && m.avatar) avatarById[m.id] = m.avatar; });
            return sheetMasters.map(m => ({ ...m, avatar: avatarById[m.id] || null }));
        }

        async function renderLevelup() {
            const grid = document.getElementById('lvlMastersGrid');
            const status = document.getElementById('lvlStatus');
            grid.innerHTML = '<div style="display:flex;justify-content:center;padding:2rem;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--accent);"></i></div>';

            const [masters, points] = await Promise.all([fetchMastersForLevelup(), fetchLevelup()]);
            lvlAllPoints = points;

            if (masters.length === 0) {
                grid.innerHTML = '<div class="placeholder"><i class="fas fa-users-slash"></i><h2>Мастеров в таблице нет</h2><p>Добавь мастеров в секцию «Master» Google-таблицы.</p></div>';
                status.textContent = 'Пусто';
                return;
            }

            const cards = masters.map(m => {
                const myPoints = points.filter(p => p.master_id === m.id);
                const count = myPoints.length;
                const pct = Math.min(100, (count / LVL_MAX) * 100);
                const isMax = count >= LVL_MAX;
                return `
                    <div class="lvl-card" data-id="${escapeHtml(m.id)}" data-nick="${escapeHtml(m.username || '')}" data-name="${escapeHtml(m.name || '')}">
                        <div class="lvl-card-head">
                            ${m.avatar ? `<img src="${m.avatar}" class="lvl-avatar" alt="">` : `<div class="lvl-avatar lvl-avatar-fb">${escapeHtml((m.name || '?').charAt(0).toUpperCase())}</div>`}
                            <div class="lvl-info">
                                <div class="lvl-name">${escapeHtml(m.name || '—')}</div>
                                <div class="lvl-nick">${escapeHtml(m.username || '')}</div>
                            </div>
                            <div class="lvl-counter ${count >= 7 ? 'high' : count >= 4 ? 'mid' : 'low'}">
                                <span class="lvl-num">${count}</span>
                                <span class="lvl-max">/ ${LVL_MAX}</span>
                            </div>
                        </div>
                        <div class="lvl-bar">
                            <div class="lvl-bar-fill" style="width:${pct}%;"></div>
                        </div>
                        <div class="lvl-actions">
                            ${canGiveLvlPoint
                                ? `<button class="btn-lvl-add ${isMax ? 'is-disabled' : ''}" data-id="${escapeHtml(m.id)}" data-nick="${escapeHtml(m.username || '')}" data-name="${escapeHtml(m.name || '')}" ${isMax ? 'disabled' : ''} type="button">
                                       <i class="fas fa-plus"></i> Выдать балл
                                   </button>` : ''}
                            <button class="btn-lvl-history" data-id="${escapeHtml(m.id)}" data-name="${escapeHtml(m.name || '')}" type="button">
                                <i class="fas fa-list"></i> История${count ? ' (' + count + ')' : ''}
                            </button>
                        </div>
                    </div>`;
            }).join('');
            grid.innerHTML = `<div class="lvl-grid">${cards}</div>`;
            status.textContent = `${masters.length} мастер${masters.length === 1 ? '' : 'а'}`;

            grid.querySelectorAll('.btn-lvl-add').forEach(b => {
                b.addEventListener('click', () => openLvlAdd({
                    id: b.dataset.id, nick: b.dataset.nick, name: b.dataset.name
                }));
            });
            grid.querySelectorAll('.btn-lvl-history').forEach(b => {
                b.addEventListener('click', () => openLvlHistory({
                    id: b.dataset.id, name: b.dataset.name
                }));
            });
        }

        function openLvlAdd(target) {
            lvlCurrentTarget = target;
            document.getElementById('lvlAddTarget').innerHTML =
                `<div style="font-weight:800;color:#fff;font-size:1rem;">${escapeHtml(target.name)}</div>
                 <div style="font-size:0.82rem;color:var(--text-secondary);margin-top:2px;">${escapeHtml(target.nick)}</div>`;
            document.getElementById('lvlAddReason').value = '';
            document.getElementById('lvlAddModal').classList.add('active');
            setTimeout(() => document.getElementById('lvlAddReason').focus(), 50);
        }
        function closeLvlAdd() {
            document.getElementById('lvlAddModal').classList.remove('active');
            lvlCurrentTarget = null;
        }
        document.getElementById('lvlAddCancel').addEventListener('click', closeLvlAdd);
        document.getElementById('lvlAddModal').addEventListener('click', e => {
            if (e.target.id === 'lvlAddModal') closeLvlAdd();
        });
        document.getElementById('lvlAddSubmit').addEventListener('click', async () => {
            if (!lvlCurrentTarget) return;
            const reason = document.getElementById('lvlAddReason').value.trim();
            if (!reason) { alert('Укажи, за что выдаёшь балл'); return; }
            const r = await fetch('/api/levelup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    master_id: lvlCurrentTarget.id,
                    master_nick: lvlCurrentTarget.nick,
                    master_name: lvlCurrentTarget.name,
                    reason
                })
            });
            const data = await r.json();
            if (!r.ok) { alert('Ошибка: ' + (data.error || r.status)); return; }
            closeLvlAdd();
            renderLevelup();
        });

        function openLvlHistory(target) {
            const items = lvlAllPoints
                .filter(p => p.master_id === target.id)
                .sort((a, b) => new Date(b.given_at) - new Date(a.given_at));
            document.getElementById('lvlHistoryTitle').textContent = 'История баллов — ' + (target.name || '');
            const list = document.getElementById('lvlHistoryList');
            if (items.length === 0) {
                list.innerHTML = '<div class="reports-empty">Баллов пока нет.</div>';
            } else {
                list.innerHTML = items.map((p, idx) => {
                    const date = new Date(p.given_at).toLocaleString('ru-RU');
                    return `
                        <div class="lvl-history-item">
                            <div class="lvl-history-num">+1</div>
                            <div class="lvl-history-body">
                                <div class="lvl-history-reason">${escapeHtml(p.reason)}</div>
                                <div class="lvl-history-meta">
                                    <span><i class="fas fa-user-shield"></i> ${escapeHtml(p.given_by)}</span>
                                    <span><i class="fas fa-calendar"></i> ${date}</span>
                                </div>
                            </div>
                            ${canDeleteLvlPoint ? `<button class="report-del" data-id="${p.id}" title="Удалить"><i class="fas fa-times"></i></button>` : ''}
                        </div>`;
                }).join('');
                list.querySelectorAll('.report-del').forEach(b => {
                    b.addEventListener('click', async () => {
                        if (!confirm('Удалить балл?')) return;
                        await fetch('/api/levelup/delete', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: +b.dataset.id })
                        });
                        closeLvlHistory();
                        renderLevelup();
                    });
                });
            }
            document.getElementById('lvlHistoryModal').classList.add('active');
        }
        function closeLvlHistory() {
            document.getElementById('lvlHistoryModal').classList.remove('active');
        }
        document.getElementById('lvlHistoryClose').addEventListener('click', closeLvlHistory);
        document.getElementById('lvlHistoryModal').addEventListener('click', e => {
            if (e.target.id === 'lvlHistoryModal') closeLvlHistory();
        });

        renderLevelup();

        // === Сверка таблицы ===
        (function initSync() {
            const btn = document.getElementById('btnStartSync');
            if (!btn) return;
            const loader   = document.getElementById('syncLoader');
            const status   = document.getElementById('syncLoaderStatus');
            const results  = document.getElementById('syncResults');

            btn.addEventListener('click', async () => {
                btn.disabled = true;
                results.classList.remove('visible');
                loader.style.display = 'block';
                const startedAt = Date.now();
                status.textContent = 'Логинимся и сверяем…';
                const tick = setInterval(() => {
                    const sec = Math.floor((Date.now() - startedAt) / 1000);
                    status.textContent = `Логинимся и сверяем… ${sec} сек`;
                }, 1000);

                try {
                    const r = await fetch('/api/sync-moderators.php');
                    const data = await r.json();
                    clearInterval(tick);
                    if (!r.ok || data.error) {
                        status.textContent = 'Ошибка: ' + (data.error || r.status);
                        return;
                    }
                    status.textContent = `Готово · В таблице: ${data.sheet_count}  ·  В Discord: ${data.discord_count}`;
                    renderSync(data);
                    results.classList.add('visible');
                    loader.style.display = 'none';
                } catch (e) {
                    clearInterval(tick);
                    status.textContent = 'Ошибка: ' + e.message;
                } finally {
                    btn.disabled = false;
                }
            });

            function renderSync(data) {
                // Дубликаты
                const dupCard = document.getElementById('syncCardDuplicates');
                const dupList = document.getElementById('syncListDuplicates');
                document.getElementById('syncCountDuplicates').textContent = (data.duplicates || []).length;
                if (data.duplicates && data.duplicates.length) {
                    dupCard.style.display = '';
                    dupList.innerHTML = data.duplicates.map(d => `
                        <div class="sync-item sync-item-dup">
                            <i class="fas fa-clone" style="color:#f97316;"></i>
                            <div class="sync-item-body">
                                <div class="sync-item-name">${escapeHtml(d.nick || '—')}</div>
                                <div class="sync-item-meta">ID: <span class="mono">${escapeHtml(d.id)}</span> · строки: ${d.rows.join(', ')}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    dupCard.style.display = 'none';
                }

                // Нет в таблице (лишние в Discord)
                const exList = document.getElementById('syncListExtra');
                document.getElementById('syncCountExtra').textContent = (data.extra || []).length;
                if (data.extra && data.extra.length) {
                    exList.innerHTML = data.extra.map(m => `
                        <div class="sync-item">
                            ${m.avatar ? `<img src="${m.avatar}" class="sync-avatar" alt="">` : `<i class="fab fa-discord" style="color:#5865F2;"></i>`}
                            <div class="sync-item-body">
                                <div class="sync-item-name">${escapeHtml(m.name || m.username || '—')}</div>
                                <div class="sync-item-meta"><span class="mono">${escapeHtml(m.id)}</span></div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    exList.innerHTML = '<div class="sync-empty">Лишних нет ✓</div>';
                }

                // Убрать из таблицы (нет в Discord)
                const misList = document.getElementById('syncListMissing');
                document.getElementById('syncCountMissing').textContent = (data.missing || []).length;
                if (data.missing && data.missing.length) {
                    misList.innerHTML = data.missing.map(m => `
                        <div class="sync-item">
                            <i class="fas fa-file-excel" style="color:#fbbf24;"></i>
                            <div class="sync-item-body">
                                <div class="sync-item-name">${escapeHtml(m.nick || '—')}</div>
                                <div class="sync-item-meta"><span class="mono">${escapeHtml(m.id)}</span> · строка: ${m.rows.join(', ')}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    misList.innerHTML = '<div class="sync-empty">Все на месте ✓</div>';
                }
            }
        })();

        // === Состав вышки (из бота через /api/roster) ===
        const ROSTER_CATEGORIES = [
            { key: 'admin',   label: 'Администраторы',    icon: 'fa-crown',         cls: 'category-admin' },
            { key: 'asst',    label: 'Ассистенты админа', icon: 'fa-user-tie',      cls: 'category-asst' },
            { key: 'chief',   label: 'Главные кураторы',  icon: 'fa-star',          cls: 'category-chief' },
            { key: 'curator', label: 'Кураторы',          icon: 'fa-shield-halved', cls: 'category-curators' },
            { key: 'master',  label: 'Мастера',           icon: 'fa-user-graduate', cls: 'category-masters' }
        ];

        async function loadWyshka() {
            const status = document.getElementById('wyshkaStatus');
            const container = document.getElementById('wyshkaContainer');
            try {
                const res = await fetch('/api/high-staff.php');
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                renderWyshka(data.roster || {});
                status.textContent = data.updated_at ? 'Обновлено: ' + new Date(data.updated_at).toLocaleString('ru-RU') : 'Обновлено';
                status.style.background = 'rgba(16, 185, 129, 0.1)';
                status.style.color = '#34d399';
                status.style.borderColor = 'rgba(16, 185, 129, 0.25)';
            } catch (e) {
                container.innerHTML = `
                    <div class="placeholder">
                        <i class="fas fa-triangle-exclamation" style="color:#f87171;"></i>
                        <h2>Не удалось загрузить состав</h2>
                        <p>${escapeHtml(e.message)}</p>
                    </div>`;
                status.textContent = 'Ошибка';
                status.style.background = 'rgba(239, 68, 68, 0.1)';
                status.style.color = '#f87171';
                status.style.borderColor = 'rgba(239, 68, 68, 0.25)';
            }
        }

        function renderWyshka(roster) {
            const container = document.getElementById('wyshkaContainer');
            const blocks = ROSTER_CATEGORIES.map(cat => {
                const members = roster[cat.key] || [];
                if (members.length === 0) return '';
                const cards = members.map(m => {
                    const isMe = CURRENT_USER.discord_id && m.id === CURRENT_USER.discord_id;
                    const bannerAttr = m.banner
                        ? ` style="background-image:url('${String(m.banner).replace(/'/g, '%27')}');"`
                        : '';
                    return `
                    <div class="staff-card${m.banner ? ' has-banner' : ''}${isMe ? ' is-me' : ''}"${bannerAttr}>
                        ${m.avatar
                            ? `<img class="staff-avatar staff-avatar-img" src="${m.avatar}" alt="">`
                            : `<div class="staff-avatar"><i class="fas ${cat.icon}"></i></div>`}
                        <div class="staff-info">
                            <div class="staff-name">${escapeHtml(m.name || m.nick || '—')}</div>
                            ${m.name && m.nick ? `<div style="font-size:0.82rem;color:var(--text-secondary);margin-top:2px;">${escapeHtml(m.nick)}</div>` : ''}
                            <div style="font-size:0.76rem;color:var(--text-muted);font-family:'Roboto Mono',monospace;margin-top:2px;word-break:break-all;">${escapeHtml(m.id || '')}</div>
                            <div style="display:flex;gap:6px;align-items:center;margin-top:8px;flex-wrap:wrap;">
                                ${m.date ? `<span class="staff-tag"><i class="fas fa-calendar" style="margin-right:4px;"></i>${escapeHtml(m.date)}</span>` : ''}
                                ${m.days ? `<span class="staff-tag">${escapeHtml(m.days)} дн.</span>` : ''}
                            </div>
                        </div>
                    </div>`;
                }).join('');
                return `
                    <div class="management-category ${cat.cls}">
                        <div class="category-header">
                            <i class="fas ${cat.icon}"></i>
                            <span class="category-title">${cat.label} <span style="opacity:0.6;font-weight:500;">(${members.length})</span></span>
                        </div>
                        <div class="members-grid">${cards}</div>
                    </div>`;
            }).join('');
            container.innerHTML = `<div class="management-list">${blocks || '<div class="placeholder"><i class="fas fa-users-slash"></i><h2>Пока пусто</h2><p>Никого нет в секции High staff.</p></div>'}</div>`;
        }

        loadWyshka();

        // === Профиль ===
        const ROLE_BADGE_COLORS = {
            admin:   { bg: 'rgba(251, 191, 36, 0.15)',  fg: '#fbbf24', border: 'rgba(251, 191, 36, 0.3)' },
            asst:    { bg: 'rgba(244, 114, 182, 0.15)', fg: '#f9a8d4', border: 'rgba(244, 114, 182, 0.3)' },
            chief:   { bg: 'rgba(168, 82, 122, 0.15)',  fg: '#d99cb8', border: 'rgba(168, 82, 122, 0.3)' },
            curator: { bg: 'rgba(16, 185, 129, 0.15)',  fg: '#34d399', border: 'rgba(16, 185, 129, 0.3)' },
            master:  { bg: 'rgba(90, 120, 155, 0.15)',  fg: '#9fb4cc', border: 'rgba(90, 120, 155, 0.3)' }
        };
        const PROFILE_STORAGE_KEY = 'profile_' + (CURRENT_USER.username || 'anon');

        let profileState = loadProfileState();
        let profileDraft = { ...profileState };

        function loadProfileState() {
            try {
                const v = JSON.parse(localStorage.getItem(PROFILE_STORAGE_KEY) || '{}');
                return { about: v.about || '', banner: v.banner || '' };
            } catch { return { about: '', banner: '' }; }
        }
        function saveProfileState(state) {
            localStorage.setItem(PROFILE_STORAGE_KEY, JSON.stringify(state));
        }

        async function fetchMyRosterEntry() {
            if (!CURRENT_USER.discord_id) return null;
            try {
                const r = await fetch('/api/high-staff.php');
                if (!r.ok) return null;
                const data = await r.json();
                const all = []
                    .concat(data.roster?.admin || [])
                    .concat(data.roster?.asst || [])
                    .concat(data.roster?.chief || [])
                    .concat(data.roster?.curator || [])
                    .concat(data.roster?.master || []);
                return all.find(m => m.id === CURRENT_USER.discord_id) || null;
            } catch { return null; }
        }

        function fallbackAvatarSVG(letter) {
            const colors = {
                admin:   ['#fbbf24', '#f59e0b'],
                chief:   ['#a8527a', '#7a3a5c'],
                curator: ['#10b981', '#059669'],
                master:  ['#5a789b', '#3d5670']
            };
            const [c1, c2] = colors[CURRENT_USER.role] || ['#ff3b3b', '#991b1b'];
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 180 180">
                <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${c1}"/><stop offset="1" stop-color="${c2}"/></linearGradient></defs>
                <rect width="180" height="180" fill="url(#g)"/>
                <text x="90" y="118" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="90" fill="#fff" text-anchor="middle">${escapeHtml(letter)}</text>
            </svg>`;
            return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
        }

        function renderProfileHeader() {
            const u = CURRENT_USER;
            document.getElementById('profName').textContent = u.username || '—';
            document.getElementById('profDiscordId').textContent = u.discord_id || '—';
            const role = document.getElementById('profRole');
            role.textContent = u.role_name || '—';
            const col = ROLE_BADGE_COLORS[u.role];
            if (col) {
                role.style.background = col.bg;
                role.style.color = col.fg;
                role.style.borderColor = col.border;
            }
            const header = document.getElementById('profHeader');
            if (profileState.banner) {
                header.style.backgroundImage = `url("${profileState.banner}")`;
            } else {
                header.style.backgroundImage = '';
            }
            document.getElementById('profAbout').textContent = profileState.about || 'Пользователь ещё не заполнил информацию о себе.';
        }

        async function renderProfileAvatar() {
            const img = document.getElementById('profAvatar');
            const fallback = fallbackAvatarSVG((CURRENT_USER.username || '?').charAt(0).toUpperCase());
            img.src = fallback;
            const me = await fetchMyRosterEntry();
            if (me && me.avatar) {
                const test = new Image();
                test.onload = () => { img.src = me.avatar; };
                test.src = me.avatar;
            }
            // Баннер хранится на сервере (виден всем), а не только в localStorage
            // этого браузера — как только пришёл ответ, он становится главным.
            if (me) {
                profileState.banner = me.banner || '';
                profileDraft.banner = profileState.banner;
                renderProfileHeader();
            }
        }

        function renderProfileStats() {
            let moder = [], master = [];
            try { moder = JSON.parse(localStorage.getItem('moder_reports') || '[]'); } catch {}
            try { master = JSON.parse(localStorage.getItem('master_reports') || '[]'); } catch {}

            // Фильтруем по reviewer (нечувствительно к регистру + триммим), если он совпадает с текущим юзером
            const me = (CURRENT_USER.username || '').toLowerCase().trim();
            const matchMe = r => (r.reviewer || '').toLowerCase().trim() === me;
            const myModer = moder.filter(matchMe);
            const myMaster = master.filter(matchMe);
            // Если у юзера нет ни одной записи как reviewer — показываем все (личное устройство)
            const moderList = myModer.length ? myModer : moder;
            const masterList = myMaster.length ? myMaster : master;

            const setText = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = val;
            };
            setText('statModerTotal', moderList.length);
            setText('statMasterTotal', masterList.length);
        }

        // Сжатие изображения до разумных размеров (макс. 1600px по ширине, JPEG q=0.85).
        function compressImageToDataURL(file, maxWidth = 1600, quality = 0.85) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                const url = URL.createObjectURL(file);
                img.onload = () => {
                    const ratio = Math.min(1, maxWidth / img.width);
                    const w = Math.round(img.width * ratio);
                    const h = Math.round(img.height * ratio);
                    const c = document.createElement('canvas');
                    c.width = w; c.height = h;
                    c.getContext('2d').drawImage(img, 0, 0, w, h);
                    URL.revokeObjectURL(url);
                    try { resolve(c.toDataURL('image/jpeg', quality)); }
                    catch (e) { reject(e); }
                };
                img.onerror = (e) => { URL.revokeObjectURL(url); reject(e); };
                img.src = url;
            });
        }

        function setBannerPreview(src) {
            const zone = document.getElementById('profBannerZone');
            const icon = document.getElementById('profBannerIcon');
            const hint = document.getElementById('profBannerHint');
            const clear = document.getElementById('profBannerClear');
            if (src) {
                zone.style.backgroundImage = `url("${src}")`;
                zone.classList.add('has-image');
                icon.style.display = 'none';
                hint.textContent = 'Клик / Ctrl+V — заменить';
                clear.style.display = 'flex';
            } else {
                zone.style.backgroundImage = '';
                zone.classList.remove('has-image');
                icon.style.display = '';
                hint.textContent = 'Клик — выбрать файл · Ctrl+V — вставить из буфера';
                clear.style.display = 'none';
            }
        }

        async function handleBannerFile(file) {
            try {
                const dataUrl = await compressImageToDataURL(file);
                profileDraft.banner = dataUrl;
                document.getElementById('profBannerInput').value = '';
                setBannerPreview(dataUrl);
            } catch (e) {
                alert('Не удалось обработать картинку: ' + e.message);
            }
        }

        function openProfileEdit() {
            document.getElementById('profBannerInput').value = '';
            document.getElementById('profAboutInput').value = profileDraft.about || '';
            // Если баннер — URL (не data:), показываем его в input
            if (profileDraft.banner && !profileDraft.banner.startsWith('data:')) {
                document.getElementById('profBannerInput').value = profileDraft.banner;
            }
            setBannerPreview(profileDraft.banner || '');
            document.getElementById('profEditModal').classList.add('active');
        }
        function closeProfileEdit() {
            document.getElementById('profEditModal').classList.remove('active');
        }
        function applyProfileDraft() {
            const urlInput = document.getElementById('profBannerInput').value.trim();
            // Если в input введён URL — он перекрывает data: из вставки
            if (urlInput) profileDraft.banner = urlInput;
            profileDraft.about = document.getElementById('profAboutInput').value;
            const header = document.getElementById('profHeader');
            header.style.backgroundImage = profileDraft.banner ? `url("${profileDraft.banner}")` : '';
            document.getElementById('profAbout').textContent = profileDraft.about || 'Пользователь ещё не заполнил информацию о себе.';
            document.getElementById('profSaveBar').style.display = 'flex';
            closeProfileEdit();
        }

        // Клик по зоне → файл-пикер
        document.getElementById('profBannerZone').addEventListener('click', () => {
            document.getElementById('profBannerFile').click();
        });
        document.getElementById('profBannerFile').addEventListener('change', (e) => {
            const f = e.target.files && e.target.files[0];
            if (f) handleBannerFile(f);
            e.target.value = '';
        });
        // Очистить баннер
        document.getElementById('profBannerClear').addEventListener('click', (e) => {
            e.stopPropagation();
            profileDraft.banner = '';
            document.getElementById('profBannerInput').value = '';
            setBannerPreview('');
        });
        // Ctrl+V в модалке — вставить картинку из буфера
        window.addEventListener('paste', async (e) => {
            if (!document.getElementById('profEditModal').classList.contains('active')) return;
            const items = (e.clipboardData && e.clipboardData.items) || [];
            for (const it of items) {
                if (it.type && it.type.startsWith('image/')) {
                    const file = it.getAsFile();
                    if (file) {
                        e.preventDefault();
                        await handleBannerFile(file);
                        return;
                    }
                }
            }
            // Если вставили текст — пробуем как URL
            const text = (e.clipboardData && e.clipboardData.getData('text')) || '';
            if (/^https?:\/\/\S+\.(png|jpe?g|webp|gif|avif)(\?\S*)?$/i.test(text.trim())) {
                e.preventDefault();
                profileDraft.banner = text.trim();
                document.getElementById('profBannerInput').value = text.trim();
                setBannerPreview(text.trim());
            }
        });
        async function saveProfileChanges() {
            profileState = { ...profileDraft };
            saveProfileState(profileState);
            document.getElementById('profSaveBar').style.display = 'none';
            try {
                await fetch('/api/save-banner.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ banner: profileState.banner || '' })
                });
            } catch {}
            loadWyshka();
        }
        function discardProfileChanges() {
            profileDraft = { ...profileState };
            renderProfileHeader();
            document.getElementById('profSaveBar').style.display = 'none';
        }

        document.getElementById('profEditBtn').addEventListener('click', openProfileEdit);
        document.getElementById('profModalCancel').addEventListener('click', closeProfileEdit);
        document.getElementById('profModalApply').addEventListener('click', applyProfileDraft);
        document.getElementById('profSaveBtn').addEventListener('click', saveProfileChanges);
        document.getElementById('profDiscardBtn').addEventListener('click', discardProfileChanges);
        document.getElementById('profEditModal').addEventListener('click', e => {
            if (e.target.id === 'profEditModal') closeProfileEdit();
        });

        renderProfileHeader();
        renderProfileStats();
        renderProfileAvatar();

        // === Выговоры (Google Sheets) ===
        const WARN_SHEET_ID = '15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU';
        const WARN_GID = '87425732';
        const WARN_CSV_URL = `https://docs.google.com/spreadsheets/d/${WARN_SHEET_ID}/export?format=csv&gid=${WARN_GID}`;

        // CSV-парсер с поддержкой кавычек
        function parseCSV(text) {
            const rows = [];
            let row = [], field = '', inQuotes = false;
            for (let i = 0; i < text.length; i++) {
                const c = text[i];
                if (inQuotes) {
                    if (c === '"') {
                        if (text[i + 1] === '"') { field += '"'; i++; }
                        else inQuotes = false;
                    } else field += c;
                } else {
                    if (c === '"') inQuotes = true;
                    else if (c === ',') { row.push(field); field = ''; }
                    else if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
                    else if (c === '\r') {}
                    else field += c;
                }
            }
            if (field.length || row.length) { row.push(field); rows.push(row); }
            return rows;
        }

        function warningLevel(w) {
            const v = (w || '').trim();
            const m = v.match(/^(\d)\s*\/\s*3$/);
            if (m) return parseInt(m[1], 10);
            return 0;
        }
        function warningClass(lvl) {
            if (lvl === 0) return 'w-clean';
            if (lvl === 1) return 'w-warn1';
            if (lvl === 2) return 'w-warn2';
            return 'w-warn3';
        }

        async function loadWarnings() {
            const status = document.getElementById('warnStatus');
            const container = document.getElementById('warnContainer');
            try {
                const [res, avatarRes] = await Promise.all([
                    fetch(WARN_CSV_URL),
                    fetch('/api/high-staff.php').then(r => r.ok ? r.json() : null).catch(() => null)
                ]);
                // Аватарки только кураторов и мастеров — тот же кэш, что и на "Главной",
                // админов/асистентов сюда специально не берём.
                const avatarById = {};
                if (avatarRes && avatarRes.roster) {
                    [...(avatarRes.roster.curator || []), ...(avatarRes.roster.master || [])]
                        .forEach(m => { if (m.id && m.avatar) avatarById[m.id] = m.avatar; });
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const text = await res.text();
                if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                    throw new Error('Таблица закрыта. Открой доступ «Все, у кого есть ссылка → Читатель».');
                }
                const rows = parseCSV(text);

                const sections = { Curator: [], Master: [] };
                const ENTER_KEYS = ['Curator', 'Master'];
                const STOP_KEYS  = ['Administrator', 'Administrative Assistant', 'Аdministrative Аssistant',
                                    'Moderators', 'Список модераторов', 'High staff'];
                function detectSection(row) {
                    for (const cell of row) {
                        const t = (cell || '').trim();
                        if (ENTER_KEYS.includes(t)) return t;
                        if (STOP_KEYS.includes(t))  return '_STOP_';
                    }
                    return null;
                }
                let currentCat = null;

                for (const row of rows) {
                    if (!row || !row.length) continue;
                    const sec = detectSection(row);
                    if (sec === '_STOP_') { currentCat = null; continue; }
                    if (sec) { currentCat = sec; continue; }
                    if (!currentCat) continue;

                    // Колонки: B=дата, C=айди, D=ник, E=дни, F=имя, G=выговоры, H=линк
                    const date = (row[1] || '').trim();
                    const id   = (row[2] || '').trim();
                    const nick = (row[3] || '').trim();
                    const days = (row[4] || '').trim();
                    const name = (row[5] || '').trim();
                    const warn = (row[6] || '').trim();

                    // Строгая проверка: ID должен быть Discord-айди (17–20 цифр) — иначе это заголовок/мусор
                    if (!/^\d{15,22}$/.test(id)) continue;

                    sections[currentCat].push({ date, id, nick, days, name, warn });
                }

                renderWarnings(sections, avatarById);
                status.textContent = 'Обновлено';
                status.style.background = 'rgba(16, 185, 129, 0.1)';
                status.style.color = '#34d399';
                status.style.borderColor = 'rgba(16, 185, 129, 0.25)';
            } catch (e) {
                console.error('loadWarnings error:', e);
                container.innerHTML = `
                    <div class="placeholder">
                        <i class="fas fa-triangle-exclamation" style="color:#f87171;"></i>
                        <h2>Не удалось загрузить</h2>
                        <p>${escapeHtml(e.message)}</p>
                    </div>`;
                status.textContent = 'Ошибка';
                status.style.background = 'rgba(239, 68, 68, 0.1)';
                status.style.color = '#f87171';
                status.style.borderColor = 'rgba(239, 68, 68, 0.25)';
            }
        }

        function renderWarnings(sections, avatarById) {
            avatarById = avatarById || {};
            const container = document.getElementById('warnContainer');
            const blocks = [
                { key: 'Curator', label: 'Кураторы',  icon: 'fa-shield-halved',  cls: 'category-curators' },
                { key: 'Master',  label: 'Мастера',   icon: 'fa-user-graduate',  cls: 'category-masters'  }
            ].map(cat => {
                const members = sections[cat.key] || [];
                if (members.length === 0) return '';
                // admin/asst (>=3) выдают всем в этом списке; кураторы (level 2) —
                // только мастерам, не друг другу; мастера — никому.
                const myLevel = (typeof currentRoleLevel === 'function') ? currentRoleLevel() : 0;
                const canIssue = myLevel >= 3 || (myLevel === 2 && cat.key === 'Master');
                const catKey = cat.key === 'Curator' ? 'curator' : 'master';
                const cards = members.map(m => {
                    const lvl = warningLevel(m.warn);
                    const wcls = warningClass(lvl);
                    const wText = m.warn && m.warn !== '-' ? m.warn : '0/3';
                    const avatar = avatarById[m.id];
                    return `
                        <div class="staff-card warn-card">
                            ${avatar
                                ? `<img class="staff-avatar staff-avatar-img" src="${avatar}" alt="">`
                                : `<div class="staff-avatar"><i class="fas ${cat.icon}"></i></div>`}
                            <div class="staff-info" style="flex:1;">
                                <div class="staff-name">${escapeHtml(m.name || m.nick || '—')}</div>
                                <div style="font-size:0.82rem;color:var(--text-secondary);margin-top:2px;">${escapeHtml(m.nick || '')}</div>
                                <div style="font-size:0.74rem;color:var(--text-muted);font-family:'Roboto Mono',monospace;margin-top:2px;word-break:break-all;">${escapeHtml(m.id || '')}</div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                                    ${m.date ? `<span class="staff-tag"><i class="fas fa-calendar" style="margin-right:4px;"></i>${escapeHtml(m.date)}</span>` : ''}
                                    ${m.days ? `<span class="staff-tag">${escapeHtml(m.days)} дн.</span>` : ''}
                                </div>
                            </div>
                            <div class="warn-right">
                                <div class="warn-badge ${wcls}" title="Выговоры">
                                    <div class="warn-num">${escapeHtml(wText)}</div>
                                    <div class="warn-label">выговоры</div>
                                </div>
                                ${canIssue ? `
                                    <button class="btn-issue-card"
                                            data-id="${escapeHtml(m.id || '')}"
                                            data-nick="${escapeHtml(m.nick || '')}"
                                            data-name="${escapeHtml(m.name || m.nick || '')}"
                                            data-cat="${catKey}"
                                            type="button">
                                        <i class="fas fa-plus"></i> Выдать
                                    </button>
                                    <button class="btn-justify-card ${lvl === 0 ? 'is-disabled' : ''}"
                                            data-id="${escapeHtml(m.id || '')}"
                                            data-name="${escapeHtml(m.name || m.nick || '')}"
                                            ${lvl === 0 ? 'disabled' : ''}
                                            type="button">
                                        <i class="fas fa-check"></i> Снять
                                    </button>
                                ` : ''}
                            </div>
                        </div>`;
                }).join('');
                return `
                    <div class="management-category ${cat.cls}">
                        <div class="category-header">
                            <i class="fas ${cat.icon}"></i>
                            <span class="category-title">${cat.label} <span style="opacity:0.6;font-weight:500;">(${members.length})</span></span>
                        </div>
                        <div class="members-grid">${cards}</div>
                    </div>`;
            }).join('');
            container.innerHTML = `<div class="management-list">${blocks || '<div class="placeholder"><h2>Пусто</h2></div>'}</div>`;
            // Привязка кнопок «Выдать» прямо на карточках
            container.querySelectorAll('.btn-issue-card').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (typeof openWarnIssueFor === 'function') {
                        openWarnIssueFor({
                            id: btn.dataset.id,
                            nick: btn.dataset.nick,
                            name: btn.dataset.name,
                            category: btn.dataset.cat
                        });
                    }
                });
            });
            // Привязка кнопок «Снять» — берём свежий активный выговор у этого человека
            container.querySelectorAll('.btn-justify-card').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (typeof openJustifyForTarget === 'function') {
                        openJustifyForTarget({ id: btn.dataset.id, name: btn.dataset.name });
                    }
                });
            });
        }

        loadWarnings();

        // === Выговоры: выдача / снятие / архив ===
        const canIssueWarning = currentRoleLevel() >= 3; // chief + admin
        const canDeleteWarning = currentRoleLevel() >= 4; // только admin
        let warnSheetTargets = []; // {id, nick, name, category}
        let archiveFilter = 'all';
        let allWarnings = [];
        let lastSheetSections = null;
        let lastAvatarById = {};

        async function fetchWarnings() {
            const r = await fetch('/api/warnings.php');
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const data = await r.json();
            allWarnings = data.items || [];
            // После любого обновления — пересчёт счёта в блоке «Текущий счёт из таблицы»
            if (lastSheetSections) renderWarnings(lastSheetSections, lastAvatarById);
            return allWarnings;
        }

        function warnRowHTML(w, opts = {}) {
            const status = w.status;
            const statusClass = status === 'active' ? 'status-active'
                              : status === 'justified' ? 'status-justified'
                              : 'status-expired';
            const statusLabel = status === 'active' ? 'Активен'
                              : status === 'justified' ? 'Снят' + (w.justified_by ? ' (' + escapeHtml(w.justified_by) + ')' : '')
                              : 'Истёк';
            const created = w.created_at ? new Date(w.created_at).toLocaleDateString('ru-RU') : '—';
            const expires = w.expires_at ? new Date(w.expires_at).toLocaleString('ru-RU') : '—';
            const justifyBtn = (opts.canJustify && status === 'active')
                ? `<button class="btn-warn-action btn-warn-justify" data-id="${w.id}" title="Снять"><i class="fas fa-check"></i></button>` : '';
            const deleteBtn = (opts.canDelete)
                ? `<button class="btn-warn-action btn-warn-delete" data-id="${w.id}" title="Удалить"><i class="fas fa-trash"></i></button>` : '';
            return `
                <div class="warn-row ${statusClass}">
                    <div class="warn-row-main">
                        <div class="warn-target">
                            <div class="warn-target-name">${escapeHtml(w.target_name || w.target_nick || '—')}</div>
                            <div class="warn-target-meta">
                                ${w.target_nick ? `<span>${escapeHtml(w.target_nick)}</span>` : ''}
                                ${w.target_id ? `<span class="mono">${escapeHtml(w.target_id)}</span>` : ''}
                                <span class="warn-cat-${escapeHtml(w.target_category)}">${w.target_category === 'master' ? 'Мастер' : w.target_category === 'curator' ? 'Куратор' : escapeHtml(w.target_category)}</span>
                            </div>
                        </div>
                        <div class="warn-reason">${escapeHtml(w.reason || '—')}</div>
                        <div class="warn-meta">
                            <div><i class="fas fa-clock"></i> ${w.duration_days} дн.</div>
                            <div><i class="fas fa-user-shield"></i> ${escapeHtml(w.issued_by || '—')}</div>
                            <div><i class="fas fa-calendar"></i> ${created}</div>
                            <div><i class="fas fa-hourglass-end"></i> ${expires}</div>
                        </div>
                    </div>
                    <div class="warn-row-side">
                        <span class="status-badge2 ${statusClass}">${statusLabel}</span>
                        <div class="warn-actions">
                            ${justifyBtn}
                            ${deleteBtn}
                        </div>
                    </div>
                </div>`;
        }

        async function renderWarningsLists() {
            try {
                const items = await fetchWarnings();

                // Архив с фильтром — показывает ВСЕ выговоры (активные/снятые/истёкшие)
                const aaList = document.getElementById('warnArchiveList');
                let shown = items;
                if (archiveFilter === 'active')         shown = items.filter(w => w.status === 'active');
                else if (archiveFilter === 'justified') shown = items.filter(w => w.status === 'justified');
                else if (archiveFilter === 'expired')   shown = items.filter(w => w.status === 'expired');
                document.getElementById('warnArchiveCount').textContent = shown.length;
                if (shown.length === 0) {
                    aaList.innerHTML = '<div class="reports-empty">Записей в архиве нет.</div>';
                } else {
                    aaList.innerHTML = shown
                        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
                        .map(w => warnRowHTML(w, { canJustify: false, canDelete: canDeleteWarning }))
                        .join('');
                }

                // Привязываем кнопки
                document.querySelectorAll('.btn-warn-justify').forEach(b => {
                    b.addEventListener('click', () => openJustify(+b.dataset.id));
                });
                document.querySelectorAll('.btn-warn-delete').forEach(b => {
                    b.addEventListener('click', async () => {
                        if (!confirm('Удалить выговор без следа?')) return;
                        await fetch('/api/warnings-delete.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: +b.dataset.id })
                        });
                        renderWarningsLists();
                    });
                });
            } catch (e) {
                console.error(e);
            }
        }

        // --- Модалка выдачи ---
        let warnIssueDuration = 7;
        let currentIssueTarget = null;
        function openWarnIssueFor(target) {
            currentIssueTarget = target;
            const info = document.getElementById('warnTargetInfo');
            info.innerHTML = `
                <div style="font-weight:800;color:#fff;font-size:1rem;">${escapeHtml(target.name || target.nick)}</div>
                <div style="font-size:0.82rem;color:var(--text-secondary);margin-top:2px;">${escapeHtml(target.nick || '')}</div>
                <div style="display:flex;gap:8px;margin-top:6px;align-items:center;">
                    <span class="warn-cat-${target.category}">${target.category === 'master' ? 'Мастер' : 'Куратор'}</span>
                    <span style="font-family:'Roboto Mono',monospace;font-size:0.72rem;color:var(--text-muted);">${escapeHtml(target.id || '')}</span>
                </div>`;
            document.getElementById('warnReasonInput').value = '';
            warnIssueDuration = 7;
            document.querySelectorAll('.warn-dur-btn').forEach(b =>
                b.classList.toggle('active', +b.dataset.days === 7));
            document.getElementById('warnIssueModal').classList.add('active');
        }
        function closeWarnIssue() {
            document.getElementById('warnIssueModal').classList.remove('active');
            currentIssueTarget = null;
        }
        document.getElementById('warnIssueCancel').addEventListener('click', closeWarnIssue);
        document.getElementById('warnIssueModal').addEventListener('click', e => {
            if (e.target.id === 'warnIssueModal') closeWarnIssue();
        });
        document.querySelectorAll('.warn-dur-btn').forEach(b => {
            b.addEventListener('click', () => {
                warnIssueDuration = +b.dataset.days;
                document.querySelectorAll('.warn-dur-btn').forEach(x => x.classList.toggle('active', x === b));
            });
        });
        document.getElementById('warnIssueSubmit').addEventListener('click', async () => {
            const reason = document.getElementById('warnReasonInput').value.trim();
            if (!currentIssueTarget) { alert('Не выбран пользователь'); return; }
            if (!reason) { alert('Укажи причину'); return; }
            const t = currentIssueTarget;
            const btn = document.getElementById('warnIssueSubmit');
            btn.disabled = true;
            try {
                const r = await fetch('/api/warnings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        target_id: t.id,
                        target_nick: t.nick,
                        target_name: t.name,
                        target_category: t.category,
                        reason,
                        duration_days: warnIssueDuration
                    })
                });
                let data;
                try {
                    data = await r.json();
                } catch (parseErr) {
                    alert('Сервер вернул некорректный ответ (HTTP ' + r.status + '). Попробуй ещё раз.');
                    return;
                }
                if (!r.ok) { alert('Ошибка: ' + (data.error || r.status)); return; }
                closeWarnIssue();
                renderWarningsLists();
            } catch (netErr) {
                alert('Не удалось связаться с сервером: ' + netErr.message);
            } finally {
                btn.disabled = false;
            }
        });

        // --- Модалка снятия ---
        let justifyTargetId = null;
        function openJustify(id) {
            justifyTargetId = id;
            // Подставим текст
            fetchWarnings().then(items => {
                const w = items.find(x => x.id === id);
                if (!w) return;
                document.getElementById('warnJustifyTarget').innerHTML =
                    `<b>${escapeHtml(w.target_name || w.target_nick)}</b> — ${escapeHtml(w.reason)}`;
                document.getElementById('warnJustifyReason').value = '';
                document.getElementById('warnJustifyModal').classList.add('active');
            });
        }
        async function openJustifyForTarget(target) {
            // Берём самый свежий АКТИВНЫЙ выговор у этого discord_id
            const items = await fetchWarnings();
            const active = items
                .filter(w => w.target_id === target.id && w.status === 'active')
                .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            if (active.length === 0) {
                alert('У ' + (target.name || 'этого пользователя') + ' нет активных выговоров.');
                return;
            }
            openJustify(active[0].id);
        }
        function closeJustify() { document.getElementById('warnJustifyModal').classList.remove('active'); justifyTargetId = null; }
        document.getElementById('warnJustifyCancel').addEventListener('click', closeJustify);
        document.getElementById('warnJustifyModal').addEventListener('click', e => {
            if (e.target.id === 'warnJustifyModal') closeJustify();
        });
        document.getElementById('warnJustifySubmit').addEventListener('click', async () => {
            if (!justifyTargetId) return;
            const reason = document.getElementById('warnJustifyReason').value.trim();
            try {
                const r = await fetch('/api/warnings-justify.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: justifyTargetId, reason })
                });
                let data;
                try {
                    data = await r.json();
                } catch (parseErr) {
                    alert('Сервер вернул некорректный ответ (HTTP ' + r.status + '). Попробуй ещё раз.');
                    return;
                }
                if (!r.ok) { alert('Ошибка: ' + (data.error || r.status)); return; }
                closeJustify();
                renderWarningsLists();
            } catch (netErr) {
                alert('Не удалось связаться с сервером: ' + netErr.message);
            }
        });

        // Фильтр архива
        document.querySelectorAll('#archiveFilter .rf-btn').forEach(b => {
            b.addEventListener('click', () => {
                archiveFilter = b.dataset.filter;
                document.querySelectorAll('#archiveFilter .rf-btn').forEach(x => x.classList.toggle('active', x === b));
                renderWarningsLists();
            });
        });

        // Когда таблица из Google Sheets загрузилась — сохраняем список целей для модалки
        // и накладываем «живой» счёт активных выговоров поверх значения из таблицы.
        const originalRenderWarnings = renderWarnings;
        renderWarnings = function(sections, avatarById) {
            lastSheetSections = sections;
            lastAvatarById = avatarById || lastAvatarById;
            warnSheetTargets = [];
            ['Curator', 'Master'].forEach(k => {
                (sections[k] || []).forEach(m => {
                    warnSheetTargets.push({
                        id: m.id, nick: m.nick, name: m.name,
                        category: k === 'Curator' ? 'curator' : 'master'
                    });
                    // Накладываем счёт из системы (если есть хоть один локальный выговор по этому id).
                    const localAll = allWarnings.filter(w => w.target_id === m.id);
                    if (localAll.length > 0) {
                        const active = localAll.filter(w => w.status === 'active').length;
                        m.warn = `${Math.min(active, 3)}/3`;
                    }
                });
            });
            originalRenderWarnings(sections, lastAvatarById);
        };

        renderWarningsLists();

        // --- Колесо фортуны ---
        const DEFAULT_WHEEL = [
            { name: 'Вариант 1', weight: 20, color: '#ff3b3b' },
            { name: 'Вариант 2', weight: 20, color: '#8b5cf6' },
            { name: 'Вариант 3', weight: 20, color: '#ec4899' },
            { name: 'Вариант 4', weight: 20, color: '#f59e0b' },
            { name: 'Вариант 5', weight: 20, color: '#10b981' }
        ];
        let wheelOptions = [];
        let wheelSpeed = 3;
        let wheelStartAngle = 0;
        let wheelAnimId = null;

        const wheelCanvas = document.getElementById('wheel-canvas');
        const wheelCtx = wheelCanvas ? wheelCanvas.getContext('2d') : null;
        const wheelOptsList = document.getElementById('options-list');
        const winnerBox = document.getElementById('winner-box');
        const winnerNameEl = document.getElementById('winner-name');
        const spinBtn = document.getElementById('spin-button');

        function loadWheel() {
            try {
                const data = JSON.parse(localStorage.getItem('wheel_options'));
                wheelOptions = Array.isArray(data) && data.length ? data : JSON.parse(JSON.stringify(DEFAULT_WHEEL));
            } catch { wheelOptions = JSON.parse(JSON.stringify(DEFAULT_WHEEL)); }
            wheelSpeed = +localStorage.getItem('wheel_speed') || 3;
            const sel = document.getElementById('wheelSpeed');
            if (sel) sel.value = wheelSpeed;
        }
        function saveWheel() { localStorage.setItem('wheel_options', JSON.stringify(wheelOptions)); }

        function drawWheel() {
            if (!wheelCtx) return;
            wheelCtx.clearRect(0, 0, 500, 500);
            const r = 230, cx = 250, cy = 250;
            const n = wheelOptions.length;
            if (n === 0) return;
            const arc = (Math.PI * 2) / n;
            for (let i = 0; i < n; i++) {
                const a = wheelStartAngle + i * arc;
                wheelCtx.fillStyle = wheelOptions[i].color;
                wheelCtx.beginPath();
                wheelCtx.moveTo(cx, cy);
                wheelCtx.arc(cx, cy, r, a, a + arc);
                wheelCtx.lineTo(cx, cy);
                wheelCtx.fill();

                wheelCtx.save();
                wheelCtx.fillStyle = '#fff';
                wheelCtx.translate(cx + Math.cos(a + arc/2) * r * 0.65, cy + Math.sin(a + arc/2) * r * 0.65);
                wheelCtx.rotate(a + arc/2 + Math.PI/2);
                wheelCtx.font = "bold 14px 'Space Grotesk', sans-serif";
                wheelCtx.shadowColor = 'rgba(0,0,0,0.5)';
                wheelCtx.shadowBlur = 4;
                let name = wheelOptions[i].name || '';
                if (name.length > 15) name = name.slice(0, 13) + '..';
                wheelCtx.fillText(name, -wheelCtx.measureText(name).width / 2, 0);
                wheelCtx.restore();
            }
            wheelCtx.beginPath();
            wheelCtx.arc(cx, cy, 45, 0, Math.PI * 2);
            wheelCtx.fillStyle = '#1a0505';
            wheelCtx.fill();
            wheelCtx.strokeStyle = 'rgba(255,255,255,0.18)';
            wheelCtx.lineWidth = 6;
            wheelCtx.stroke();
            wheelCtx.fillStyle = '#fff';
            wheelCtx.font = "bold 11px 'JetBrains Mono', monospace";
            wheelCtx.shadowBlur = 0;
            const t = 'FUTURAMA';
            wheelCtx.fillText(t, cx - wheelCtx.measureText(t).width / 2, cy + 4);
        }

        function renderWheelOptions() {
            if (!wheelOptsList) return;
            wheelOptsList.innerHTML = wheelOptions.map((opt, i) => `
                <div class="wheel-opt" data-i="${i}">
                    <input type="color" class="wo-color" value="${opt.color}">
                    <input type="text" class="wo-name" value="${escapeHtml(opt.name)}" placeholder="Название">
                    <input type="number" class="wo-weight" value="${opt.weight}" min="1" max="999" title="Вес / шанс">
                    <button class="wo-del" type="button" title="Удалить"><i class="fas fa-times"></i></button>
                </div>`).join('');
            wheelOptsList.querySelectorAll('.wheel-opt').forEach(row => {
                const i = +row.dataset.i;
                row.querySelector('.wo-color').addEventListener('input', e => { wheelOptions[i].color = e.target.value; saveWheel(); drawWheel(); });
                row.querySelector('.wo-name').addEventListener('input', e => { wheelOptions[i].name = e.target.value; saveWheel(); drawWheel(); });
                row.querySelector('.wo-weight').addEventListener('input', e => { wheelOptions[i].weight = Math.max(1, +e.target.value || 1); saveWheel(); });
                row.querySelector('.wo-del').addEventListener('click', () => { wheelOptions.splice(i, 1); saveWheel(); renderWheelOptions(); drawWheel(); });
            });
        }

        function spinWheel() {
            if (!wheelCanvas || wheelOptions.length === 0) return;
            if (winnerBox) winnerBox.classList.remove('show');
            spinBtn.disabled = true;

            let totalW = 0;
            wheelOptions.forEach(o => totalW += +o.weight);
            const rnd = Math.random() * totalW;
            let target = 0, cum = 0;
            for (let i = 0; i < wheelOptions.length; i++) {
                cum += +wheelOptions[i].weight;
                if (rnd <= cum) { target = i; break; }
            }
            const arc = (Math.PI * 2) / wheelOptions.length;

            let duration, rotations;
            if (wheelSpeed === 1)      { duration = 10000; rotations = 4; }
            else if (wheelSpeed === 2) { duration = 8000;  rotations = 5; }
            else if (wheelSpeed === 3) { duration = 6500;  rotations = 6; }
            else if (wheelSpeed === 4) { duration = 4000;  rotations = 8; }
            else                       { duration = 2000;  rotations = 10; }

            const targetAng = 1.5 * Math.PI - (target * arc + arc/2) + (Math.PI * 2 * rotations);
            const t0 = performance.now();
            const a0 = wheelStartAngle % (Math.PI * 2);

            function anim(now) {
                const p = Math.min((now - t0) / duration, 1);
                const ease = 1 - Math.pow(1 - p, 3);
                wheelStartAngle = a0 + ease * (targetAng - a0);
                drawWheel();
                if (p < 1) wheelAnimId = requestAnimationFrame(anim);
                else {
                    cancelAnimationFrame(wheelAnimId);
                    const win = wheelOptions[target];
                    if (winnerNameEl) winnerNameEl.textContent = win.name;
                    if (winnerBox) winnerBox.classList.add('show');
                    spinBtn.disabled = false;
                    if (typeof confetti === 'function') {
                        confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 },
                            colors: ['#ff3b3b', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b'] });
                    }
                }
            }
            wheelAnimId = requestAnimationFrame(anim);
        }

        if (wheelCanvas) {
            loadWheel();
            renderWheelOptions();
            drawWheel();
            spinBtn.addEventListener('click', spinWheel);
            document.getElementById('wheelAddBtn').addEventListener('click', () => {
                const palette = ['#ff3b3b', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#06b6d4', '#a3e635'];
                wheelOptions.push({ name: 'Новый вариант', weight: 20, color: palette[wheelOptions.length % palette.length] });
                saveWheel(); renderWheelOptions(); drawWheel();
            });
            document.getElementById('wheelResetBtn').addEventListener('click', () => {
                if (!confirm('Сбросить варианты к значениям по умолчанию?')) return;
                wheelOptions = JSON.parse(JSON.stringify(DEFAULT_WHEEL));
                saveWheel(); renderWheelOptions(); drawWheel();
            });
            document.getElementById('wheelSpeed').addEventListener('change', e => {
                wheelSpeed = +e.target.value;
                localStorage.setItem('wheel_speed', wheelSpeed);
            });
        }

        // --- Общая отчётность (агрегатор обоих собесов) ---
        const REPORT_SOURCES = [
            { key: 'moder_reports',  type: 'moder',  label: 'Собес на модера',  maxScore: 10, pass: 7,  hasVariants: true  },
            { key: 'master_reports', type: 'master', label: 'Собес на мастера', maxScore: 15, pass: 11, hasVariants: false }
        ];
        let currentReportsFilter = 'all';

        function loadAllReports() {
            const out = [];
            REPORT_SOURCES.forEach(src => {
                let arr = [];
                try { arr = JSON.parse(localStorage.getItem(src.key) || '[]'); } catch { arr = []; }
                arr.forEach((r, i) => out.push({ ...r, _src: src, _idx: i }));
            });
            return out.sort((a, b) => (b.ts || 0) - (a.ts || 0));
        }

        async function renderAllReports() {
            const list = document.getElementById('allReportsList');
            const count = document.getElementById('allReportsCount');
            if (!list) return;
            const reports = loadAllReports().map(r => ({ ...r, _kind: 'report' }));
            let events = [];
            try {
                const r = await fetch('/api/events');
                if (r.ok) {
                    const d = await r.json();
                    events = (d.items || []).map(e => ({ ...e, _kind: 'event' }));
                }
            } catch {}
            const all = [...reports, ...events].sort((a, b) => {
                const ta = a._kind === 'event' ? new Date(a.created_at).getTime() : (a.ts || 0);
                const tb = b._kind === 'event' ? new Date(b.created_at).getTime() : (b.ts || 0);
                return tb - ta;
            });
            let shown = all;
            if (currentReportsFilter === 'events') shown = all.filter(x => x._kind === 'event');
            else if (currentReportsFilter !== 'all') shown = all.filter(x => x._kind === 'report' && x._src.type === currentReportsFilter);
            if (count) count.textContent = shown.length;
            if (shown.length === 0) {
                list.innerHTML = '<div class="reports-empty">Отчётов пока нет.</div>';
                return;
            }
            list.innerHTML = shown.map(r => {
                if (r._kind === 'event') {
                    const dt = fmtEventDate(r.date) + (r.time ? ' · ' + r.time : '');
                    const winnerInfo = r.format === 'team5v5' && r.winner
                        ? `Команда ${r.winner}`
                        : (r.first ? `1: ${r.first}` : '—');
                    const participantsHtml = r.format === 'team5v5' ? `
                        <div class="event-teams-display" style="margin-top:0.7rem;">
                            <div class="event-team-display ${r.winner === 1 ? 'is-winner' : ''}">
                                <div class="etd-head">Команда 1 ${r.winner === 1 ? '<span class="etd-win-tag">Победа</span>' : ''}</div>
                                <ul>${(r.team1 || []).map(p => `<li>${escapeHtml(p)}</li>`).join('') || '<li style="opacity:0.5;">—</li>'}</ul>
                            </div>
                            <div class="event-team-display ${r.winner === 2 ? 'is-winner' : ''}">
                                <div class="etd-head">Команда 2 ${r.winner === 2 ? '<span class="etd-win-tag">Победа</span>' : ''}</div>
                                <ul>${(r.team2 || []).map(p => `<li>${escapeHtml(p)}</li>`).join('') || '<li style="opacity:0.5;">—</li>'}</ul>
                            </div>
                        </div>` : `
                        <div class="event-podium-display" style="margin-top:0.7rem;">
                            ${r.first  ? `<div class="evp-line evp-1"><b>1</b> ${escapeHtml(r.first)}</div>` : ''}
                            ${r.second ? `<div class="evp-line evp-2"><b>2</b> ${escapeHtml(r.second)}</div>` : ''}
                            ${r.third  ? `<div class="evp-line evp-3"><b>3</b> ${escapeHtml(r.third)}</div>` : ''}
                            ${(r.participants && r.participants.length) ? `<div class="evp-others">Участники: ${r.participants.map(escapeHtml).join(', ')}</div>` : ''}
                        </div>`;
                    return `
                        <div class="report-card r-event">
                            <div class="report-row">
                                <div class="report-main">
                                    <div class="report-nick">
                                        ${escapeHtml(r.type_label || 'Ивент')}
                                        <span class="report-type rt-event">Ивент</span>
                                    </div>
                                    <div class="report-id">Победитель: ${escapeHtml(winnerInfo)}</div>
                                </div>
                                <button class="report-del" data-event-id="${r.id}" title="Удалить"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="report-info">
                                <span><i class="fas fa-calendar"></i> ${escapeHtml(dt) || '—'}</span>
                                <span><i class="fas fa-user-shield"></i> ${escapeHtml(r.organizer || '—')}</span>
                            </div>
                            ${participantsHtml}
                            ${r.notes ? `<div class="event-card-notes" style="margin-top:0.7rem;">${escapeHtml(r.notes)}</div>` : ''}
                        </div>`;
                }
                const passed = r.score >= r._src.pass;
                return `
                    <div class="report-card ${passed ? 'r-passed' : 'r-failed'}">
                        <div class="report-row">
                            <div class="report-main">
                                <div class="report-nick">
                                    ${escapeHtml(r.nick || '—')}
                                    <span class="report-type rt-${r._src.type}">${r._src.label}</span>
                                </div>
                                <div class="report-id">ID: ${escapeHtml(r.id || '—')}</div>
                            </div>
                            <div class="report-score ${passed ? 'pass' : 'fail'}">
                                ${r.score} / ${r._src.maxScore}
                                <span class="report-verdict">${passed ? 'Сдал' : 'Не сдал'}</span>
                            </div>
                            <button class="report-del" data-src="${r._src.key}" data-i="${r._idx}" title="Удалить"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="report-info">
                            <span><i class="fas fa-calendar"></i> ${r.date}</span>
                            <span><i class="fas fa-user-shield"></i> ${escapeHtml(r.reviewer || '—')}</span>
                            ${r._src.hasVariants ? `<span><i class="fas fa-layer-group"></i> Вариант ${r.variant}</span>` : ''}
                        </div>
                    </div>`;
            }).join('');

            list.querySelectorAll('.report-del').forEach(b => {
                b.addEventListener('click', async () => {
                    // Ивент?
                    if (b.dataset.eventId) {
                        if (!confirm('Удалить ивент?')) return;
                        await fetch('/api/events/delete', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: +b.dataset.eventId })
                        });
                        renderAllReports();
                        if (typeof renderEventsList === 'function') renderEventsList();
                        return;
                    }
                    // Иначе — обычный отчёт собеса
                    if (!confirm('Удалить отчёт?')) return;
                    const srcKey = b.dataset.src;
                    const idx = +b.dataset.i;
                    let arr = [];
                    try { arr = JSON.parse(localStorage.getItem(srcKey) || '[]'); } catch { arr = []; }
                    arr.splice(idx, 1);
                    localStorage.setItem(srcKey, JSON.stringify(arr));
                    renderAllReports();
                    if (typeof moderInterview === 'object') moderInterview.renderReports();
                    if (typeof masterInterview === 'object') masterInterview.renderReports();
                });
            });
        }

        document.querySelectorAll('#reportsFilter .rf-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                currentReportsFilter = btn.dataset.filter;
                document.querySelectorAll('#reportsFilter .rf-btn').forEach(b => b.classList.toggle('active', b === btn));
                renderAllReports();
            });
        });

        renderAllReports();

    </script>
</body>
</html>
