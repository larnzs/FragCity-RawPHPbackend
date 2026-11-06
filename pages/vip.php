<?php
require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/../class/VipCoreRepository.php';

$steamid64 = (string) ($_SESSION['steamid'] ?? '');
$isLoggedIn = ($_SESSION['user_logged_in'] ?? false) === true;
$isVipTestAdmin = $isLoggedIn && (string)($_SESSION['role'] ?? '') === 'admin';

$userVip = [
    'vip_level' => 0,
    'vip_expire' => null,
    'vip_active' => false,
    'vip_permanent' => false,
];
$vipStatusUnavailable = false;
$vipFreeTrialStatus = [
    'available' => false,
    'claimed' => false,
    'blocked_by_active_membership' => false,
    'claimed_at' => null,
    'expires' => null,
    'group' => null,
];
$vipFreeTrialStatusUnavailable = false;

$vipSettings = fragcity_settings('files.pages.vip', []);
$vipPurchaseCatalog = is_array($vipSettings['purchase_catalog'] ?? null)
    ? $vipSettings['purchase_catalog']
    : [];
$vipPurchasePackages = is_array($vipPurchaseCatalog['packages'] ?? null)
    ? $vipPurchaseCatalog['packages']
    : [];
$vipCurrency = (string) ($vipPurchaseCatalog['currency'] ?? 'تومان');

if ($isLoggedIn && $steamid64 !== '') {
    try {
        $vipStatus = (new VipCoreRepository())->getStatus($steamid64);
        $vipExpires = $vipStatus['expires'];

        $userVip = [
            'vip_level' => $vipStatus['active'] ? $vipStatus['level'] : 0,
            'vip_expire' => $vipExpires !== null && $vipExpires > 0 ? $vipExpires : null,
            'vip_active' => $vipStatus['active'],
            'vip_permanent' => $vipStatus['active'] && $vipExpires === 0,
        ];
    } catch (Throwable $e) {
        $vipStatusUnavailable = true;
        error_log(
            '[FragCity VIP storefront] VIPCore status lookup failed; steam_hash='
            . substr(hash('sha256', $steamid64), 0, 16)
            . '; type=' . get_class($e)
        );
    }
}

if ($isLoggedIn && $steamid64 !== '') {
    try {
        $vipFreeTrialStatus = (new VipCoreRepository())->getFreeTrialStatus($steamid64);
    } catch (Throwable $e) {
        $vipFreeTrialStatusUnavailable = true;
        error_log(
            '[FragCity VIP storefront] Free Silver trial status lookup failed; steam_hash='
            . substr(hash('sha256', $steamid64), 0, 16)
            . '; type=' . get_class($e)
        );
    }
}

$vipNames = [0 => 'No VIP', 1 => 'Bronze', 2 => 'Silver', 3 => 'Gold', 4 => 'Platinum', 5 => 'Emerald', 6 => 'Diamond'];

$vipLevels = [
    1 => [
        'name' => 'Bronze',
        'fa_icon' => 'fas fa-medal',
        'color' => '#CD7F32',
        'available' => false,
        'summary' => 'Internal test-only dummy tier. Bronze is not offered as a customer membership.',
        'summary_fa' => 'سطح آزمایشی داخلی VIPCore است و به‌عنوان اشتراک واقعی به کاربران عرضه نمی‌شود.',
    ],
    2 => [
        'name' => 'Silver',
        'fa_icon' => 'fas fa-medal',
        'color' => '#C0C0C0',
        'available' => true,
        'summary' => 'One-time seven-day Silver welcome membership with 125 armor, a defuse kit, smoke effects and 3% vampirism.',
        'summary_fa' => 'هدیه خوش‌آمدگویی یک‌هفته‌ای و یک‌بار برای هر حساب؛ همراه ۱۲۵ زره، کیت خنثی‌سازی، دود رنگی و ۳٪ Vampirism.',
    ],
    3 => [
        'name' => 'Gold',
        'fa_icon' => 'fas fa-crown',
        'color' => '#F5B042',
        'available' => true,
        'summary' => 'A stronger loadout with 100 HP, 150 armor, a healthshot and advanced combat perks.',
        'summary_fa' => 'تجهیزات کامل‌تر با ۱۰۰ سلامتی، ۱۵۰ زره، یک Healthshot و قابلیت‌های پیشرفته.',
    ],
    4 => [
        'name' => 'Platinum',
        'fa_icon' => 'fas fa-gem',
        'color' => '#9CC9E8',
        'available' => false,
        'summary' => 'Advanced movement, increased gravity, anti-flash, decoy teleport and teammate healing.',
        'summary_fa' => 'حرکت پیشرفته، گرانش افزایشی، Anti-Flash، تلپورت با Decoy و درمان هم‌تیمی.',
    ],
    5 => [
        'name' => 'Emerald',
        'fa_icon' => 'fas fa-gem',
        'color' => '#34D399',
        'available' => false,
        'summary' => 'Stronger regeneration, infinite ammo, a round-two loadout and enhanced VIP utilities.',
        'summary_fa' => 'بازیابی قوی‌تر، مهمات بی‌نهایت، منوی تجهیزات از راند دوم و قابلیت‌های ویژه‌تر.',
    ],
    6 => [
        'name' => 'Diamond',
        'fa_icon' => 'fas fa-gem',
        'color' => '#93C5FD',
        'available' => false,
        'summary' => 'The top tier with 300 HP, maximum configured regeneration, extra respawn and premium utilities.',
        'summary_fa' => 'بالاترین سطح با ۳۰۰ سلامتی، بیشترین بازیابی، Respawn اضافه و قابلیت‌های ویژه.',
    ],
];

