<?php
declare(strict_types=1);

/**
 * Customer order history.
 * The account SteamID comes only from the authenticated server-side session.
 */
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /orders/', true, 303);
    exit;
}

if (($_SESSION['user_logged_in'] ?? false) !== true) {
    $_SESSION['login_return_url'] = '/orders/';
    header('Location: /login/', true, 303);
    exit;
}

require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/VipCoreRepository.php';

$ordersPageSteamId = (string)($_SESSION['steamid'] ?? '');
$ordersPageRows = [];
$ordersPageError = false;
$ordersPageErrorMessage = '';
$ordersPageVipStatus = null;
$ordersPageVipStatusUnavailable = false;
$ordersPageCsrf = (string)($_SESSION['auth_csrf_token'] ?? '');

$ordersPageEscape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$ordersPageDate = static function ($value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y/m/d · H:i');
    } catch (Throwable) {
        return '—';
    }
};

$ordersPageLevels = [
    1 => 'Bronze',
    2 => 'Silver',
    3 => 'Gold',
    4 => 'Platinum',
    5 => 'Emerald',
    6 => 'Diamond',
];

$ordersPageStatuses = [
    'provisioned' => [
        'label' => 'تکمیل‌شده',
        'class' => 'success',
        'icon' => 'fa-circle-check',
        'description' => 'پرداخت ثبت شده و عضویت VIP توسط API در VIPCore تأیید شده است.',
    ],
    'paid' => [
        'label' => 'در انتظار فعال‌سازی',
        'class' => 'processing',
        'icon' => 'fa-arrows-rotate',
        'description' => 'پرداخت ثبت شده است؛ تأیید نهایی عضویت در VIPCore هنوز تکمیل نشده است.',
    ],
    'pending' => [
        'label' => 'در انتظار پرداخت',
        'class' => 'pending',
        'icon' => 'fa-clock',
        'description' => 'سفارش ایجاد شده اما پرداخت آن هنوز نهایی نشده است.',
    ],
    'expired' => [
        'label' => 'منقضی‌شده',
        'class' => 'muted',
        'icon' => 'fa-calendar-xmark',
        'description' => 'مهلت پرداخت این سفارش به پایان رسیده است.',
    ],
    'failed' => [
        'label' => 'ناموفق',
        'class' => 'danger',
        'icon' => 'fa-circle-xmark',
        'description' => 'پرداخت یا پردازش این سفارش با موفقیت تکمیل نشده است.',
    ],
    'cancelled' => [
        'label' => 'لغوشده',
        'class' => 'muted',
        'icon' => 'fa-ban',
        'description' => 'این سفارش لغو شده است.',
    ],
    'revoked' => [
        'label' => 'باطل‌شده توسط کاربر',
        'class' => 'muted',
        'icon' => 'fa-shield-halved',
        'description' => 'دسترسی VIP این سفارش به درخواست شما از VIPCore حذف شده است؛ سابقه پرداخت برای پیگیری نگهداری می‌شود.',
    ],
];

if (!preg_match('/^[0-9]{17}$/D', $ordersPageSteamId)) {
    $ordersPageError = true;
    $ordersPageErrorMessage = 'شناسه حساب Steam معتبر نیست. یک‌بار خارج شوید و دوباره وارد شوید.';
} else {
    try {
        $ordersDatabaseProfile = fragcity_settings(
            'files.pages.zarinpal.vip_request.database_profile',
            'main'
        );
        $ordersDatabaseProfile = is_string($ordersDatabaseProfile)
            && trim($ordersDatabaseProfile) !== ''
            ? trim($ordersDatabaseProfile)
            : 'main';

        $ordersPdo = fragcity_create_pdo($ordersDatabaseProfile);
        if (!$ordersPdo instanceof PDO) {
            throw new RuntimeException('vip_orders_database_unavailable');
        }

        $ordersQuery = $ordersPdo->prepare(
            'SELECT id, order_ref, vip_level, duration_days, amount_rial,
                    authority, ref_id, status, purchase_type,
                    upgrade_from_level, created_at, paid_at, provisioned_at
             FROM vip_orders
             WHERE steamid64 = ?
             ORDER BY id DESC
             LIMIT 100'
        );
        $ordersQuery->execute([$ordersPageSteamId]);
        $ordersPageRows = $ordersQuery->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($ordersPageRows)) {
            $ordersPageRows = [];
        }
    } catch (Throwable $e) {
        $ordersPageError = true;
        $ordersPageErrorMessage = 'دریافت سفارش‌ها در حال حاضر امکان‌پذیر نیست. چند لحظه بعد صفحه را تازه‌سازی کنید.';
        error_log('[FragCity Orders] VIP order history lookup failed; type=' . get_class($e));
    }
}


