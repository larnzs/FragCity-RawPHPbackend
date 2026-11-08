<?php
if (
    isset($_GET['game']) &&
    is_string($_GET['game']) &&
    $_GET['game'] === 'minecraft'
) {
?>
<?php
$mcStorePricing = [
    'base_slots' => 5,
    'base_price' => 499000,
    'extra_slot_price' => 50000,
    'ram_prices' => [
        1024 => 0,
        1280 => 40000,
        1536 => 75000,
    ],
];
$mcStoreDefaultPlayers = 10;
?>
<main class="mc-store-page" dir="rtl">
    <div class="mc-store-shell">
        <section class="mc-store-hero">
            <div class="mc-store-hero__copy">
                <span class="mc-store-eyebrow"><i class="fa-solid fa-cubes" aria-hidden="true"></i> MINECRAFT HOSTING</span>
                <h1>سرور Minecraft خودت رو بساز</h1>
                <p>ظرفیت بازیکن و منابع مورد نیازت رو انتخاب کن و قیمت ماهانه رو لحظه‌ای ببین.</p>
            </div>
            <div class="mc-store-hero__mark" aria-hidden="true">
                <i class="fa-solid fa-cubes"></i>
                <span>MC</span>
            </div>
        </section>

        <div class="mc-store-layout">
            <section class="mc-store-panel mc-store-config" aria-labelledby="mcStoreConfigTitle">
                <div class="mc-store-panel__heading">
                    <div>
                        <span class="mc-store-kicker">SERVER CONFIGURATION</span>
                        <h2 id="mcStoreConfigTitle">پیکربندی سرور</h2>
                    </div>
                    <span class="mc-store-step">01 / 02</span>
                </div>

                <div
                    class="mc-store-pricing-data"
                    id="mcStorePricingData"
                    data-base-slots="<?= (int) $mcStorePricing['base_slots'] ?>"
                    data-base-price="<?= (int) $mcStorePricing['base_price'] ?>"
                    data-extra-slot-price="<?= (int) $mcStorePricing['extra_slot_price'] ?>"
                    data-ram-price-1024="<?= (int) $mcStorePricing['ram_prices'][1024] ?>"
                    data-ram-price-1280="<?= (int) $mcStorePricing['ram_prices'][1280] ?>"
                    data-ram-price-1536="<?= (int) $mcStorePricing['ram_prices'][1536] ?>"
                ></div>

                <div class="mc-store-field mc-store-field--capacity">
                    <div class="mc-store-field__top">
                        <label for="mcStorePlayers">ظرفیت بازیکن هم‌زمان</label>
                        <output id="mcStorePlayersValue" for="mcStorePlayers"><?= (int) $mcStoreDefaultPlayers ?></output>
                    </div>
                    <input
                        class="mc-store-range"
                        id="mcStorePlayers"
                        name="players"
                        type="range"
                        min="5"
                        max="64"
                        step="1"
                        value="<?= (int) $mcStoreDefaultPlayers ?>"
                        aria-describedby="mcStorePlayersHelp"
                    >
                    <div class="mc-store-range__limits"><span>۵ بازیکن</span><span>۶۴ بازیکن</span></div>
                    <small id="mcStorePlayersHelp">پلن پایه شامل ۵ اسلات است؛ هر اسلات بیشتر، ۵۰ هزار تومان به قیمت ماهانه اضافه می‌کند.</small>
                </div>

                <div class="mc-store-fields-grid">
                    <div class="mc-store-field">
                        <label for="mcStoreMemory">حافظه RAM</label>
                        <select class="mc-store-select" id="mcStoreMemory" name="memory">
                            <option value="1024" selected>۱ گیگابایت</option>
                            <option value="1280">۱.۲۵ گیگابایت</option>
                            <option value="1536">۱.۵ گیگابایت</option>
                        </select>
                        <small>ارتقای RAM به‌صورت جداگانه محاسبه می‌شود.</small>
                    </div>

                    <div class="mc-store-field">
                        <label for="mcStoreCpu">قدرت پردازنده</label>
                        <select class="mc-store-select" id="mcStoreCpu" name="cpu">
                            <option value="100" selected>۱ vCore · ۱۰۰٪</option>
                            <option value="200">۲ vCore · ۲۰۰٪</option>
                            <option value="300">۳ vCore · ۳۰۰٪</option>
                            <option value="400">۴ vCore · ۴۰۰٪</option>
                        </select>
                        <small>محدوده پردازنده از ۱ تا ۴ vCore.</small>
                    </div>

                    <div class="mc-store-field mc-store-field--wide">
                        <label for="mcStoreDisk">فضای ذخیره‌سازی SSD</label>
                        <select class="mc-store-select" id="mcStoreDisk" name="disk">
                            <option value="5120">۵ گیگابایت</option>
                            <option value="6144">۶ گیگابایت</option>
                            <option value="7168">۷ گیگابایت</option>
                            <option value="8192">۸ گیگابایت</option>
                            <option value="9216">۹ گیگابایت</option>
                            <option value="10240" selected>۱۰ گیگابایت</option>
                        </select>
                        <small>فضای SSD قابل انتخاب: ۵ تا ۱۰ گیگابایت.</small>
                    </div>
                </div>

                <div class="mc-store-info">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p>مقدار CPU و SSD در محدوده‌ی انتخابی قابل تنظیم است. قیمت این صفحه بر اساس ظرفیت بازیکن و سطح RAM محاسبه می‌شود.</p>
                </div>
            </section>

            <aside class="mc-store-panel mc-store-summary" aria-labelledby="mcStoreSummaryTitle">
                <div class="mc-store-panel__heading">
                    <div>
                        <span class="mc-store-kicker">MONTHLY SUMMARY</span>
                        <h2 id="mcStoreSummaryTitle">خلاصه هزینه</h2>
                    </div>
                    <span class="mc-store-summary__icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                </div>

                <div class="mc-store-summary__specs">
                    <div><span>ظرفیت</span><strong data-mc-summary="players">۱۰ بازیکن</strong></div>
                    <div><span>RAM</span><strong data-mc-summary="memory">۱ گیگابایت</strong></div>
                    <div><span>CPU</span><strong data-mc-summary="cpu">۱ vCore</strong></div>
                    <div><span>SSD</span><strong data-mc-summary="disk">۱۰ گیگابایت</strong></div>
                </div>

                <div class="mc-store-price-breakdown">
                    <div><span>پلن پایه · ۵ اسلات</span><strong data-mc-summary="base-price">۴۹۹٬۰۰۰ تومان</strong></div>
                    <div><span>اسلات‌های اضافه</span><strong data-mc-summary="extra-price">۵ × ۵۰٬۰۰۰ = ۲۵۰٬۰۰۰ تومان</strong></div>
                    <div><span>ارتقای RAM</span><strong data-mc-summary="ram-price">بدون هزینه اضافه</strong></div>
                </div>

                <div class="mc-store-total">
                    <span>هزینه ماهانه</span>
                    <strong data-mc-summary="total-price">۷۴۹٬۰۰۰ تومان</strong>
                    <small>قیمت نهایی بر اساس ظرفیت و RAM انتخاب‌شده محاسبه می‌شود.</small>
                </div>

                <div class="mc-store-traffic-note">
                    <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
                    <p>هزینه ترافیک جداگانه به این مبلغ اضافه نمی‌شود و در تعرفه ظرفیت لحاظ شده است. مصرف واقعی به تعداد بازیکنان آنلاین، نوع دنیا و افزونه‌ها بستگی دارد.</p>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php
    return;
}
?>
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

