<?php
declare(strict_types=1);

/**
 * Authenticated customer inventory for FragCity CS2 services.
 * Server metadata is fetched server-side through the signed Paymenter bridge.
 */
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php?page=myservers', true, 303);
    exit;
}

if (
    !isset($_SESSION['user_logged_in'])
    || $_SESSION['user_logged_in'] !== true
) {
    header('Location: index.php?page=login', true, 303);
    exit;
}

require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

$myServers = [];
$myServersError = false;
$myServersSteamId = (string) ($_SESSION['steamid'] ?? '');
$myServersCsrf = (string) ($_SESSION['auth_csrf_token'] ?? '');

$myServersEscape = static function ($value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$myServersDate = static function ($value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y/m/d H:i');
    } catch (Throwable) {
        return '—';
    }
};

$myServersMiB = static function ($value): string {
    $value = (int) $value;
    if ($value <= 0) {
        return 'بدون محدودیت';
    }

    if ($value >= 1024) {
        return number_format($value / 1024, 1) . ' GB';
    }

    return number_format($value) . ' MB';
};

$myServersServiceLabels = [
    'active' => 'فعال',
    'pending' => 'در انتظار',
    'suspended' => 'معلق',
    'cancelled' => 'لغوشده',
];

if (preg_match('/^[0-9]{17}$/D', $myServersSteamId)) {
    try {
        $myServersClient = new PaymenterApiClient();
        $myServers = $myServersClient->getCustomerServers($myServersSteamId);
    } catch (Throwable $e) {
        $myServersError = true;
        error_log(
            '[FragCity] My Servers page could not retrieve customer services; steam_hash='
            . substr(hash('sha256', $myServersSteamId), 0, 16)
            . '; exception=' . get_class($e) . '.'
        );
    }
} else {
    $myServersError = true;
}
?>

<section
    class="fc-myservers"
    id="fcMyServers"
    data-csrf="<?= $myServersEscape($myServersCsrf) ?>"
