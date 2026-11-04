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
    1 => ['name' => 'Bronze', 'fa_icon' => 'fas fa-medal', 'color' => '#CD7F32', 'available' => true, 'summary' => 'A simple starting membership for your game sessions.'],
    2 => ['name' => 'Silver', 'fa_icon' => 'fas fa-medal', 'color' => '#C0C0C0', 'available' => true, 'summary' => 'Extra utility, team support, and quality-of-life perks.'],
    3 => ['name' => 'Gold', 'fa_icon' => 'fas fa-crown', 'color' => '#F5B042', 'available' => true, 'summary' => 'The most advanced tier currently listed in the public catalog.'],
    4 => ['name' => 'Platinum', 'fa_icon' => 'fas fa-gem', 'color' => '#9CC9E8', 'available' => false, 'summary' => 'An advanced membership configuration. This tier is currently locked.'],
    5 => ['name' => 'Emerald', 'fa_icon' => 'fas fa-gem', 'color' => '#34D399', 'available' => false, 'summary' => 'An expanded loadout and regeneration configuration. This tier is currently locked.'],
    6 => ['name' => 'Diamond', 'fa_icon' => 'fas fa-gem', 'color' => '#93C5FD', 'available' => false, 'summary' => 'The highest configured membership tier. This tier is currently locked.']
];

