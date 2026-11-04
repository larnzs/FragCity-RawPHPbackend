<?php
declare(strict_types=1);

if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php?page=lifecycle', true, 303);
    exit;
}

if (
    ($_SESSION['user_logged_in'] ?? false) !== true
    || (string) ($_SESSION['role'] ?? '') !== 'admin'
) {
    http_response_code(403);
    echo '<section class="fc-lifecycle"><div class="fc-lifecycle__notice">دسترسی به این بخش مجاز نیست.</div></section>';
    return;
}

require_once dirname(__DIR__) . '/class/PaymenterApiClient.php';

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$actorSteamId = (string) ($_SESSION['steamid'] ?? '');
$actorUsername = (string) ($_SESSION['username'] ?? 'admin');
$csrf = (string) ($_SESSION['auth_csrf_token'] ?? '');
$selectedServiceId = filter_var($_GET['service_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$state = strtolower(trim((string) ($_GET['lifecycle_state'] ?? 'all')));
$search = trim((string) ($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 100);
$pageNumber = filter_var($_GET['lifecycle_page'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 100000],
]);
$pageNumber = $pageNumber === false ? 1 : $pageNumber;
$perPage = (int) ($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50], true)) $perPage = 25;

$stateOptions = [
    'all' => 'همه وضعیت‌ها',
    'ready' => 'آماده',
    'active' => 'فعال',
    'provisioning' => 'در حال ساخت',
    'suspended' => 'معلق',
    'missing_external' => 'سرور در Pterodactyl نیست',
    'paymenter_missing' => 'سرویس در Paymenter نیست',
    'review_required' => 'نیازمند بررسی',
    'deleted' => 'حذف‌شده',
    'failed' => 'ناموفق',
    'pending' => 'در انتظار',
    'deleting' => 'در حال حذف',
];
if (!array_key_exists($state, $stateOptions)) $state = 'all';

$stateLabels = [
    'ready' => 'آماده',
    'active' => 'فعال',
    'pending' => 'در انتظار',
    'provisioning' => 'در حال ساخت',
    'suspending' => 'در حال تعلیق',
    'suspended' => 'معلق',
    'unsuspending' => 'در حال فعال‌سازی',
    'deleting' => 'در حال حذف',
    'deleted' => 'حذف‌شده',
    'missing_external' => 'سرور گم‌شده',
    'paymenter_missing' => 'سرویس یتیم',
    'review_required' => 'نیازمند بررسی',
    'failed' => 'ناموفق',
];

$inventory = ['items' => [], 'summary' => ['total' => 0, 'by_state' => []], 'pagination' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1]];
$detail = null;
$pageError = '';

try {
    if (!preg_match('/^[0-9]{17}$/D', $actorSteamId)) {
        throw new RuntimeException('invalid_session');
    }
    $client = new PaymenterApiClient();
    $inventory = $client->adminLifecycleInventory($actorSteamId, $actorUsername, [
        'page' => $pageNumber,
        'per_page' => $perPage,
        'state' => $state,
        'search' => $search,
    ]);
    if ($selectedServiceId !== false && $selectedServiceId !== null) {
        $detail = $client->adminLifecycleDetail($actorSteamId, $actorUsername, (int) $selectedServiceId);
    } elseif (!empty($inventory['items'][0]['service_id'])) {
        $selectedServiceId = (int) $inventory['items'][0]['service_id'];
        $detail = $client->adminLifecycleDetail($actorSteamId, $actorUsername, (int) $selectedServiceId);
    }
} catch (Throwable $e) {
    $pageError = $e->getMessage();
    error_log('[FragCity] Admin Lifecycle page load failed; error=' . preg_replace('/[^a-z0-9_]/i', '', $pageError) . '.');
}

