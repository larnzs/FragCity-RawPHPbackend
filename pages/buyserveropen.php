<?php
/**
 * FragCity CS2 server storefront.
 *
 * Checkout values mirror the FragCityCS2 Paymenter manifest. This page collects
 * a local configuration preview only; order creation and provisioning remain
 * disabled until the signed server-order backend is implemented.
 */
$serverStoreGameModes = [
    ['label' => 'Casual — casual.cfg', 'config' => 'casual.cfg', 'map_groups' => ['mg_active', 'mg_comp']],
    ['label' => 'Gun Game — gg.cfg', 'config' => 'gg.cfg', 'map_groups' => ['mg_gg']],
    ['label' => 'Deathmatch — deathmatch.cfg', 'config' => 'deathmatch.cfg', 'map_groups' => ['mg_dm']],
    ['label' => 'Competitive — comp.cfg', 'config' => 'comp.cfg', 'map_groups' => ['mg_comp']],
    ['label' => 'Wingman — wingman.cfg', 'config' => 'wingman.cfg', 'map_groups' => ['mg_wingman']],
    ['label' => 'Practice Mode — prac.cfg', 'config' => 'prac.cfg', 'map_groups' => ['mg_comp']],
    ['label' => 'Prefire — prefire.cfg', 'config' => 'prefire.cfg', 'map_groups' => ['mg_prefire']],
    ['label' => 'Retakes — retake.cfg', 'config' => 'retake.cfg', 'map_groups' => ['mg_retake']],
    ['label' => 'Executes — executes.cfg', 'config' => 'executes.cfg', 'map_groups' => ['mg_executes']],
    ['label' => 'Awp Only — awp.cfg', 'config' => 'awp.cfg', 'map_groups' => ['mg_awp']],
    ['label' => '1v1 Arenas — 1v1.cfg', 'config' => '1v1.cfg', 'map_groups' => ['mg_1v1']],
    ['label' => 'Aim — aim.cfg', 'config' => 'aim.cfg', 'map_groups' => ['mg_aim']],
    ['label' => 'Bhop — bhop.cfg', 'config' => 'bhop.cfg', 'map_groups' => ['mg_bhop']],
    ['label' => 'Surf — surf.cfg', 'config' => 'surf.cfg', 'map_groups' => ['mg_surf']],
    ['label' => 'Kreedz Climbing — kz.cfg', 'config' => 'kz.cfg', 'map_groups' => ['mg_kz']],
    ['label' => 'Capture The Flag — ctf.cfg', 'config' => 'ctf.cfg', 'map_groups' => ['mg_ctf']],
    ['label' => 'Hide N Seek — hns.cfg', 'config' => 'hns.cfg', 'map_groups' => ['mg_hns']],
    ['label' => 'Soccer — soccer.cfg', 'config' => 'soccer.cfg', 'map_groups' => ['mg_soccer']],
    ['label' => 'Course — course.cfg', 'config' => 'course.cfg', 'map_groups' => ['mg_course']],
    ['label' => 'Deathrun — deathrun.cfg', 'config' => 'deathrun.cfg', 'map_groups' => ['mg_deathrun']],
    ['label' => 'Minigames — minigames.cfg', 'config' => 'minigames.cfg', 'map_groups' => ['mg_minigames']],
    ['label' => 'ScoutzKnivez — scoutzknivez.cfg', 'config' => 'scoutzknivez.cfg', 'map_groups' => ['mg_scoutzknivez']],
    ['label' => 'One In The Chamber — oitc.cfg', 'config' => 'oitc.cfg', 'map_groups' => ['mg_gg']],
    ['label' => 'Battle Ball — battle.cfg', 'config' => 'battle.cfg', 'map_groups' => ['mg_battle']],
    ['label' => 'Battle Royale — br.cfg', 'config' => 'br.cfg', 'map_groups' => ['mg_battleroyale']],
    ['label' => 'Casual (1.6) — Casual-1.6.cfg', 'config' => 'Casual-1.6.cfg', 'map_groups' => ['mg_casual-1.6']],
    ['label' => 'Competitive (Valve) — valve-competitive.cfg', 'config' => 'valve-competitive.cfg', 'map_groups' => ['mg_valve_competitive']],
    ['label' => 'Deathmatch FFA (Valve) — valve-deathmatch-freeforall.cfg', 'config' => 'valve-deathmatch-freeforall.cfg', 'map_groups' => ['mg_valve_deathmatch']],
    ['label' => 'Arms Race (Valve) — valve-armsrace.cfg', 'config' => 'valve-armsrace.cfg', 'map_groups' => ['mg_valve_arms_race']],
    ['label' => 'Wingman (Valve) — valve-wingman.cfg', 'config' => 'valve-wingman.cfg', 'map_groups' => ['mg_valve_wingman']],
    ['label' => 'Casual Workshop — casualw.cfg', 'config' => 'casualw.cfg', 'map_groups' => ['mg_casualw']],
    ['label' => 'Casual Active — casual-active.cfg', 'config' => 'casual-active.cfg', 'map_groups' => ['mg_casuala']],
    ['label' => 'Retakes (Valve) — valve-retake.cfg', 'config' => 'valve-retake.cfg', 'map_groups' => ['mg_valve_retakes']],
    ['label' => 'Competitive 45° — 45.cfg', 'config' => '45.cfg', 'map_groups' => ['mg_45']],
];

