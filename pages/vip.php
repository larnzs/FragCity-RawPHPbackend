<?php
require_once __DIR__ . '/../settings/loader.php';

$steamid64 = (string) ($_SESSION['steamid'] ?? '');
$isLoggedIn = isset($_SESSION['user_logged_in']);
$userVip = ['vip_level' => 0, 'vip_expire' => null];

$vipSettings = fragcity_settings('files.pages.vip', []);
$vipProfile = (string) ($vipSettings['database_profile'] ?? 'main');
$vipPurchaseCatalog = is_array($vipSettings['purchase_catalog'] ?? null)
    ? $vipSettings['purchase_catalog']
    : [];
$vipPurchasePackages = is_array($vipPurchaseCatalog['packages'] ?? null)
    ? $vipPurchaseCatalog['packages']
    : [];
$vipCurrency = (string) ($vipPurchaseCatalog['currency'] ?? 'تومان');

if ($isLoggedIn && $steamid64 !== '') {
    try {
        $pdo = fragcity_create_pdo($vipProfile);
        if (!$pdo) {
            throw new PDOException('VIP database is unavailable.');
        }

        $stmt = $pdo->prepare(
            'SELECT vip_level, vip_expire FROM users WHERE steamid64 = ? LIMIT 1'
        );
        $stmt->execute([$steamid64]);
        $userVip = $stmt->fetch(PDO::FETCH_ASSOC) ?: $userVip;
    } catch (Throwable $e) {
        error_log('[FragCity VIP storefront] ' . $e->getMessage());
    }
}

$vipNames = [0 => 'No VIP', 1 => 'Bronze', 2 => 'Silver', 3 => 'Gold', 4 => 'Platinum', 5 => 'Emerald', 6 => 'Diamond'];

