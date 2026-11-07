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
$vipSelectableLevels = [1, 2, 3, 4, 5, 6]; // All VIPCore tiers are inspectable; purchase eligibility remains separate.

$vipLevels = [
    1 => [
        'name' => 'Bronze',
        'fa_icon' => 'fas fa-medal',
        'color' => '#CD7F32',
        'available' => true,
        'summary' => 'The base FragCity VIP membership for official servers.',
        'summary_fa' => 'پلن پایه VIP فرگ‌سیتی برای استفاده در تمام سرورهای رسمی.',
        'purchase_mode' => 'paid',
    ],
    2 => [
        'name' => 'Silver',
        'fa_icon' => 'fas fa-medal',
        'color' => '#C0C0C0',
        'available' => true,
        'summary' => 'Paid Silver membership with a separate one-time three-day welcome trial.',
        'summary_fa' => 'عضویت پولی Silver همراه با هدیه خوش‌آمدگویی رایگان سه‌روزه که فقط یک‌بار قابل دریافت است.',
        'purchase_mode' => 'paid',
    ],
    3 => [
        'name' => 'Gold',
        'fa_icon' => 'fas fa-crown',
        'color' => '#F5B042',
        'available' => true,
        'summary' => 'Paid VIP membership with expanded in-game perks.',
        'summary_fa' => 'پلن پولی با دسترسی به قابلیت‌های کامل‌تر VIP.',
        'purchase_mode' => 'paid',
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

$vipComparisonGroups = [];
$vipComparisonCategoryTitles = [
    'economy' => 'اقتصاد و امکانات عمومی',
    'combat' => 'نبرد و آسیب',
    'survival' => 'بقا و بازیابی',
    'equipment' => 'تجهیزات و سلاح',
    'movement' => 'حرکت و جابه‌جایی',
    'cosmetics' => 'ظاهر و شخصی‌سازی',
    'community' => 'امکانات اجتماعی',
];
$vipComparisonCategory = static function (string $nameEn): string {
    $name = strtolower($nameEn);
    if (str_starts_with($name, 'vip star tag') || str_contains($name, 'kill screen')
        || str_contains($name, 'smoke color') || str_contains($name, 'rainbow')) {
        return 'cosmetics';
    }
    if (str_contains($name, 'vampirism') || str_contains($name, 'damage')
        || str_contains($name, 'sniper') || str_contains($name, 'fast defuse')
        || str_contains($name, 'fast plant')) {
        return 'combat';
    }
    if (str_contains($name, 'spawn health') || str_contains($name, 'spawn armor')
        || str_contains($name, 'regeneration') || str_contains($name, 'healthshot')
        || str_contains($name, 'respawn') || str_contains($name, 'defuse kit')) {
        return 'survival';
    }
    if (str_contains($name, 'bunnyhop') || str_contains($name, 'gravity')
        || str_contains($name, 'decoy teleport') || str_contains($name, 'air jump')
        || str_contains($name, 'fall damage')) {
        return 'movement';
    }
    if (str_contains($name, 'weapon') || str_contains($name, 'zeus')
        || str_contains($name, 'ammo')) {
        return 'equipment';
    }
    if (str_contains($name, 'playervotes')) {
        return 'community';
    }
    return 'economy';
};

foreach ($vipFeaturesData as $featureLevel => $features) {
    foreach ($features as $feature) {
        $nameEn = (string) ($feature['name_en'] ?? 'VIP Feature');
        $featureKey = str_starts_with($nameEn, 'VIP Star Tag') ? 'vip_star_tag' : $nameEn;
        $category = $vipComparisonCategory($nameEn);
        if (!isset($vipComparisonGroups[$category])) {
            $vipComparisonGroups[$category] = ['rows' => []];
        }
        if (!isset($vipComparisonGroups[$category]['rows'][$featureKey])) {
            $vipComparisonGroups[$category]['rows'][$featureKey] = [
                'icon' => (string) ($feature['icon'] ?? 'fas fa-check'),
                'name_fa' => str_starts_with($nameEn, 'VIP Star Tag') ? 'تگ ستاره‌ای VIP' : (string) ($feature['name_fa'] ?? $nameEn),
                'name_en' => str_starts_with($nameEn, 'VIP Star Tag') ? 'VIP Star Tag' : $nameEn,
                'tiers' => [],
            ];
        }
        $vipComparisonGroups[$category]['rows'][$featureKey]['tiers'][(int) $featureLevel] = [
            'desc_fa' => (string) ($feature['desc_fa'] ?? ''),
            'desc_en' => (string) ($feature['desc_en'] ?? ''),
        ];
    }
}

$vipAllowedPurchaseDurations = [7, 15, 30, 60, 90];
$vipPurchasePriceMap = [];
foreach ($vipLevels as $level => $tier) {
    if (empty($tier['available']) || ($tier['purchase_mode'] ?? '') !== 'paid') {
        continue;
    }
    $package = is_array($vipPurchasePackages[(string) $level] ?? null)
        ? $vipPurchasePackages[(string) $level]
        : [];
    foreach ($vipAllowedPurchaseDurations as $days) {
        $durationPackage = is_array($package['durations'][(string) $days] ?? null)
            ? $package['durations'][(string) $days]
            : [];
        $rawPrice = $durationPackage['price_toman'] ?? null;
        $validatedPrice = filter_var($rawPrice, FILTER_VALIDATE_INT);
        $price = $validatedPrice !== false && $validatedPrice > 0 && $validatedPrice <= 1000000000000
            ? $validatedPrice
            : null;
        $vipPurchasePriceMap[(string) $level][(string) $days] = $price;
    }
}
$vipCheckoutIntegrationReady = false; // Fail closed until order creation, ZarinPal verification and paid VIPCore provisioning are implemented.
?>
<main
    class="vip-page"
    id="vipStorefront"
    data-vip-has-active="<?= $vipHasActiveMembership ? '1' : '0' ?>"
    data-vip-current-level="<?= $vipCurrentLevel ?>"
    data-vip-status-unavailable="<?= $vipStatusUnavailable ? '1' : '0' ?>"
    data-vip-logged-in="<?= $isLoggedIn ? '1' : '0' ?>"
    data-vip-test-admin="<?= $isVipTestAdmin ? '1' : '0' ?>"
    data-vip-prices="<?= htmlspecialchars(json_encode($vipPurchasePriceMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-vip-currency="<?= htmlspecialchars($vipCurrency, ENT_QUOTES, 'UTF-8') ?>"
    data-vip-checkout-ready="<?= $vipCheckoutIntegrationReady ? '1' : '0' ?>"
>
    <header class="vip-store-hero">
        <div class="vip-store-hero-icon" aria-hidden="true"><i class="fas fa-crown"></i></div>
        <div class="vip-store-hero-copy">
            <span class="vip-store-eyebrow">FRAGCITY MEMBERSHIP STORE</span>
            <h1>عضویت VIP فرگ‌سیتی</h1>
            <p>عضویت مرکزی FragCity را برای تمام سرورهای رسمی در نظر گرفته‌ایم؛ وضعیت و اعتبار VIP را همین‌جا مدیریت کن.</p>
        </div>
        <div class="vip-store-hero-aside">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            <span>یک عضویت مرکزی · تمام سرورهای رسمی</span>
        </div>
    </header>

    <section class="vip-current-strip" id="vipMembershipStatusBar" aria-label="وضعیت اشتراک فعلی">
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

    <section class="vip-notice-grid vip-free-trial-grid" aria-label="هدیه سه‌روزه VIP Silver">
        <aside class="vip-notice vip-notice-store vip-free-trial-card">
            <div class="vip-free-trial-copy">
                <div class="vip-notice-heading">
                    <span class="vip-notice-icon" aria-hidden="true"><i class="fas fa-gift"></i></span>
                    <span>
                        <strong>هدیه خوش‌آمدگویی FragCity</strong>
                        <small>ONE-TIME SILVER MEMBERSHIP · 3 DAYS</small>
                    </span>
                </div>
                <p>با حساب Steam متصل به FragCity، یک‌بار عضویت Silver را برای ۳ روز فعال کن. سطح و مدت از سمت سرور تعیین می‌شوند و قابل تغییر از مرورگر نیستند.</p>
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
                <?php elseif (!empty($vipFreeTrialStatus['blocked_by_active_membership'])): ?>
                    <span class="vip-free-trial-state is-blocked"><i class="fas fa-lock" aria-hidden="true"></i> این حساب اشتراک فعال Silver یا بالاتر دارد.</span>
                    <small class="vip-test-purchase-note">برای دریافت هدیه، حساب نباید اشتراک VIP فعال داشته باشد.</small>
                <?php elseif (!empty($vipFreeTrialStatus['available'])): ?>
                    <button
                        type="button"
                        class="vip-test-purchase-button"
                        data-vip-free-claim
                        data-csrf-token="<?= htmlspecialchars((string)($_SESSION['auth_csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <i class="fas fa-gift" aria-hidden="true"></i>
                        دریافت Silver رایگان · ۳ روز
                    </button>
                    <p class="vip-test-purchase-feedback" data-vip-free-feedback role="status" aria-live="polite" hidden></p>
                <?php else: ?>
                    <button type="button" class="vip-test-purchase-button" disabled>هدیه فعلاً قابل دریافت نیست</button>
                <?php endif; ?>
            </div>
        </aside>
    </section>







    <div class="vip-store-layout">
        <div class="vip-store-main-column">

            <section class="vip-network-banner" aria-label="محدوده عضویت مرکزی FragCity">
                <span class="vip-network-banner-icon" aria-hidden="true"><i class="fas fa-globe"></i></span>
                <div class="vip-network-banner-copy">
                    <span class="vip-store-eyebrow">ONE ACCOUNT · ALL OFFICIAL SERVERS</span>
                    <h2>یک عضویت برای تمام سرورهای رسمی FragCity</h2>
                    <p>هدف سیستم مرکزی این است که VIP به حساب Steam شما متصل باشد؛ نه اینکه برای هر سرور رسمی اشتراک جداگانه بخرید.</p>
                </div>
                <div class="vip-network-banner-points" aria-label="ویژگی‌های عضویت مرکزی">
                    <span><i class="fas fa-steam" aria-hidden="true"></i> یک حساب Steam</span>
                    <span><i class="fas fa-layer-group" aria-hidden="true"></i> یک عضویت مرکزی</span>
                    <span><i class="fas fa-server" aria-hidden="true"></i> تمام سرورهای رسمی</span>
                </div>
            </section>

    <section class="vip-plans-grid vip-public-plans-grid" aria-label="سطح‌های فعال و عمومی VIP">
        <?php foreach ($vipLevels as $lvl => $tier): ?>
            <?php
                $levelPrices = $vipPurchasePriceMap[(string) $lvl] ?? [];
                $configuredPrices = array_values(array_filter(
                    $levelPrices,
                    static fn ($price): bool => is_int($price) && $price > 0
                ));
                $tierPriceLabel = !$tier['available']
                    ? 'فعلاً قابل خرید نیست'
                    : (!empty($configuredPrices)
                        ? 'از ' . number_format(min($configuredPrices)) . ' ' . $vipCurrency
                        : ((int)$lvl === 2
                            ? 'هدیه ۳ روزه · قیمت اشتراک تعیین نشده'
                            : 'قیمت‌گذاری تکمیل نشده'));
                $tierDurationLabel = !$tier['available']
                    ? 'فعلاً برای خرید فعال نیست'
                    : ((int)$lvl === 2
                        ? '۷، ۱۵، ۳۰، ۶۰ یا ۹۰ روز · هدیه ۳ روزه یک‌باره'
                        : '۷، ۱۵، ۳۰، ۶۰ یا ۹۰ روز');
                $isCurrentTier = $vipHasActiveMembership && $vipCurrentLevel === (int) $lvl;
                $isSelectableTier = in_array((int) $lvl, $vipSelectableLevels, true);
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
                        <strong>پلن‌های دیگر</strong>
                        <small>Platinum · Emerald · Diamond</small>
                    </span>
                    <span class="vip-locked-toggle-status">قابل انتخاب · خرید فعلاً غیرفعال</span>
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
                data-vip-selectable="<?= $isSelectableTier ? '1' : '0' ?>"
                data-vip-purchase-mode="<?= htmlspecialchars((string)($tier['purchase_mode'] ?? 'none'), ENT_QUOTES, 'UTF-8') ?>"
                data-vip-current="<?= $isCurrentTier ? '1' : '0' ?>"
                data-vip-color="<?= htmlspecialchars($tier['color'], ENT_QUOTES, 'UTF-8') ?>"
                data-price-label="<?= htmlspecialchars($tierPriceLabel, ENT_QUOTES, 'UTF-8') ?>"
                data-duration-label="<?= htmlspecialchars($tierDurationLabel, ENT_QUOTES, 'UTF-8') ?>"
                style="--vip-tier-color: <?= htmlspecialchars($tier['color'], ENT_QUOTES, 'UTF-8') ?>; --vip-tier-order: <?= (int)$lvl ?>;"
            >
                <div class="vip-plan-topline">
                    <span class="vip-plan-index">FRAGCITY / VIP 0<?= (int) $lvl ?></span>
                    <?php if ($isCurrentTier): ?>
                        <span class="vip-plan-current"><i class="fas fa-check" aria-hidden="true"></i> پلن فعلی</span>
                    <?php elseif (!$tier['available']): ?>
                        <span class="vip-plan-locked"><i class="fas fa-lock" aria-hidden="true"></i> قفل‌شده</span>
                    <?php elseif ((int)$lvl === 2): ?>
                        <span class="vip-plan-public">PAID TIER · FREE TRIAL GIFT</span>
                    <?php else: ?>
                        <span class="vip-plan-public">PAID TIER</span>
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

                <div class="vip-plan-price<?= !$tier['available'] ? ' is-locked' : ' is-paid' ?>">
                    <span class="vip-plan-price-label">
                        <i class="fas <?= !$tier['available'] ? 'fa-lock' : ((int)$lvl === 2 ? 'fa-gift' : 'fa-tag') ?>" aria-hidden="true"></i>
                        <?= !$tier['available'] ? 'وضعیت پلن' : ((int)$lvl === 2 ? 'قیمت اشتراک و هدیه' : 'قیمت پلن') ?>
                    </span>
                    <strong><?= htmlspecialchars($tierPriceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($tierDurationLabel, ENT_QUOTES, 'UTF-8') ?></small>
                </div>

                <div class="vip-plan-actions">
                    <button
                        type="button"
                        class="vip-plan-select"
                        data-vip-select-plan
                        aria-pressed="false"
                        <?= !$isSelectableTier ? 'disabled' : '' ?>
                    >
                        <?= $isCurrentTier
                            ? 'مشاهده پلن فعال'
                            : ($isSelectableTier
                                ? 'انتخاب این سطح'
                                : 'فعلاً برای خرید فعال نیست') ?>
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    </button>
                    <button
                        type="button"
                        class="vip-plan-feature-button"
                        data-vip-open-comparison
                        aria-haspopup="dialog"
                        aria-controls="vipComparisonModal"
                    >
                        <i class="fas fa-list-check" aria-hidden="true"></i>
                        ویژگی‌های این سطح
                    </button>
                </div>
                </article>
            </div>
        <?php endforeach; ?>
                </div>
            </section>

    <section
        class="vip-duration-selector"
        id="vipPurchaseDurationSelector"
        data-vip-duration-selector
        aria-labelledby="vipPurchaseDurationTitle"
        aria-hidden="true"
        hidden
    >
        <div class="vip-duration-selector-heading">
            <span class="vip-store-eyebrow">CHOOSE SUBSCRIPTION DURATION</span>
            <h3 id="vipPurchaseDurationTitle">مدت اشتراک را انتخاب کن</h3>
            <p>مدت اشتراک Bronze، Silver یا Gold را انتخاب کن؛ فاکتور سمت چپ با قیمت رسمی FragCity به‌روزرسانی می‌شود.</p>
        </div>
        <div class="vip-duration-options" role="group" aria-label="مدت اشتراک">
            <button type="button" class="vip-duration-option" data-vip-duration-option data-duration-days="7" data-duration-label="۱ هفته" aria-pressed="false">
                <span class="vip-duration-code">07 DAYS</span><strong>۱ هفته</strong><small data-duration-price>قیمت تعیین نشده</small>
            </button>
            <button type="button" class="vip-duration-option" data-vip-duration-option data-duration-days="15" data-duration-label="۱۵ روز" aria-pressed="false">
                <span class="vip-duration-code">15 DAYS</span><strong>۱۵ روز</strong><small data-duration-price>قیمت تعیین نشده</small>
            </button>
            <button type="button" class="vip-duration-option" data-vip-duration-option data-duration-days="30" data-duration-label="۳۰ روز" aria-pressed="false">
                <span class="vip-duration-code">30 DAYS</span><strong>۳۰ روز</strong><small data-duration-price>قیمت تعیین نشده</small>
            </button>
            <button type="button" class="vip-duration-option" data-vip-duration-option data-duration-days="60" data-duration-label="۶۰ روز" aria-pressed="false">
                <span class="vip-duration-code">60 DAYS</span><strong>۶۰ روز</strong><small data-duration-price>قیمت تعیین نشده</small>
            </button>
            <button type="button" class="vip-duration-option" data-vip-duration-option data-duration-days="90" data-duration-label="۹۰ روز" aria-pressed="false">
                <span class="vip-duration-code">90 DAYS</span><strong>۹۰ روز</strong><small data-duration-price>قیمت تعیین نشده</small>
            </button>
        </div>
        <p class="vip-duration-selection-status" data-vip-duration-selection-status role="status" aria-live="polite">
            ابتدا Bronze، Silver یا Gold را انتخاب کن و سپس مدت موردنظرت را مشخص کن.
        </p>
    </section>

    <?php if ($isVipTestAdmin): ?>
        <div class="vip-test-purchase" data-vip-test-purchase-box hidden>
            <button type="button" class="vip-test-purchase-button" data-vip-test-purchase data-csrf-token="<?= htmlspecialchars((string)($_SESSION['auth_csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-level="1" disabled>
                ثبت آزمایشی VIP بدون پرداخت
            </button>
            <small class="vip-test-purchase-note">حالت آزمایشی فقط برای حساب مدیر و SteamIDهای مجاز در Linux فعال است.</small>
            <p class="vip-test-purchase-feedback" data-vip-test-feedback role="status" aria-live="polite" hidden></p>
        </div>
    <?php endif; ?>



        </div>

        <aside class="vip-invoice-panel vip-checkout-bar" id="vipOrderSummary" data-vip-invoice aria-label="فاکتور و خلاصه سفارش">
            <div class="vip-invoice-topline">
                <span class="vip-store-eyebrow">FRAGCITY · ORDER SUMMARY</span>
                <span class="vip-invoice-icon" aria-hidden="true"><i class="fas fa-receipt"></i></span>
            </div>

            <h2>خلاصه سفارش</h2>
            <p class="vip-invoice-subtitle">پلن و مدت انتخابی را اینجا بررسی کن؛ فاکتور با انتخاب‌های تو به‌روزرسانی می‌شود.</p>

            <div class="vip-invoice-product">
                <span>پلن انتخاب‌شده</span>
                <strong data-invoice-tier>هنوز پلنی انتخاب نشده</strong>
                <small data-invoice-level>سطح عضویت: —</small>
            </div>

            <p class="vip-invoice-status" data-invoice-status role="status" aria-live="polite">
                برای ساخت فاکتور، یک سطح قابل خرید را انتخاب کن.
            </p>

            <div class="vip-invoice-lines">
                <div class="vip-invoice-line">
                    <span>مدت اشتراک</span>
                    <strong data-invoice-duration>—</strong>
                </div>
            </div>

            <div class="vip-invoice-total">
                <span>مبلغ نهایی</span>
                <strong data-invoice-total>— تومان</strong>
            </div>

            <div class="vip-invoice-actions">
                <button type="button" class="vip-invoice-checkout" data-vip-checkout disabled>
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    پرداخت و فعال‌سازی
                </button>
                <?php if ($isLoggedIn): ?>
                    <button type="button" class="vip-invoice-wallet" data-fc-wallet-charge-open>
                        <i class="fas fa-wallet" aria-hidden="true"></i>
                        کیف پول
                    </button>
                <?php else: ?>
                    <a class="vip-invoice-wallet" href="/login/" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal">
                        <i class="fab fa-steam" aria-hidden="true"></i>
                        ورود با Steam
                    </a>
                <?php endif; ?>
            </div>

            <small class="vip-invoice-checkout-note">پرداخت مستقیم تا تکمیل اتصال سرویس سفارش و تأیید نهایی VIPCore غیرفعال است.</small>
        </aside>
    </div>

    <section class="vip-faq-section" aria-labelledby="vipFaqTitle">
        <header class="vip-faq-heading">
            <span class="vip-store-eyebrow">FRAGCITY · HELP CENTER</span>
            <h2 id="vipFaqTitle">سؤالات متداول VIP</h2>
            <p>عضویت مرکزی FragCity، هدیه Silver و تفاوت آن با VIP مستقل سرورهای اجاره‌ای.</p>
        </header>

        <div class="vip-faq-list">
            <details class="vip-faq-item">
                <summary>آیا برای هر سرور رسمی باید VIP جداگانه بخرم؟</summary>
                <div class="vip-faq-answer">
                    <p>خیر؛ مدل هدف FragCity یک عضویت مرکزی متصل به حساب Steam است که روی تمام سرورهای رسمی FragCity اعتبار داشته باشد. قرار نیست برای جابه‌جایی بین سرورهای رسمی، چند اشتراک جدا تهیه کنی.</p>
                </div>
            </details>

            <details class="vip-faq-item">
                <summary>اگر سرور اجاره‌ای داشته باشم، می‌توانم VIP مخصوص خودم را بفروشم؟</summary>
                <div class="vip-faq-answer">
                    <p>بله؛ این مسیر مستقل از فروشگاه مرکزی FragCity است. VIPCore سرور اجاره‌ای به دیتابیس جداگانه همان مالک متصل می‌شود؛ بنابراین مالک قیمت VIP خودش را مستقل تعیین می‌کند و حتی می‌تواند آن را رایگان عرضه کند. کف قیمت Bronze، Silver و Gold فقط برای فروشگاه و سرورهای رسمی FragCity است و به سرورهای مشتریان تحمیل نمی‌شود. اطلاعات VIP خصوصی هم نباید از دیتابیس مرکزی FragCity خوانده شود. این بخش مسیر توسعه و پیکربندی جداگانه صاحبان سرور است و تا زمان ارائه تنظیماتش در پنل، قابلیت فعال‌شده محسوب نمی‌شود.</p>
                </div>
            </details>

            <details class="vip-faq-item">
                <summary>چطور VIP را از طریق پنل سرور مدیریت یا غیرفعال کنم؟</summary>
                <div class="vip-faq-answer">
                    <p>دستور <bdi dir="ltr">!admin</bdi> را اجرا کنید؛ سپس از مسیر <bdi dir="ltr">Server Manage → Plugins → VIPCore</bdi> پلاگین VIPCore را خاموش کنید. برای غیرفعال‌کردن VIP در سرور، همین کار کافی است.</p>
                </div>
            </details>

            <details class="vip-faq-item">
                <summary>وضعیت سطح‌های Bronze، Silver و Gold و خرید VIP چیست؟</summary>
                <div class="vip-faq-answer">
                    <p>Bronze، Silver و Gold سه سطح قابل‌فروش در فروشگاه رسمی FragCity هستند و قیمت هرکدام بر اساس مدت اشتراک از تنظیمات قیمت‌گذاری خوانده می‌شود. Silver علاوه بر اشتراک پولی، یک هدیه رایگان سه‌روزه و یک‌باره دارد. قیمت‌های این سه سطح، کف قیمت رسمی FragCity هستند. Platinum، Emerald و Diamond نیز برای مشاهده و مقایسه قابل انتخاب‌اند، اما فعلاً برای خرید فعال نیستند. Checkout مستقیم همچنان تا تکمیل اتصال سفارش، پرداخت و فعال‌سازی VIPCore غیرفعال است.</p>
                </div>
            </details>

            <details class="vip-faq-item">
                <summary>شرایط دریافت هدیه رایگان Silver چیست؟</summary>
                <div class="vip-faq-answer">
                    <p>هر حساب Steam فقط یک‌بار می‌تواند هدیه Silver را برای ۳ روز دریافت کند. این هدیه فقط برای حسابی قابل دریافت است که اشتراک فعال Silver یا سطح بالاتر نداشته باشد. داشتن Bronze فعال مانع دریافت هدیه Silver نیست؛ اما پس از یک‌بار دریافت، حتی بعد از پایان سه روز هم امکان دریافت مجدد وجود ندارد.</p>
                </div>
            </details>

            <details class="vip-faq-item">
                <summary>VIP فعال شده اما من هنوز در سرور دسترسی ندارم؛ چه کار کنم؟</summary>
                <div class="vip-faq-answer">
                    <p>به یکی از ادمین‌های سرور اطلاع بده و درخواست کن فهرست VIPها را به‌روزرسانی کند. ادمین باید دستور <code dir="ltr">Player reload</code> را اجرا کند تا فهرست بازخوانی شود.</p>
                </div>
            </details>
        </div>
    </section>

</main>

<div class="vip-comparison-modal" id="vipComparisonModal" hidden aria-hidden="true">
    <div class="vip-comparison-backdrop" data-vip-comparison-close></div>
    <section class="vip-comparison-dialog" id="vipComparisonDialog" role="dialog" aria-modal="true" aria-labelledby="vipComparisonTitle" aria-describedby="vipComparisonDescription" tabindex="-1">
        <header class="vip-comparison-header">
            <div class="vip-comparison-heading">
                <span class="vip-store-eyebrow">FRAGCITY · VIP TIER COMPARISON</span>
                <h2 id="vipComparisonTitle">مقایسه قابلیت‌های VIPCore</h2>
                <p id="vipComparisonDescription">این جدول برای شفافیت قابلیت‌های تعریف‌شده است؛ خرید Level جداگانه در فروشگاه FragCity وجود ندارد.</p>
                <div class="vip-comparison-selected-tier" data-vip-comparison-selected>
                    <span class="vip-comparison-selected-dot" aria-hidden="true"></span>
                    <span data-vip-comparison-selected-name>مقایسه همه سطح‌ها</span>
                </div>
            </div>
            <button type="button" class="vip-comparison-close" data-vip-comparison-close aria-label="بستن پنجره مقایسه">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="vip-comparison-table-scroll" role="region" aria-label="جدول مقایسه شش سطح VIP" tabindex="0">
            <table class="vip-comparison-table">
                <thead>
                    <tr>
                        <th class="vip-comparison-feature-heading" scope="col">قابلیت و مشخصات</th>
                        <?php foreach ($vipLevels as $level => $tier): ?>
                            <th scope="col" data-comparison-tier="<?= (int)$level ?>" style="--vip-tier-color: <?= htmlspecialchars($tier['color'], ENT_QUOTES, 'UTF-8') ?>;">
                                <span class="vip-comparison-tier-dot" aria-hidden="true"></span>
                                <strong><?= htmlspecialchars($tier['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small>VIP 0<?= (int)$level ?></small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vipComparisonGroups as $category => $group): ?>
                        <tr class="vip-comparison-category">
                            <th colspan="7" scope="colgroup"><?= htmlspecialchars($vipComparisonCategoryTitles[$category] ?? $category, ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                        <?php foreach ($group['rows'] as $row): ?>
                            <tr>
                                <th class="vip-comparison-feature" scope="row">
                                    <span class="vip-comparison-feature-icon" aria-hidden="true"><i class="<?= htmlspecialchars($row['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                    <span>
                                        <strong><?= htmlspecialchars($row['name_fa'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small lang="en" dir="ltr"><?= htmlspecialchars($row['name_en'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </span>
                                </th>
                                <?php foreach ($vipLevels as $level => $tier): ?>
                                    <td class="<?= isset($row['tiers'][$level]) ? 'has-vip-feature' : 'no-vip-feature' ?>" style="--vip-tier-color: <?= htmlspecialchars($tier['color'], ENT_QUOTES, 'UTF-8') ?>;">
                                        <?php if (isset($row['tiers'][$level])): ?>
                                            <span class="vip-comparison-check" aria-label="فعال"><i class="fas fa-check" aria-hidden="true"></i></span>
                                            <small lang="fa" dir="rtl"><?= htmlspecialchars($row['tiers'][$level]['desc_fa'], ENT_QUOTES, 'UTF-8') ?></small>
                                            <span class="vip-comparison-description-en" lang="en" dir="ltr"><?= htmlspecialchars($row['tiers'][$level]['desc_en'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="vip-comparison-dash" aria-label="در این سطح وجود ندارد">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="vip-comparison-footer">
            <span><i class="fas fa-circle-info" aria-hidden="true"></i> مقادیر جدول بر اساس مشخصات تعریف‌شده برای VIPCore نمایش داده می‌شوند.</span>
            <button type="button" class="vip-comparison-done" data-vip-comparison-close>بستن مقایسه</button>
        </footer>
    </section>
</div>

<?php
$fcSuccessModalConfig = [
    'kind' => 'gift',
    'dismissible' => true,
    'reload_on_close' => true,
];
require __DIR__ . '/../includes/fragcity-success-modal.php';
?>