$serverStoreCapacityPricing = [
    'base_slots' => 5,
    'base_price' => 499000,
    'extra_slot_price' => 50000,
];
$serverStoreRamPricing = [
    1024 => 0,
    1280 => 40000,
    1536 => 75000,
];
$serverStoreDefaultPlayers = 10;
?>
<main class="server-store-page" dir="rtl">
    <div class="server-store-container">
        <header class="server-store-hero" data-fc-reveal="up">
            <div class="server-store-hero-main">
                <div class="server-store-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-server"></i>
                </div>
                <div class="server-store-hero-copy">
                    <span class="server-store-eyebrow" dir="ltr">FRAGCITY / GAME HOSTING</span>
                    <h1>سرور CS2 خودت رو بساز</h1>
                    <p>از ۵ تا ۶۴ اسلات انتخاب کن و یک سرور چندحالته بساز؛ مودهای متنوع CS2 در یک سرویس، بدون نیاز به خرید پلن جدا برای هر سبک بازی.</p>
                    <div class="server-store-hero-tags">
                        <span><i class="fa-solid fa-users" aria-hidden="true"></i> ۵ تا ۶۴ اسلات</span>
                        <span><i class="fa-solid fa-gamepad" aria-hidden="true"></i> همه مودها در یک سرویس</span>
                        <span><i class="fa-solid fa-sliders" aria-hidden="true"></i> منابع قابل تنظیم</span>
                    </div>
                </div>
            </div>

            <div class="server-store-hero-product">
                <span class="server-store-product-kicker">YOUR GAME SERVER</span>
                <strong>CS2 Multimode</strong>
                <span class="server-store-product-status"><i class="fa-solid fa-circle" aria-hidden="true"></i> ظرفیت انتخابی ۵ تا ۶۴ اسلات</span>
                <div class="server-store-product-mark" aria-hidden="true">
                    <i class="fa-solid fa-crosshairs"></i>
                </div>
            </div>
        </header>

        <div class="server-store-layout">
            <div class="server-store-main">
                <form id="serverStoreConfigForm" class="server-store-config-form" autocomplete="off">
                    <section class="server-store-section server-store-capacity-section" aria-labelledby="serverCapacityTitle" data-fc-reveal="up" data-fc-reveal-delay="20">
                        <div class="server-store-section-heading">
                            <span class="server-store-section-index">01</span>
                            <div>
                                <h2 id="serverCapacityTitle">ظرفیت بازیکنان</h2>
                                <p>تعداد بازیکنانی را انتخاب کن که می‌توانند به‌صورت هم‌زمان وارد سرورت شوند.</p>
                            </div>
                            <span class="server-store-section-state">۵ تا ۶۴ اسلات</span>
                        </div>

                        <div
                            class="server-store-capacity-panel"
                            data-base-slots="<?= (int) $serverStoreCapacityPricing['base_slots'] ?>"
                            data-base-price="<?= (int) $serverStoreCapacityPricing['base_price'] ?>"
                            data-extra-slot-price="<?= (int) $serverStoreCapacityPricing['extra_slot_price'] ?>"
                            data-ram-price-1024="<?= (int) $serverStoreRamPricing[1024] ?>"
                            data-ram-price-1280="<?= (int) $serverStoreRamPricing[1280] ?>"
                            data-ram-price-1536="<?= (int) $serverStoreRamPricing[1536] ?>"
                        >
                            <div class="server-store-capacity-head">
                                <div>
                                    <span class="server-store-capacity-kicker">MAX PLAYER SLOTS</span>
                                    <div class="server-store-capacity-value">
                                        <output id="serverStorePlayersValue" for="serverStorePlayers" aria-live="polite"><?= (int) $serverStoreDefaultPlayers ?></output>
                                        <span>اسلات</span>
                                    </div>
                                </div>
                                <span class="server-store-capacity-range-label">قابل تنظیم با گام یک‌تایی</span>
                            </div>

                            <label class="server-store-capacity-slider-label" for="serverStorePlayers">ظرفیت اتصال هم‌زمان</label>
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
                            <div class="server-store-range-meta" aria-hidden="true"><span>5 اسلات</span><span>64 اسلات</span></div>

                            <div class="server-store-capacity-presets" role="group" aria-label="انتخاب سریع ظرفیت">
                                <button type="button" data-player-preset="5" aria-pressed="false">5</button>
                                <button type="button" data-player-preset="10" aria-pressed="true">10</button>
                                <button type="button" data-player-preset="16" aria-pressed="false">16</button>
                                <button type="button" data-player-preset="20" aria-pressed="false">20</button>
                                <button type="button" data-player-preset="24" aria-pressed="false">24</button>
                                <button type="button" data-player-preset="32" aria-pressed="true">32</button>
                                <button type="button" data-player-preset="48" aria-pressed="false">48</button>
                                <button type="button" data-player-preset="64" aria-pressed="false">64</button>
                            </div>

                            <div class="server-store-capacity-pricing-note">
                                <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                                <span>۵ اسلات پایه: <strong>۴۹۹٬۰۰۰ تومان</strong> · هر اسلات اضافه: <strong>۵۰٬۰۰۰ تومان در ماه</strong></span>
                            </div>
                            <small class="server-store-capacity-help" id="serverStorePlayersHelp">
                                هر اسلات اضافه ظرفیت اتصال هم‌زمان را بیشتر می‌کند و مصرف مورد انتظار اینترنت و CPU را بالا می‌برد؛ به همین دلیل قیمت با هر اسلات افزایش پیدا می‌کند. مصرف واقعی اینترنت به تعداد بازیکنان آنلاین و مدت حضورشان وابسته است.
                            </small>
                        </div>
                    </section>

                <section class="server-store-section server-store-resources-section" aria-labelledby="serverResourcesTitle" data-fc-reveal="up" data-fc-reveal-delay="40">
                    <div class="server-store-section-heading">
                        <span class="server-store-section-index">02</span>
                        <div>
                            <h2 id="serverResourcesTitle">منابع قابل تنظیم سرور</h2>
                            <p>منابع سرور را متناسب با نیاز کامیونیتی خودت انتخاب کن؛ مقدار هر اسلایدر در لحظه به‌روزرسانی می‌شود.</p>
                        </div>
                        <span class="server-store-section-state">قابل تنظیم</span>
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
                                <label class="server-store-resource-slider-label" for="serverStoreMemory">مقدار حافظه</label>
                                <input class="server-store-range" id="serverStoreMemory" name="memory" type="range" min="1024" max="1536" step="256" value="1024" aria-describedby="serverStoreMemoryHelp">
                                <div class="server-store-range-meta" aria-hidden="true"><span>1 GB</span><span>1.5 GB</span></div>
                                <small class="server-store-range-help" id="serverStoreMemoryHelp">۱ گیگابایت پایه است؛ ۱.۲۵ گیگابایت ۴۰ هزار تومان و ۱.۵ گیگابایت ۷۵ هزار تومان به مبلغ ماهانه اضافه می‌کند.</small>
                            </div>
                        </article>

                        <article class="server-store-resource-card">
                            <div class="server-store-resource-head">
                                <span class="server-store-resource-icon"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i></span>
                                <div class="server-store-resource-copy">
                                    <small>SSD STORAGE</small>
                                    <strong><output id="serverStoreDiskValue" for="serverStoreDisk">10 GB</output></strong>
                                    <span>فضای ذخیره‌سازی قابل تخصیص</span>
                                </div>
                            </div>
                            <div class="server-store-range-wrap">
                                <label class="server-store-resource-slider-label" for="serverStoreDisk">فضای دیسک</label>
                                <input class="server-store-range" id="serverStoreDisk" name="disk" type="range" min="5120" max="10240" step="1024" value="10240" aria-describedby="serverStoreDiskHelp">
                                <div class="server-store-range-meta" aria-hidden="true"><span>5 GB</span><span>10 GB</span></div>
                                <small class="server-store-range-help" id="serverStoreDiskHelp">محدوده تخصیص دیسک: ۵ تا ۱۰ گیگابایت.</small>
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
                                <small class="server-store-range-help" id="serverStoreCpuHelp">هر ۱۰۰٪ محدودیت CPU معادل یک vCore است.</small>
                            </div>
                        </article>
                    </div>
                    <div class="server-store-resource-footnote">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        مقدار RAM و Storage به مگابایت و مقدار CPU به درصد بیان می‌شود. نود و پورت اتصال به‌صورت خودکار تخصیص داده می‌شوند.
                    </div>
                </section>

                <section class="server-store-section server-store-config-section" aria-labelledby="serverGameModeTitle" data-fc-reveal="up" data-fc-reveal-delay="80">
                        <div class="server-store-section-heading">
                            <span class="server-store-section-index">03</span>
                            <div>
                                <h2 id="serverGameModeTitle">تنظیمات گیم‌سرور</h2>
                                <p>مود بازی، نقشه شروع و تنظیمات پایه سرورت را مشخص کن.</p>
                            </div>
                            <span class="server-store-section-state">تنظیمات الزامی</span>
                        </div>

                        <div class="server-store-field-grid">
                            <div class="server-store-field">
                                <label for="serverStoreMap">نقشه پیش‌فرض <span class="server-store-required" aria-hidden="true">*</span></label>
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

                            <div class="server-store-field">
                                <label for="serverStoreTickrate">Tickrate سرور <span class="server-store-required" aria-hidden="true">*</span></label>
                                <select class="server-store-select" id="serverStoreTickrate" name="TICKRATE" required aria-describedby="serverStoreTickrateHelp">
                                    <option value="64" selected>64 Tick · نرخ پایه</option>
                                    <option value="128">128 Tick · ارتقای پولی</option>
                                </select>
                                <small id="serverStoreTickrateHelp">Tickrate قابل انتخاب برای سرور. گزینه 128 به‌عنوان ارتقای پولی در سبد خرید FragCity محاسبه می‌شود.</small>
                            </div>

                            <div class="server-store-field">
                                <label for="serverStoreExec">حالت بازی (Game Mode CFG) <span class="server-store-required" aria-hidden="true">*</span></label>
                                <select class="server-store-select" id="serverStoreExec" name="EXEC" required aria-describedby="serverStoreExecHelp">
                                    <?php foreach ($serverStoreGameModes as $serverStoreMode): ?>
                                        <option
                                            value="<?= htmlspecialchars($serverStoreMode['config'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-map-groups="<?= htmlspecialchars(implode('|', $serverStoreMode['map_groups']), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $serverStoreMode['config'] === $serverStoreDefaultConfig ? 'selected' : '' ?>
                                        ><?= htmlspecialchars($serverStoreMode['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="serverStoreExecHelp">فایل CFG مربوط به GameModeManager. فقط مودهای تعریف‌شده در GameModes فهرست می‌شوند؛ فایل‌های Settings، Warmup و custom در دسترس Checkout نیستند.</small>
                            </div>

                            <div class="server-store-field server-store-field--wide">
                                <label for="serverStoreMapgroup">گروه نقشه (MAPGROUP) <span class="server-store-required" aria-hidden="true">*</span></label>
                                <select class="server-store-select" id="serverStoreMapgroup" name="MAPGROUP" required aria-describedby="serverStoreMapgroupHelp">
                                    <?php foreach ($serverStoreMapGroupLabels as $serverStoreMapGroupValue => $serverStoreMapGroupLabel): ?>
                                        <option
                                            value="<?= htmlspecialchars($serverStoreMapGroupValue, ENT_QUOTES, 'UTF-8') ?>"
                                            data-game-modes="<?= htmlspecialchars(implode('|', $serverStoreMapGroupModes[$serverStoreMapGroupValue] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $serverStoreMapGroupValue === $serverStoreDefaultMapGroup ? 'selected' : '' ?>
                                        ><?= htmlspecialchars($serverStoreMapGroupLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="serverStoreMapgroupHelp">شناسه Map Group مرتبط با مود بازی. گزینه‌ها بر اساس CFG انتخاب‌شده و مقادیر MapGroups در GameModeManager فیلتر می‌شوند.</small>
                            </div>
                        </div>
                    </section>

                    <section class="server-store-section server-store-security-section" aria-labelledby="serverAddonTitle" data-fc-reveal="up" data-fc-reveal-delay="120">
                        <div class="server-store-section-heading">
                            <span class="server-store-section-index">04</span>
                            <div>
                                <h2 id="serverAddonTitle">محافظت و قوانین اتصال</h2>
                                <p>قوانین ورود بازیکنان و افزونه محافظت در برابر بات‌ها را تنظیم کن.</p>
                            </div>
                        </div>

                        <div class="server-store-field-grid">
                            <div class="server-store-field">
                                <label for="serverStoreCooldown">اخراج بازیکنان دارای Cooldown <span class="server-store-required" aria-hidden="true">*</span></label>
                                <select class="server-store-select" id="serverStoreCooldown" name="COOLDOWN" required aria-describedby="serverStoreCooldownHelp">
                                    <option value="0" selected>غیرفعال</option>
                                    <option value="1">فعال</option>
                                </select>
                                <small id="serverStoreCooldownHelp">معادل sv_kick_players_with_cooldown.</small>
                            </div>

                            <div class="server-store-field">
                                <label for="serverStoreGuard">فعال‌سازی محافظ Bot Guard <span class="server-store-required" aria-hidden="true">*</span></label>
                                <select class="server-store-select" id="serverStoreGuard" name="ENABLE_GUARD" required aria-describedby="serverStoreGuardHelp">
                                    <option value="0" selected>غیرفعال</option>
                                    <option value="1">فعال — افزونه پولی</option>
                                </select>
                                <small id="serverStoreGuardHelp">محافظت در برابر بات‌ها و اتصال‌های مخرب. فعال‌سازی Bot Guard به‌عنوان افزونه پولی در سبد خرید FragCity محاسبه می‌شود.</small>
                            </div>

                            <div class="server-store-field server-store-field--wide" id="serverStoreGuardMinutesField" hidden>
                                <label for="serverStoreGuardMinutes">مدت مسدودسازی Bot Guard (دقیقه)</label>
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
                            انتخاب‌ها در این صفحه برای پیش‌نمایش سفارش هستند؛ ثبت سفارش و Provisioning هنوز به Backend متصل نشده‌اند.
                        </div>
                    </section>
                </form>
            </div>

            <aside class="server-store-summary" aria-labelledby="serverSummaryTitle" data-fc-reveal="up" data-fc-reveal-delay="80">
                <div class="server-store-summary-topline">
                    <span><i class="fa-solid fa-receipt" aria-hidden="true"></i> ORDER SUMMARY</span>
                    <span class="server-store-summary-badge">تعرفه ظرفیت</span>
                </div>

                <h2 id="serverSummaryTitle">خلاصه سفارش</h2>
                <p class="server-store-summary-description">خلاصه انتخاب‌هایت اینجا به‌روزرسانی می‌شود. پیش از فعال‌شدن خرید، همه موارد را بررسی کن.</p>

                <div class="server-store-summary-product">
                    <span class="server-store-summary-product-icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
                    <span><strong>CS2 Multimode Server</strong><small>تمام مودهای پشتیبانی‌شده در یک سرویس</small></span>
                </div>

                <div class="server-store-summary-lines">
                    <div><span>RAM</span><strong data-server-summary="memory">1 GB · 1024 MB</strong></div>
                    <div><span>CPU</span><strong data-server-summary="cpu">100% · 1 vCore</strong></div>
                    <div><span>Storage</span><strong data-server-summary="disk">10 GB · 10240 MB</strong></div>
                    <div><span>نقشه پیش‌فرض</span><strong data-server-summary="map">de_dust2</strong></div>
                    <div><span>ظرفیت هم‌زمان</span><strong data-server-summary="players">10 اسلات</strong></div>
                    <div><span>پلن پایه · ۵ اسلات</span><strong data-server-summary="capacity-base-price">۴۹۹٬۰۰۰ تومان</strong></div>
                    <div><span>اسلات‌های اضافه</span><strong data-server-summary="extra-slots-price">۵ × ۵۰٬۰۰۰ = ۲۵۰٬۰۰۰ تومان</strong></div>
                    <div><span>هزینه ارتقای RAM</span><strong data-server-summary="memory-price">بدون هزینه اضافه</strong></div>
                    <div><span>Tickrate</span><strong data-server-summary="tickrate">64 Tick</strong></div>
                    <div><span>Game Mode CFG</span><strong data-server-summary="exec">Competitive — comp.cfg</strong></div>
                    <div><span>MAPGROUP</span><strong data-server-summary="mapgroup">Casual / Competitive / Practice Mode — mg_comp</strong></div>
                    <div><span>Cooldown</span><strong data-server-summary="cooldown">غیرفعال</strong></div>
                    <div><span>Bot Guard</span><strong data-server-summary="guard">غیرفعال</strong></div>
                    <div><span>مدت مسدودسازی</span><strong data-server-summary="guard-minutes">فقط هنگام فعال بودن</strong></div>
                    <div><span>نود و پورت</span><strong>تخصیص خودکار</strong></div>
                </div>

                <div class="server-store-summary-total">
                    <span>قیمت ماهانه پیکربندی انتخاب‌شده</span>
                    <strong data-server-summary="monthly-price">۷۴۹٬۰۰۰ تومان</strong>
                    <small>شامل پلن پایه ۵ اسلات، اسلات‌های اضافه و RAM انتخاب‌شده است. هزینه اینترنت جداگانه از مشتری دریافت نمی‌شود و در تعرفه ظرفیت لحاظ شده است. افزونه‌های پولی مانند Tickrate 128 و Bot Guard جداگانه محاسبه می‌شوند.</small>
                </div>

                <button type="button" class="server-store-submit" disabled>
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    ثبت سفارش هنوز فعال نیست
                </button>

                <div class="server-store-summary-footnote">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    این صفحه فعلاً برای انتخاب و بررسی پیکربندی است. سفارش‌گیری پس از نهایی‌شدن تعرفه‌ها و اتصال Backend فعال می‌شود.
                </div>
            </aside>
        </div>
    </div>
</main>