$vipFeature = static function (
    string $icon,
    string $nameFa,
    string $nameEn,
    string $descFa,
    string $descEn
): array {
    return [
        'icon' => $icon,
        'name_fa' => $nameFa,
        'name_en' => $nameEn,
        'desc_fa' => $descFa,
        'desc_en' => $descEn,
    ];
};

$vipCash = static fn (int $amount): array => $vipFeature(
    'fas fa-coins',
    'پول اضافه شروع راند',
    'Bonus Round Money',
    'در شروع راند، ' . number_format($amount) . ' دلار پول اضافه دریافت می‌کنی.',
    'Receive +$' . number_format($amount) . ' bonus money at round start.'
);
$vipTag = static fn (string $tier, int $starCount): array => $vipFeature(
    'fas fa-star',
    'تگ ستاره‌ای ' . $tier,
    'VIP Star Tag · ' . str_repeat('⭐', $starCount),
    'پیشوند چت: [VIP : ' . str_repeat('⭐', $starCount) . ']؛ تگ اسکوربورد: VIP : ' . str_repeat('⭐', $starCount) . '. رنگ اختصاصی سطح ' . $tier . ' حفظ می‌شود. با دستور !tag یا از منوی !vip ← Tag می‌توانی تگ را انتخاب یا خاموش کنی؛ انتخابت ذخیره می‌شود.',
    'Chat prefix: [VIP : ' . str_repeat('⭐', $starCount) . ']; scoreboard clan tag: VIP : ' . str_repeat('⭐', $starCount) . '. The ' . $tier . ' tier colour is retained. Use !tag or !vip → Tag to select or disable it; your choice is saved.'
);
$vipSniperCount = $vipFeature(
    'fas fa-crosshairs',
    'نمایش آمار اسنایپرهای دو تیم',
    'Team Sniper Count',
    'پس از پایان زمان خرید، تعداد AWP، SSG 08، SCAR-20 و G3SG1 هر تیم در چت برای بازیکنان VIP نمایش داده می‌شود.',
    'After freeze time, VIPs see each team’s AWP, SSG 08, SCAR-20 and G3SG1 counts in chat.'
);
$vipVote = $vipFeature(
    'fas fa-check-to-slot',
    'شروع رأی‌گیری بازیکنان',
    'PlayerVotes Access',
    'امکان شروع رأی‌گیری برای Kick، Ban، Mute، Gag و Silence با حداقل ۲ بازیکن فعال و حدنصاب ۸۰٪. بازیکنان دارای این قابلیت از هدف رأی‌گیری مصون هستند.',
    'Start Kick, Ban, Mute, Gag and Silence votes with at least 2 active players and an 80% threshold. Players with this perk are protected from being vote targets.'
);
$vipDefuser = $vipFeature(
    'fas fa-bomb',
    'کیت خنثی‌سازی بمب',
    'Defuse Kit',
    'با کیت خنثی‌سازی رایگان وارد راند می‌شوی.',
    'Spawn with a free defuse kit.'
);
$vipArmor = static fn (int $amount): array => $vipFeature(
    'fas fa-shield-halved',
    'زره شروع راند',
    'Spawn Armor',
    'با ' . number_format($amount) . ' واحد زره وارد راند می‌شوی.',
    'Spawn with ' . number_format($amount) . ' armor.'
);
$vipSpawnHealth = static fn (int $amount): array => $vipFeature(
    'fas fa-heart',
    'سلامتی شروع راند',
    'Spawn Health',
    'با ' . number_format($amount) . ' واحد سلامتی وارد راند می‌شوی.',
    'Spawn with ' . number_format($amount) . ' HP.'
);
$vipSmoke = $vipFeature(
    'fas fa-smog',
    'دود با رنگ تصادفی',
    'Random Smoke Color',
    'رنگ دود نارنجک به‌صورت تصادفی انتخاب می‌شود.',
    'Smoke grenades use a randomly selected color.'
);
$vipKillscreen = $vipFeature(
    'fas fa-display',
    'افکت صفحه کشتن',
    'Custom Kill Screen',
    'افکت اختصاصی Kill Screen برایت فعال می‌شود.',
    'Unlock the custom kill-screen effect.'
);
$vipFastReload = $vipFeature(
    'fas fa-bolt',
    'خشاب‌گذاری سریع‌تر',
    'Fast Reload',
    'سرعت خشاب‌گذاری اسلحه‌ها افزایش پیدا می‌کند.',
    'Reload weapons faster.'
);
$vipNoFall = $vipFeature(
    'fas fa-feather',
    'بدون آسیب سقوط',
    'No Fall Damage',
    'از سقوط، آسیب سلامتی دریافت نمی‌کنی.',
    'Fall damage is disabled.'
);
$vipZeus = $vipFeature(
    'fas fa-bolt-lightning',
    'زیوس رایگان',
    'Free Zeus',
    'در شروع راند یک Zeus دریافت می‌کنی.',
    'Receive a Zeus at spawn.'
);
$vipRainbow = $vipFeature(
    'fas fa-rainbow',
    'مدل رنگین‌کمانی',
    'Rainbow Model',
    'افکت مدل رنگین‌کمانی بازیکن فعال می‌شود.',
    'Enable the rainbow player-model effect.'
);
$vipBhop = $vipFeature(
    'fas fa-person-running',
    'بانـی‌هاپ',
    'Bunnyhop',
    'قابلیت Bunnyhop برای حرکت سریع‌تر فعال است.',
    'Bunnyhop support is enabled.'
);
$vipAntiFlash = $vipFeature(
    'fas fa-eye-slash',
    'محافظت ضد فلش تیمی',
    'Team and Self Anti-Flash',
    'فلش خودت و فلش بازیکنان تیم خودی روی تو بی‌اثر می‌شود؛ فلش دشمن همچنان اثر دارد.',
    'Your own flashes and flashes from teammates do not blind you; enemy flashes still work.'
);
$vipFastPlant = $vipFeature(
    'fas fa-fire',
    'کاشت سریع‌تر بمب',
    'Fast Plant',
    'سرعت کاشت بمب افزایش پیدا می‌کند.',
    'Plant the bomb faster.'
);
$vipInfiniteAmmo = $vipFeature(
    'fas fa-infinity',
    'مهمات بی‌نهایت',
    'Infinite Ammo · Type 1',
    'حالت Type 1 مهمات بی‌نهایت فعال است؛ خشاب اسلحه با شلیک و خشاب‌گذاری دوباره شارژ می‌شود.',
    'Type 1 infinite ammo is enabled; the weapon clip is refilled on fire and reload.'
);
$vipHealthshot = static fn (int $count): array => $vipFeature(
    'fas fa-syringe',
    'سرنگ Healthshot',
    'Healthshot',
    'در هر بار Spawn، ' . number_format($count) . ' عدد Healthshot دریافت می‌کنی.',
    'Receive ' . number_format($count) . ' healthshot(s) on spawn.'
);
$vipDecoyTp = static fn (int $count): array => $vipFeature(
    'fas fa-location-crosshairs',
    'تلپورت با نارنجک Decoy',
    'Decoy Teleport',
    'در هر راند ' . number_format($count) . ' بار می‌توانی با Decoy تلپورت کنی.',
    'Teleport with a decoy up to ' . number_format($count) . ' time(s) per round.'
);
$vipBuyTeamWeapon = $vipFeature(
    'fas fa-right-left',
    'خرید اسلحه تیم مقابل',
    'Buy Opposing-Team Weapons',
    'در محدوده خرید می‌توانی اسلحه‌های منتخب تیم مقابل را با دستورهای اختصاصی خریداری کنی.',
    'Buy selected weapons normally restricted to the opposing team while inside a buy zone.'
);
$vipGravity = static function (float $multiplier) use ($vipFeature): array {
    $value = number_format($multiplier, 2, '.', '');
    return $vipFeature(
        'fas fa-arrow-up-wide-short',
        'ضریب گرانش افزایشی',
        'Increased Gravity',
        'ضریب گرانش روی ' . $value . 'x تنظیم شده است؛ مقدار استاندارد 1.00x است.',
        'Gravity is set to ' . $value . 'x; the standard multiplier is 1.00x.'
    );
};
$vipJumps = $vipFeature(
    'fas fa-angles-up',
    'پرش اضافه در هوا',
    'Additional Air Jump',
    'یک پرش اضافه در هوا فعال است؛ حداکثر ۵ بار در هر راند.',
    'One additional air jump is enabled, with a limit of 5 uses per round.'
);
$vipVampirism = static fn (int $percent): array => $vipFeature(
    'fas fa-droplet',
    'سرقت سلامتی',
    'Vampirism',
    $percent . '٪ از دمیجی که وارد می‌کنی به سلامتی تبدیل می‌شود.',
    'Convert ' . $percent . '% of dealt damage into health.'
);
$vipDamageMultiplier = static fn (float $multiplier): array => $vipFeature(
    'fas fa-burst',
    'ضریب آسیب بیشتر',
    'Damage Multiplier',
    number_format(($multiplier - 1) * 100, 0) . '٪ آسیب بیشتر وارد می‌کنی (ضریب ' . number_format($multiplier, 2) . 'x).',
    'Deal ' . number_format(($multiplier - 1) * 100, 0) . '% more damage (' . number_format($multiplier, 2) . 'x multiplier).'
);
$vipFastDefuse = static fn (int $percent): array => $vipFeature(
    'fas fa-stopwatch',
    'خنثی‌سازی سریع‌تر',
    'Fast Defuse',
    'بمب را ' . number_format($percent) . '٪ سریع‌تر خنثی می‌کنی.',
    'Defuse the bomb ' . number_format($percent) . '% faster.'
);
$vipHealthRegen = static fn (int $amount): array => $vipFeature(
    'fas fa-heart-pulse',
    'بازیابی سلامتی',
    'Health Regeneration',
    'در هر نوبت بازیابی، ' . number_format($amount) . ' واحد سلامتی برمی‌گردد.',
    'Restore ' . number_format($amount) . ' HP per regeneration tick.'
);
$vipArmorRegen = static fn (int $amount): array => $vipFeature(
    'fas fa-shield-heart',
    'بازیابی زره',
    'Armor Regeneration',
    'در هر نوبت بازیابی، ' . number_format($amount) . ' واحد زره برمی‌گردد.',
    'Restore ' . number_format($amount) . ' armor per regeneration tick.'
);
$vipTeammatesHeal = $vipFeature(
    'fas fa-hand-holding-medical',
    'درمان هم‌تیمی با شلیک',
    'Teammate Healing',
    '۱۰۰٪ دمیج شلیک به هم‌تیمی به سلامتی تبدیل می‌شود؛ حداکثر ۱۰ HP در هر تیر و تا سقف ۱۰۰ HP برای بازیکن درمان‌شونده.',
    '100% of shot damage heals a teammate, capped at 10 HP per shot and 100 HP target health.'
);
$vipWeaponMenu = $vipFeature(
    'fas fa-crosshairs',
    'منوی انتخاب تجهیزات از راند دوم',
    'Round 2 Weapon Loadout',
    'از راند دوم، منوی انتخاب ترکیب‌های ازپیش‌تعریف‌شده اسلحه برای هر دو تیم CT و T فعال می‌شود.',
    'From round 2, choose from preset weapon combinations for both CT and T.'
);
$vipExtraRespawn = $vipFeature(
    'fas fa-rotate',
    'یک Respawn اضافه',
    'Extra Respawn',
    'یک فرصت Respawn اضافه در تنظیمات این سطح در نظر گرفته شده است.',
    'One additional respawn is configured for this tier.'
);

