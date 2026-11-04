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

$vipNames = [0 => 'ندارد', 1 => 'برنزی', 2 => 'نقره‌ای', 3 => 'طلایی', 4 => 'پلاتینیوم', 5 => 'امرالد', 6 => 'دایموند'];
// بیشترین XP قابل انتظار
$vipLevels = [
    1 => ['name' => 'برنزی', 'fa_icon' => 'fas fa-medal', 'color' => '#cd7f32', 'gradient' => 'linear-gradient(135deg, #cd7f32, #a0522d)'],
    2 => ['name' => 'نقره‌ای', 'fa_icon' => 'fas fa-medal', 'color' => '#c0c0c0', 'gradient' => 'linear-gradient(135deg, #c0c0c0, #808080)'],
    3 => ['name' => 'طلایی', 'fa_icon' => 'fas fa-crown', 'color' => '#F5B042', 'gradient' => 'linear-gradient(135deg, #F5B042, #E68A2E)']
];

// فقط سه سطح اول در فروشگاه عمومی نمایش داده می‌شوند.
// Tierهای 4 تا 6 در سیستم VIP و وضعیت اشتراک کاربران همچنان معتبر هستند، اما در کاتالوگ فروش عمومی نیستند.
$vipFeaturesData = [
    1 => [
        ['icon' => 'fas fa-heart-pulse', 'title' => 'HealShot هم‌تیمی', 'desc' => 'درمان هم‌تیمی با شلیک'],
        ['icon' => 'fas fa-coins', 'title' => 'پول اضافه', 'desc' => '۱۰۰۰ دلار'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ اختصاصی', 'desc' => 'VIP : BRONZE']
    ],
    2 => [
        ['icon' => 'fas fa-shield', 'title' => 'زره', 'desc' => '۱۲۵ واحد'],
        ['icon' => 'fas fa-coins', 'title' => 'پول اضافه', 'desc' => '۲۰۰۰ دلار'],
        ['icon' => 'fas fa-bomb', 'title' => 'دیفیوزر', 'desc' => 'رایگان'],
        ['icon' => 'fas fa-droplet', 'title' => 'Vampirism', 'desc' => '۳٪'],
        ['icon' => 'fas fa-smog', 'title' => 'Smoke Color', 'desc' => 'رنگ تصادفی دود'],
        ['icon' => 'fas fa-desktop', 'title' => 'Kill Screen', 'desc' => 'افکت نمایشی'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'HealShot هم‌تیمی', 'desc' => 'درمان هم‌تیمی با شلیک'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ اختصاصی', 'desc' => 'VIP : SILVER']
    ],
    3 => [
        ['icon' => 'fas fa-heart', 'title' => 'سلامتی', 'desc' => '۱۰۰ HP'],
        ['icon' => 'fas fa-shield', 'title' => 'زره', 'desc' => '۱۵۰ واحد'],
        ['icon' => 'fas fa-coins', 'title' => 'پول اضافه', 'desc' => '۳۰۰۰ دلار'],
        ['icon' => 'fas fa-bomb', 'title' => 'دیفیوزر', 'desc' => 'رایگان'],
        ['icon' => 'fas fa-feather', 'title' => 'بدون آسیب سقوط', 'desc' => 'فعال'],
        ['icon' => 'fas fa-bolt', 'title' => 'ریلود سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-droplet', 'title' => 'Vampirism', 'desc' => '۵٪'],
        ['icon' => 'fas fa-burst', 'title' => 'افزایش آسیب', 'desc' => '۱.۰۵ برابر'],
        ['icon' => 'fas fa-bolt-lightning', 'title' => 'Zeus', 'desc' => 'دریافت در شروع راند'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'دیفیوز سریع', 'desc' => '۱۰٪ کاهش زمان'],
        ['icon' => 'fas fa-globe', 'title' => 'جاذبه', 'desc' => '۰.۹۵'],
        ['icon' => 'fas fa-smog', 'title' => 'Smoke Color', 'desc' => 'رنگ تصادفی دود'],
        ['icon' => 'fas fa-rainbow', 'title' => 'Rainbow Character', 'desc' => 'مدل رنگین‌کمان'],
        ['icon' => 'fas fa-desktop', 'title' => 'Kill Screen', 'desc' => 'افکت نمایشی'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'HealShot هم‌تیمی', 'desc' => 'درمان هم‌تیمی با شلیک'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ اختصاصی', 'desc' => 'VIP : GOLD']
    ]
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
<link rel="stylesheet" href="css/pages-base.css">
<link rel="stylesheet" href="css/vip.css">

<main class="vip-page" id="vipStorefront">
    <header class="vip-store-hero">
        <div class="vip-store-hero-icon" aria-hidden="true"><i class="fas fa-crown"></i></div>
        <div class="vip-store-hero-copy">
            <span class="vip-store-eyebrow">FRAGCITY MEMBERSHIP STORE</span>
            <h1>خرید VIP فرگ‌سیتی</h1>
            <p>سطح موردنظرت را انتخاب کن و قابلیت‌های هر پلن را با هم مقایسه کن.</p>
        </div>
        <div class="vip-store-hero-aside">
            <i class="fas fa-shield-halved" aria-hidden="true"></i>
            <span>۳ سطح قابل خرید</span>
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
            <a class="vip-wallet-shortcut" href="?page=login" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal">
                <i class="fas fa-wallet" aria-hidden="true"></i>
                ورود برای شارژ کیف پول
            </a>
        <?php endif; ?>
    </div>

    <aside class="vip-competitive-note" role="note">
        <span class="vip-competitive-note-icon" aria-hidden="true"><i class="fas fa-scale-balanced"></i></span>
        <span>
            <strong>وضعیت فعال بودن VIP در مودهای بازی</strong>
            <small>VIP در مودهای Competitive، Practice، Wingman، Deathmatch، Valve Competitive، Valve Wingman، Valve Deathmatch و Deathmatch FFA، Retakes، Valve Retakes، Executes، Prefire، 1v1 Arenas و Aim غیرفعال است. در مودهای AWP، AWP Aim، ScoutzKnivez، Gun Game، One in the Chamber، Battle Royale، Valve Arms Race و Competitive 45° فعال است.</small>
        </span>
    </aside>

    <section class="vip-plans-grid" aria-label="سه پلن قابل خرید VIP">
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
                $tierPriceLabel = $tierPrice !== null
                    ? number_format($tierPrice) . ' ' . $vipCurrency
                    : 'قیمت تعیین نشده';
                $tierDurationLabel = $tierDuration !== null
                    ? number_format($tierDuration) . ' روز'
                    : 'مدت اشتراک مشخص نشده';
                $isCurrentTier = $vipHasActiveMembership && $vipCurrentLevel === (int) $lvl;
            ?>
            <article
                class="vip-plan-card<?= $isCurrentTier ? ' is-current' : '' ?>"
                data-vip-plan
                data-level="<?= (int) $lvl ?>"
                data-vip-name="<?= htmlspecialchars($tier['name'], ENT_QUOTES, 'UTF-8') ?>"
                data-price-label="<?= htmlspecialchars($tierPriceLabel, ENT_QUOTES, 'UTF-8') ?>"
                data-duration-label="<?= htmlspecialchars($tierDurationLabel, ENT_QUOTES, 'UTF-8') ?>"
                style="--vip-tier-color: <?= htmlspecialchars($tier['color'], ENT_QUOTES, 'UTF-8') ?>;"
            >
                <div class="vip-plan-topline">
                    <span class="vip-plan-index">FRAGCITY / VIP 0<?= (int) $lvl ?></span>
                    <?php if ($isCurrentTier): ?>
                        <span class="vip-plan-current"><i class="fas fa-check"></i> پلن فعلی</span>
                    <?php elseif ($lvl === 3): ?>
                        <span class="vip-plan-popular"><i class="fas fa-fire"></i> محبوب</span>
                    <?php endif; ?>
                </div>

                <div class="vip-plan-title-row">
                    <span class="vip-tier-emblem">
                        <i class="<?= htmlspecialchars($tier['fa_icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </span>
                    <div>
                        <h3><?= htmlspecialchars($tier['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <span>VIP TIER <?= (int) $lvl ?></span>
                    </div>
                </div>

                <div class="vip-plan-price">
                    <span>قیمت پلن</span>
                    <strong><?= htmlspecialchars($tierPriceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($tierDurationLabel, ENT_QUOTES, 'UTF-8') ?></small>
                </div>

                <div class="vip-plan-features-heading">
                    <strong>قابلیت‌های این سطح</strong>
                    <span><?= count($vipFeaturesData[$lvl] ?? []) ?> قابلیت</span>
                </div>

                <ul class="vip-plan-features">
                    <?php foreach ($vipFeaturesData[$lvl] as $feature): ?>
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

                <button type="button" class="vip-plan-select" data-vip-select aria-pressed="false">
                    انتخاب این سطح
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </button>
            </article>
        <?php endforeach; ?>
    </section>

    <aside class="vip-checkout-bar" aria-label="خلاصه پلن انتخاب‌شده">
        <div class="vip-checkout-selection">
            <span class="vip-checkout-label">
                <i class="fas fa-bag-shopping" aria-hidden="true"></i>
                پلن انتخاب‌شده
            </span>
            <strong id="vipSelectedName">برنزی</strong>
            <small id="vipSelectedMeta">قیمت تعیین نشده · مدت اشتراک مشخص نشده</small>
        </div>
        <div class="vip-checkout-actions">
            <button type="button" class="vip-checkout-button" disabled>
                خرید آنلاین به‌زودی فعال می‌شود
                <i class="fas fa-lock" aria-hidden="true"></i>
            </button>
            <?php if ($isLoggedIn): ?>
                <button type="button" class="vip-checkout-wallet" data-fc-wallet-charge-open>
                    <i class="fas fa-wallet" aria-hidden="true"></i>
                    شارژ کیف پول
                </button>
            <?php else: ?>
                <a href="?page=login" data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal" class="vip-checkout-wallet">
                    <i class="fas fa-wallet" aria-hidden="true"></i>
                    ورود
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <p class="vip-checkout-note">
        کاتالوگ و انتخاب سطح آماده است؛ پرداخت مستقیم VIP هنوز فعال نشده، چون مسیر امن خرید، کسر موجودی و اعمال خودکار اشتراک روی سرور بازی در نسخه فعلی وجود ندارد.
    </p>
</main>

<script src="js/vip.js" defer></script>