$vipFeaturesData = [
    1 => [
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Team HealShot', 'desc' => 'Shoot a teammate to restore health.'],
        ['icon' => 'fas fa-coins', 'title' => 'Bonus Cash', 'desc' => '+$1,000 bonus cash at spawn.'],
        ['icon' => 'fas fa-tag', 'title' => 'VIP Tag', 'desc' => 'VIP : BRONZE tag in chat and scoreboard.']
    ],
    2 => [
        ['icon' => 'fas fa-shield-halved', 'title' => 'Armor', 'desc' => 'Spawn with 125 armor.'],
        ['icon' => 'fas fa-coins', 'title' => 'Bonus Cash', 'desc' => '+$2,000 bonus cash at spawn.'],
        ['icon' => 'fas fa-bomb', 'title' => 'Defuse Kit', 'desc' => 'Receive a free defuse kit.'],
        ['icon' => 'fas fa-droplet', 'title' => 'Vampirism', 'desc' => 'Convert 3% of dealt damage into health.'],
        ['icon' => 'fas fa-smog', 'title' => 'Random Smoke Color', 'desc' => 'Smoke grenades use a random color.'],
        ['icon' => 'fas fa-display', 'title' => 'Kill Screen', 'desc' => 'Unlock the custom kill-screen effect.'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Team HealShot', 'desc' => 'Shoot a teammate to restore health.'],
        ['icon' => 'fas fa-tag', 'title' => 'VIP Tag', 'desc' => 'VIP : SILVER tag in chat and scoreboard.']
    ],
    3 => [
        ['icon' => 'fas fa-heart', 'title' => 'Health', 'desc' => 'Spawn with 100 HP.'],
        ['icon' => 'fas fa-shield-halved', 'title' => 'Armor', 'desc' => 'Spawn with 150 armor.'],
        ['icon' => 'fas fa-coins', 'title' => 'Bonus Cash', 'desc' => '+$3,000 bonus cash at spawn.'],
        ['icon' => 'fas fa-bomb', 'title' => 'Defuse Kit', 'desc' => 'Receive a free defuse kit.'],
        ['icon' => 'fas fa-feather', 'title' => 'No Fall Damage', 'desc' => 'Fall damage is disabled.'],
        ['icon' => 'fas fa-bolt', 'title' => 'Fast Reload', 'desc' => 'Reload weapons faster.'],
        ['icon' => 'fas fa-droplet', 'title' => 'Vampirism', 'desc' => 'Convert 5% of dealt damage into health.'],
        ['icon' => 'fas fa-burst', 'title' => 'Damage Multiplier', 'desc' => 'Deal 1.05x damage (5% bonus).'],
        ['icon' => 'fas fa-bolt-lightning', 'title' => 'Zeus', 'desc' => 'Receive a Zeus at spawn.'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'Fast Defuse', 'desc' => 'Defuse the bomb 10% faster.'],
        ['icon' => 'fas fa-globe', 'title' => 'Reduced Gravity', 'desc' => 'Gravity is set to 0.95.'],
        ['icon' => 'fas fa-smog', 'title' => 'Random Smoke Color', 'desc' => 'Smoke grenades use a random color.'],
        ['icon' => 'fas fa-rainbow', 'title' => 'Rainbow Model', 'desc' => 'Use the rainbow player-model effect.'],
        ['icon' => 'fas fa-display', 'title' => 'Kill Screen', 'desc' => 'Unlock the custom kill-screen effect.'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Team HealShot', 'desc' => 'Shoot a teammate to restore health.'],
        ['icon' => 'fas fa-tag', 'title' => 'VIP Tag', 'desc' => 'VIP : GOLD tag in chat and scoreboard.']
    ],
    4 => [
        ['icon' => 'fas fa-heart', 'title' => 'Health', 'desc' => 'Spawn with 150 HP.'],
        ['icon' => 'fas fa-coins', 'title' => 'Bonus Cash', 'desc' => '+$3,000 bonus cash at spawn.'],
        ['icon' => 'fas fa-bomb', 'title' => 'Defuse Kit', 'desc' => 'Receive a free defuse kit.'],
        ['icon' => 'fas fa-person-running', 'title' => 'Bunnyhop', 'desc' => 'Bunnyhop support is enabled.'],
        ['icon' => 'fas fa-bolt', 'title' => 'Fast Reload', 'desc' => 'Reload weapons faster.'],
        ['icon' => 'fas fa-droplet', 'title' => 'Vampirism', 'desc' => 'Convert 10% of dealt damage into health.'],
        ['icon' => 'fas fa-burst', 'title' => 'Damage Multiplier', 'desc' => 'Deal 1.10x damage (10% bonus).'],
        ['icon' => 'fas fa-feather', 'title' => 'No Fall Damage', 'desc' => 'Fall damage is disabled.'],
        ['icon' => 'fas fa-eye-slash', 'title' => 'Anti-Flash Protection', 'desc' => 'Blocks flash effects from self and teammates.'],
        ['icon' => 'fas fa-bolt-lightning', 'title' => 'Zeus', 'desc' => 'Receive a Zeus at spawn.'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'Fast Defuse', 'desc' => 'Defuse the bomb 15% faster.'],
        ['icon' => 'fas fa-fire', 'title' => 'Fast Plant', 'desc' => 'Plant the bomb faster.'],
        ['icon' => 'fas fa-globe', 'title' => 'Reduced Gravity', 'desc' => 'Gravity is set to 0.90.'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Health Regeneration', 'desc' => 'Regenerate health over time (+2 per tick).'],
        ['icon' => 'fas fa-shield-heart', 'title' => 'Armor Regeneration', 'desc' => 'Regenerate armor over time (+2 per tick).'],
        ['icon' => 'fas fa-smog', 'title' => 'Random Smoke Color', 'desc' => 'Smoke grenades use a random color.'],
        ['icon' => 'fas fa-rainbow', 'title' => 'Rainbow Model', 'desc' => 'Use the rainbow player-model effect.'],
        ['icon' => 'fas fa-display', 'title' => 'Kill Screen', 'desc' => 'Unlock the custom kill-screen effect.'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Team HealShot', 'desc' => 'Shoot a teammate to restore health.'],
        ['icon' => 'fas fa-tag', 'title' => 'VIP Tag', 'desc' => 'VIP : PLATINUM tag in chat and scoreboard.']
    ],
    5 => [
        ['icon' => 'fas fa-heart', 'title' => 'Health', 'desc' => 'Spawn with 180 HP.'],
        ['icon' => 'fas fa-coins', 'title' => 'Bonus Cash', 'desc' => '+$5,000 bonus cash at spawn.'],
        ['icon' => 'fas fa-bomb', 'title' => 'Defuse Kit', 'desc' => 'Receive a free defuse kit.'],
        ['icon' => 'fas fa-person-running', 'title' => 'Bunnyhop', 'desc' => 'Bunnyhop support is enabled.'],
        ['icon' => 'fas fa-bolt', 'title' => 'Fast Reload', 'desc' => 'Reload weapons faster.'],
        ['icon' => 'fas fa-droplet', 'title' => 'Vampirism', 'desc' => 'Convert 15% of dealt damage into health.'],
        ['icon' => 'fas fa-burst', 'title' => 'Damage Multiplier', 'desc' => 'Deal 1.20x damage (20% bonus).'],
        ['icon' => 'fas fa-feather', 'title' => 'No Fall Damage', 'desc' => 'Fall damage is disabled.'],
        ['icon' => 'fas fa-eye-slash', 'title' => 'Anti-Flash Protection', 'desc' => 'Blocks flash effects from self and teammates.'],
        ['icon' => 'fas fa-bolt-lightning', 'title' => 'Zeus', 'desc' => 'Receive a Zeus at spawn.'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'Fast Defuse', 'desc' => 'Defuse the bomb 20% faster.'],
        ['icon' => 'fas fa-fire', 'title' => 'Fast Plant', 'desc' => 'Plant the bomb faster.'],
        ['icon' => 'fas fa-globe', 'title' => 'Reduced Gravity', 'desc' => 'Gravity is set to 0.85.'],
        ['icon' => 'fas fa-infinity', 'title' => 'Infinite Ammo', 'desc' => 'Infinite ammunition is enabled.'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Health Regeneration', 'desc' => 'Regenerate health over time (+4 per tick).'],
        ['icon' => 'fas fa-shield-heart', 'title' => 'Armor Regeneration', 'desc' => 'Regenerate armor over time (+4 per tick).'],
        ['icon' => 'fas fa-crosshairs', 'title' => 'Weapon Loadout Menu', 'desc' => 'Round 2: M4A1 + Deagle (CT) or AK-47 + Deagle (T).'],
        ['icon' => 'fas fa-smog', 'title' => 'Random Smoke Color', 'desc' => 'Smoke grenades use a random color.'],
        ['icon' => 'fas fa-rainbow', 'title' => 'Rainbow Model', 'desc' => 'Use the rainbow player-model effect.'],
        ['icon' => 'fas fa-display', 'title' => 'Kill Screen', 'desc' => 'Unlock the custom kill-screen effect.'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Team HealShot', 'desc' => 'Shoot a teammate to restore health.'],
        ['icon' => 'fas fa-tag', 'title' => 'VIP Tag', 'desc' => 'VIP : EMERALD tag in chat and scoreboard.']
    ],
    6 => [
        ['icon' => 'fas fa-heart', 'title' => 'Health', 'desc' => 'Spawn with 300 HP.'],
        ['icon' => 'fas fa-coins', 'title' => 'Bonus Cash', 'desc' => '+$20,000 bonus cash at spawn.'],
        ['icon' => 'fas fa-bomb', 'title' => 'Defuse Kit', 'desc' => 'Receive a free defuse kit.'],
        ['icon' => 'fas fa-person-running', 'title' => 'Bunnyhop', 'desc' => 'Bunnyhop support is enabled.'],
        ['icon' => 'fas fa-bolt', 'title' => 'Fast Reload', 'desc' => 'Reload weapons faster.'],
        ['icon' => 'fas fa-droplet', 'title' => 'Vampirism', 'desc' => 'Convert 40% of dealt damage into health.'],
        ['icon' => 'fas fa-burst', 'title' => 'Damage Multiplier', 'desc' => 'Deal 1.35x damage (35% bonus).'],
        ['icon' => 'fas fa-feather', 'title' => 'No Fall Damage', 'desc' => 'Fall damage is disabled.'],
        ['icon' => 'fas fa-eye-slash', 'title' => 'Anti-Flash Protection', 'desc' => 'Blocks flash effects from self and teammates.'],
        ['icon' => 'fas fa-bolt-lightning', 'title' => 'Zeus', 'desc' => 'Receive a Zeus at spawn.'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'Fast Defuse', 'desc' => 'Defuse the bomb 25% faster.'],
        ['icon' => 'fas fa-fire', 'title' => 'Fast Plant', 'desc' => 'Plant the bomb faster.'],
        ['icon' => 'fas fa-globe', 'title' => 'Reduced Gravity', 'desc' => 'Gravity is set to 0.75.'],
        ['icon' => 'fas fa-infinity', 'title' => 'Infinite Ammo', 'desc' => 'Infinite ammunition is enabled.'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Health Regeneration', 'desc' => 'Regenerate health over time (+15 per tick).'],
        ['icon' => 'fas fa-shield-heart', 'title' => 'Armor Regeneration', 'desc' => 'Regenerate armor over time (+15 per tick).'],
        ['icon' => 'fas fa-crosshairs', 'title' => 'Weapon Loadout Menu', 'desc' => 'Round 2: M4A1 + Deagle (CT) or AK-47 + Deagle (T).'],
        ['icon' => 'fas fa-rotate', 'title' => 'Extra Respawn', 'desc' => 'One additional respawn is configured.'],
        ['icon' => 'fas fa-smog', 'title' => 'Random Smoke Color', 'desc' => 'Smoke grenades use a random color.'],
        ['icon' => 'fas fa-rainbow', 'title' => 'Rainbow Model', 'desc' => 'Use the rainbow player-model effect.'],
        ['icon' => 'fas fa-display', 'title' => 'Kill Screen', 'desc' => 'Unlock the custom kill-screen effect.'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'Team HealShot', 'desc' => 'Shoot a teammate to restore health.'],
        ['icon' => 'fas fa-tag', 'title' => 'VIP Tag', 'desc' => 'VIP : DIAMOND tag in chat and scoreboard.']
    ]
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

    <div class="vip-catalog-heading">
        <div>
            <span class="vip-store-eyebrow">CHOOSE YOUR TIER</span>
            <h2>پلن‌های VIP</h2>
            <p>با انتخاب هر سطح، تمام قابلیت‌های همان سطح را مشاهده می‌کنی.</p>
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
            <article
                class="vip-plan-card<?= $isCurrentTier ? ' is-current' : '' ?><?= !$tier['available'] ? ' is-locked' : '' ?>"
                data-vip-plan
                data-level="<?= (int) $lvl ?>"
                data-vip-name="<?= htmlspecialchars($tier['name'], ENT_QUOTES, 'UTF-8') ?>"
                data-vip-summary="<?= htmlspecialchars($tier['summary'], ENT_QUOTES, 'UTF-8') ?>"
                data-vip-available="<?= $tier['available'] ? '1' : '0' ?>"
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

                <p class="vip-plan-summary"><?= htmlspecialchars($tier['summary'], ENT_QUOTES, 'UTF-8') ?></p>

                <div class="vip-plan-price">
                    <span><?= $tier['available'] ? 'قیمت پلن' : 'وضعیت' ?></span>
                    <strong><?= htmlspecialchars($tierPriceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($tierDurationLabel, ENT_QUOTES, 'UTF-8') ?></small>
                </div>

                <button type="button" class="vip-plan-select" data-vip-select aria-pressed="false">
                    مشاهده قابلیت‌ها
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </button>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="vip-feature-panel" aria-labelledby="vipDetailTierName" aria-live="polite">
        <div class="vip-feature-panel-head">
            <div class="vip-feature-panel-title">
                <span class="vip-store-eyebrow">TIER DETAILS</span>
                <h2 id="vipDetailTierName">Bronze</h2>
                <p id="vipDetailTierSummary">A simple starting membership for your game sessions.</p>
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
                            <strong><?= htmlspecialchars($feature['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($feature['desc'], ENT_QUOTES, 'UTF-8') ?></small>
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