$vipFeaturesData = [
    1 => [
        $vipCash(1000),
        $vipSniperCount,
        $vipTag('Bronze', 1),
    ],
    2 => [
        $vipCash(2000),
        $vipVote,
        $vipDefuser,
        $vipArmor(125),
        $vipSmoke,
        $vipKillscreen,
        $vipVampirism(3),
        $vipSniperCount,
        $vipTag('Silver', 2),
    ],
    3 => [
        $vipSpawnHealth(100),
        $vipVote,
        $vipArmor(150),
        $vipCash(3000),
        $vipDefuser,
        $vipFastReload,
        $vipVampirism(5),
        $vipDamageMultiplier(1.05),
        $vipNoFall,
        $vipZeus,
        $vipFastDefuse(10),
        $vipHealthshot(1),
        $vipSmoke,
        $vipRainbow,
        $vipKillscreen,
        $vipSniperCount,
        $vipTag('Gold', 3),
    ],
    4 => [
        $vipSpawnHealth(150),
        $vipVote,
        $vipCash(3000),
        $vipDefuser,
        $vipBhop,
        $vipFastReload,
        $vipVampirism(10),
        $vipDamageMultiplier(1.10),
        $vipNoFall,
        $vipAntiFlash,
        $vipZeus,
        $vipFastDefuse(15),
        $vipFastPlant,
        $vipGravity(1.05),
        $vipHealthshot(2),
        $vipDecoyTp(1),
        $vipBuyTeamWeapon,
        $vipInfiniteAmmo,
        $vipHealthRegen(2),
        $vipArmorRegen(2),
        $vipTeammatesHeal,
        $vipJumps,
        $vipSmoke,
        $vipRainbow,
        $vipKillscreen,
        $vipSniperCount,
        $vipTag('Platinum', 4),
    ],
    5 => [
        $vipSpawnHealth(180),
        $vipVote,
        $vipCash(5000),
        $vipDefuser,
        $vipBhop,
        $vipFastReload,
        $vipVampirism(15),
        $vipDamageMultiplier(1.20),
        $vipNoFall,
        $vipAntiFlash,
        $vipZeus,
        $vipFastDefuse(20),
        $vipFastPlant,
        $vipGravity(1.10),
        $vipHealthshot(2),
        $vipDecoyTp(1),
        $vipBuyTeamWeapon,
        $vipInfiniteAmmo,
        $vipHealthRegen(4),
        $vipArmorRegen(4),
        $vipWeaponMenu,
        $vipTeammatesHeal,
        $vipJumps,
        $vipSmoke,
        $vipRainbow,
        $vipKillscreen,
        $vipSniperCount,
        $vipTag('Emerald', 5),
    ],
    6 => [
        $vipSpawnHealth(300),
        $vipVote,
        $vipCash(20000),
        $vipDefuser,
        $vipBhop,
        $vipFastReload,
        $vipVampirism(40),
        $vipDamageMultiplier(1.35),
        $vipNoFall,
        $vipAntiFlash,
        $vipZeus,
        $vipFastDefuse(25),
        $vipFastPlant,
        $vipGravity(1.15),
        $vipHealthshot(3),
        $vipDecoyTp(2),
        $vipBuyTeamWeapon,
        $vipInfiniteAmmo,
        $vipHealthRegen(15),
        $vipArmorRegen(15),
        $vipWeaponMenu,
        $vipExtraRespawn,
        $vipTeammatesHeal,
        $vipJumps,
        $vipSmoke,
        $vipRainbow,
        $vipKillscreen,
        $vipSniperCount,
        $vipTag('Diamond', 6),
    ],
];

