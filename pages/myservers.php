<?php
declare(strict_types=1);

/**
 * FragCity customer server dashboard.
 *
 * The website is the customer-facing UI. Paymenter/Pterodactyl data is
 * resolved server-side through the signed bridge; no panel API credentials
 * are exposed to the browser.
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
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

$myServersExpirySummary = static function ($purchaseValue, $expiresValue): array {
    $expiresValue = trim((string) $expiresValue);
    if ($expiresValue === '') {
        return [
            'has_expiry' => false,
            'days_remaining' => null,
            'progress' => 0,
            'critical' => false,
            'label' => 'No Expiry',
        ];
    }

    try {
        $expiresAt = new DateTimeImmutable($expiresValue);
        $now = new DateTimeImmutable('now', $expiresAt->getTimezone());
        $remainingSeconds = $expiresAt->getTimestamp() - $now->getTimestamp();
        $daysRemaining = $remainingSeconds > 0
            ? (int) ceil($remainingSeconds / 86400)
            : 0;

        $progress = 0.0;
        $purchaseValue = trim((string) $purchaseValue);
        if ($purchaseValue !== '') {
            try {
                $purchasedAt = new DateTimeImmutable($purchaseValue);
                $totalSeconds = $expiresAt->getTimestamp() - $purchasedAt->getTimestamp();
                if ($totalSeconds > 0) {
                    $progress = round(
                        max(0, min(100, ($remainingSeconds / $totalSeconds) * 100)),
                        1
                    );
                }
            } catch (Throwable) {
                $progress = 0.0;
            }
        }

        return [
            'has_expiry' => true,
            'days_remaining' => $daysRemaining,
            'progress' => $progress,
            'critical' => $daysRemaining <= 5,
            'label' => $daysRemaining === 0
                ? 'Expired'
                : $daysRemaining . ($daysRemaining === 1 ? ' day left' : ' days left'),
        ];
    } catch (Throwable) {
        return [
            'has_expiry' => false,
            'days_remaining' => null,
            'progress' => 0,
            'critical' => false,
            'label' => 'Unavailable',
        ];
    }
};

$myServersMiB = static function ($value): string {
    $value = (int) $value;
    if ($value <= 0) {
        return 'نامحدود';
    }

    if ($value >= 1024) {
        return number_format($value / 1024, 1) . ' GB';
    }

    return number_format($value) . ' MB';
};

$myServersStatus = static function (array $row): array {
    $state = strtolower(trim((string) ($row['state'] ?? 'unavailable')));
    $serviceStatus = strtolower(trim((string) ($row['service_status'] ?? 'unknown')));
    $server = is_array($row['server'] ?? null) ? $row['server'] : [];
    $suspended = !empty($server['suspended']) || $serviceStatus === 'suspended';

    if ($state === 'provisioning') {
        return ['در حال آماده‌سازی', 'provisioning'];
    }
    if ($state !== 'ready') {
        return ['نیازمند بررسی', 'unavailable'];
    }
    if ($suspended) {
        return ['معلق', 'suspended'];
    }
    if ($serviceStatus === 'active') {
        return ['فعال', 'active'];
    }

    return ['در انتظار', 'pending'];
};

$myServersServices = [];

if (preg_match('/^[0-9]{17}$/D', $myServersSteamId)) {
    try {
        $myServersClient = new PaymenterApiClient();
        $myServers = $myServersClient->getCustomerServers($myServersSteamId);

        foreach ($myServers as $row) {
            if (!is_array($row)) {
                continue;
            }

            $serviceId = (int) ($row['service_id'] ?? 0);
            if ($serviceId < 1) {
                continue;
            }

            $server = is_array($row['server'] ?? null) ? $row['server'] : [];
            $panel = is_array($row['panel'] ?? null) ? $row['panel'] : [];
            $limits = is_array($server['limits'] ?? null) ? $server['limits'] : [];
            $features = is_array($server['feature_limits'] ?? null) ? $server['feature_limits'] : [];
            $status = $myServersStatus($row);
            $panelUrl = (string) ($panel['server_url'] ?? '');
            $panelUrlParts = parse_url($panelUrl);
            $safePanelUrl = is_array($panelUrlParts)
                && strtolower((string) ($panelUrlParts['scheme'] ?? '')) === 'https'
                && !empty($panelUrlParts['host'])
                ? $panelUrl
                : '';

            $panelBaseUrl = (string) ($panel['base_url'] ?? '');
            $panelBaseUrlParts = parse_url($panelBaseUrl);
            $safePanelBaseUrl = is_array($panelBaseUrlParts)
                && strtolower((string) ($panelBaseUrlParts['scheme'] ?? '')) === 'https'
                && !empty($panelBaseUrlParts['host'])
                ? $panelBaseUrl
                : '';

            $myServersServices[$serviceId] = [
                'service_id' => $serviceId,
                'product_name' => trim((string) ($row['product_name'] ?? 'CS2 Server')),
                'service_status' => strtolower(trim((string) ($row['service_status'] ?? 'unknown'))),
                'state' => (string) ($row['state'] ?? 'unavailable'),
                'status_label' => $status[0],
                'status_class' => $status[1],
                'server' => $server,
                'panel' => $panel,
                'limits' => $limits,
                'features' => $features,
                'panel_url' => $safePanelUrl,
                'panel_base_url' => $safePanelBaseUrl,
                'name' => trim((string) ($server['name'] ?? '')),
                'uuid' => trim((string) ($server['uuid'] ?? '')),
                'identifier' => trim((string) ($server['identifier'] ?? '')),
                'username' => trim((string) ($panel['username'] ?? '')),
                'email' => trim((string) ($panel['email'] ?? '')),
                'password_set' => !empty($panel['password_set']),
                'runtime_status' => strtolower(trim((string) ($server['server_status'] ?? ''))),
            ];
        }
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

$myServersServices = array_values($myServersServices);
$myServersCount = count($myServersServices);
$requestedServiceId = filter_var(
    $_GET['service_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$selectedServer = null;

if ($requestedServiceId !== false && $requestedServiceId !== null) {
    foreach ($myServersServices as $candidate) {
        if ((int) $candidate['service_id'] === (int) $requestedServiceId) {
            $selectedServer = $candidate;
            break;
        }
    }
}
if ($selectedServer === null && $myServersServices !== []) {
    $selectedServer = $myServersServices[0];
}

$allowedTabs = ['overview', 'access'];
$requestedTab = strtolower(trim((string) ($_GET['tab'] ?? 'overview')));
$activeTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'overview';

$myServersTabUrl = static function (int $serviceId, string $tab): string {
    return 'index.php?page=myservers&service_id=' . $serviceId . '&tab=' . rawurlencode($tab);
};

$myServersRuntimeLabel = static function (string $status): string {
    return match ($status) {
        'installing' => 'Installing',
        'install_failed' => 'Installation Failed',
        'reinstall_failed' => 'Reinstallation Failed',
        'restoring_backup' => 'Restoring Backup',
        'suspended' => 'Suspended',
        'running' => 'Running',
        default => 'Status synced from panel',
    };
};

$myServersDetailsStatus = static function (string $statusClass): string {
    return match ($statusClass) {
        'active' => 'Active',
        'provisioning' => 'Provisioning',
        'suspended' => 'Suspended',
        'pending' => 'Pending',
        default => 'Action Required',
    };
};
?>

<section
    class="fc-myservers"
    id="fcMyServers"
    data-csrf="<?= $myServersEscape($myServersCsrf) ?>"
>
    <div class="fc-myservers__background-glow" aria-hidden="true"></div>

    <header class="fc-myservers__page-header">
        <div>
            <h1>مدیریت سرورهای من</h1>
            <p>از این بخش می‌توانید سرویس‌ها و دسترسی پنل سرورهای خود را مدیریت کنید.</p>
        </div>
        <a href="?page=buyserveropen" class="fc-myservers__new-server" data-ajax-nav data-slug="buyserveropen">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            خرید سرور جدید
        </a>
    </header>

    <?php if ($myServersError): ?>
        <div class="fc-myservers__state-card fc-myservers__state-card--error" role="alert">
            <span class="fc-myservers__state-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
            <div>
                <strong>دریافت اطلاعات سرورها انجام نشد</strong>
                <p>ارتباط با سرویس مدیریت سرورها برقرار نشد. چند لحظه بعد صفحه را تازه‌سازی کنید.</p>
            </div>
        </div>

    <?php elseif ($myServersCount === 0): ?>
        <div class="fc-myservers__empty">
            <div class="fc-myservers__empty-orbit">
                <i class="fa-solid fa-server" aria-hidden="true"></i>
            </div>
            <h2>هنوز سروری ندارید</h2>
            <p>بعد از خرید و آماده‌سازی اولین سرویس CS2، داشبورد مدیریت آن در همین صفحه نمایش داده می‌شود.</p>
            <a href="?page=buyserveropen" class="fc-myservers__new-server fc-myservers__new-server--primary" data-ajax-nav data-slug="buyserveropen">
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                مشاهده پلن‌های سرور
            </a>
        </div>

    <?php else: ?>
        <?php
        $selectedId = (int) $selectedServer['service_id'];
        $selectedServerName = $selectedServer['name'] !== ''
            ? $selectedServer['name']
            : $selectedServer['product_name'] . ' #' . $selectedId;
        $selectedStatusClass = $selectedServer['status_class'];
        $selectedStatusLabel = $selectedServer['status_label'];
        $selectedServerData = $selectedServer['server'];
        $selectedLimits = $selectedServer['limits'];
        $selectedFeatures = $selectedServer['features'];
        $selectedCanManage = $selectedServer['state'] === 'ready';
        $selectedPasswordSet = $selectedServer['password_set'];
        ?>

        <div class="fc-myservers__server-switcher">
            <div class="fc-myservers__switcher-label">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                سرورهای شما
                <span><?= number_format($myServersCount) ?></span>
            </div>
            <div class="fc-myservers__server-list">
                <?php foreach ($myServersServices as $item): ?>
                    <?php
                    $itemId = (int) $item['service_id'];
                    $itemServer = is_array($item['server'] ?? null) ? $item['server'] : [];
                    $itemLimits = is_array($item['limits'] ?? null) ? $item['limits'] : [];
                    $itemName = $item['name'] !== '' ? $item['name'] : $item['product_name'] . ' #' . $itemId;
                    $itemExpiry = $myServersExpirySummary(
                        $itemServer['service_created_at'] ?? '',
                        $itemServer['expires_at'] ?? ''
                    );
                    if (($item['state'] ?? '') !== 'ready') {
                        $itemExpiry = [
                            'has_expiry' => false,
                            'days_remaining' => null,
                            'progress' => 0,
                            'critical' => false,
                            'label' => 'Pending',
                        ];
                    }
                    $itemIdentifier = trim((string) ($item['identifier'] ?? ''));
                    $itemReady = ($item['state'] ?? '') === 'ready';
                    $itemMemory = (int) ($itemLimits['memory'] ?? 0);
                    $itemCpu = (int) ($itemLimits['cpu'] ?? 0);
                    $itemIp = trim((string) ($itemServer['allocation_ip'] ?? ''));
                    $itemAlias = trim((string) ($itemServer['allocation_alias'] ?? ''));
                    $itemPort = (int) ($itemServer['allocation_port'] ?? 0);
                    $itemHost = $itemIp !== '' ? $itemIp : $itemAlias;
                    $itemEndpoint = !$itemReady
                        ? 'Pending'
                        : ($itemHost !== ''
                            ? $itemHost . ($itemPort > 0 ? ':' . $itemPort : '')
                            : 'Not assigned');
                    $itemPurchaseDate = $myServersDate($itemServer['service_created_at'] ?? '');
                    $itemExpiryPercent = max(0, min(100, (float) $itemExpiry['progress']));
                    ?>
                    <a
                        href="<?= $myServersEscape($myServersTabUrl($itemId, 'overview')) ?>"
                        class="fc-myservers__server-card <?= $itemId === $selectedId ? 'is-selected' : '' ?>"
                        data-ajax-nav
                        data-slug="myservers"
                        <?= $itemId === $selectedId ? 'aria-current="page"' : '' ?>
                        dir="ltr"
                    >
                        <div class="fc-server-card__top">
                            <img
                                class="fc-server-card__logo"
                                src="/assets/images/logo/cs2.png"
                                alt="Counter-Strike 2"
                                width="52"
                                height="52"
                            >
                            <div class="fc-server-card__identity">
                                <strong><?= $myServersEscape($itemName) ?></strong>
                                <span>CS2 GAME SERVER · SERVICE #<?= number_format($itemId) ?></span>
                            </div>
                            <span class="fc-server-card__status fc-server-card__status--<?= $myServersEscape($item['status_class']) ?> <?= $itemId === $selectedId ? 'is-current' : '' ?>">
                                <?= $itemId === $selectedId ? 'انتخاب شده' : $myServersEscape($item['status_label']) ?>
                            </span>
                            <span class="fc-server-card__reveal" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
                        </div>

                        <div class="fc-server-card__facts">
                            <div class="fc-server-card__fact">
                                <span>UID</span>
                                <strong class="fc-mono"><?= $myServersEscape($itemIdentifier !== '' ? $itemIdentifier : '—') ?></strong>
                            </div>
                            <div class="fc-server-card__fact">
                                <span>RAM</span>
                                <strong><?= !$itemReady ? 'Pending' : ($itemMemory > 0 ? $myServersEscape($myServersMiB($itemMemory)) : 'Unlimited') ?></strong>
                            </div>
                            <div class="fc-server-card__fact">
                                <span>CPU</span>
                                <strong><?= !$itemReady ? 'Pending' : ($itemCpu > 0 ? number_format($itemCpu) . '%' : 'Unlimited') ?></strong>
                            </div>
                            <div class="fc-server-card__fact fc-server-card__fact--network">
                                <span>IP / PORT</span>
                                <strong class="fc-mono"><?= $myServersEscape($itemEndpoint) ?></strong>
                            </div>
                            <div class="fc-server-card__fact fc-server-card__fact--purchase">
                                <span>PURCHASED</span>
                                <strong><?= $myServersEscape($itemPurchaseDate) ?></strong>
                            </div>
                        </div>

                        <div class="fc-server-card__expiry <?= $itemExpiry['critical'] ? 'is-critical' : '' ?>">
                            <div class="fc-server-card__expiry-heading">
                                <span>REMAINING TIME</span>
                                <strong><?= $myServersEscape($itemExpiry['label']) ?></strong>
                            </div>
                            <?php if ($itemExpiry['has_expiry']): ?>
                                <div
                                    class="fc-server-card__progress"
                                    role="progressbar"
                                    aria-label="Subscription time remaining"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="<?= (int) round($itemExpiryPercent) ?>"
                                >
                                    <span style="width: <?= $itemExpiryPercent ?>%;"></span>
                                </div>
                            <?php else: ?>
                                <div class="fc-server-card__no-expiry"></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="fc-myservers__dashboard-heading">
            <div class="fc-myservers__dashboard-title">
                <div class="fc-myservers__game-icon"><i class="fa-solid fa-gamepad" aria-hidden="true"></i></div>
                <div>
                    <h2><?= $myServersEscape($selectedServerName) ?></h2>
                    <div class="fc-myservers__dashboard-subtitle">
                        <span class="fc-server-status fc-server-status--<?= $myServersEscape($selectedStatusClass) ?>">
                            <i></i><?= $myServersEscape($selectedStatusLabel) ?>
                        </span>
                        <?php if ($selectedServer['identifier'] !== ''): ?>
                            <span class="fc-myservers__identifier"><?= $myServersEscape($selectedServer['identifier']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="fc-myservers__dashboard-actions">
                <?php if ($selectedCanManage && $selectedServer['panel_url'] !== ''): ?>
                    <a class="fc-myservers__panel-button" href="<?= $myServersEscape($selectedServer['panel_url']) ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        ورود به پنل سرور
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="fc-myservers__dashboard-layout">
            <aside class="fc-myservers__sidebar" aria-label="بخش‌های مدیریت سرور">
                <div class="fc-myservers__sidebar-group">
                    <span class="fc-myservers__sidebar-caption">مدیریت سرور</span>

                    <a href="<?= $myServersEscape($myServersTabUrl($selectedId, 'overview')) ?>"
                       class="fc-myservers__nav-item <?= $activeTab === 'overview' ? 'is-active' : '' ?>" data-ajax-nav data-slug="myservers"
                       <?= $activeTab === 'overview' ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid fa-house"></i>
                        <span>نمای کلی</span>
                    </a>

                    <a href="<?= $myServersEscape($myServersTabUrl($selectedId, 'access')) ?>"
                       class="fc-myservers__nav-item <?= $activeTab === 'access' ? 'is-active' : '' ?>" data-ajax-nav data-slug="myservers"
                       <?= $activeTab === 'access' ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid fa-key"></i>
                        <span>دسترسی پنل</span>
                        <?php if (!$selectedPasswordSet && $selectedCanManage): ?>
                            <span class="fc-myservers__nav-alert"></span>
                        <?php endif; ?>
                    </a>
                </div>

                <div class="fc-myservers__sidebar-divider"></div>

                <div class="fc-myservers__sidebar-group">
                    <span class="fc-myservers__sidebar-caption">ابزارهای سرور</span>
                    <button type="button" class="fc-myservers__nav-item is-disabled" disabled title="این بخش در نسخه بعدی فعال می‌شود">
                        <i class="fa-solid fa-terminal"></i><span>کنسول</span><small>به‌زودی</small>
                    </button>
                    <button type="button" class="fc-myservers__nav-item is-disabled" disabled title="این بخش در نسخه بعدی فعال می‌شود">
                        <i class="fa-solid fa-folder-open"></i><span>مدیریت فایل‌ها</span><small>به‌زودی</small>
                    </button>
                    <button type="button" class="fc-myservers__nav-item is-disabled" disabled title="این بخش در نسخه بعدی فعال می‌شود">
                        <i class="fa-solid fa-database"></i><span>پایگاه‌داده‌ها</span><small>به‌زودی</small>
                    </button>
                    <button type="button" class="fc-myservers__nav-item is-disabled" disabled title="این بخش در نسخه بعدی فعال می‌شود">
                        <i class="fa-solid fa-box-archive"></i><span>بکاپ‌ها</span><small>به‌زودی</small>
                    </button>
                    <button type="button" class="fc-myservers__nav-item is-disabled" disabled title="این بخش در نسخه بعدی فعال می‌شود">
                        <i class="fa-solid fa-clock"></i><span>زمان‌بندی‌ها</span><small>به‌زودی</small>
                    </button>
                </div>

                <div class="fc-myservers__sidebar-footer">
                    <div class="fc-myservers__sidebar-footer-icon"><i class="fa-solid fa-headset"></i></div>
                    <div><strong>نیاز به راهنمایی دارید؟</strong><span>با پشتیبانی FragCity در ارتباط باشید.</span></div>
                </div>
            </aside>

            <main class="fc-myservers__workspace">
                <?php if (!$selectedCanManage): ?>
                    <div class="fc-myservers__workspace-state fc-myservers__workspace-state--<?= $myServersEscape($selectedStatusClass) ?>">
                        <div class="fc-myservers__workspace-state-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                        <div>
                            <h3><?= $selectedServer['state'] === 'provisioning' ? 'سرور در حال آماده‌سازی است' : 'اطلاعات سرور فعلاً در دسترس نیست' ?></h3>
                            <p><?= $selectedServer['state'] === 'provisioning' ? 'بعد از تکمیل ساخت سرور در Pterodactyl، اطلاعات و دسترسی‌ها در این داشبورد فعال می‌شوند.' : 'سرویس ثبت شده است، اما اطلاعات پنل قابل تأیید نیست. در صورت تداوم مشکل با پشتیبانی تماس بگیرید.' ?></p>
                        </div>
                    </div>
                <?php elseif ($activeTab === 'overview'): ?>
                    <div class="fc-myservers__workspace-header">
                        <div>
                            <h3>نمای کلی سرور</h3>
                            <p>وضعیت سرویس، مشخصات فنی و منابع اختصاص‌یافته به سرور شما</p>
                        </div>
                    </div>

                    <div class="fc-myservers__overview-grid">
                        <section class="fc-dashboard-card fc-dashboard-card--server">
                            <div class="fc-dashboard-card__top">
                                <div class="fc-dashboard-card__icon"><i class="fa-solid fa-server"></i></div>
                                <span class="fc-dashboard-card__label">SERVER STATUS</span>
                            </div>
                            <h3><?= $myServersEscape($selectedServerName) ?></h3>
                            <div class="fc-dashboard-card__status-line">
                                <span class="fc-server-status fc-server-status--<?= $myServersEscape($selectedStatusClass) ?>"><i></i><?= $myServersEscape($selectedStatusLabel) ?></span>
                                <span class="fc-dashboard-card__service-id">Service #<?= number_format($selectedId) ?></span>
                            </div>
                            <div class="fc-dashboard-card__footer">
                                <a href="<?= $myServersEscape($myServersTabUrl($selectedId, 'access')) ?>" data-ajax-nav data-slug="myservers"><i class="fa-solid fa-key"></i> مدیریت دسترسی پنل</a>
                                <?php if ($selectedServer['panel_url'] !== ''): ?>
                                    <a href="<?= $myServersEscape($selectedServer['panel_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="باز کردن پنل"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="fc-dashboard-card fc-details-card fc-overview-identity">
                            <div class="fc-dashboard-card__heading" dir="ltr">
                                <div><h3>Server Information</h3></div>
                                <i class="fa-solid fa-fingerprint" aria-hidden="true"></i>
                            </div>
                            <div class="fc-details-grid" dir="ltr">
                                <div><span>Server Name</span><strong><?= $myServersEscape($selectedServerName) ?></strong></div>
                                <div><span>Paymenter Service ID</span><strong class="fc-mono">#<?= number_format($selectedId) ?></strong></div>
                                <div><span>Pterodactyl Server ID</span><strong class="fc-mono">#<?= number_format((int) ($selectedServerData['id'] ?? 0)) ?></strong></div>
                                <div><span>UUID</span><strong class="fc-mono"><?= $myServersEscape($selectedServer['uuid'] !== '' ? $selectedServer['uuid'] : '—') ?></strong></div>
                                <div><span>Identifier</span><strong class="fc-mono"><?= $myServersEscape($selectedServer['identifier'] !== '' ? $selectedServer['identifier'] : '—') ?></strong></div>
                                <div><span>Node ID</span><strong class="fc-mono"><?= number_format((int) ($selectedServerData['node'] ?? 0)) ?></strong></div>
                                <div><span>Allocation ID</span><strong class="fc-mono"><?= number_format((int) ($selectedServerData['allocation'] ?? 0)) ?></strong></div>
                                <div><span>Status</span><strong><?= $myServersEscape($myServersDetailsStatus($selectedStatusClass)) ?></strong></div>
                                <div><span>Server Created</span><strong><?= $myServersEscape($myServersDate($selectedServerData['created_at'] ?? '')) ?></strong></div>
                                <div><span>Service Created</span><strong><?= $myServersEscape($myServersDate($selectedServerData['service_created_at'] ?? '')) ?></strong></div>
                                <div><span>Expiration Date</span><strong><?= $myServersEscape($myServersDate($selectedServerData['expires_at'] ?? '')) ?></strong></div>
                                <div><span>Installation Status</span><strong><?= $myServersEscape($myServersRuntimeLabel($selectedServer['runtime_status'])) ?></strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="fc-myservers__section-heading">
                        <div><h3>منابع اختصاص‌یافته</h3></div>
                        <span class="fc-myservers__section-note">مقادیر پلن فعلی</span>
                    </div>

                    <div class="fc-myservers__resource-grid">
                        <div class="fc-resource-card">
                            <span class="fc-resource-card__icon"><i class="fa-solid fa-microchip"></i></span>
                            <span class="fc-resource-card__label">پردازنده CPU</span>
                            <strong><?= (int) ($selectedLimits['cpu'] ?? 0) > 0 ? number_format((int) $selectedLimits['cpu']) . '%' : 'نامحدود' ?></strong>
                            <small>سهم پردازشی مجاز</small>
                        </div>
                        <div class="fc-resource-card">
                            <span class="fc-resource-card__icon"><i class="fa-solid fa-memory"></i></span>
                            <span class="fc-resource-card__label">حافظه RAM</span>
                            <strong><?= $myServersEscape($myServersMiB($selectedLimits['memory'] ?? 0)) ?></strong>
                            <small>حداکثر حافظه اختصاص‌یافته</small>
                        </div>
                        <div class="fc-resource-card">
                            <span class="fc-resource-card__icon"><i class="fa-solid fa-hard-drive"></i></span>
                            <span class="fc-resource-card__label">فضای دیسک</span>
                            <strong><?= $myServersEscape($myServersMiB($selectedLimits['disk'] ?? 0)) ?></strong>
                            <small>فضای ذخیره‌سازی مجاز</small>
                        </div>
                        <div class="fc-resource-card">
                            <span class="fc-resource-card__icon"><i class="fa-solid fa-database"></i></span>
                            <span class="fc-resource-card__label">پایگاه‌داده</span>
                            <strong><?= number_format((int) ($selectedFeatures['databases'] ?? 0)) ?></strong>
                            <small>ظرفیت مجاز دیتابیس</small>
                        </div>
                        <div class="fc-resource-card">
                            <span class="fc-resource-card__icon"><i class="fa-solid fa-box-archive"></i></span>
                            <span class="fc-resource-card__label">بکاپ</span>
                            <strong><?= number_format((int) ($selectedFeatures['backups'] ?? 0)) ?></strong>
                            <small>ظرفیت مجاز نسخه پشتیبان</small>
                        </div>
                        <div class="fc-resource-card">
                            <span class="fc-resource-card__icon"><i class="fa-solid fa-network-wired"></i></span>
                            <span class="fc-resource-card__label">Allocation</span>
                            <strong><?= number_format((int) ($selectedFeatures['allocations'] ?? 0)) ?></strong>
                            <small>تعداد اتصال شبکه مجاز</small>
                        </div>
                    </div>

                    <div class="fc-myservers__lower-grid">
                        <section class="fc-dashboard-card fc-dashboard-card--quick">
                            <div class="fc-dashboard-card__heading"><div><h3>دسترسی سریع</h3></div></div>
                            <a href="<?= $myServersEscape($myServersTabUrl($selectedId, 'access')) ?>" class="fc-quick-link" data-ajax-nav data-slug="myservers"><span><i class="fa-solid fa-key"></i></span><div><strong>اطلاعات ورود پنل</strong><small>مشاهده نام کاربری و تنظیم رمز</small></div><i class="fa-solid fa-chevron-left"></i></a>
                            <?php if ($selectedServer['panel_url'] !== ''): ?>
                                <a href="<?= $myServersEscape($selectedServer['panel_url']) ?>" target="_blank" rel="noopener noreferrer" class="fc-quick-link"><span><i class="fa-solid fa-arrow-up-right-from-square"></i></span><div><strong>ورود به Pterodactyl</strong><small>بازکردن صفحه همین سرور</small></div><i class="fa-solid fa-chevron-left"></i></a>
                            <?php endif; ?>
                        </section>

                        <section class="fc-dashboard-card fc-dashboard-card--notice">
                            <div class="fc-dashboard-card__heading"><div><h3>مدیریت یکپارچه سرور</h3></div><i class="fa-solid fa-shield-halved"></i></div>
                            <p>این داشبورد به‌تدریج امکانات مدیریت سرور را در خود FragCity در دسترس قرار می‌دهد. بخش‌های کنسول، فایل‌ها، بکاپ و تنظیمات پس از اتصال API مربوط به هر قابلیت فعال خواهند شد.</p>
                            <div class="fc-dashboard-card__notice-footer"><i class="fa-solid fa-circle-check"></i> اطلاعات مالکیت و سرویس از Paymenter تأیید می‌شود.</div>
                        </section>
                    </div>

                <?php elseif ($activeTab === 'access'): ?>
                    <div class="fc-myservers__workspace-header">
                        <div><h3>دسترسی پنل</h3><p>اطلاعات ورود به حساب مدیریت سرورهای شما</p></div>
                    </div>
                    <section class="fc-dashboard-card fc-access-card">
                        <div class="fc-access-card__header" dir="ltr">
                            <div class="fc-access-card__brand"><span><i class="fa-solid fa-server"></i></span><div><strong>Pterodactyl Panel</strong><small>Independent server management account</small></div></div>
                            <span class="fc-access-card__password-state <?= $selectedPasswordSet ? 'is-set' : 'is-pending' ?>" data-password-status>
                                <i class="fa-solid <?= $selectedPasswordSet ? 'fa-circle-check' : 'fa-key' ?>" aria-hidden="true"></i>
                                <?= $selectedPasswordSet ? 'Password Set' : 'Password Required' ?>
                            </span>
                        </div>

                        <div class="fc-access-card__credentials" dir="ltr">
                            <div class="fc-access-field">
                                <span>Panel URL</span><strong class="fc-mono"><?= $myServersEscape($selectedServer['panel_base_url'] ?? '') ?></strong>
                                <?php if ($selectedServer['panel_base_url'] !== ''): ?>
                                    <a href="<?= $myServersEscape($selectedServer['panel_base_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open panel website"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                <?php endif; ?>
                            </div>
                            <div class="fc-access-field">
                                <span>Username</span><strong class="fc-mono" data-panel-username-value><?= $myServersEscape($selectedServer['username']) ?></strong>
                                <button type="button" data-copy-value="<?= $myServersEscape($selectedServer['username']) ?>" data-panel-username-copy aria-label="Copy username"><i class="fa-regular fa-copy"></i></button>
                            </div>
                            <div class="fc-access-field">
                                <span>Login Email</span><strong class="fc-mono"><?= $myServersEscape($selectedServer['email']) ?></strong>
                                <button type="button" data-copy-value="<?= $myServersEscape($selectedServer['email']) ?>" aria-label="Copy email"><i class="fa-regular fa-copy"></i></button>
                            </div>
                            <div class="fc-access-field">
                                <span>Direct Server URL</span><strong class="fc-mono"><?= $myServersEscape($selectedServer['panel_url'] !== '' ? $selectedServer['panel_url'] : 'Unavailable') ?></strong>
                                <?php if ($selectedServer['panel_url'] !== ''): ?>
                                    <a href="<?= $myServersEscape($selectedServer['panel_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="باز کردن پنل"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section class="fc-dashboard-card fc-password-card">
                        <div class="fc-dashboard-card__heading">
                            <div><h3>تنظیمات حساب پنل</h3></div>
                            <span class="fc-password-card__icon"><i class="fa-solid fa-user-gear"></i></span>
                        </div>
                        <p class="fc-password-card__description">نام کاربری و رمز عبور متعلق به حساب مشترک Pterodactyl شما هستند؛ تغییر هرکدام برای تمام سرورهای CS2 این حساب اعمال می‌شود.</p>

                        <div class="fc-panel-settings-section">
                            <div class="fc-panel-settings-section__heading">
                                <strong>نام کاربری پنل</strong>
                                <span>قابل ویرایش</span>
                            </div>
                            <form class="fc-panel-username__form" data-panel-username-form>
                                <input type="hidden" name="service_id" value="<?= $selectedId ?>">
                                <label>
                                    <span>Username جدید</span>
                                    <input type="text" name="username" value="<?= $myServersEscape($selectedServer['username']) ?>" autocomplete="off" autocapitalize="none" spellcheck="false" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9_-]{1,48}[A-Za-z0-9]" required>
                                </label>
                                <label>
                                    <span>رمز فعلی FragCity برای تأیید</span>
                                    <input type="password" name="current_password" autocomplete="current-password" minlength="8" required>
                                </label>
                                <div class="fc-panel-username__footer">
                                    <span class="fc-panel-username__message" data-username-message role="status" aria-live="polite"></span>
                                    <button type="submit" class="fc-panel-username__submit"><i class="fa-solid fa-floppy-disk"></i><span>ذخیره نام کاربری</span></button>
                                </div>
                            </form>
                        </div>

                        <div class="fc-panel-settings-divider"></div>

                        <div class="fc-panel-settings-section">
                            <div class="fc-panel-settings-section__heading">
                                <strong><?= $selectedPasswordSet ? 'تغییر رمز عبور پنل' : 'تنظیم رمز عبور پنل' ?></strong>
                                <span>امنیت حساب</span>
                            </div>
                            <form class="fc-panel-password__form" data-panel-password-form>
                                <input type="hidden" name="service_id" value="<?= $selectedId ?>">
                                <div class="fc-panel-password__fields">
                                    <label><span>رمز فعلی FragCity</span><input type="password" name="current_password" autocomplete="current-password" minlength="8" required></label>
                                    <label><span>رمز جدید پنل</span><input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" required></label>
                                    <label><span>تکرار رمز جدید</span><input type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="128" required></label>
                                </div>
                                <p class="fc-panel-password__hint">حداقل ۱۲ کاراکتر، شامل حروف و عدد و بدون فاصله. رمز جدید باید با رمز FragCity متفاوت باشد. رمز فعلی پنل قابل بازیابی یا نمایش نیست.</p>
                                <div class="fc-panel-password__footer">
                                    <span class="fc-panel-password__message" data-password-message role="status" aria-live="polite"></span>
                                    <button type="submit" class="fc-panel-password__submit"><i class="fa-solid fa-key"></i><span><?= $selectedPasswordSet ? 'ذخیره رمز جدید' : 'تنظیم رمز پنل' ?></span></button>
                                </div>
                            </form>
                        </div>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</section>