if (preg_match('/^[0-9]{17}$/D', $ordersPageSteamId)) {
    try {
        $ordersPageVipStatus = (new VipCoreRepository())->getStatus($ordersPageSteamId);
    } catch (Throwable $e) {
        $ordersPageVipStatusUnavailable = true;
        error_log(
            '[FragCity Orders] VIPCore status lookup failed; steam_hash='
            . substr(hash('sha256', $ordersPageSteamId), 0, 16)
            . '; type=' . get_class($e)
        );
    }
}

$ordersPageCurrentVipActive = is_array($ordersPageVipStatus)
    && ($ordersPageVipStatus['active'] ?? false) === true;
$ordersPageCurrentVipLevel = $ordersPageCurrentVipActive
    ? (int)($ordersPageVipStatus['level'] ?? 0)
    : 0;
$ordersPageCurrentVipName = $ordersPageLevels[$ordersPageCurrentVipLevel] ?? 'VIP';
$ordersPageCurrentVipExpires = $ordersPageCurrentVipActive
    ? (int)($ordersPageVipStatus['expires'] ?? 0)
    : null;
$ordersPageCurrentVipExpiryLabel = '—';
if ($ordersPageCurrentVipActive) {
    if ($ordersPageCurrentVipExpires === 0) {
        $ordersPageCurrentVipExpiryLabel = 'دائمی';
    } elseif ($ordersPageCurrentVipExpires !== null && $ordersPageCurrentVipExpires > 0) {
        $ordersPageCurrentVipExpiryLabel = (new DateTimeImmutable('@' . $ordersPageCurrentVipExpires))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y/m/d · H:i');
    }
}

// Paid VIP cancellation becomes available one hour after the actual payment.
// Keep this server-rendered countdown informational only; the API repeats the
// check against the same paid_at timestamp before deleting vip_users.
$ordersPageRevokeAvailableAt = 0;
$ordersPageRevokeRemainingSeconds = 0;
$ordersPageRevokeCooldownUnavailable = false;
$ordersPageRevokeCooldownRequired = false;