$vipModeDisabled = [
    'Competitive', 'Practice', 'Wingman', 'Deathmatch', 'Valve Competitive',
    'Valve Wingman', 'Valve Deathmatch', 'Deathmatch FFA', 'Retakes',
    'Valve Retakes', 'Executes', 'Prefire', '1v1 Arenas', 'Aim'
];
$vipModeEnabled = [
    'AWP', 'AWP Aim', 'ScoutzKnivez', 'Gun Game', 'One in the Chamber',
    'Battle Royale', 'Valve Arms Race', 'Competitive 45°'
];

$vipCurrentLevel = max(0, (int) ($userVip['vip_level'] ?? 0));
$vipActive = !empty($userVip['vip_active']);
$vipExpiryLabel = '—';

if ($vipActive && !empty($userVip['vip_permanent'])) {
    $vipExpiryLabel = 'دائمی';
} elseif ($vipActive && !empty($userVip['vip_expire'])) {
    try {
        $vipExpiry = (new DateTimeImmutable('@' . (int)$userVip['vip_expire']))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $vipExpiryLabel = $vipExpiry->format('Y/m/d H:i');
    } catch (Throwable $e) {
        $vipExpiryLabel = 'نامشخص';
    }
}

$vipHasActiveMembership = $vipActive && $vipCurrentLevel > 0;
$vipCurrentName = $vipNames[$vipCurrentLevel] ?? 'نامشخص';
$vipCurrentStatus = !$isLoggedIn
    ? 'برای مشاهده وضعیت وارد شوید'
    : ($vipStatusUnavailable
        ? 'وضعیت VIPCore فعلاً در دسترس نیست'
        : ($vipHasActiveMembership ? 'اشتراک فعال' : 'بدون اشتراک فعال'));