$vipLevels = [
    1 => ['name' => 'Bronze', 'fa_icon' => 'fas fa-medal', 'color' => '#CD7F32', 'available' => true, 'summary' => 'A practical entry tier with bonus cash, team healing and a Bronze tag.', 'summary_fa' => 'شروع اقتصادی با پول اضافه، HealShot تیمی و تگ اختصاصی Bronze.'],
    2 => ['name' => 'Silver', 'fa_icon' => 'fas fa-medal', 'color' => '#C0C0C0', 'available' => true, 'summary' => 'More utility with a defuse kit, smoke effects, kill screen and vampirism.', 'summary_fa' => 'امکانات بیشتر با کیت خنثی‌سازی، دود رنگی، Kill Screen و Vampirism.'],
    3 => ['name' => 'Gold', 'fa_icon' => 'fas fa-crown', 'color' => '#F5B042', 'available' => true, 'summary' => 'A stronger loadout with 100 HP, 120 armor and advanced combat perks.', 'summary_fa' => 'تجهیزات کامل‌تر با ۱۰۰ HP، زره ۱۲۰ و قابلیت‌های پیشرفته مبارزه.'],
    4 => ['name' => 'Platinum', 'fa_icon' => 'fas fa-gem', 'color' => '#9CC9E8', 'available' => false, 'summary' => 'Advanced movement, anti-flash and health/armor regeneration.', 'summary_fa' => 'حرکت پیشرفته، Anti-Flash و بازیابی تدریجی سلامتی و زره.'],
    5 => ['name' => 'Emerald', 'fa_icon' => 'fas fa-gem', 'color' => '#34D399', 'available' => false, 'summary' => 'A stronger regeneration setup, infinite ammo and a round-two loadout.', 'summary_fa' => 'بازیابی قوی‌تر، مهمات بی‌نهایت و تجهیزات ویژه از راند دوم.'],
    6 => ['name' => 'Diamond', 'fa_icon' => 'fas fa-gem', 'color' => '#93C5FD', 'available' => false, 'summary' => 'The highest configured tier with powerful combat perks and an extra respawn.', 'summary_fa' => 'بالاترین سطح با قابلیت‌های قدرتمند مبارزه و یک Respawn اضافه.']
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
    'Bonus Spawn Money',
    'در شروع راند، ' . number_format($amount) . ' دلار پول اضافه دریافت می‌کنی.',
    'Receive +$' . number_format($amount) . ' bonus money at round start.'
);
$vipHealShot = $vipFeature(
    'fas fa-heart-pulse',
    'HealShot تیمی',
    'Team HealShot',
    'با شلیک به هم‌تیمی، ۱۰ واحد سلامتی بازیابی می‌شود؛ سقف سلامتی درمان‌شده ۱۰۰ است.',
    'Each qualifying shot restores 10 HP to a teammate, up to 100 HP. VIP-only.'
);
$vipTag = static fn (string $tier): array => $vipFeature(
    'fas fa-tag',
    'تگ اختصاصی ' . $tier,
    $tier . ' VIP Tag',
    'تگ VIP : ' . strtoupper($tier) . ' در چت و اسکوربورد نمایش داده می‌شود.',
    'Displays VIP : ' . strtoupper($tier) . ' in chat and on the scoreboard.'
);
$vipDefuser = $vipFeature(
    'fas fa-bomb',
    'کیت خنثی‌سازی بمب',
    'Defuse Kit',
    'با کیت خنثی‌سازی رایگان وارد راند می‌شوی.',
    'Spawn with a free defuse kit.'
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
    'محافظت ضد فلش',
    'Anti-Flash Protection',
    'قابلیت محافظت در برابر افکت فلش فعال می‌شود.',
    'Anti-flash protection is enabled.'
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
    'Infinite Ammo',
    'قابلیت مهمات بی‌نهایت فعال می‌شود.',
    'Infinite ammunition is enabled.'
);
$vipWeaponMenu = $vipFeature(
    'fas fa-crosshairs',
    'منوی تجهیزات راند دوم',
    'Round 2 Weapon Loadout',
    'از راند دوم: CT اسلحه M4A1 و Deagle و تیم T اسلحه AK-47 و Deagle دریافت می‌کند.',
    'From round 2: CT receives M4A1 + Deagle; T receives AK-47 + Deagle.'
);