if ($ordersPageCurrentVipActive
    && ($ordersPageVipStatus['paid_ledger_available'] ?? false) !== true) {
    $ordersPageRevokeCooldownRequired = true;
    $ordersPageRevokeCooldownUnavailable = true;
} elseif ($ordersPageCurrentVipActive
    && is_array($ordersPageVipStatus['paid_purchase'] ?? null)) {
    $ordersPageRevokeCooldownRequired = true;
    try {
        $revokeOrderRef = (string)($ordersPageVipStatus['paid_purchase']['order_ref'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/D', $revokeOrderRef)) {
            throw new RuntimeException('vip_paid_order_reference_invalid_for_revocation_cooldown');
        }

        $revokeTimeProfile = fragcity_settings(
            'files.pages.zarinpal.vip_request.database_profile',
            'main'
        );
        $revokeTimeProfile = is_string($revokeTimeProfile) && trim($revokeTimeProfile) !== ''
            ? trim($revokeTimeProfile)
            : 'main';
        $revokeTimePdo = fragcity_create_pdo($revokeTimeProfile);
        if (!$revokeTimePdo instanceof PDO) {
            throw new RuntimeException('vip_paid_order_database_unavailable_for_revocation_cooldown');
        }

        $revokeTimeQuery = $revokeTimePdo->prepare(
            'SELECT UNIX_TIMESTAMP(COALESCE(paid_at, provisioned_at, created_at))
             FROM vip_orders
             WHERE order_ref = ? AND steamid64 = ?
             LIMIT 1'
        );
        $revokeTimeQuery->execute([
            $revokeOrderRef,
            $ordersPageSteamId,
        ]);
        $revokePurchaseTimestamp = (int)$revokeTimeQuery->fetchColumn();

        if ($revokePurchaseTimestamp < 1) {
            throw new RuntimeException('vip_paid_order_timestamp_missing_for_revocation_cooldown');
        }

        $ordersPageRevokeAvailableAt = $revokePurchaseTimestamp + 3600;
        $ordersPageRevokeRemainingSeconds = max(0, $ordersPageRevokeAvailableAt - time());
    } catch (Throwable $e) {
        $ordersPageRevokeCooldownUnavailable = true;
        error_log(
            '[FragCity Orders] VIP revocation cooldown lookup failed; steam_hash='
            . substr(hash('sha256', $ordersPageSteamId), 0, 16)
            . '; type=' . get_class($e)
        );
    }
}

$ordersPageTotal = count($ordersPageRows);
$ordersPageCompleted = 0;
$ordersPageAttention = 0;
$ordersPageSpentRial = 0;

foreach ($ordersPageRows as $ordersPageRow) {
    $rowStatus = (string)($ordersPageRow['status'] ?? '');
    if ($rowStatus === 'provisioned') {
        $ordersPageCompleted++;
    } elseif (in_array($rowStatus, ['paid', 'pending'], true)) {
        $ordersPageAttention++;
    }

    if (in_array($rowStatus, ['paid', 'provisioned'], true)) {
        $ordersPageSpentRial += max(0, (int)($ordersPageRow['amount_rial'] ?? 0));
    }
}
?>

<section class="fc-orders" id="fcOrders" data-vip-revoke-csrf="<?= $ordersPageEscape($ordersPageCsrf) ?>">
    <div class="fc-orders__ambient fc-orders__ambient--one" aria-hidden="true"></div>
    <div class="fc-orders__ambient fc-orders__ambient--two" aria-hidden="true"></div>

    <header class="fc-orders__header">
        <div class="fc-orders__header-copy">
            <span class="fc-orders__eyebrow"><i class="fa-solid fa-receipt" aria-hidden="true"></i> FRAGCITY · ORDER CENTER</span>
            <h1>سفارش‌های شما</h1>
            <p>وضعیت پرداخت، پردازش و فعال‌سازی سفارش‌ها را از یک بخش پیگیری کنید.</p>
        </div>
        <a class="fc-orders__refresh" href="/orders/" aria-label="تازه‌سازی فهرست سفارش‌ها">
            <i class="fa-solid fa-rotate" aria-hidden="true"></i>
            تازه‌سازی
        </a>
    </header>

    <section class="fc-orders__vip-status<?= $ordersPageCurrentVipActive ? ' is-active' : '' ?>" id="fcOrdersVipStatus" aria-labelledby="fcOrdersVipStatusTitle">
        <span class="fc-orders__vip-status-icon" aria-hidden="true">
            <i class="fa-solid <?= $ordersPageCurrentVipActive ? 'fa-crown' : ($ordersPageVipStatusUnavailable ? 'fa-triangle-exclamation' : 'fa-shield-halved') ?>"></i>
        </span>
        <div class="fc-orders__vip-status-copy">
            <h2 id="fcOrdersVipStatusTitle">وضعیت فعلی VIP</h2>
            <?php if ($ordersPageVipStatusUnavailable): ?>
                <p class="fc-orders__vip-status-description is-unavailable">دریافت وضعیت از VIPCore ممکن نیست؛ برای جلوگیری از نمایش اطلاعات اشتباه، عملیات ابطال در دسترس نیست.</p>
            <?php elseif ($ordersPageCurrentVipActive): ?>
                <p class="fc-orders__vip-status-description">
                    اشتراک <strong data-vip-current-level><?= $ordersPageEscape($ordersPageCurrentVipName) ?></strong>
                    <span class="fc-orders__vip-active-pill"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> فعال</span>
                </p>
                <div class="fc-orders__vip-status-meta">
                    <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> اعتبار تا: <strong data-vip-current-expiry><?= $ordersPageEscape($ordersPageCurrentVipExpiryLabel) ?></strong></span>
                </div>
            <?php else: ?>
                <p class="fc-orders__vip-status-description" data-vip-current-empty>در حال حاضر هیچ سطح VIP فعالی روی حساب شما ثبت نشده است.</p>
            <?php endif; ?>
        </div>
        <div class="fc-orders__vip-status-actions">
            <?php if ($ordersPageCurrentVipActive): ?>
                <button
                    class="fc-orders__revoke-trigger"
                    type="button"
                    data-vip-revoke-open
                    data-vip-revoke-available-at="<?= $ordersPageRevokeAvailableAt ?>"
                    data-vip-revoke-server-now="<?= time() ?>"
                    <?= ($ordersPageRevokeRemainingSeconds > 0 || $ordersPageRevokeCooldownUnavailable) ? 'disabled' : '' ?>
                >
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    ابطال وضعیت VIP
                </button>
                <?php if ($ordersPageRevokeCooldownUnavailable): ?>
                    <span class="fc-orders__revoke-cooldown is-unavailable">زمان پرداخت سفارش برای بررسی محدودیت ابطال در دسترس نیست؛ فعلاً امکان ابطال وجود ندارد.</span>
                <?php elseif ($ordersPageRevokeCooldownRequired && $ordersPageRevokeRemainingSeconds > 0): ?>
                    <span class="fc-orders__revoke-cooldown" data-vip-revoke-countdown>امکان ابطال یک ساعت پس از پرداخت فعال می‌شود.</span>
                <?php elseif ($ordersPageRevokeCooldownRequired): ?>
                    <span class="fc-orders__revoke-cooldown" data-vip-revoke-countdown>محدودیت یک‌ساعته به پایان رسیده است؛ امکان ابطال فعال است.</span>
                <?php endif; ?>
            <?php elseif (!$ordersPageVipStatusUnavailable): ?>
                <a class="fc-orders__buy-link" href="/vip/" data-ajax-nav data-slug="vip">
                    <i class="fa-solid fa-crown" aria-hidden="true"></i>
                    خرید VIP
                </a>
            <?php endif; ?>
        </div>
        <span class="fc-orders__vip-status-feedback" data-vip-revoke-feedback role="status" aria-live="polite"></span>
    </section>

    <dialog class="fc-orders__revoke-dialog" id="fcVipRevokeDialog" aria-labelledby="fcVipRevokeTitle" aria-describedby="fcVipRevokeDescription">
        <div class="fc-orders__revoke-panel">
            <span class="fc-orders__revoke-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span class="fc-orders__section-kicker">VIP MEMBERSHIP CONTROL</span>
            <h2 id="fcVipRevokeTitle">ابطال وضعیت VIP</h2>
            <p id="fcVipRevokeDescription">آیا مطمئنی می‌خوای سطح فعلی VIP رو باطل کنی؟</p>
            <p class="fc-orders__revoke-warning">با تأیید، VIP فعلی باطل می‌شود. مبلغ مربوط به ثانیه‌های استفاده‌نشده، با محاسبه دقیق زمان و با احتساب پرداخت‌های ارتقا، به کیف پول FragCity شما برمی‌گردد؛ سفارش‌های پرداخت‌شده‌ای که هنوز فعال نشده‌اند نیز به‌طور کامل اعتبار می‌گیرند.</p>
            <div class="fc-orders__revoke-actions">
                <button type="button" class="fc-orders__revoke-cancel" data-vip-revoke-cancel>انصراف</button>
                <button type="button" class="fc-orders__revoke-confirm" data-vip-revoke-confirm>
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    بله، باطل کن
                </button>
            </div>
            <p class="fc-orders__revoke-feedback" data-vip-revoke-modal-feedback role="status" aria-live="polite"></p>
        </div>
    </dialog>

    <section class="fc-orders__summary" aria-label="خلاصه سفارش‌ها">
        <article class="fc-orders__metric">
            <span class="fc-orders__metric-icon"><i class="fa-solid fa-box" aria-hidden="true"></i></span>
            <span class="fc-orders__metric-label">کل سفارش‌های VIP</span>
            <strong><?= number_format($ordersPageTotal) ?></strong>
            <small>آخرین ۱۰۰ سفارش ثبت‌شده</small>
        </article>
        <article class="fc-orders__metric">
            <span class="fc-orders__metric-icon fc-orders__metric-icon--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
            <span class="fc-orders__metric-label">فعال‌سازی تکمیل‌شده</span>
            <strong><?= number_format($ordersPageCompleted) ?></strong>
            <small>تأییدشده از طریق VIPCore API</small>
        </article>
        <article class="fc-orders__metric">
            <span class="fc-orders__metric-icon fc-orders__metric-icon--pending"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
            <span class="fc-orders__metric-label">نیازمند پیگیری</span>
            <strong><?= number_format($ordersPageAttention) ?></strong>
            <small>در انتظار پرداخت یا فعال‌سازی</small>
        </article>
    </section>

    <nav class="fc-orders__categories" aria-label="دسته سفارش‌ها">
        <div class="fc-orders__category fc-orders__category--active" aria-current="page">
            <span class="fc-orders__category-icon"><i class="fa-solid fa-crown" aria-hidden="true"></i></span>
            <span><strong>سفارش‌های VIP</strong><small>پرداخت و فعال‌سازی اشتراک</small></span>
            <span class="fc-orders__category-count"><?= number_format($ordersPageTotal) ?></span>
        </div>
        <div class="fc-orders__category fc-orders__category--disabled" aria-disabled="true">
            <span class="fc-orders__category-icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
            <span><strong>سفارش‌های سرور</strong><small>خرید، تمدید و تخصیص منابع</small></span>
            <span class="fc-orders__coming-soon">به‌زودی</span>
        </div>
    </nav>

    <section class="fc-orders__content" aria-labelledby="fcOrdersVipTitle">
        <div class="fc-orders__section-heading">
            <div>
                <span class="fc-orders__section-kicker">VIP PURCHASE HISTORY</span>
                <h2 id="fcOrdersVipTitle">تاریخچه سفارش‌های VIP</h2>
                <p>هر سفارش مستقل نمایش داده می‌شود؛ پرداخت موفق به‌تنهایی به معنی فعال‌سازی نیست.</p>
            </div>
            <a href="/vip/" class="fc-orders__buy-link" data-ajax-nav data-slug="vip">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                خرید VIP
            </a>
        </div>

        <?php if ($ordersPageError): ?>
            <div class="fc-orders__state fc-orders__state--error" role="alert">
                <span class="fc-orders__state-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                <div>
                    <strong>دریافت سفارش‌ها انجام نشد</strong>
                    <p><?= $ordersPageEscape($ordersPageErrorMessage) ?></p>
                </div>
            </div>
        <?php elseif ($ordersPageTotal === 0): ?>
            <div class="fc-orders__empty">
                <span class="fc-orders__empty-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                <span class="fc-orders__empty-kicker">NO VIP ORDERS YET</span>
                <h3>هنوز سفارشی ثبت نکرده‌اید</h3>
                <p>بعد از اولین خرید VIP، وضعیت پرداخت و فعال‌سازی آن در همین صفحه قرار می‌گیرد.</p>
                <a href="/vip/" class="fc-orders__buy-link fc-orders__buy-link--primary" data-ajax-nav data-slug="vip">
                    <i class="fa-solid fa-crown" aria-hidden="true"></i>
                    مشاهده پلن‌های VIP
                </a>
            </div>
        <?php else: ?>
            <div class="fc-orders__list">
                <?php foreach ($ordersPageRows as $order): ?>
                    <?php
                    $orderId = (int)($order['id'] ?? 0);
                    $orderRef = (string)($order['order_ref'] ?? '');
                    $orderStatus = strtolower(trim((string)($order['status'] ?? 'unknown')));
                    $statusInfo = $ordersPageStatuses[$orderStatus] ?? [
                        'label' => 'وضعیت نامشخص',
                        'class' => 'muted',
                        'icon' => 'fa-circle-question',
                        'description' => 'وضعیت این سفارش قابل شناسایی نیست.',
                    ];
                    $orderLevel = (int)($order['vip_level'] ?? 0);
                    $orderTier = $ordersPageLevels[$orderLevel] ?? 'VIP';
                    $orderDays = max(0, (int)($order['duration_days'] ?? 0));
                    $orderAmountRial = max(0, (int)($order['amount_rial'] ?? 0));
                    $orderAmountToman = intdiv($orderAmountRial, 10);
                    $orderAuthority = trim((string)($order['authority'] ?? ''));
                    $isWalletOrder = str_starts_with($orderAuthority, 'WALLET_');
                    $paymentLabel = $isWalletOrder
                        ? 'کیف پول FragCity'
                        : ($orderAuthority !== '' ? 'زرین‌پال' : 'روش پرداخت نامشخص');
                    $purchaseType = (string)($order['purchase_type'] ?? 'new');
                    $purchaseTypeLabel = match ($purchaseType) {
                        'upgrade' => 'ارتقای اشتراک',
                        'replacement' => 'جایگزینی اشتراک',
                        default => 'خرید جدید',
                    };
                    $orderCreatedLabel = $ordersPageDate($order['created_at'] ?? '');
                    $orderPaidLabel = $ordersPageDate($order['paid_at'] ?? '');
                    $orderProvisionedLabel = $ordersPageDate($order['provisioned_at'] ?? '');
                    $canRetryActivation = $orderStatus === 'paid'
                        && preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $orderAuthority);
                    $retryActivationUrl = $canRetryActivation
                        ? '/pages/zarinpal/zarinpal_vip_callback.php?Authority=' . rawurlencode($orderAuthority) . '&Status=OK'
                        : '';
                    ?>
                    <article class="fc-orders__order-card" data-order-ref="<?= $ordersPageEscape($orderRef) ?>" data-order-status="<?= $ordersPageEscape($orderStatus) ?>">
                        <div class="fc-orders__order-main">
                            <span class="fc-orders__order-icon"><i class="fa-solid fa-crown" aria-hidden="true"></i></span>
                            <div class="fc-orders__order-identity">
                                <div class="fc-orders__order-title-row">
                                    <h3>اشتراک VIP <?= $ordersPageEscape($orderTier) ?></h3>
                                    <span class="fc-orders__order-id">سفارش #<?= number_format($orderId) ?></span>
                                </div>
                                <div class="fc-orders__order-meta">
                                    <span><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= $ordersPageEscape($orderCreatedLabel) ?></span>
                                    <span><i class="fa-solid fa-clock" aria-hidden="true"></i><?= number_format($orderDays) ?> روز</span>
                                    <span><i class="fa-solid fa-arrow-up-right-dots" aria-hidden="true"></i><?= $ordersPageEscape($purchaseTypeLabel) ?></span>
                                </div>
                            </div>
                            <span class="fc-orders__status fc-orders__status--<?= $ordersPageEscape($statusInfo['class']) ?>">
                                <i class="fa-solid <?= $ordersPageEscape($statusInfo['icon']) ?>" aria-hidden="true"></i>
                                <?= $ordersPageEscape($statusInfo['label']) ?>
                            </span>
                        </div>

                        <div class="fc-orders__order-details">
                            <div class="fc-orders__detail">
                                <span>مبلغ سفارش</span>
                                <strong><?= number_format($orderAmountToman) ?> <small>تومان</small></strong>
                            </div>
                            <div class="fc-orders__detail">
                                <span>روش پرداخت</span>
                                <strong><?= $ordersPageEscape($paymentLabel) ?></strong>
                            </div>
                            <div class="fc-orders__detail">
                                <span>شناسه سفارش</span>
                                <strong class="fc-orders__mono" title="<?= $ordersPageEscape($orderRef) ?>"><?= $ordersPageEscape($orderRef) ?></strong>
                            </div>
                            <div class="fc-orders__detail">
                                <span><?= $orderStatus === 'provisioned' ? 'تاریخ فعال‌سازی' : 'آخرین وضعیت پرداخت' ?></span>
                                <strong><?= $ordersPageEscape($orderStatus === 'provisioned' ? $orderProvisionedLabel : ($orderPaidLabel !== '—' ? $orderPaidLabel : $orderCreatedLabel)) ?></strong>
                            </div>
                        </div>

                        <div class="fc-orders__order-footer">
                            <p class="fc-orders__order-description"><?= $ordersPageEscape($statusInfo['description']) ?></p>
                            <?php if ($canRetryActivation): ?>
                                <a class="fc-orders__retry" href="<?= $ordersPageEscape($retryActivationUrl) ?>">
                                    <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                                    تلاش مجدد برای فعال‌سازی
                                </a>
                            <?php elseif ($orderStatus === 'provisioned'): ?>
                                <span class="fc-orders__verified"><i class="fa-solid fa-shield-check" aria-hidden="true"></i> تأییدشده توسط VIPCore</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="fc-orders__footnote">برای حفظ سرعت صفحه، آخرین ۱۰۰ سفارش نمایش داده می‌شود. اطلاعات این بخش مستقیماً از سفارش‌های ثبت‌شده در حساب شما خوانده می‌شود.</p>
        <?php endif; ?>
    </section>
</section>