$vipLockedExpanded = $vipHasActiveMembership
    && in_array($vipCurrentLevel, [4, 5, 6], true);
?>
<main
    class="vip-page"
    id="vipStorefront"
    data-vip-has-active="<?= $vipHasActiveMembership ? '1' : '0' ?>"
    data-vip-current-level="<?= $vipCurrentLevel ?>"
>
    <header class="vip-store-hero">
        <div class="vip-store-hero-icon" aria-hidden="true"><i class="fas fa-crown"></i></div>
        <div class="vip-store-hero-copy">
            <span class="vip-store-eyebrow">FRAGCITY MEMBERSHIP STORE</span>
            <h1>عضویت VIP فرگ‌سیتی</h1>
            <p>سطح موردنظرت را انتخاب کن؛ جزئیات و قابلیت‌های همان سطح در ادامه نمایش داده می‌شود.</p>
        </div>
        <div class="vip-store-hero-aside">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            <span>۶ سطح VIP · ۲ سطح فعال برای کاربران</span>
        </div>
    </header>

    <section class="vip-current-strip" aria-label="وضعیت اشتراک فعلی">
        <div class="vip-current-icon" aria-hidden="true"><i class="fas fa-id-badge"></i></div>
        <div class="vip-current-copy">
            <span>وضعیت اشتراک شما</span>
            <strong><?= htmlspecialchars($vipCurrentName, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <span class="vip-current-state <?= $vipHasActiveMembership ? 'is-active' : 'is-inactive' ?>">
            <i class="fas <?= $vipHasActiveMembership ? 'fa-circle-check' : 'fa-circle-info' ?>" aria-hidden="true"></i>
            <?= htmlspecialchars($vipCurrentStatus, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <div class="vip-current-expiry">
            <span>اعتبار تا</span>
            <strong><?= htmlspecialchars($vipHasActiveMembership ? $vipExpiryLabel : '—', ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
    </section>

    <section class="vip-notice-grid" aria-label="اطلاعیه‌ها و قوانین VIP">
        <aside class="vip-notice vip-notice-modes" role="note">
            <div class="vip-notice-heading">
                <span class="vip-notice-icon" aria-hidden="true"><i class="fas fa-scale-balanced"></i></span>
                <span>
                    <strong>قوانین فعال بودن VIP در مودهای بازی</strong>
                    <small>برای حفظ تعادل رقابتی، VIPCore در مودهای زیر فعال یا غیرفعال می‌شود.</small>
                </span>
            </div>
            <div class="vip-mode-group vip-mode-disabled">
                <strong><i class="fas fa-ban" aria-hidden="true"></i> VIP غیرفعال</strong>
                <div class="vip-mode-chips">
                    <?php foreach ($vipModeDisabled as $mode): ?>
                        <span><?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="vip-mode-group vip-mode-enabled">
                <strong><i class="fas fa-circle-check" aria-hidden="true"></i> VIP فعال</strong>
                <div class="vip-mode-chips">
                    <?php foreach ($vipModeEnabled as $mode): ?>
                        <span><?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <aside class="vip-notice vip-notice-store" role="status">
            <div class="vip-notice-heading">
                <span class="vip-notice-icon" aria-hidden="true"><i class="fas fa-bullhorn"></i></span>
                <span>
                    <strong>اطلاعیه فروش VIP</strong>
                    <small>وضعیت فعلی کاتالوگ و پرداخت</small>
                </span>
            </div>
            <p>Bronze فقط یک سطح Dummy برای تست داخلی است و به کاربران عرضه نمی‌شود. هر حساب Steam می‌تواند یک‌بار Silver را به مدت ۷ روز رایگان دریافت کند؛ Gold سطح پولی است. Platinum، Emerald و Diamond فعلاً برای خرید فعال نیستند.</p>
            <p class="vip-notice-store-warning"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> هدیه Silver فقط برای حساب بدون VIP فعال قابل دریافت است و پس از استفاده، حتی بعد از پایان ۷ روز، دوباره قابل دریافت نخواهد بود.</p>
        </aside>
    </section>

    <section class="vip-notice-grid vip-free-trial-grid" aria-label="هدیه یک‌هفته‌ای VIP Silver">
        <aside class="vip-notice vip-notice-store vip-free-trial-card">
            <div class="vip-free-trial-copy">
                <div class="vip-notice-heading">
                    <span class="vip-notice-icon" aria-hidden="true"><i class="fas fa-gift"></i></span>
                    <span>
                        <strong>هدیه خوش‌آمدگویی FragCity</strong>
                        <small>ONE-TIME SILVER MEMBERSHIP · 7 DAYS</small>
                    </span>
                </div>
                <p>با حساب Steam متصل به FragCity، یک‌بار عضویت Silver را برای ۷ روز فعال کن. سطح و مدت از سمت سرور تعیین می‌شوند و قابل تغییر از مرورگر نیستند.</p>
            </div>
            <div class="vip-free-trial-actions">
                <?php if (!$isLoggedIn): ?>
                    <a class="vip-wallet-shortcut" href="/login/" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal">
                        <i class="fab fa-steam" aria-hidden="true"></i>
                        ورود با Steam برای دریافت هدیه
                    </a>
                <?php elseif ($vipStatusUnavailable || $vipFreeTrialStatusUnavailable): ?>
                    <button type="button" class="vip-test-purchase-button" disabled>بررسی وضعیت VIP فعلاً ممکن نیست</button>
                    <small class="vip-test-purchase-note">سرویس VIPCore یا جدول ثبت هدیه در دسترس نیست.</small>
                <?php elseif (!empty($vipFreeTrialStatus['claimed'])): ?>
                    <span class="vip-free-trial-state is-claimed"><i class="fas fa-check-circle" aria-hidden="true"></i> هدیه Silver قبلاً دریافت شده است.</span>
                    <small class="vip-test-purchase-note">هر SteamID64 فقط یک‌بار می‌تواند از این هدیه استفاده کند.</small>
                <?php elseif ($vipHasActiveMembership || !empty($vipFreeTrialStatus['blocked_by_active_membership'])): ?>
                    <span class="vip-free-trial-state is-blocked"><i class="fas fa-lock" aria-hidden="true"></i> این حساب VIP فعال دارد.</span>
                    <small class="vip-test-purchase-note">برای دریافت هدیه، حساب نباید اشتراک VIP فعال داشته باشد.</small>
                <?php elseif (!empty($vipFreeTrialStatus['available'])): ?>
                    <button
                        type="button"
                        class="vip-test-purchase-button"
                        data-vip-free-claim
                        data-csrf-token="<?= htmlspecialchars((string)($_SESSION['auth_csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <i class="fas fa-gift" aria-hidden="true"></i>
                        دریافت Silver رایگان · ۷ روز
                    </button>
                    <p class="vip-test-purchase-feedback" data-vip-free-feedback role="status" aria-live="polite" hidden></p>
                <?php else: ?>
                    <button type="button" class="vip-test-purchase-button" disabled>هدیه فعلاً قابل دریافت نیست</button>
                <?php endif; ?>
            </div>
        </aside>
    </section>

    <div class="vip-catalog-heading">
        <div>
            <span class="vip-store-eyebrow">CHOOSE YOUR TIER</span>
            <h2>سطح‌های VIP</h2>
            <p>هر سطح یک کارت مستقل دارد؛ برای دیدن قابلیت‌ها، کارت موردنظر را انتخاب کن.</p>
        </div>
        <?php if ($isLoggedIn): ?>
            <button type="button" class="vip-wallet-shortcut" data-fc-wallet-charge-open>
                <i class="fas fa-wallet" aria-hidden="true"></i>
                شارژ کیف پول
            </button>
        <?php else: ?>
            <a class="vip-wallet-shortcut" href="/login/" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal">
                <i class="fas fa-wallet" aria-hidden="true"></i>
                ورود برای شارژ کیف پول
            </a>
        <?php endif; ?>
    </div>

    <section class="vip-plans-grid" aria-label="سطح‌های فعال و عمومی VIP">
        <?php foreach ($vipLevels as $lvl => $tier): ?>
            <?php
                $package = is_array($vipPurchasePackages[(string) $lvl] ?? null)
                    ? $vipPurchasePackages[(string) $lvl]
                    : [];
                $rawPrice = $package['price_toman'] ?? null;
                $rawDuration = $package['duration_days'] ?? null;
                $tierPrice = is_numeric($rawPrice) && (int) $rawPrice > 0
                    ? (int) $rawPrice
                    : null;
                $tierDuration = is_numeric($rawDuration) && (int) $rawDuration > 0
                    ? (int) $rawDuration
                    : null;
                $tierPriceLabel = (int)$lvl === 2
                    ? 'هدیه رایگان · ۷ روز'
                    : (!$tier['available']
                        ? 'غیرفعال'
                        : ($tierPrice !== null ? number_format($tierPrice) . ' ' . $vipCurrency : 'قیمت تعیین نشده'));
                $tierDurationLabel = (int)$lvl === 2
                    ? 'فقط یک‌بار برای هر SteamID64'
                    : (!$tier['available']
                        ? 'Not available'
                        : ($tierDuration !== null ? number_format($tierDuration) . ' روز' : 'مدت اشتراک مشخص نشده'));
                $isCurrentTier = $vipHasActiveMembership && $vipCurrentLevel === (int) $lvl;
            ?>
            <?php if ((int) $lvl === 4): ?>
            </section>

            <section class="vip-locked-section" aria-label="سطح‌های VIP غیرفعال">
                <button
                    type="button"
                    class="vip-locked-toggle"
                    id="vipLockedToggle"
                    aria-expanded="<?= $vipLockedExpanded ? 'true' : 'false' ?>"
                    aria-controls="vipInactivePlans"
                >
                    <span class="vip-locked-toggle-emblem" aria-hidden="true">
                        <i class="fas fa-lock"></i>
                    </span>
                    <span class="vip-locked-toggle-copy">
                        <strong>پلن‌های غیرفعال</strong>
                        <small>Platinum · Emerald · Diamond</small>
                    </span>
                    <span class="vip-locked-toggle-status">غیرفعال برای خرید</span>
                    <span class="vip-locked-toggle-count">۳ سطح</span>
                    <i class="fas fa-chevron-down vip-locked-chevron" aria-hidden="true"></i>
                </button>

                <div
                    class="vip-plans-grid vip-inactive-grid"
                    id="vipInactivePlans"
                    aria-hidden="<?= $vipLockedExpanded ? 'false' : 'true' ?>"
                    <?= $vipLockedExpanded ? '' : 'hidden' ?>
                >
            <?php endif; ?>
            <div class="vip-plan-item">
                <article
                class="vip-plan-card<?= $isCurrentTier ? ' is-current' : '' ?><?= !$tier['available'] ? ' is-locked' : '' ?>"
                data-vip-plan
                data-level="<?= (int) $lvl ?>"
                data-vip-name="<?= htmlspecialchars($tier['name'], ENT_QUOTES, 'UTF-8') ?>"
                data-vip-summary="<?= htmlspecialchars($tier['summary'], ENT_QUOTES, 'UTF-8') ?>"
                data-vip-summary-fa="<?= htmlspecialchars($tier['summary_fa'], ENT_QUOTES, 'UTF-8') ?>"
                data-vip-available="<?= $tier['available'] ? '1' : '0' ?>"
                role="button"
                tabindex="0"
                aria-pressed="false"
                aria-expanded="false"
                aria-controls="vipFeaturePanel"
                aria-label="<?= htmlspecialchars('Select ' . $tier['name'] . ' VIP to view its features', ENT_QUOTES, 'UTF-8') ?>"
                data-price-label="<?= htmlspecialchars($tierPriceLabel, ENT_QUOTES, 'UTF-8') ?>"
                data-duration-label="<?= htmlspecialchars($tierDurationLabel, ENT_QUOTES, 'UTF-8') ?>"
                style="--vip-tier-color: <?= htmlspecialchars($tier['color'], ENT_QUOTES, 'UTF-8') ?>;"
            >
                <div class="vip-plan-topline">
                    <span class="vip-plan-index">FRAGCITY / VIP 0<?= (int) $lvl ?></span>
                    <?php if ($isCurrentTier): ?>
                        <span class="vip-plan-current"><i class="fas fa-check" aria-hidden="true"></i> پلن فعلی</span>
                    <?php elseif (!$tier['available']): ?>
                        <span class="vip-plan-locked"><i class="fas fa-lock" aria-hidden="true"></i> قفل‌شده</span>
                    <?php else: ?>
                        <span class="vip-plan-public">PUBLIC TIER</span>
                    <?php endif; ?>
                </div>

                <div class="vip-plan-title-row">
                    <span class="vip-tier-emblem">
                        <i class="<?= htmlspecialchars($tier['fa_icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </span>
                    <div>
                        <h3><?= htmlspecialchars($tier['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <span>VIP TIER 0<?= (int) $lvl ?></span>
                    </div>
                </div>

                <p class="vip-plan-summary">
                    <span lang="fa" dir="rtl"><?= htmlspecialchars($tier['summary_fa'], ENT_QUOTES, 'UTF-8') ?></span>
                    <small lang="en" dir="ltr"><?= htmlspecialchars($tier['summary'], ENT_QUOTES, 'UTF-8') ?></small>
                </p>

                <?php $tierFeatures = $vipFeaturesData[$lvl] ?? []; ?>
                <div class="vip-plan-preview-heading">
                    <strong>ویژگی‌های شاخص <span>/ KEY PERKS</span></strong>
                    <small><?= count($tierFeatures) ?> قابلیت / PERKS</small>
                </div>
                <ul class="vip-plan-preview">
                    <?php foreach (array_slice($tierFeatures, 0, 3) as $previewFeature): ?>
                        <li>
                            <i class="<?= htmlspecialchars($previewFeature['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            <span>
                                <strong lang="fa" dir="rtl"><?= htmlspecialchars($previewFeature['name_fa'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small lang="en" dir="ltr"><?= htmlspecialchars($previewFeature['name_en'], ENT_QUOTES, 'UTF-8') ?></small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="vip-plan-price">
                    <span><?= $tier['available'] ? 'قیمت پلن' : 'وضعیت' ?></span>
                    <strong><?= htmlspecialchars($tierPriceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($tierDurationLabel, ENT_QUOTES, 'UTF-8') ?></small>
                </div>

                <span class="vip-plan-select" data-vip-select aria-hidden="true">
                    مشاهده قابلیت‌ها
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </span>
                </article>
            </div>
        <?php endforeach; ?>
                </div>
            </section>

    <span id="vipDetailPanelHome" hidden aria-hidden="true"></span>
    <section class="vip-feature-panel" id="vipFeaturePanel" aria-labelledby="vipDetailTierName" aria-live="polite" hidden>
        <div class="vip-feature-panel-head">
            <div class="vip-feature-panel-title">
                <span class="vip-store-eyebrow">TIER DETAILS</span>
                <h2 id="vipDetailTierName">Bronze</h2>
                <p id="vipDetailTierSummaryFa" lang="fa" dir="rtl">شروع اقتصادی با قابلیت‌های پایه VIP.</p>
                <small id="vipDetailTierSummaryEn" lang="en" dir="ltr">A practical entry tier with core VIP perks.</small>
            </div>
            <span class="vip-detail-state" id="vipDetailTierState">PUBLIC TIER</span>
        </div>
        <div class="vip-detail-feature-grid" id="vipDetailFeatures"></div>
        <?php if ($isVipTestAdmin): ?>
        <div class="vip-test-purchase" data-vip-test-purchase-box>
                <button
                    type="button"
                    class="vip-test-purchase-button"
                    data-vip-test-purchase
                    data-csrf-token="<?= htmlspecialchars((string)($_SESSION['auth_csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-level="1"
                    disabled
                >
                    ثبت آزمایشی VIP بدون پرداخت
                </button>
            <small class="vip-test-purchase-note">حالت آزمایشی فقط برای حساب مدیر و SteamIDهای مجاز در Linux فعال است.</small>
            <p class="vip-test-purchase-feedback" data-vip-test-feedback role="status" aria-live="polite" hidden></p>
        </div>
        <?php endif; ?>
    </section>

    <?php foreach ($vipFeaturesData as $featureLevel => $features): ?>
        <template data-vip-feature-template data-level="<?= (int) $featureLevel ?>">
            <ul class="vip-detail-features-list">
                <?php foreach ($features as $feature): ?>
                    <li>
                        <span class="vip-feature-icon" aria-hidden="true">
                            <i class="<?= htmlspecialchars($feature['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </span>
                        <span class="vip-feature-copy">
                            <strong lang="fa" dir="rtl"><?= htmlspecialchars($feature['name_fa'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small class="vip-feature-name-en" lang="en" dir="ltr"><?= htmlspecialchars($feature['name_en'], ENT_QUOTES, 'UTF-8') ?></small>
                            <span class="vip-feature-description-fa" lang="fa" dir="rtl"><?= htmlspecialchars($feature['desc_fa'], ENT_QUOTES, 'UTF-8') ?></span>
                            <small class="vip-feature-description-en" lang="en" dir="ltr"><?= htmlspecialchars($feature['desc_en'], ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </template>
    <?php endforeach; ?>


</main>