$serverStoreMapGroupLabels = [
    'mg_active' => 'Casual — mg_active',
    'mg_comp' => 'Casual / Competitive / Practice Mode — mg_comp',
    'mg_gg' => 'Gun Game / One In The Chamber — mg_gg',
    'mg_dm' => 'Deathmatch — mg_dm',
    'mg_wingman' => 'Wingman — mg_wingman',
    'mg_prefire' => 'Prefire — mg_prefire',
    'mg_retake' => 'Retakes — mg_retake',
    'mg_executes' => 'Executes — mg_executes',
    'mg_awp' => 'Awp Only — mg_awp',
    'mg_1v1' => '1v1 Arenas — mg_1v1',
    'mg_aim' => 'Aim — mg_aim',
    'mg_bhop' => 'Bhop — mg_bhop',
    'mg_surf' => 'Surf — mg_surf',
    'mg_kz' => 'Kreedz Climbing — mg_kz',
    'mg_ctf' => 'Capture The Flag — mg_ctf',
    'mg_hns' => 'Hide N Seek — mg_hns',
    'mg_soccer' => 'Soccer — mg_soccer',
    'mg_course' => 'Course — mg_course',
    'mg_deathrun' => 'Deathrun — mg_deathrun',
    'mg_minigames' => 'Minigames — mg_minigames',
    'mg_scoutzknivez' => 'ScoutzKnivez — mg_scoutzknivez',
    'mg_battle' => 'Battle Ball — mg_battle',
    'mg_battleroyale' => 'Battle Royale — mg_battleroyale',
    'mg_casual-1.6' => 'Casual (1.6) — mg_casual-1.6',
    'mg_valve_competitive' => 'Competitive (Valve) — mg_valve_competitive',
    'mg_valve_deathmatch' => 'Deathmatch FFA (Valve) — mg_valve_deathmatch',
    'mg_valve_arms_race' => 'Arms Race (Valve) — mg_valve_arms_race',
    'mg_valve_wingman' => 'Wingman (Valve) — mg_valve_wingman',
    'mg_casualw' => 'Casual Workshop — mg_casualw',
    'mg_casuala' => 'Casual Active — mg_casuala',
    'mg_valve_retakes' => 'Retakes (Valve) — mg_valve_retakes',
    'mg_45' => 'Competitive 45° — mg_45',
];

$serverStoreMapGroupModes = [];
foreach ($serverStoreGameModes as $serverStoreMode) {
    foreach ($serverStoreMode['map_groups'] as $serverStoreMapGroup) {
        $serverStoreMapGroupModes[$serverStoreMapGroup][] = $serverStoreMode['config'];
    }
}
$serverStoreDefaultConfig = 'comp.cfg';
$serverStoreDefaultMapGroup = 'mg_comp';