>
    <div class="fc-myservers__ambient" aria-hidden="true"></div>

    <header class="fc-myservers__header">
        <div class="fc-myservers__eyebrow">
            <span class="fc-myservers__eyebrow-dot"></span>
            FRAGCITY CONTROL CENTER
        </div>
        <h1>سرورهای من</h1>
        <p>تمام سرویس‌های خریداری‌شده را اینجا ببینید و به پنل مدیریت هر سرور دسترسی داشته باشید.</p>

        <div class="fc-myservers__summary">
            <div class="fc-myservers__summary-icon">
                <i class="fa-solid fa-server" aria-hidden="true"></i>
            </div>
            <div>
                <strong><?= number_format(count($myServers)) ?></strong>
                <span>سرویس ثبت‌شده</span>
            </div>
        </div>
    </header>

    <?php if ($myServersError): ?>
        <div class="fc-myservers__notice fc-myservers__notice--error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>دریافت اطلاعات سرورها انجام نشد</strong>
                <p>ارتباط با سرویس مدیریت سرورها برقرار نشد. چند لحظه بعد صفحه را تازه‌سازی کنید.</p>
            </div>
        </div>

    <?php elseif ($myServers === []): ?>
        <div class="fc-myservers__empty">
            <div class="fc-myservers__empty-icon">
                <i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i>
            </div>
            <span class="fc-myservers__empty-kicker">YOUR GAME SERVERS</span>
            <h2>هنوز سروری ندارید</h2>
            <p>بعد از خرید و ثبت سرویس CS2، کارت سرور شما در همین صفحه نمایش داده می‌شود.</p>
            <a href="?page=buyserveropen" data-ajax-nav data-slug="buyserveropen" class="fc-myservers__primary-link">
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                مشاهده پلن‌های سرور
            </a>
        </div>

    <?php else: ?>
        <div class="fc-myservers__toolbar">
            <div>
                <h2>سرویس‌های شما</h2>
                <p>برای مشاهده مشخصات و اطلاعات ورود، کارت هر سرور را باز کنید.</p>
            </div>
            <span class="fc-myservers__count"><?= number_format(count($myServers)) ?> SERVER<?= count($myServers) === 1 ? '' : 'S' ?></span>
        </div>

        <div class="fc-myservers__grid">
            <?php foreach ($myServers as $serverRow): ?>
                <?php
                if (!is_array($serverRow)) {
                    continue;
                }

                $state = (string) ($serverRow['state'] ?? 'unavailable');
                $serviceId = (int) ($serverRow['service_id'] ?? 0);
                $productName = trim((string) ($serverRow['product_name'] ?? 'CS2 Server'));
                $serviceStatus = strtolower(trim((string) ($serverRow['service_status'] ?? 'unknown')));
                $serviceLabel = $myServersServiceLabels[$serviceStatus] ?? 'نامشخص';
                $server = is_array($serverRow['server'] ?? null) ? $serverRow['server'] : [];
                $panel = is_array($serverRow['panel'] ?? null) ? $serverRow['panel'] : [];
                $serverName = trim((string) ($server['name'] ?? ''));
                $uuid = trim((string) ($server['uuid'] ?? ''));
                $identifier = trim((string) ($server['identifier'] ?? ''));
                $serverRuntimeStatus = strtolower(trim((string) ($server['server_status'] ?? '')));
                $serverRuntimeLabel = match ($serverRuntimeStatus) {
                    'installing' => 'در حال نصب',
                    'install_failed' => 'خطای نصب',
                    'reinstall_failed' => 'خطای نصب مجدد',
                    'restoring_backup' => 'در حال بازیابی بکاپ',
                    'suspended' => 'معلق',
                    default => 'آماده',
                };
                $panelUsername = trim((string) ($panel['username'] ?? ''));
                $panelEmail = trim((string) ($panel['email'] ?? ''));
                $panelUrl = (string) ($panel['server_url'] ?? '');
                $panelUrlParts = parse_url($panelUrl);
                $safePanelUrl = is_array($panelUrlParts)
                    && strtolower((string) ($panelUrlParts['scheme'] ?? '')) === 'https'
                    && !empty($panelUrlParts['host'])
                    ? $panelUrl
                    : '';
                $isSuspended = !empty($server['suspended']) || $serviceStatus === 'suspended';
                $cardStatus = $state === 'ready'
                    ? ($isSuspended ? 'suspended' : 'active')
                    : $state;
                $cardStatusLabel = match ($cardStatus) {
                    'active' => 'آماده استفاده',
                    'suspended' => 'معلق',
                    'provisioning' => 'در حال آماده‌سازی',
                    default => 'نیازمند بررسی',
                };
                $limits = is_array($server['limits'] ?? null) ? $server['limits'] : [];
                $features = is_array($server['feature_limits'] ?? null) ? $server['feature_limits'] : [];
                $passwordSet = !empty($panel['password_set']);
                $cardId = 'fcServerDetails' . $serviceId;
                ?>

                <details class="fc-server-card" id="<?= $myServersEscape($cardId) ?>">
                    <summary class="fc-server-card__summary">
                        <span class="fc-server-card__icon">
                            <i class="fa-solid fa-server" aria-hidden="true"></i>
                        </span>
                        <span class="fc-server-card__main">
                            <span class="fc-server-card__title"><?= $myServersEscape($serverName !== '' ? $serverName : $productName . ' #' . $serviceId) ?></span>
                            <span class="fc-server-card__meta">
                                <span>Service #<?= number_format($serviceId) ?></span>
                                <?php if ($uuid !== ''): ?>
                                    <span class="fc-server-card__meta-separator">·</span>
                                    <span>UUID <?= $myServersEscape(substr($uuid, 0, 8)) ?>…</span>
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="fc-server-card__status fc-server-card__status--<?= $myServersEscape($cardStatus) ?>">
                            <span></span>
                            <?= $myServersEscape($cardStatusLabel) ?>
                        </span>
                        <span class="fc-server-card__chevron">
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </span>
                    </summary>

                    <div class="fc-server-card__body">
                        <?php if ($state === 'provisioning'): ?>
                            <div class="fc-myservers__notice fc-myservers__notice--pending">
                                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                <div>
                                    <strong>سرور در حال آماده‌سازی است</strong>
                                    <p>به‌محض تکمیل ساخت سرور در پنل، اطلاعات ورود و مشخصات آن اینجا نمایش داده می‌شود.</p>
                                </div>
                            </div>
                        <?php elseif ($state !== 'ready'): ?>
                            <div class="fc-myservers__notice fc-myservers__notice--error">
                                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                <div>
                                    <strong>اطلاعات این سرور فعلاً در دسترس نیست</strong>
                                    <p>سرویس ثبت شده است، اما امکان تأیید اطلاعات پنل وجود نداشت. در صورت تداوم مشکل با پشتیبانی تماس بگیرید.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="fc-server-card__section-heading">
                                <span>مشخصات سرور</span>
                                <span class="fc-server-card__section-line"></span>
                            </div>

                            <div class="fc-server-info-grid">
                                <div class="fc-server-info">
                                    <span>نام سرور</span>
                                    <strong><?= $myServersEscape($serverName !== '' ? $serverName : $productName) ?></strong>
                                </div>
                                <div class="fc-server-info">
                                    <span>شناسه Pterodactyl</span>
                                    <strong>#<?= number_format((int) ($server['id'] ?? 0)) ?></strong>
                                </div>
                                <div class="fc-server-info fc-server-info--wide">
                                    <span>UUID</span>
                                    <strong class="fc-server-info__mono"><?= $myServersEscape($uuid) ?></strong>
                                    <button type="button" class="fc-copy-button" data-copy-value="<?= $myServersEscape($uuid) ?>" aria-label="کپی UUID">
                                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="fc-server-info">
                                    <span>Identifier</span>
                                    <strong class="fc-server-info__mono"><?= $myServersEscape($identifier) ?></strong>
                                </div>
                                <div class="fc-server-info">
                                    <span>Node ID</span>
                                    <strong><?= number_format((int) ($server['node'] ?? 0)) ?></strong>
                                </div>
                                <div class="fc-server-info">
                                    <span>زمان ایجاد سرور</span>
                                    <strong><?= $myServersEscape($myServersDate($server['created_at'] ?? '')) ?></strong>
                                </div>
                                <div class="fc-server-info">
                                    <span>تاریخ انقضای سرویس</span>
                                    <strong><?= $myServersEscape($myServersDate($server['expires_at'] ?? '')) ?></strong>
                                </div>
                            </div>

                            <div class="fc-server-card__section-heading">
                                <span>منابع اختصاص‌یافته</span>
                                <span class="fc-server-card__section-line"></span>
                            </div>
                            <div class="fc-server-resource-grid">
                                <div class="fc-server-resource">
                                    <i class="fa-solid fa-microchip" aria-hidden="true"></i>
                                    <span>CPU</span>
                                    <strong><?= (int) ($limits['cpu'] ?? 0) > 0 ? number_format((int) $limits['cpu']) . '%' : 'بدون محدودیت' ?></strong>
                                </div>
                                <div class="fc-server-resource">
                                    <i class="fa-solid fa-memory" aria-hidden="true"></i>
                                    <span>RAM</span>
                                    <strong><?= $myServersEscape($myServersMiB($limits['memory'] ?? 0)) ?></strong>
                                </div>
                                <div class="fc-server-resource">
                                    <i class="fa-solid fa-hard-drive" aria-hidden="true"></i>
                                    <span>Disk</span>
                                    <strong><?= $myServersEscape($myServersMiB($limits['disk'] ?? 0)) ?></strong>
                                </div>
                                <div class="fc-server-resource">
                                    <i class="fa-solid fa-database" aria-hidden="true"></i>
                                    <span>Databases</span>
                                    <strong><?= number_format((int) ($features['databases'] ?? 0)) ?></strong>
                                </div>
                                <div class="fc-server-resource">
                                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                                    <span>Backups</span>
                                    <strong><?= number_format((int) ($features['backups'] ?? 0)) ?></strong>
                                </div>
                                <div class="fc-server-resource">
                                    <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
                                    <span>Allocations</span>
                                    <strong><?= number_format((int) ($features['allocations'] ?? 0)) ?></strong>
                                </div>
                            </div>

                            <div class="fc-server-card__section-heading">
                                <span>اطلاعات ورود به پنل</span>
                                <span class="fc-server-card__section-line"></span>
                            </div>

                            <div class="fc-panel-access">
                                <div class="fc-panel-access__top">
                                    <div>
                                        <span class="fc-panel-access__eyebrow">PTERODACTYL CONTROL PANEL</span>
                                        <h3>حساب مدیریت سرور</h3>
                                    </div>
                                    <span class="fc-panel-access__password-state <?= $passwordSet ? 'is-set' : 'is-pending' ?>" data-password-status>
                                        <i class="fa-solid <?= $passwordSet ? 'fa-circle-check' : 'fa-key' ?>" aria-hidden="true"></i>
                                        <?= $passwordSet ? 'رمز اختصاصی تنظیم شده' : 'نیازمند تنظیم رمز' ?>
                                    </span>
                                </div>

                                <div class="fc-panel-access__credentials">
                                    <div class="fc-panel-access__credential">
                                        <span>آدرس پنل</span>
                                        <strong><?= $myServersEscape($panel['base_url'] ?? '') ?></strong>
                                    </div>
                                    <div class="fc-panel-access__credential">
                                        <span>Username</span>
                                        <strong class="fc-server-info__mono"><?= $myServersEscape($panelUsername) ?></strong>
                                        <button type="button" class="fc-copy-button" data-copy-value="<?= $myServersEscape($panelUsername) ?>" aria-label="کپی نام کاربری">
                                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="fc-panel-access__credential">
                                        <span>Email</span>
                                        <strong class="fc-server-info__mono"><?= $myServersEscape($panelEmail) ?></strong>
                                        <button type="button" class="fc-copy-button" data-copy-value="<?= $myServersEscape($panelEmail) ?>" aria-label="کپی ایمیل">
                                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="fc-panel-password">
                                    <div class="fc-panel-password__heading">
                                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                        <div>
                                            <strong><?= $passwordSet ? 'تغییر رمز عبور پنل' : 'تنظیم رمز عبور پنل' ?></strong>
                                            <p>این رمز فقط برای ورود به Pterodactyl است و با رمز FragCity یا Paymenter تفاوت دارد.</p>
                                        </div>
                                    </div>

                                    <form class="fc-panel-password__form" data-panel-password-form>
                                        <input type="hidden" name="service_id" value="<?= $serviceId ?>">

                                        <label>
                                            <span>رمز فعلی FragCity برای تأیید هویت</span>
                                            <input type="password" name="current_password" autocomplete="current-password" minlength="8" required>
                                        </label>
                                        <div class="fc-panel-password__fields">
                                            <label>
                                                <span>رمز جدید پنل</span>
                                                <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" required>
                                            </label>
                                            <label>
                                                <span>تکرار رمز جدید</span>
                                                <input type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="128" required>
                                            </label>
                                        </div>
                                        <p class="fc-panel-password__hint">حداقل ۱۲ کاراکتر، شامل حروف و عدد و بدون فاصله. رمز فعلی پنل قابل بازیابی نیست؛ می‌توانید هر زمان رمز جدیدی تنظیم کنید.</p>
                                        <div class="fc-panel-password__footer">
                                            <span class="fc-panel-password__message" data-password-message role="status" aria-live="polite"></span>
                                            <button type="submit" class="fc-panel-password__submit">
                                                <i class="fa-solid fa-key" aria-hidden="true"></i>
                                                <span><?= $passwordSet ? 'ذخیره رمز جدید' : 'تنظیم رمز پنل' ?></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <?php if ($safePanelUrl !== ''): ?>
                                    <a class="fc-panel-access__open" href="<?= $myServersEscape($safePanelUrl) ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                        ورود به صفحه همین سرور
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