$vipFeaturesData = [
    1 => [
        $vipCash(1000),
        $vipHealShot,
        $vipTag('Bronze'),
    ],
    2 => [
        $vipCash(2000),
        $vipDefuser,
        $vipSmoke,
        $vipKillscreen,
        $vipFeature('fas fa-droplet', 'سرقت سلامتی', 'Vampirism', '۳٪ از دمیجی که وارد می‌کنی به سلامتی تبدیل می‌شود.', 'Convert 3% of dealt damage into health.'),
        $vipHealShot,
        $vipTag('Silver'),
    ],
    3 => [
        $vipFeature('fas fa-heart', 'سلامتی شروع راند', 'Spawn Health', 'با ۱۰۰ واحد سلامتی وارد راند می‌شوی.', 'Spawn with 100 HP.'),
        $vipFeature('fas fa-shield-halved', 'زره شروع راند', 'Spawn Armor', 'با ۱۲۰ واحد زره وارد راند می‌شوی.', 'Spawn with 120 armor.'),
        $vipCash(3000),
        $vipDefuser,
        $vipFastReload,
        $vipFeature('fas fa-droplet', 'سرقت سلامتی', 'Vampirism', '۵٪ از دمیجی که وارد می‌کنی به سلامتی تبدیل می‌شود.', 'Convert 5% of dealt damage into health.'),
        $vipFeature('fas fa-burst', 'ضریب آسیب بیشتر', 'Damage Multiplier', '۵٪ آسیب بیشتر وارد می‌کنی (ضریب 1.05x).', 'Deal 5% more damage (1.05x multiplier).'),
        $vipNoFall,
        $vipZeus,
        $vipFeature('fas fa-stopwatch', 'خنثی‌سازی سریع‌تر', 'Fast Defuse', 'بمب را ۱۰٪ سریع‌تر خنثی می‌کنی.', 'Defuse the bomb 10% faster.'),
        $vipFeature('fas fa-globe', 'گرانش کمتر', 'Reduced Gravity', 'گرانش روی 0.95 تنظیم می‌شود.', 'Gravity is set to 0.95.'),
        $vipSmoke,
        $vipRainbow,
        $vipKillscreen,
        $vipHealShot,
        $vipTag('Gold'),
    ],
    4 => [
        $vipFeature('fas fa-heart', 'سلامتی شروع راند', 'Spawn Health', 'با ۱۵۰ واحد سلامتی وارد راند می‌شوی.', 'Spawn with 150 HP.'),
        $vipCash(3000),
        $vipDefuser,
        $vipBhop,
        $vipFastReload,
        $vipFeature('fas fa-droplet', 'سرقت سلامتی', 'Vampirism', '۱۰٪ از دمیجی که وارد می‌کنی به سلامتی تبدیل می‌شود.', 'Convert 10% of dealt damage into health.'),
        $vipFeature('fas fa-burst', 'ضریب آسیب بیشتر', 'Damage Multiplier', '۱۰٪ آسیب بیشتر وارد می‌کنی (ضریب 1.10x).', 'Deal 10% more damage (1.10x multiplier).'),
        $vipNoFall,
        $vipAntiFlash,
        $vipZeus,
        $vipFeature('fas fa-stopwatch', 'خنثی‌سازی سریع‌تر', 'Fast Defuse', 'بمب را ۱۵٪ سریع‌تر خنثی می‌کنی.', 'Defuse the bomb 15% faster.'),
        $vipFastPlant,
        $vipFeature('fas fa-globe', 'گرانش کمتر', 'Reduced Gravity', 'گرانش روی 0.90 تنظیم می‌شود.', 'Gravity is set to 0.90.'),
        $vipFeature('fas fa-heart-pulse', 'بازیابی سلامتی', 'Health Regeneration', 'در هر نوبت بازیابی، ۲ واحد سلامتی برمی‌گردد.', 'Restore 2 HP per regeneration tick.'),
        $vipFeature('fas fa-shield-heart', 'بازیابی زره', 'Armor Regeneration', 'در هر نوبت بازیابی، ۲ واحد زره برمی‌گردد.', 'Restore 2 armor per regeneration tick.'),
        $vipSmoke,
        $vipRainbow,
        $vipKillscreen,
        $vipHealShot,
        $vipTag('Platinum'),
    ],
    5 => [
        $vipFeature('fas fa-heart', 'سلامتی شروع راند', 'Spawn Health', 'با ۱۸۰ واحد سلامتی وارد راند می‌شوی.', 'Spawn with 180 HP.'),
        $vipCash(5000),
        $vipDefuser,
        $vipBhop,
        $vipFastReload,
        $vipFeature('fas fa-droplet', 'سرقت سلامتی', 'Vampirism', '۱۵٪ از دمیجی که وارد می‌کنی به سلامتی تبدیل می‌شود.', 'Convert 15% of dealt damage into health.'),
        $vipFeature('fas fa-burst', 'ضریب آسیب بیشتر', 'Damage Multiplier', '۲۰٪ آسیب بیشتر وارد می‌کنی (ضریب 1.20x).', 'Deal 20% more damage (1.20x multiplier).'),
        $vipNoFall,
        $vipAntiFlash,
        $vipZeus,
        $vipFeature('fas fa-stopwatch', 'خنثی‌سازی سریع‌تر', 'Fast Defuse', 'بمب را ۲۰٪ سریع‌تر خنثی می‌کنی.', 'Defuse the bomb 20% faster.'),
        $vipFastPlant,
        $vipFeature('fas fa-globe', 'گرانش کمتر', 'Reduced Gravity', 'گرانش روی 0.85 تنظیم می‌شود.', 'Gravity is set to 0.85.'),
        $vipInfiniteAmmo,
        $vipFeature('fas fa-heart-pulse', 'بازیابی سلامتی', 'Health Regeneration', 'در هر نوبت بازیابی، ۴ واحد سلامتی برمی‌گردد.', 'Restore 4 HP per regeneration tick.'),
        $vipFeature('fas fa-shield-heart', 'بازیابی زره', 'Armor Regeneration', 'در هر نوبت بازیابی، ۴ واحد زره برمی‌گردد.', 'Restore 4 armor per regeneration tick.'),
        $vipWeaponMenu,
        $vipSmoke,
        $vipRainbow,
        $vipKillscreen,
        $vipHealShot,
        $vipTag('Emerald'),
    ],
    6 => [
        $vipFeature('fas fa-heart', 'سلامتی شروع راند', 'Spawn Health', 'با ۳۰۰ واحد سلامتی وارد راند می‌شوی.', 'Spawn with 300 HP.'),
        $vipCash(20000),
        $vipDefuser,
        $vipBhop,
        $vipFastReload,
        $vipFeature('fas fa-droplet', 'سرقت سلامتی', 'Vampirism', '۴۰٪ از دمیجی که وارد می‌کنی به سلامتی تبدیل می‌شود.', 'Convert 40% of dealt damage into health.'),
        $vipFeature('fas fa-burst', 'ضریب آسیب بیشتر', 'Damage Multiplier', '۳۵٪ آسیب بیشتر وارد می‌کنی (ضریب 1.35x).', 'Deal 35% more damage (1.35x multiplier).'),
        $vipNoFall,
        $vipAntiFlash,
        $vipZeus,
        $vipFeature('fas fa-stopwatch', 'خنثی‌سازی سریع‌تر', 'Fast Defuse', 'بمب را ۲۵٪ سریع‌تر خنثی می‌کنی.', 'Defuse the bomb 25% faster.'),
        $vipFastPlant,
        $vipFeature('fas fa-globe', 'گرانش کمتر', 'Reduced Gravity', 'گرانش روی 0.75 تنظیم می‌شود.', 'Gravity is set to 0.75.'),
        $vipInfiniteAmmo,
        $vipFeature('fas fa-heart-pulse', 'بازیابی سلامتی', 'Health Regeneration', 'در هر نوبت بازیابی، ۱۵ واحد سلامتی برمی‌گردد.', 'Restore 15 HP per regeneration tick.'),
        $vipFeature('fas fa-shield-heart', 'بازیابی زره', 'Armor Regeneration', 'در هر نوبت بازیابی، ۱۵ واحد زره برمی‌گردد.', 'Restore 15 armor per regeneration tick.'),
        $vipWeaponMenu,
        $vipFeature('fas fa-rotate', 'یک Respawn اضافه', 'Extra Respawn', 'یک فرصت Respawn اضافه در تنظیمات این سطح در نظر گرفته شده است.', 'One additional respawn is configured for this tier.'),
        $vipSmoke,
        $vipRainbow,
        $vipKillscreen,
        $vipHealShot,
        $vipTag('Diamond'),
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
$vipActive = false;
$vipExpiryLabel = '—';

if (!empty($userVip['vip_expire'])) {
    try {
        $vipExpiry = new DateTimeImmutable((string) $userVip['vip_expire']);
        $vipActive = $vipExpiry > new DateTimeImmutable('now');
        $vipExpiryLabel = $vipExpiry->format('Y/m/d H:i');
        if (!$vipActive) {
            $vipExpiryLabel .= ' · منقضی شده';
        }
    } catch (Throwable $e) {
        $vipExpiryLabel = 'نامشخص';
    }
}

$vipHasActiveMembership = $vipActive && $vipCurrentLevel > 0;
$vipCurrentName = $vipNames[$vipCurrentLevel] ?? 'نامشخص';
$vipCurrentStatus = !$isLoggedIn
    ? 'برای مشاهده وضعیت وارد شوید'
    : ($vipHasActiveMembership ? 'اشتراک فعال' : 'بدون اشتراک فعال');
?>
<link rel="stylesheet" href="/css/pages-base.css?v=<?= htmlspecialchars(asset_version(__DIR__ . '/../css/pages-base.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/css/vip.css?v=<?= htmlspecialchars(asset_version(__DIR__ . '/../css/vip.css'), ENT_QUOTES, 'UTF-8') ?>">

<main class="vip-page" id="vipStorefront">
    <header class="vip-store-hero">
        <div class="vip-store-hero-icon" aria-hidden="true"><i class="fas fa-crown"></i></div>
        <div class="vip-store-hero-copy">
            <span class="vip-store-eyebrow">FRAGCITY MEMBERSHIP STORE</span>
            <h1>عضویت VIP فرگ‌سیتی</h1>
            <p>سطح موردنظرت را انتخاب کن؛ جزئیات و قابلیت‌های همان سطح در ادامه نمایش داده می‌شود.</p>
        </div>
        <div class="vip-store-hero-aside">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            <span>۶ سطح VIP · ۳ سطح عمومی</span>
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
            <p>سطح‌های Bronze، Silver و Gold در کاتالوگ عمومی قرار دارند. سطح‌های Platinum، Emerald و Diamond فقط برای مشاهده جزئیات نمایش داده می‌شوند و فعلاً قابل خرید نیستند.</p>
            <p class="vip-notice-store-warning"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> پرداخت آنلاین و اعمال خودکار VIP هنوز فعال نشده است؛ انتخاب سطح در این صفحه به‌تنهایی خریدی انجام نمی‌دهد.</p>
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

    <section class="vip-plans-grid" aria-label="شش سطح VIP">
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
                $tierPriceLabel = !$tier['available']
                    ? 'غیرفعال'
                    : ($tierPrice !== null ? number_format($tierPrice) . ' ' . $vipCurrency : 'قیمت تعیین نشده');
                $tierDurationLabel = !$tier['available']
                    ? 'Not available'
                    : ($tierDuration !== null ? number_format($tierDuration) . ' روز' : 'مدت اشتراک مشخص نشده');
                $isCurrentTier = $vipHasActiveMembership && $vipCurrentLevel === (int) $lvl;
            ?>
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

    <aside class="vip-checkout-bar" aria-label="خلاصه پلن انتخاب‌شده">
        <div class="vip-checkout-selection">
            <span class="vip-checkout-label">
                <i class="fas fa-bag-shopping" aria-hidden="true"></i>
                پلن انتخاب‌شده
            </span>
            <strong id="vipSelectedName">Bronze</strong>
            <small id="vipSelectedMeta">قیمت تعیین نشده · مدت اشتراک مشخص نشده</small>
        </div>
        <div class="vip-checkout-actions">
            <button type="button" class="vip-checkout-button" id="vipCheckoutButton" disabled>
                خرید VIP فعلاً غیرفعال است
                <i class="fas fa-lock" aria-hidden="true"></i>
            </button>
            <?php if ($isLoggedIn): ?>
                <button type="button" class="vip-checkout-wallet" data-fc-wallet-charge-open>
                    <i class="fas fa-wallet" aria-hidden="true"></i>
                    شارژ کیف پول
                </button>
            <?php else: ?>
                <a href="/login/" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal" class="vip-checkout-wallet">
                    <i class="fas fa-wallet" aria-hidden="true"></i>
                    ورود
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <p class="vip-checkout-note">
        انتخاب سطح فقط برای مشاهده جزئیات است. تا زمان تکمیل مسیر پرداخت امن و اعمال خودکار اشتراک روی سرور، هیچ خریدی از این صفحه ثبت نمی‌شود.
    </p>
</main>

<script src="js/vip.js" defer></script>