$serverStoreDefaultPlayers = 10;
$serverStoreDurations = [
    ['days' => 1, 'label' => '۱ روزه', 'caption' => '۲۴ ساعت'],
    ['days' => 7, 'label' => '۱ هفته', 'caption' => '۷ روز'],
    ['days' => 15, 'label' => '۱۵ روزه', 'caption' => 'نیم‌ماه'],
    ['days' => 30, 'label' => '۳۰ روزه', 'caption' => 'یک ماه', 'recommended' => true],
];
?>
<main class="server-store-page" dir="rtl" data-api-url="/api/server-store.php" data-authenticated="<?= (($_SESSION['user_logged_in'] ?? false) === true) ? '1' : '0' ?>" data-csrf-token="<?= htmlspecialchars((string) ($authCsrfToken ?? $_SESSION['auth_csrf_token'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="server-store-container">
        <header class="server-store-hero" data-fc-reveal="up">
            <div class="server-store-hero-main">
                <div class="server-store-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-server"></i>
                </div>
                <div class="server-store-hero-copy">
                    <span class="server-store-eyebrow" dir="ltr">FRAGCITY / GAME HOSTING</span>
                    <h1>سرور بازی خودت رو بساز</h1>
                    <p>بازی موردنظرت را انتخاب کن و منابع سرورت را متناسب با کامیونیتی خودت تنظیم کن.</p>
                    <div class="server-store-hero-tags">
                        <span><i class="fa-solid fa-users" aria-hidden="true"></i> ۵ تا ۶۴ اسلات</span>
                        <span><i class="fa-solid fa-gamepad" aria-hidden="true"></i> همه مودها در یک سرویس</span>
                        <span><i class="fa-solid fa-sliders" aria-hidden="true"></i> منابع قابل تنظیم</span>
                    </div>
                </div>
            </div>

            <div class="server-store-hero-product">
                <span class="server-store-product-kicker">YOUR GAME SERVER</span>
                <strong>Counter-Strike Servers</strong>
                <span class="server-store-product-status"><i class="fa-solid fa-circle" aria-hidden="true"></i> CS2 فعال · CS 1.6 به‌زودی</span>
                <div class="server-store-product-mark" aria-hidden="true">
                    <i class="fa-solid fa-crosshairs"></i>
                </div>
            </div>
        </header>

        <div class="server-store-game-tabs" role="tablist" aria-label="انتخاب بازی">
            <button type="button" class="server-store-game-tab is-active" id="serverStoreTabCs2" role="tab" aria-selected="true" aria-controls="serverStorePanelCs2" tabindex="0" data-server-game-tab="cs2">
                <i class="fa-solid fa-crosshairs" aria-hidden="true"></i>
                <span>Counter-Strike 2</span>
            </button>
            <button type="button" class="server-store-game-tab" id="serverStoreTabCs16" role="tab" aria-selected="false" aria-controls="serverStorePanelCs16" tabindex="-1" data-server-game-tab="cs16">
                <i class="fa-solid fa-gamepad" aria-hidden="true"></i>
                <span>Counter-Strike 1.6</span>
            </button>
        </div>
        <div class="server-store-game-panel" id="serverStorePanelCs2" role="tabpanel" aria-labelledby="serverStoreTabCs2" data-server-game-panel="cs2">
        <div class="server-store-layout">
            <div class="server-store-main">
                <form id="serverStoreConfigForm" class="server-store-config-form" autocomplete="off">
                    <section class="server-store-section server-store-resources-section" aria-labelledby="serverResourcesTitle" data-fc-reveal="up" data-fc-reveal-delay="20">
                        <div class="server-store-section-heading">
                            <span class="server-store-section-index">01</span>
                            <div>
                                <h2 id="serverResourcesTitle">ظرفیت و منابع سرور</h2>
                                <p>RAM، SSD، CPU و ظرفیت بازیکن را انتخاب کن؛ قیمت قطعی مستقیماً از Paymenter استعلام می‌شود.</p>
                            </div>
                            <span class="server-store-section-state">۳ منبع قابل انتخاب</span>
                        </div>

                        <div class="server-store-resource-grid">


                            <article class="server-store-resource-card">
                                <div class="server-store-resource-head">
                                    <span class="server-store-resource-icon"><i class="fa-solid fa-memory" aria-hidden="true"></i></span>
                                    <div class="server-store-resource-copy">
                                        <small>RAM</small>
                                        <strong><output id="serverStoreMemoryValue" for="serverStoreMemory">1 GB</output></strong>
                                        <span>DDR5 · 6000 MHz · CL30</span>
                                    </div>
                                </div>
                                <div class="server-store-range-wrap">
                                    <label class="server-store-resource-slider-label" for="serverStoreMemory">حافظه RAM</label>
                                    <input class="server-store-range" id="serverStoreMemory" name="memory" type="range" min="1024" max="1536" step="256" value="1024" aria-describedby="serverStoreMemoryHelp">
                                    <div class="server-store-range-meta" aria-hidden="true"><span>1 GB</span><span>1.5 GB</span></div>
                                    <small class="server-store-range-help" id="serverStoreMemoryHelp">مقادیر مجاز و قیمت هر سطح از گزینه‌های پیکربندی Paymenter خوانده و هنگام ثبت سفارش دوباره بررسی می‌شود.</small>
                                </div>
                            </article>

                            <article class="server-store-resource-card">
                                <div class="server-store-resource-head">
                                    <span class="server-store-resource-icon"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i></span>
                                    <div class="server-store-resource-copy">
                                        <small>SSD STORAGE</small>
                                        <strong><output id="serverStoreDiskValue" for="serverStoreDisk">10 GB</output></strong>
                                        <span>فضای ذخیره‌سازی</span>
                                    </div>
                                </div>
                                <div class="server-store-range-wrap">
                                    <label class="server-store-resource-slider-label" for="serverStoreDisk">فضای دیسک</label>
                                    <input class="server-store-range" id="serverStoreDisk" name="disk" type="range" min="5120" max="10240" step="1024" value="10240" aria-describedby="serverStoreDiskHelp">
                                    <div class="server-store-range-meta" aria-hidden="true"><span>5 GB</span><span>10 GB</span></div>
                                    <small class="server-store-range-help" id="serverStoreDiskHelp">سقف فضای مجاز برای فایل‌ها و داده‌های سرور.</small>
                                </div>
                            </article>

                            <article class="server-store-resource-card">
                                <div class="server-store-resource-head">
                                    <span class="server-store-resource-icon"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span>
                                    <div class="server-store-resource-copy">
                                        <small>CPU</small>
                                        <strong><output id="serverStoreCpuValue" for="serverStoreCpu">100% · 1 vCore</output></strong>
                                        <span>AMD Ryzen 9 7950X · 5.7 GHz</span>
                                    </div>
                                </div>
                                <div class="server-store-range-wrap">
                                    <label class="server-store-resource-slider-label" for="serverStoreCpu">قدرت پردازنده</label>
                                    <input class="server-store-range" id="serverStoreCpu" name="cpu" type="range" min="100" max="400" step="100" value="100" aria-describedby="serverStoreCpuHelp">
                                    <div class="server-store-range-meta" aria-hidden="true"><span>100%</span><span>400%</span></div>
                                    <small class="server-store-range-help" id="serverStoreCpuHelp">هر ۱۰۰٪ سقف مصرف معادل یک هسته است؛ این مقدار به معنی رزرو یک هسته فیزیکی نیست.</small>
                                </div>
                            </article>
                        </div>
                        <div class="server-store-resource-footnote">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            RAM و Storage به مگابایت و CPU به درصد بیان می‌شوند. قیمت هر سه منبع RAM، Storage و CPU در Quote رسمی Paymenter لحاظ می‌شود؛ هیچ قیمت محلی در مرورگر محاسبه نمی‌شود.
                        </div>
                    </section>

                    <section class="server-store-section server-store-name-section" aria-labelledby="serverNameTitle" data-fc-reveal="up" data-fc-reveal-delay="40">
                        <div class="server-store-section-heading">
                            <span class="server-store-section-index">02</span>
                            <div>
                                <h2 id="serverNameTitle">نام سرور</h2>
                                <p>برای شناسایی راحت‌تر سرورت در پنل، یک نام انتخاب کن.</p>
                            </div>
                            <span class="server-store-section-state">الزامی</span>
                        </div>

                        <div class="server-store-field-grid">
                            <div class="server-store-field server-store-field--wide">
                                <label for="serverStoreName"><i class="fa-solid fa-tag" aria-hidden="true"></i> نام نمایشی سرور <span class="server-store-required" aria-hidden="true">*</span></label>
                                <input
                                    class="server-store-input"
                                    id="serverStoreName"
                                    name="server_name"
                                    type="text"
                                    value=""
                                    placeholder="My CS2 Server"
                                    pattern="[A-Za-z0-9 ]+"
                                    required
                                    aria-describedby="serverStoreNameHelp"
                                    autocomplete="off"
                                >
                                <small id="serverStoreNameHelp">پیشنهاد نام: FragCity Public یا My Community. فقط حروف انگلیسی، اعداد و فاصله مجاز است. این نام فقط برای شناسایی سرور در فهرست «سرورهای من» استفاده می‌شود و بعد از خرید می‌توانی نام دلخواهت را از بخش تنظیمات سرور تغییر بدهی.</small>
                            </div>
                        </div>
                    </section>

                    <section class="server-store-section server-store-config-section" aria-labelledby="serverGameModeTitle" data-fc-reveal="up" data-fc-reveal-delay="80">
                        <div class="server-store-section-heading">
                            <span class="server-store-section-index">03</span>
                            <div>
                                <h2 id="serverGameModeTitle">تنظیمات گیم‌سرور</h2>
                                <p>تمام مقادیر موردنیاز Egg در Paymenter را یکجا تنظیم کن؛ ظرفیت بازیکن هم در همین بخش قرار دارد.</p>
                            </div>
                            <span class="server-store-section-state">PAYMENTER EGG</span>
                        </div>

                        <div class="server-store-egg-settings-panel">
                            <div class="server-store-field-grid server-store-field-grid--egg">
                            <article
                                class="server-store-resource-card server-store-resource-card--capacity server-store-capacity-panel server-store-egg-capacity-card"
                            >
                                <div class="server-store-resource-head">
                                    <span class="server-store-resource-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                                    <div class="server-store-resource-copy">
                                        <small>PLAYER SLOTS</small>
                                        <strong><output id="serverStorePlayersValue" for="serverStorePlayers" aria-live="polite"><?= (int) $serverStoreDefaultPlayers ?></output> اسلات</strong>
                                        <span>ظرفیت اتصال هم‌زمان</span>
                                    </div>
                                </div>
                                <div class="server-store-range-wrap">
                                    <label class="server-store-resource-slider-label" for="serverStorePlayers">تعداد بازیکن</label>
                                    <input
                                        class="server-store-range server-store-capacity-range"
                                        id="serverStorePlayers"
                                        name="SRCDS_MAXPLAYERS"
                                        type="range"
                                        min="5"
                                        max="64"
                                        step="1"
                                        value="<?= (int) $serverStoreDefaultPlayers ?>"
                                        aria-describedby="serverStorePlayersHelp"
                                    >
                                    <div class="server-store-range-meta" aria-hidden="true"><span>5</span><span>64</span></div>
                                </div>
                                <div class="server-store-capacity-presets" role="group" aria-label="انتخاب سریع ظرفیت">
                                    <button type="button" data-player-preset="5" aria-pressed="false">5</button>
                                    <button type="button" data-player-preset="10" aria-pressed="true">10</button>
                                    <button type="button" data-player-preset="16" aria-pressed="false">16</button>
                                    <button type="button" data-player-preset="20" aria-pressed="false">20</button>
                                    <button type="button" data-player-preset="24" aria-pressed="false">24</button>
                                    <button type="button" data-player-preset="32" aria-pressed="false">32</button>
                                    <button type="button" data-player-preset="48" aria-pressed="false">48</button>
                                    <button type="button" data-player-preset="64" aria-pressed="false">64</button>
                                </div>
                                <small class="server-store-range-help" id="serverStorePlayersHelp">ظرفیت مجاز ۵ تا ۶۴ بازیکن است؛ قیمت دقیق این گزینه از Paymenter محاسبه می‌شود.</small>
                            </article>
                                <div class="server-store-field">
                                    <label for="serverStoreMap"><i class="fa-solid fa-map" aria-hidden="true"></i> نقشه پیش‌فرض <span class="server-store-required" aria-hidden="true">*</span></label>
                                    <input
                                        class="server-store-input"
                                        id="serverStoreMap"
                                        name="SRCDS_MAP"
                                        type="text"
                                        value="de_dust2"
                                        placeholder="de_dust2"
                                        pattern="[A-Za-z0-9_-]+"
                                        maxlength="64"
                                        required
                                        aria-describedby="serverStoreMapHelp"
                                    >
                                    <small id="serverStoreMapHelp">نام نقشه‌ای که سرور پس از راه‌اندازی با آن بالا می‌آید؛ مانند de_dust2 یا de_mirage.</small>
                                </div>

                                <div class="server-store-field server-store-field--compact">
                                    <label for="serverStoreTickrate"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Tickrate سرور</label>
                                    <select class="server-store-select" id="serverStoreTickrate" name="TICKRATE" required aria-describedby="serverStoreTickrateHelp">
                                        <option value="64" selected>64 Tick · Subtick</option>
                                        <option value="128">128 Tick · رایگان</option>
                                    </select>
                                    <small id="serverStoreTickrateHelp">هر دو گزینه ۶۴ و ۱۲۸ فعلاً رایگان هستند. مقدار انتخابی از طریق آرگومان -tickrate در دستور Startup به CS2 ارسال می‌شود.</small>
                                </div>

                                <div class="server-store-field">
                                    <label for="serverStoreExec"><i class="fa-solid fa-gamepad" aria-hidden="true"></i> حالت بازی (Game Mode CFG) <span class="server-store-required" aria-hidden="true">*</span></label>
                                    <select class="server-store-select" id="serverStoreExec" name="EXEC" required aria-describedby="serverStoreExecHelp">
                                        <?php foreach ($serverStoreGameModes as $serverStoreMode): ?>
                                            <option
                                                value="<?= htmlspecialchars($serverStoreMode['config'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-map-groups="<?= htmlspecialchars(implode('|', $serverStoreMode['map_groups']), ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $serverStoreMode['config'] === $serverStoreDefaultConfig ? 'selected' : '' ?>
                                            ><?= htmlspecialchars($serverStoreMode['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="serverStoreExecHelp">فایل CFG مربوط به GameModeManager؛ فقط مودهای تعریف‌شده در GameModes قابل انتخاب‌اند.</small>
                                </div>

                                <div class="server-store-field">
                                    <label for="serverStoreMapgroup"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> گروه نقشه (MAPGROUP) <span class="server-store-required" aria-hidden="true">*</span></label>
                                    <select class="server-store-select" id="serverStoreMapgroup" name="MAPGROUP" required aria-describedby="serverStoreMapgroupHelp">
                                        <?php foreach ($serverStoreMapGroupLabels as $serverStoreMapGroupValue => $serverStoreMapGroupLabel): ?>
                                            <option
                                                value="<?= htmlspecialchars($serverStoreMapGroupValue, ENT_QUOTES, 'UTF-8') ?>"
                                                data-game-modes="<?= htmlspecialchars(implode('|', $serverStoreMapGroupModes[$serverStoreMapGroupValue] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $serverStoreMapGroupValue === $serverStoreDefaultMapGroup ? 'selected' : '' ?>
                                            ><?= htmlspecialchars($serverStoreMapGroupLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="serverStoreMapgroupHelp">شناسه Map Group مرتبط با مود بازی؛ گزینه‌ها براساس CFG انتخاب‌شده فیلتر می‌شوند.</small>
                                </div>

                                <div class="server-store-field">
                                    <label for="serverStoreCooldown"><i class="fa-solid fa-clock" aria-hidden="true"></i> اخراج بازیکنان دارای Cooldown <span class="server-store-required" aria-hidden="true">*</span></label>
                                    <select class="server-store-select" id="serverStoreCooldown" name="COOLDOWN" required aria-describedby="serverStoreCooldownHelp">
                                        <option value="0" selected>غیرفعال</option>
                                        <option value="1">فعال</option>
                                    </select>
                                    <small id="serverStoreCooldownHelp">معادل sv_kick_players_with_cooldown.</small>
                                </div>

                                <div class="server-store-field">
                                    <label for="serverStoreGuard"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> فعال‌سازی محافظ Bot Guard <span class="server-store-required" aria-hidden="true">*</span></label>
                                    <select class="server-store-select" id="serverStoreGuard" name="ENABLE_GUARD" required aria-describedby="serverStoreGuardHelp">
                                        <option value="0" selected>غیرفعال</option>
                                        <option value="1" disabled>فعلاً در دسترس نیست — تعرفه تعریف نشده</option>
                                    </select>
                                    <small id="serverStoreGuardHelp">این افزونه تا زمان تعریف قیمت رسمی در Paymenter قابل خرید نیست و فعلاً غیرفعال می‌ماند.</small>
                                </div>

                                <div class="server-store-field" id="serverStoreGuardMinutesField" hidden>
                                    <label for="serverStoreGuardMinutes"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> مدت مسدودسازی Bot Guard (دقیقه)</label>
                                    <input
                                        class="server-store-input"
                                        id="serverStoreGuardMinutes"
                                        name="GUARD_BAN_MINUTES"
                                        type="number"
                                        min="1"
                                        step="1"
                                        value="60"
                                        inputmode="numeric"
                                        disabled
                                        aria-describedby="serverStoreGuardMinutesHelp"
                                    >
                                    <small id="serverStoreGuardMinutesHelp">مدت مسدودسازی در صورت انتخاب رفتار Kick یا Block توسط مدیر محصول. فقط در صورت فعال بودن Bot Guard کاربرد دارد.</small>
                                </div>
                            </div>

                            <div class="server-store-config-note" role="note">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                پیکربندی و قیمت پیش از پرداخت توسط Paymenter اعتبارسنجی می‌شود. Bot Guard تا زمان تعریف تعرفه رسمی در Paymenter غیرفعال است.
                            </div>
                        </div>
                    </section>

                    <section class="server-store-section server-store-duration-section" aria-labelledby="serverDurationTitle" data-fc-reveal="up" data-fc-reveal-delay="140">
                        <div class="server-store-section-heading">
                            <span class="server-store-section-index">04</span>
                            <div>
                                <h2 id="serverDurationTitle">مدت سرویس</h2>
                                <p>هرچه مدت اشتراک بیشتر باشد، هزینه مؤثر هر ماه کمتر می‌شود.</p>
                            </div>
                            <span class="server-store-section-state">۱ روز تا ۳۰ روز</span>
                        </div>

                        <div class="server-store-duration-grid" role="radiogroup" aria-label="مدت اعتبار سرور">
                            <?php foreach ($serverStoreDurations as $serverStoreDuration): ?>
                                <?php
                                $serverStoreDurationDays = (int) $serverStoreDuration['days'];
                                ?>
                                <label class="server-store-duration-card<?= !empty($serverStoreDuration['recommended']) ? ' server-store-duration-card--recommended' : '' ?><?= !empty($serverStoreDuration['best_value']) ? ' server-store-duration-card--best-value' : '' ?>">
                                    <input
                                        type="radio"
                                        name="duration_days"
                                        value="<?= $serverStoreDurationDays ?>"
                                        data-duration-label="<?= htmlspecialchars($serverStoreDuration['label'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-discount-percent="<?= (int) ($serverStoreDuration['discount_percent'] ?? 0) ?>"
                                        <?= $serverStoreDurationDays === 30 ? 'checked' : '' ?>
                                    >
                                    <span class="server-store-duration-card__top">
                                        <strong><?= htmlspecialchars($serverStoreDuration['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if (!empty($serverStoreDuration['discount_percent'])): ?>
                                            <em><?= (int) $serverStoreDuration['discount_percent'] ?>٪ تخفیف</em>
                                        <?php elseif (!empty($serverStoreDuration['recommended'])): ?>
                                            <em>پیشنهادی</em>
                                        <?php endif; ?>
                                    </span>
                                    <strong
                                        class="server-store-duration-card__price"
                                        data-duration-price="<?= $serverStoreDurationDays ?>"
                                        data-price-factor="<?= $serverStoreDurationFactorAttribute ?>"
                                    >برای مشاهده قیمت انتخاب کنید</strong>
                                    <small
                                        data-duration-caption="<?= $serverStoreDurationDays ?>"
                                    ><?= htmlspecialchars($serverStoreDuration['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="server-store-duration-note" role="note">
                            <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                            <span>قیمت هر مدت و هر پیکربندی مستقیماً از Plan و Config Optionهای Paymenter استعلام می‌شود؛ قیمت نمایش‌داده‌شده از محاسبه محلی مرورگر به دست نمی‌آید.</span>
                        </div>
                    </section>


                </form>
            </div>

            <aside class="server-store-summary" aria-labelledby="serverSummaryTitle" data-fc-reveal="up" data-fc-reveal-delay="80">
                <div class="server-store-summary-topline">
                    <span><i class="fa-solid fa-receipt" aria-hidden="true"></i> ORDER SUMMARY</span>
                    <span class="server-store-summary-badge">Paymenter Quote</span>
                </div>

                <h2 id="serverSummaryTitle">خلاصه سفارش</h2>
                <p class="server-store-summary-description">مبلغ قطعی پس از اعتبارسنجی تنظیمات در Paymenter نمایش داده می‌شود.</p>

                <div class="server-store-summary-product">
                    <span class="server-store-summary-product-icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
                    <span><strong>CS2 Multimode Server</strong><small>تمام مودهای پشتیبانی‌شده در یک سرویس</small></span>
                </div>

                <div class="server-store-summary-total">
                    <span>قیمت نهایی دوره انتخاب‌شده</span>
                    <strong data-server-summary="period-price">در انتظار استعلام قیمت</strong>
                    <small>این مبلغ شامل Plan، تمام Config Optionهای قیمت‌دار، هزینه راه‌اندازی و مالیات حساب کاربری در Paymenter است. مبلغ نهایی پیش از پرداخت دوباره محاسبه می‌شود.</small>
                </div>

                <details class="server-store-summary-details">
                    <summary><i class="fa-solid fa-list-check" aria-hidden="true"></i> مشاهده مقادیر فاکتور <i class="fa-solid fa-chevron-down server-store-summary-chevron" aria-hidden="true"></i></summary>
                                    <div class="server-store-summary-lines">
                    <div><span>مدت سرویس</span><strong data-server-summary="duration">۱ ماهه</strong></div>
                    <div><span>نام نمایشی سرور</span><strong data-server-summary="server-name">وارد نشده</strong></div>
                    <div><span>تخفیف دوره</span><strong data-server-summary="discount">بدون تخفیف</strong></div>
                    <div><span>قیمت ماهانه پیکربندی</span><strong data-server-summary="monthly-price">طبق تعرفه Paymenter</strong></div>
                    <div><span>RAM</span><strong data-server-summary="memory">1 GB · 1024 MB</strong></div>
                    <div><span>CPU</span><strong data-server-summary="cpu">100% · 1 vCore</strong></div>
                    <div><span>Storage</span><strong data-server-summary="disk">10 GB · 10240 MB</strong></div>
                    <div><span>نقشه پیش‌فرض</span><strong data-server-summary="map">de_dust2</strong></div>
                    <div><span>ظرفیت هم‌زمان</span><strong data-server-summary="players">10 اسلات</strong></div>
                    <div><span>پلن پایه · ۵ اسلات</span><strong data-server-summary="capacity-base-price">طبق Config Optionهای Paymenter</strong></div>
                    <div><span>اسلات‌های اضافه</span><strong data-server-summary="extra-slots-price">در Quote رسمی مشخص می‌شود</strong></div>
                    <div><span>هزینه ارتقای RAM</span><strong data-server-summary="memory-price">در Quote رسمی مشخص می‌شود</strong></div>
                    <div><span>Tickrate / Subtick</span><strong data-server-summary="tickrate">64 Tick · Subtick فعال</strong></div>
                    <div><span>Game Mode CFG</span><strong data-server-summary="exec">Competitive — comp.cfg</strong></div>
                    <div><span>MAPGROUP</span><strong data-server-summary="mapgroup">Casual / Competitive / Practice Mode — mg_comp</strong></div>
                    <div><span>Cooldown</span><strong data-server-summary="cooldown">غیرفعال</strong></div>
                    <div><span>Bot Guard</span><strong data-server-summary="guard">غیرفعال</strong></div>
                    <div><span>مدت مسدودسازی</span><strong data-server-summary="guard-minutes">فقط هنگام فعال بودن</strong></div>
                    <div><span>نود و پورت</span><strong>تخصیص خودکار</strong></div>
                </div>
                </details>

                <div class="server-store-checkout" aria-labelledby="serverStorePaymentTitle">
                    <h3 id="serverStorePaymentTitle">روش پرداخت</h3>
                    <label class="server-store-payment-option">
                        <input type="radio" name="payment_method" value="zarinpal" form="serverStoreConfigForm" checked>
                        <span><strong>پرداخت آنلاین با زرین‌پال</strong><small>پرداخت امن؛ ثبت سفارش پس از تأیید سروربه‌سر انجام می‌شود.</small></span>
                    </label>
                    <label class="server-store-payment-option">
                        <input type="radio" name="payment_method" value="wallet" form="serverStoreConfigForm">
                        <span><strong>کیف پول Paymenter</strong><small>موجودی کیف پول در Paymenter به‌صورت اتمیک بررسی و کسر می‌شود.</small></span>
                    </label>
                </div>

                <div id="serverStoreQuoteStatus" class="server-store-quote-status" role="status" aria-live="polite">
                    برای دریافت قیمت قطعی، ابتدا وارد حساب FragCity شوید.
                </div>
                <div id="serverStoreQuoteLines" class="server-store-quote-lines" aria-live="polite"></div>

                <button type="submit" form="serverStoreConfigForm" id="serverStoreSubmit" class="server-store-submit" disabled>
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    ابتدا قیمت قطعی را دریافت کنید
                </button>

                <div class="server-store-summary-footnote">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    پرداخت و ساخت سرویس فقط پس از Quote معتبر Paymenter انجام می‌شود. پس از تأیید پرداخت، Provisioning در Paymenter صف‌بندی خواهد شد.
                </div>
            </aside>
        </div>
        </div>
        <section class="server-store-coming-soon" id="serverStorePanelCs16" role="tabpanel" aria-labelledby="serverStoreTabCs16" data-server-game-panel="cs16" hidden>
            <div class="server-store-coming-soon__icon"><i class="fa-solid fa-gamepad" aria-hidden="true"></i></div>
            <span class="server-store-coming-soon__eyebrow">FRAGCITY / CLASSIC SERVERS</span>
            <h2>سرور Counter-Strike 1.6</h2>
            <p>بخش سرورهای CS 1.6 در حال آماده‌سازی است. منابع پایه، ظرفیت بازیکن و تعرفه‌ها به‌صورت جداگانه برای این بازی تعریف می‌شوند.</p>
            <span class="server-store-coming-soon__status"><i class="fa-solid fa-circle" aria-hidden="true"></i> به‌زودی</span>
        </section>
    </div>
</main>