$summary = is_array($inventory['summary'] ?? null) ? $inventory['summary'] : [];
$counts = is_array($summary['by_state'] ?? null) ? $summary['by_state'] : [];
$pagination = is_array($inventory['pagination'] ?? null) ? $inventory['pagination'] : [];
$items = is_array($inventory['items'] ?? null) ? $inventory['items'] : [];
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$mapping = is_array($detail['mapping'] ?? null) ? $detail['mapping'] : [];
$customer = is_array($detail['customer'] ?? null) ? $detail['customer'] : [];
$service = is_array($detail['service'] ?? null) ? $detail['service'] : [];
$history = is_array($detail['history'] ?? null) ? $detail['history'] : [];
$selectedState = (string) ($mapping['lifecycle_state'] ?? '');
$desiredState = (string) ($mapping['desired_state'] ?? '');
$hasSelection = $mapping !== [];

$buildUrl = static function (array $override = []) use ($state, $search, $perPage, $currentPage, $selectedServiceId): string {
    $params = [
        'page' => 'lifecycle',
        'lifecycle_state' => $state,
        'q' => $search,
        'per_page' => $perPage,
        'lifecycle_page' => $currentPage,
    ];
    if ($selectedServiceId !== false && $selectedServiceId !== null) $params['service_id'] = (int) $selectedServiceId;
    foreach ($override as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]);
        else $params[$key] = $value;
    }
    return 'index.php?' . http_build_query($params);
};

$formatDate = static function ($value): string {
    $value = trim((string) $value);
    if ($value === '') return '—';
    try { return (new DateTimeImmutable($value))->format('Y/m/d H:i:s'); }
    catch (Throwable) { return '—'; }
};

$kpis = [
    ['label' => 'کل نگاشت‌ها', 'value' => (int) ($summary['total'] ?? 0), 'icon' => 'fa-diagram-project'],
    ['label' => 'سرورهای آماده', 'value' => (int) ($counts['ready'] ?? 0), 'icon' => 'fa-circle-check'],
    ['label' => 'سرورهای گم‌شده', 'value' => (int) ($counts['missing_external'] ?? 0), 'icon' => 'fa-triangle-exclamation'],
    ['label' => 'سرویس‌های یتیم', 'value' => (int) ($counts['paymenter_missing'] ?? 0), 'icon' => 'fa-link-slash'],
];
?>
<section class="fc-lifecycle" id="fcLifecycleDashboard"
         data-api="/api/admin-lifecycle.php"
         data-csrf="<?= $escape($csrf) ?>">
    <header class="fc-lifecycle__header">
        <div>
            <span class="fc-lifecycle__eyebrow">FRAGCITY CONTROL PLANE</span>
            <h1>مدیریت چرخه عمر سرویس‌ها</h1>
            <p>بررسی نگاشت مشتری، سرویس Paymenter و سرور Pterodactyl؛ همراه با سابقه عملیات و کنترل بازیابی.</p>
        </div>
        <div class="fc-lifecycle__header-actions">
            <a href="<?= $escape($buildUrl(['lifecycle_page' => 1])) ?>" class="fc-lifecycle__button fc-lifecycle__button--quiet">
                <i class="fa-solid fa-rotate"></i><span>تازه‌سازی</span>
            </a>
            <span class="fc-lifecycle__admin-chip"><i class="fa-solid fa-shield-halved"></i> Admin</span>
        </div>
    </header>

    <?php if ($pageError !== ''): ?>
        <div class="fc-lifecycle__notice fc-lifecycle__notice--error" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>دریافت اطلاعات Lifecycle انجام نشد</strong>
                <p>ارتباط با Paymenter Bridge یا ذخیره‌سازی Lifecycle را بررسی کنید. جزئیات خطا در لاگ سمت سرور ثبت شده است.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="fc-lifecycle__kpis">
        <?php foreach ($kpis as $kpi): ?>
            <article class="fc-lifecycle__kpi">
                <span class="fc-lifecycle__kpi-icon"><i class="fa-solid <?= $escape($kpi['icon']) ?>"></i></span>
                <span class="fc-lifecycle__kpi-label"><?= $escape($kpi['label']) ?></span>
                <strong><?= number_format((int) $kpi['value']) ?></strong>
            </article>
        <?php endforeach; ?>
    </div>

    <form class="fc-lifecycle__filters" method="get" action="index.php">
        <input type="hidden" name="page" value="lifecycle">
        <label class="fc-lifecycle__search">
            <span>جست‌وجو</span>
            <div><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= $escape($search) ?>" placeholder="Service ID، Server ID، UUID یا ایمیل"></div>
        </label>
        <label>
            <span>وضعیت Lifecycle</span>
            <select name="lifecycle_state">
                <?php foreach ($stateOptions as $value => $label): ?>
                    <option value="<?= $escape($value) ?>" <?= $state === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>تعداد در صفحه</span>
            <select name="per_page">
                <?php foreach ([10, 25, 50] as $size): ?>
                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <input type="hidden" name="lifecycle_page" value="1">
        <button type="submit" class="fc-lifecycle__button"><i class="fa-solid fa-filter"></i><span>اعمال فیلتر</span></button>
    </form>

    <div class="fc-lifecycle__workspace">
        <section class="fc-lifecycle__list-panel">
            <div class="fc-lifecycle__panel-heading">
                <div><h2>سرویس‌ها و نگاشت‌ها</h2><p><?= number_format((int) ($pagination['total'] ?? 0)) ?> رکورد</p></div>
                <span><?= $currentPage ?> / <?= $totalPages ?></span>
            </div>
            <?php if ($items === []): ?>
                <div class="fc-lifecycle__empty"><i class="fa-solid fa-box-open"></i><strong>رکوردی پیدا نشد</strong><span>فیلترها را تغییر دهید یا اتصال API را بررسی کنید.</span></div>
            <?php else: ?>
                <div class="fc-lifecycle__table-wrap">
                    <table class="fc-lifecycle__table">
                        <thead><tr><th>سرویس</th><th>مشتری</th><th>وضعیت</th><th>سرور پنل</th><th>آخرین بررسی</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemId = (int) ($item['service_id'] ?? 0);
                            $itemState = (string) ($item['lifecycle_state'] ?? 'unknown');
                            $itemStatusClass = match ($itemState) {
                                'ready', 'active' => 'success',
                                'missing_external', 'paymenter_missing', 'failed', 'review_required' => 'danger',
                                'deleted' => 'muted',
                                default => 'pending',
                            };
                            $rowUrl = $buildUrl(['service_id' => $itemId]);
                            ?>
                            <tr class="<?= (int) $selectedServiceId === $itemId ? 'is-selected' : '' ?>">
                                <td><a href="<?= $escape($rowUrl) ?>" data-ajax-nav data-slug="lifecycle" class="fc-lifecycle__service-link">#<?= number_format($itemId) ?></a><small><?= $escape($item['server_identifier'] ?? '—') ?></small></td>
                                <td><strong><?= $escape($item['customer_name'] ?: 'مشتری نامشخص') ?></strong><small><?= $escape($item['customer_email'] ?? '') ?></small></td>
                                <td><span class="fc-lifecycle__state fc-lifecycle__state--<?= $escape($itemStatusClass) ?>"><?= $escape($stateLabels[$itemState] ?? $itemState) ?></span><small><?= $escape($item['paymenter_service_status'] ?? 'Paymenter missing') ?></small></td>
                                <td><strong><?= (int) ($item['pterodactyl_server_id'] ?? 0) > 0 ? '#' . number_format((int) $item['pterodactyl_server_id']) : '—' ?></strong><small>Client #<?= number_format((int) ($item['pterodactyl_user_id'] ?? 0)) ?></small></td>
                                <td><small><?= $escape($formatDate($item['updated_at'] ?? '')) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <nav class="fc-lifecycle__pagination" aria-label="صفحه‌بندی">
                <a href="<?= $escape($buildUrl(['lifecycle_page' => max(1, $currentPage - 1)])) ?>" <?= $currentPage <= 1 ? 'aria-disabled="true"' : '' ?>>قبلی</a>
                <span>صفحه <?= $currentPage ?> از <?= $totalPages ?></span>
                <a href="<?= $escape($buildUrl(['lifecycle_page' => min($totalPages, $currentPage + 1)])) ?>" <?= $currentPage >= $totalPages ? 'aria-disabled="true"' : '' ?>>بعدی</a>
            </nav>
        </section>

        <aside class="fc-lifecycle__detail-panel">
            <?php if (!$hasSelection): ?>
                <div class="fc-lifecycle__empty fc-lifecycle__empty--detail"><i class="fa-solid fa-arrow-pointer"></i><strong>سرویسی انتخاب نشده</strong><span>برای مشاهده جزئیات، یکی از رکوردها را انتخاب کنید.</span></div>
            <?php else: ?>
                <div class="fc-lifecycle__panel-heading">
                    <div><span class="fc-lifecycle__eyebrow">SERVICE #<?= number_format((int) ($mapping['service_id'] ?? 0)) ?></span><h2>جزئیات Lifecycle</h2></div>
                    <span class="fc-lifecycle__state fc-lifecycle__state--<?= $selectedState === 'ready' ? 'success' : (in_array($selectedState, ['missing_external', 'paymenter_missing', 'review_required'], true) ? 'danger' : 'pending') ?>"><?= $escape($stateLabels[$selectedState] ?? $selectedState) ?></span>
                </div>

                <div class="fc-lifecycle__detail-grid">
                    <div><span>Desired State</span><strong><?= $escape($mapping['desired_state'] ?? '—') ?></strong></div>
                    <div><span>Observed State</span><strong><?= $escape($mapping['observed_state'] ?? '—') ?></strong></div>
                    <div><span>Paymenter User</span><strong>#<?= number_format((int) ($mapping['paymenter_user_id'] ?? 0)) ?></strong></div>
                    <div><span>Paymenter Service</span><strong><?= !empty($service['present']) ? $escape($service['status'] ?? '—') : 'Missing' ?></strong></div>
                    <div><span>Pterodactyl Client</span><strong>#<?= number_format((int) ($mapping['pterodactyl_user_id'] ?? 0)) ?></strong></div>
                    <div><span>Pterodactyl Server</span><strong>#<?= number_format((int) ($mapping['pterodactyl_server_id'] ?? 0)) ?></strong></div>
                    <div><span>Identifier</span><strong class="fc-lifecycle__mono"><?= $escape($mapping['server_identifier'] ?? '—') ?></strong></div>
                    <div><span>Last Seen</span><strong><?= $escape($formatDate($mapping['last_seen_at'] ?? '')) ?></strong></div>
                    <div><span>Next Reconcile</span><strong><?= $escape($formatDate($mapping['next_reconcile_at'] ?? '')) ?></strong></div>
                    <div><span>Expires At</span><strong><?= $escape($formatDate($service['expires_at'] ?? '')) ?></strong></div>
                </div>

                <div class="fc-lifecycle__identity">
                    <h3>هویت مشتری</h3>
                    <div><span>نام</span><strong><?= $escape($customer['paymenter_name'] ?? '—') ?></strong></div>
                    <div><span>ایمیل</span><strong><?= $escape($customer['paymenter_email'] ?? '—') ?></strong></div>
                    <div><span>FragCity Username</span><strong><?= $escape($customer['fragcity_username'] ?? '—') ?></strong></div>
                    <div><span>SteamID64</span><strong class="fc-lifecycle__mono"><?= $escape($customer['steamid64'] ?? '—') ?></strong></div>
                    <div><span>Customer UUID</span><strong class="fc-lifecycle__mono"><?= $escape($customer['customer_uuid'] ?? '—') ?></strong></div>
                </div>

                <?php if (!empty($mapping['last_error'])): ?>
                    <div class="fc-lifecycle__last-error"><strong>آخرین خطا</strong><p><?= $escape($mapping['last_error']) ?></p></div>
                <?php endif; ?>

                <div class="fc-lifecycle__actions">
                    <?php if ($selectedState !== 'deleted'): ?>
                        <button type="button" class="fc-lifecycle__button fc-lifecycle__button--quiet" data-lifecycle-reconcile data-service-id="<?= (int) $mapping['service_id'] ?>">
                            <i class="fa-solid fa-rotate"></i><span>بررسی مجدد وضعیت</span>
                        </button>
                    <?php endif; ?>

                    <?php if ($selectedState === 'missing_external' && $desiredState !== 'recreate_authorized'): ?>
                        <form class="fc-lifecycle__authorize" data-lifecycle-authorize data-service-id="<?= (int) $mapping['service_id'] ?>">
                            <strong>مجوز ساخت مجدد سرور</strong>
                            <p>این کار فقط مجوز را ثبت می‌کند. اجرای CreateJob باید جداگانه و پس از بررسی Backup/Data Loss توسط ادمین انجام شود.</p>
                            <label><span>دلیل تأیید (اجباری)</span><textarea name="reason" rows="3" minlength="8" maxlength="500" required placeholder="علت تأیید ساخت مجدد این سرویس را بنویسید..."></textarea></label>
                            <button type="submit" class="fc-lifecycle__button fc-lifecycle__button--warning"><i class="fa-solid fa-shield-halved"></i><span>ثبت مجوز ساخت مجدد</span></button>
                        </form>
                    <?php elseif ($selectedState === 'missing_external' && $desiredState === 'recreate_authorized'): ?>
                        <div class="fc-lifecycle__notice fc-lifecycle__notice--warning"><i class="fa-solid fa-circle-info"></i><div><strong>ساخت مجدد مجاز شده است</strong><p>اکنون ادمین باید CreateJob همان Paymenter Service را به‌صورت دستی Retry کند. این داشبورد Job را اجرا نمی‌کند.</p></div></div>
                    <?php elseif ($selectedState === 'paymenter_missing'): ?>
                        <div class="fc-lifecycle__notice fc-lifecycle__notice--warning"><i class="fa-solid fa-triangle-exclamation"></i><div><strong>رکورد Paymenter پیدا نشد</strong><p>این نگاشت برای بررسی نگه داشته شده است. هیچ سرور خارجی به‌صورت خودکار حذف نمی‌شود.</p></div></div>
                    <?php endif; ?>
                </div>

                <div class="fc-lifecycle__history">
                    <div class="fc-lifecycle__history-heading"><h3>تاریخچه عملیات</h3><span>آخرین <?= count($history) ?> رویداد</span></div>
                    <?php if ($history === []): ?>
                        <p class="fc-lifecycle__history-empty">هنوز رویدادی ثبت نشده است.</p>
                    <?php else: ?>
                        <ol>
                            <?php foreach ($history as $event): ?>
                                <?php $eventDetails = is_array($event['details'] ?? null) ? $event['details'] : []; ?>
                                <li>
                                    <div class="fc-lifecycle__event-top"><strong><?= $escape($event['event_key'] ?? 'event') ?></strong><time><?= $escape($formatDate($event['occurred_at'] ?? '')) ?></time></div>
                                    <small><?= $escape(($event['from_state'] ?? '—') . ' → ' . ($event['to_state'] ?? '—')) ?></small>
                                    <?php if (!empty($eventDetails['reason'])): ?><p><?= $escape($eventDetails['reason']) ?></p><?php endif; ?>
                                    <?php if (!empty($eventDetails['actor_username']) || !empty($eventDetails['actor_steamid64'])): ?><small>Actor: <?= $escape($eventDetails['actor_username'] ?? '') ?> · <?= $escape($eventDetails['actor_steamid64'] ?? '') ?></small><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="fc-lifecycle__feedback" data-lifecycle-feedback role="status" aria-live="polite"></div>
</section>
