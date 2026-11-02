<?php
/**
 * Public CS2 server browser.
 *
 * Pterodactyl provides the allowlisted server allocations. The Linux API
 * performs on-demand A2S_INFO queries and returns a short-lived cached snapshot.
 * This page does not scan ports, access the Pterodactyl token, or send UDP.
 */
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php?page=servers_vps', true, 303);
    exit;
}

require_once dirname(__DIR__) . '/class/PterodactylStandbyRepository.php';

$serverInventory = [];
$serverSnapshotStale = false;
$serverSnapshotAge = null;

try {
    $serverSnapshot = (new PterodactylStandbyRepository())->getSnapshot();
    $serverInventory = is_array($serverSnapshot['servers'] ?? null)
        ? $serverSnapshot['servers']
        : [];
    $serverSnapshotStale = !empty($serverSnapshot['stale']);
    $serverSnapshotAge = isset($serverSnapshot['cache_age_seconds'])
        ? max(0, (int)$serverSnapshot['cache_age_seconds'])
        : null;
} catch (Throwable $e) {
    error_log('[FragCity] Pterodactyl Server Browser API read failed: ' . get_class($e));
}

$escapeServerValue = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$serverlistGuyAssetVersion = function_exists('asset_version')
    ? asset_version(dirname(__DIR__) . '/img/webp/serverlistguy.webp')
    : '1';

$lowerServerValue = static function ($value): string {
    $value = (string) $value;
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
};

$serverRows = [];
foreach ($serverInventory as $entry) {
    if (!is_array($entry)) {
        continue;
    }

    $name = trim((string) ($entry['name'] ?? ''));
    $host = trim((string) ($entry['host'] ?? ''));
    $gamePort = filter_var(
        $entry['game_port'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 65535]]
    );
    $queryPort = filter_var(
        $entry['query_port'] ?? ($entry['game_port'] ?? null),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 65535]]
    );

    $validIpv4 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    $validIpv6 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    $validHostname = (bool) preg_match(
        '/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*$/D',
        $host
    );

    if ($name === '' || (!$validIpv4 && !$validIpv6 && !$validHostname) || $gamePort === false || $queryPort === false) {
        continue;
    }

    $status = strtolower(trim((string) ($entry['status'] ?? 'unknown')));
    if (!in_array($status, ['online', 'offline', 'starting', 'unknown'], true)) {
        $status = 'unknown';
    }

    $players = max(0, (int) ($entry['players'] ?? 0));
    $maxPlayers = max(0, (int) ($entry['max_players'] ?? 0));
    if ($maxPlayers > 0) {
        $players = min($players, $maxPlayers);
    } else {
        $players = 0;
    }

    $displayHost = $validIpv6 ? '[' . $host . ']' : $host;
    $address = $displayHost . ':' . $gamePort;
    $connectHost = $validIpv6 ? '[' . $host . ']' : $host;
    $mode = trim((string) ($entry['mode'] ?? 'Community')) ?: 'Community';
    $map = trim((string) ($entry['map'] ?? 'Unknown')) ?: 'Unknown';
    $queryRtt = isset($entry['query_rtt_ms']) && is_numeric($entry['query_rtt_ms'])
        ? round(max(0, (float) $entry['query_rtt_ms']), 2)
        : null;

    $serverRows[] = [
        'id' => (string) ($entry['id'] ?? count($serverRows) + 1),
        'name' => $name,
        'host' => $host,
        'game_port' => (int) $gamePort,
        'query_port' => (int) $queryPort,
        'address' => $address,
        'connect_url' => 'steam://connect/' . $connectHost . ':' . $gamePort,
        'mode' => $mode,
        'map' => $map,
        'status' => $status,
        'players' => $players,
        'max_players' => $maxPlayers,
        'query_rtt_ms' => $queryRtt,
        'vac' => array_key_exists('vac', $entry) && $entry['vac'] !== null ? (bool) $entry['vac'] : null,
        'password' => array_key_exists('password', $entry) && $entry['password'] !== null ? (bool) $entry['password'] : null,
        'region' => trim((string) ($entry['region'] ?? '')),
        'country' => strtoupper(trim((string) ($entry['country'] ?? ''))),
    ];
}

$onlineServers = array_values(array_filter(
    $serverRows,
    static fn (array $server): bool => $server['status'] === 'online'
));
$totalPlayers = array_sum(array_column($onlineServers, 'players'));
$serverMapCounts = [];
foreach ($onlineServers as $server) {
    $mapName = trim((string)($server['map'] ?? ''));
    if ($mapName === '' || strcasecmp($mapName, 'Unknown') === 0) {
        continue;
    }
    $serverMapCounts[$mapName] = ($serverMapCounts[$mapName] ?? 0) + 1;
}
arsort($serverMapCounts, SORT_NUMERIC);
$uniqueMaps = array_keys($serverMapCounts);
$availableSlots = array_sum(array_map(
    static fn (array $server): int => max(0, $server['max_players'] - $server['players']),
    $onlineServers
));
$serverGroups = [];
foreach ($serverRows as $server) {
    $groupKey = $server['mode'];
    if (!isset($serverGroups[$groupKey])) {
        $serverGroups[$groupKey] = [];
    }
    $serverGroups[$groupKey][] = $server;
}

$statusLabels = [
    'online' => 'آنلاین',
    'offline' => 'آفلاین',
    'starting' => 'در حال راه‌اندازی',
    'unknown' => 'در انتظار Query',
];
$faceitIconVersion = function_exists('asset_version')
    ? asset_version(dirname(__DIR__) . '/img/faceit/6.svg')
    : '1';
?>

<section
    class="serverlist-page"
    id="fragcityServerBrowser"
    dir="rtl"
    data-faceit-icon-base="/img/faceit/"
    data-faceit-icon-version="<?= $escapeServerValue($faceitIconVersion) ?>"
>
    <div class="sl-page-shell">
        <section class="sl-hero" aria-labelledby="slHeroTitle">
            <div class="sl-hero-copy">
                <div class="sl-eyebrow" data-fc-reveal="up">
                    <span class="sl-eyebrow-mark"></span>
                    FRAGCITY <span>/</span> CS2 COMMUNITY
                </div>
                <h1 id="slHeroTitle" data-fc-reveal="up" data-fc-reveal-delay="70">
                    سرور بعدی‌ات را
                    <span>اینجا پیدا کن.</span>
                </h1>
                <p data-fc-reveal="up" data-fc-reveal-delay="140">
                    فهرست سرورهای عمومی FragCity را مرور کن، وضعیت و نقشه را ببین،
                    بازیکنان آنلاین را بررسی کن و با یک کلیک به سرور موردنظرت وصل شو.
                </p>
                <div class="sl-hero-actions" data-fc-reveal="up" data-fc-reveal-delay="210">
                    <a class="sl-primary-action" href="#slBrowserControls">
                        <i class="fa-solid fa-compass" aria-hidden="true"></i>
                        پیدا کردن سرور
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    </a>
                    <a class="sl-secondary-action" href="/index.php?page=status" data-ajax-nav data-slug="status">
                        <i class="fa-solid fa-heart-pulse" aria-hidden="true"></i>
                        وضعیت سرویس‌ها
                    </a>
                    <a class="sl-secondary-action" href="/index.php?page=buyserveropen" data-ajax-nav data-slug="buyserveropen">
                        سرور اختصاصی خودت را بساز
                        <i class="fa-solid fa-arrow-up-left" aria-hidden="true"></i>
                    </a>
                    <a class="sl-guide-action" href="/index.php?page=serverguide" data-ajax-nav data-slug="serverguide">
                        <i class="fa-solid fa-book-open" aria-hidden="true"></i>
                        راهنمای سرور
                    </a>
                </div>
                <div class="sl-hero-signals" data-fc-reveal="up" data-fc-reveal-delay="270">
                    <span><i class="fa-solid fa-signal" aria-hidden="true"></i> وضعیت سرورها</span>
                    <span><i class="fa-solid fa-users" aria-hidden="true"></i> تعداد بازیکنان</span>
                    <span><i class="fa-solid fa-map" aria-hidden="true"></i> نقشه و گیم‌مود</span>
                </div>
            </div>

            <div class="sl-hero-art sl-hero-mascot" aria-hidden="true">
                <span class="sl-mascot-glow"></span>
                <span class="sl-mascot-orbit sl-mascot-orbit--outer"></span>
                <span class="sl-mascot-orbit sl-mascot-orbit--inner"></span>
                <span class="sl-mascot-grid"></span>
                <span class="sl-mascot-aim-line"></span>
                <span class="sl-mascot-tag sl-mascot-tag--top">FRAGCITY / FIELD GUIDE</span>
                <span class="sl-mascot-tag sl-mascot-tag--bottom">COMMUNITY // 01</span>
                <span class="sl-mascot-target"><i class="fa-solid fa-crosshairs"></i></span>
                <img
                    class="sl-hero-guy"
                    src="/img/webp/serverlistguy.webp?v=<?= rawurlencode($serverlistGuyAssetVersion) ?>"
                    alt=""
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                >
            </div>
        </section>

        <section class="sl-overview" aria-label="آمار سرورها">
            <article class="sl-overview-card" data-fc-reveal="up">
                <span class="sl-overview-icon sl-icon-orange"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
                <div class="sl-overview-copy">
                    <span>کل سرورها</span>
                    <strong><?= count($serverRows) ?></strong>
                </div>
                <span class="sl-overview-hint">REGISTERED</span>
            </article>
            <article class="sl-overview-card" data-fc-reveal="up" data-fc-reveal-delay="60">
                <span class="sl-overview-icon sl-icon-green"><i class="fa-solid fa-signal" aria-hidden="true"></i></span>
                <div class="sl-overview-copy">
                    <span>سرورهای آنلاین</span>
                    <strong><?= count($onlineServers) ?></strong>
                </div>
                <span class="sl-overview-hint">ONLINE</span>
            </article>
            <article class="sl-overview-card" data-fc-reveal="up" data-fc-reveal-delay="120">
                <span class="sl-overview-icon sl-icon-muted"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                <div class="sl-overview-copy">
                    <span>بازیکنان آنلاین</span>
                    <strong><?= $totalPlayers ?></strong>
                </div>
                <span class="sl-overview-hint">PLAYERS</span>
            </article>
            <article class="sl-overview-card" data-fc-reveal="up" data-fc-reveal-delay="180">
                <span class="sl-overview-icon sl-icon-warm"><i class="fa-solid fa-map" aria-hidden="true"></i></span>
                <div class="sl-overview-copy">
                    <span>نقشه‌های فعال</span>
                    <strong><?= count($uniqueMaps) ?></strong>
                </div>
                <span class="sl-overview-hint">MAPS</span>
            </article>
            <article class="sl-overview-card" data-fc-reveal="up" data-fc-reveal-delay="240">
                <span class="sl-overview-icon sl-icon-orange"><i class="fa-solid fa-door-open" aria-hidden="true"></i></span>
                <div class="sl-overview-copy">
                    <span>جای خالی آنلاین</span>
                    <strong><?= number_format($availableSlots) ?></strong>
                </div>
                <span class="sl-overview-hint">OPEN SLOTS</span>
            </article>
        </section>

            <section class="sl-browser" id="slBrowserControls">
            <div class="sl-section-heading" data-fc-reveal="up">
                <div>
                    <span class="sl-section-kicker">SERVER DIRECTORY</span>
                    <h2>سرورهای پابلیک</h2>
                    <p>سرور مناسب سبک بازی‌ات را پیدا کن.</p>
                </div>
                <div class="sl-directory-count">
                    <span class="sl-count-pulse"></span>
                    <strong><?= count($serverRows) ?></strong>
                    <span>سرور در فهرست</span>
                </div>
            </div>
            <?php if ($serverSnapshotStale): ?>
                <div class="sl-snapshot-stale" role="status" data-fc-reveal="up">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    اتصال به منبع زنده موقتاً ناموفق بود؛ آخرین اطلاعات موفق
                    <?= $serverSnapshotAge !== null ? ' (' . $serverSnapshotAge . ' ثانیه قبل)' : '' ?>
                    نمایش داده می‌شود.
                </div>
            <?php endif; ?>

            <div class="sl-browser-layout">
                <aside class="sl-filter-sidebar" aria-label="فیلترهای سرور">
                    <div class="sl-sidebar-heading">
                        <div>
                            <span class="sl-section-kicker">REFINE SERVERS</span>
                            <h3>فیلترها</h3>
                        </div>
                        <button type="button" class="sl-reset-filters" id="slClearFilters">
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                            پاک‌کردن
                        </button>
                    </div>

                    <section class="sl-facet" data-sl-facet="vac">
                        <h4><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Anti-Cheat <span>VAC</span></h4>
                        <div class="sl-facet-options">
                            <button type="button" class="sl-facet-option active" data-sl-option="vac" data-sl-value="all" aria-pressed="true">
                                همه <span data-sl-count>0</span>
                            </button>
                            <button type="button" class="sl-facet-option" data-sl-option="vac" data-sl-value="protected" aria-pressed="false">
                                محافظت‌شده با VAC <span data-sl-count>0</span>
                            </button>
                            <button type="button" class="sl-facet-option" data-sl-option="vac" data-sl-value="unprotected" aria-pressed="false">
                                بدون VAC <span data-sl-count>0</span>
                            </button>
                            <button type="button" class="sl-facet-option" data-sl-option="vac" data-sl-value="unknown" aria-pressed="false">
                                وضعیت نامشخص <span data-sl-count>0</span>
                            </button>
                        </div>
                    </section>

                    <section class="sl-facet" data-sl-facet="game">
                        <h4><i class="fa-solid fa-gamepad" aria-hidden="true"></i> بازی</h4>
                        <div class="sl-facet-options">
                            <button type="button" class="sl-facet-option active" data-sl-option="game" data-sl-value="all" aria-pressed="true">
                                همه بازی‌ها <span data-sl-count>0</span>
                            </button>
                            <button type="button" class="sl-facet-option" data-sl-option="game" data-sl-value="cs2" aria-pressed="false">
                                Counter-Strike 2 <span data-sl-count>0</span>
                            </button>
                        </div>
                        <p class="sl-facet-note">این فهرست در حال حاضر فقط سرورهای دارای AppID 730 را دریافت می‌کند.</p>
                    </section>

                    <section class="sl-facet" data-sl-facet="mode">
                        <h4><i class="fa-solid fa-layer-group" aria-hidden="true"></i> مود سرور</h4>
                        <div class="sl-facet-options">
                            <button type="button" class="sl-facet-option active" data-sl-option="mode" data-sl-value="all" aria-pressed="true">
                                همه مودها <span data-sl-count>0</span>
                            </button>
                            <?php foreach (array_keys($serverGroups) as $modeName): ?>
                                <button
                                    type="button"
                                    class="sl-facet-option"
                                    data-sl-option="mode"
                                    data-sl-value="<?= $escapeServerValue($lowerServerValue($modeName)) ?>"
                                    aria-pressed="false"
                                >
                                    <?= $escapeServerValue($modeName) ?>
                                    <span data-sl-count>0</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="sl-facet" data-sl-facet="players">
                        <h4><i class="fa-solid fa-users" aria-hidden="true"></i> بازیکنان</h4>
                        <div class="sl-facet-options">
                            <button type="button" class="sl-facet-option active" data-sl-option="players" data-sl-value="all" aria-pressed="true">
                                همه <span data-sl-count>0</span>
                            </button>
                            <button type="button" class="sl-facet-option" data-sl-option="players" data-sl-value="playing" aria-pressed="false">
                                دارای بازیکن <span data-sl-count>0</span>
                            </button>
                            <button type="button" class="sl-facet-option" data-sl-option="players" data-sl-value="slots" aria-pressed="false">
                                دارای جای خالی <span data-sl-count>0</span>
                            </button>
                            <button type="button" class="sl-facet-option" data-sl-option="players" data-sl-value="empty" aria-pressed="false">
                                کاملاً خالی <span data-sl-count>0</span>
                            </button>
                        </div>
                    </section>

                    <section class="sl-facet" data-sl-facet="display">
                        <h4><i class="fa-solid fa-eye" aria-hidden="true"></i> نمایش</h4>
                        <label class="sl-toggle-option">
                            <input type="checkbox" data-sl-hide-offline checked>
                            <span>پنهان کردن آفلاین‌ها</span>
                        </label>
                        <label class="sl-toggle-option">
                            <input type="checkbox" data-sl-hide-password>
                            <span>پنهان کردن سرورهای رمزدار</span>
                        </label>
                        <label class="sl-toggle-option">
                            <input type="checkbox" data-sl-favorites-only>
                            <span>فقط علاقه‌مندی‌ها <b data-sl-favorite-count>0</b></span>
                        </label>
                    </section>

                    <section class="sl-facet sl-map-facet" data-sl-facet="map">
                        <h4><i class="fa-solid fa-map" aria-hidden="true"></i> مپ</h4>
                        <label class="sl-map-search" for="slMapSearch">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input id="slMapSearch" type="search" autocomplete="off" placeholder="search map...">
                        </label>
                        <div class="sl-map-options" id="slMapOptions">
                            <?php foreach ($serverMapCounts as $mapName => $mapCount): ?>
                                <button
                                    type="button"
                                    class="sl-map-option"
                                    data-sl-map-option="<?= $escapeServerValue($lowerServerValue($mapName)) ?>"
                                    aria-pressed="false"
                                >
                                    <span dir="ltr"><?= $escapeServerValue($mapName) ?></span>
                                    <b data-sl-map-count><?= number_format($mapCount) ?></b>
                                </button>
                            <?php endforeach; ?>
                            <p class="sl-map-empty" data-sl-map-empty hidden>مپ موردنظر پیدا نشد.</p>
                        </div>
                    </section>
                </aside>

                <div class="sl-browser-main">
                    <div class="sl-filter-panel" data-fc-reveal="up">
                        <div class="sl-filter-top">
                            <div class="sl-view-switch" role="group" aria-label="نحوه نمایش سرورها">
                                <button type="button" class="sl-view-button active" data-sl-view-option="list" aria-pressed="true" title="نمایش لیستی">
                                    <i class="fa-solid fa-list" aria-hidden="true"></i>
                                    <span>لیستی</span>
                                </button>
                                <button type="button" class="sl-view-button" data-sl-view-option="grid" aria-pressed="false" title="نمایش کارت‌ها">
                                    <i class="fa-solid fa-grip" aria-hidden="true"></i>
                                    <span>کارت‌ها</span>
                                </button>
                            </div>

                            <label class="sl-search" for="slSearchInput">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input id="slSearchInput" type="search" autocomplete="off" placeholder="جستجوی نام سرور، آدرس، مود یا مپ..." <?= $serverRows === [] ? 'disabled' : '' ?>>
                                <kbd>/</kbd>
                            </label>
                        </div>

                        <div class="sl-filter-bottom">
                            <label class="sl-select-wrap sl-sort-wrap">
                                <span>مرتب‌سازی</span>
                                <select id="slSortSelect" <?= $serverRows === [] ? 'disabled' : '' ?>>
                                    <option value="default">پیش‌فرض</option>
                                    <option value="players-desc">بازیکن بیشتر</option>
                                    <option value="name-asc">نام سرور</option>
                                </select>
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </label>
                            <span class="sl-results-label" id="slResultsLabel" aria-live="polite">
                                نمایش <?= count($onlineServers) ?> سرور
                            </span>
                        </div>
                    </div>

            <div class="sl-server-list" id="serverList" data-sl-view="list">
                <?php if ($serverRows !== []): ?>
                    <?php foreach ($serverGroups as $groupName => $groupServers): ?>
                        <section class="sl-server-group" data-sl-group>
                            <div class="sl-group-heading" data-fc-reveal="up">
                                <div class="sl-group-title">
                                    <span class="sl-group-accent"></span>
                                    <h3><?= $escapeServerValue($groupName) ?></h3>
                                    <span class="sl-group-total"><?= count($groupServers) ?> سرور</span>
                                </div>
                                <span class="sl-group-caption">COMMUNITY SERVERS</span>
                            </div>

                            <div class="sl-server-grid">
                                <?php foreach ($groupServers as $serverIndex => $server): ?>
                                    <?php
                                    $status = $server['status'];
                                    $statusClass = 'sl-status-' . $status;
                                    $statusLabel = $statusLabels[$status] ?? $statusLabels['unknown'];
                                    $capacity = $server['max_players'] > 0
                                        ? min(100, (int) round(($server['players'] / $server['max_players']) * 100))
                                        : 0;
                                    $modeSearch = $lowerServerValue($server['mode']);
                                    $nameSearch = $lowerServerValue($server['name']);
                                    $mapSearch = $lowerServerValue($server['map']);
                                    ?>
                                    <article
                                        class="sl-server-card<?= $status === 'offline' ? ' sl-server-card--offline' : '' ?>"
                                        data-sl-card
                                        data-server-id="<?= $escapeServerValue($server['id']) ?>"
                                        data-status="<?= $escapeServerValue($status) ?>"
                                        data-name="<?= $escapeServerValue($nameSearch) ?>"
                                        data-map="<?= $escapeServerValue($mapSearch) ?>"
                                        data-mode="<?= $escapeServerValue($modeSearch) ?>"
                                        data-address="<?= $escapeServerValue($server['address']) ?>"
                                        data-players="<?= $server['players'] ?>"
                                        data-max-players="<?= $server['max_players'] ?>"
                                        data-vac="<?= $server['vac'] === null ? 'unknown' : ($server['vac'] ? 'protected' : 'unprotected') ?>"
                                        data-password="<?= $server['password'] === null ? 'unknown' : ($server['password'] ? 'true' : 'false') ?>"
                                        data-game="cs2"
                                        data-fc-reveal="up"
                                        data-fc-reveal-delay="<?= min(240, ($serverIndex % 5) * 48) ?>"
                                        data-fc-interactive
                                    >
                                        <div class="sl-card-cover">
                                            <div class="sl-cover-topline">
                                                <span><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i> MAP OVERVIEW</span>
                                                <span class="sl-cover-code">CS2</span>
                                            </div>
                                            <div class="sl-cover-map-name"><?= $escapeServerValue($server['map']) ?></div>
                                            <div class="sl-cover-grid"></div>
                                            <div class="sl-cover-crosshair" aria-hidden="true"><i class="fa-solid fa-crosshairs"></i></div>
                                        </div>

                                        <div class="sl-card-content">
                                            <div class="sl-card-heading">
                                                <div class="sl-card-title-wrap">
                                                    <span class="sl-status-dot <?= $statusClass ?>"></span>
                                                    <div>
                                                        <h4><?= $escapeServerValue($server['name']) ?></h4>
                                                        <span class="sl-card-mode"><?= $escapeServerValue($server['mode']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="sl-card-actions">
                                                    <span class="sl-status-badge <?= $statusClass ?>"><?= $escapeServerValue($statusLabel) ?></span>
                                                    <button type="button" class="sl-favorite-toggle" data-sl-favorite aria-pressed="false" title="افزودن به علاقه‌مندی‌ها" aria-label="افزودن به علاقه‌مندی‌ها">
                                                        <i class="fa-regular fa-star" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="sl-card-address">
                                                <code dir="ltr"><?= $escapeServerValue($server['address']) ?></code>
                                                <button type="button" class="sl-copy-btn" data-sl-copy="<?= $escapeServerValue($server['address']) ?>" aria-label="کپی آدرس سرور" title="کپی آدرس">
                                                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                                </button>
                                            </div>

                                            <div class="sl-card-metrics">
                                                <div class="sl-metric sl-player-metric">
                                                    <span><i class="fa-solid fa-users" aria-hidden="true"></i> بازیکنان</span>
                                                    <strong><?= $server['status'] === 'online' ? $server['players'] . ' / ' . $server['max_players'] : '—' ?></strong>
                                                </div>
                                                <div
                                                    class="sl-metric sl-metric--client-ping"
                                                    title="پینگ واقعی بازدیدکننده هنوز از مرورگر قابل اندازه‌گیری نیست؛ برای این مقدار به ابزار کلاینت/ارتباط UDP سازگار نیاز داریم."
                                                    aria-label="پینگ شما تا سرور؛ فعلاً در دسترس نیست"
                                                >
                                                    <span><i class="fa-solid fa-signal" aria-hidden="true"></i> Ping شما</span>
                                                    <strong dir="ltr" data-sl-client-ping>—</strong>
                                                </div>
                                                <div class="sl-metric">
                                                    <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> VAC</span>
                                                    <strong><?= $server['vac'] === null ? '—' : ($server['vac'] ? 'فعال' : 'غیرفعال') ?></strong>
                                                </div>
                                            </div>

                                            <div class="sl-capacity">
                                                <div class="sl-capacity-label">
                                                    <span>ظرفیت سرور</span>
                                                    <strong><?= $server['status'] === 'online' && $server['max_players'] > 0 ? $capacity . '%' : '—' ?></strong>
                                                </div>
                                                <div class="sl-capacity-track">
                                                    <span style="width: <?= $server['status'] === 'online' ? $capacity : 0 ?>%"></span>
                                                </div>
                                            </div>

                                            <div class="sl-card-footer">
                                                <span class="sl-region">
                                                    <i class="fa-solid fa-globe" aria-hidden="true"></i>
                                                    <?= $escapeServerValue($server['region'] !== '' ? $server['region'] : 'FragCity Community') ?>
                                                </span>
                                                <?php if ($status === 'online'): ?>
                                                    <button
                                                        type="button"
                                                        class="sl-players-btn"
                                                        data-sl-players
                                                        data-server-id="<?= $escapeServerValue($server['id']) ?>"
                                                        data-server-name="<?= $escapeServerValue($server['name']) ?>"
                                                        data-server-address="<?= $escapeServerValue($server['address']) ?>"
                                                        aria-label="دیدن بازیکنان <?= $escapeServerValue($server['name']) ?>"
                                                    >
                                                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                                                        دیدن بازیکنان
                                                        <span><?= (int)$server['players'] ?></span>
                                                    </button>
                                                    <a class="sl-connect-btn" href="<?= $escapeServerValue($server['connect_url']) ?>">
                                                        اتصال <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="sl-players-btn" disabled title="سرور آنلاین نیست">
                                                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                                                        دیدن بازیکنان
                                                    </button>
                                                    <span class="sl-connect-disabled">در دسترس نیست</span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="sl-faceit-average-row" data-sl-faceit-average-row hidden>
                                                <span class="sl-faceit-average-label">میانگین:</span>
                                                <img
                                                    class="sl-faceit-average-icon"
                                                    data-sl-faceit-average
                                                    data-average-state="pending"
                                                    width="28"
                                                    height="28"
                                                    alt="میانگین سطح FACEIT"
                                                    decoding="async"
                                                >
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="sl-empty-state" id="slEmptyState" <?= $serverRows !== [] ? 'hidden' : '' ?> data-fc-reveal="up">
                <div class="sl-empty-visual" aria-hidden="true">
                    <span class="sl-empty-ring sl-empty-ring-one"></span>
                    <span class="sl-empty-ring sl-empty-ring-two"></span>
                    <span class="sl-empty-icon"><i class="fa-solid fa-server"></i></span>
                </div>
                <span class="sl-empty-kicker">SERVER DIRECTORY</span>
                <h3>سرورها در حال آماده‌سازی‌اند</h3>
                <p>پس از اتصال Pterodactyl و دریافت اولین پاسخ A2S، نقشه، بازیکنان، ظرفیت و زمان پاسخ سرورها اینجا نمایش داده می‌شود.</p>
                <div class="sl-empty-status">
                    <span class="sl-empty-status-dot"></span>
                    در انتظار اتصال Pterodactyl
                </div>
                <small>فقط سرورهای تأییدشده با AppID 730 نمایش داده می‌شوند.</small>
            </div>

            <div class="sl-no-match" id="slNoMatch" hidden data-fc-reveal="up">
                <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
                <strong>نتیجه‌ای پیدا نشد</strong>
                <span>فیلترها یا عبارت جستجو را تغییر بده.</span>
                <button type="button" data-sl-reset>پاک‌کردن فیلترها</button>
            </div>
                </div><!-- .sl-browser-main -->
            </div><!-- .sl-browser-layout -->
            </section>

        <section class="sl-query-explainer" data-fc-reveal="up">
            <div class="sl-query-explainer-icon"><i class="fa-solid fa-satellite-dish" aria-hidden="true"></i></div>
            <div>
                <span class="sl-section-kicker">LIVE SERVER DATA</span>
                <h2>اطلاعات زنده با A2S</h2>
                <p>وضعیت آنلاین، نقشه و ظرفیت بازیکنان با A2S دریافت می‌شود. پینگ واقعی شما تا هر سرور در مرحلهٔ بعد با یک روش کلاینت‌محور اضافه می‌شود؛ مرورگر به‌تنهایی نمی‌تواند UDP پورت بازی را Query کند.</p>
            </div>
            <div class="sl-query-checklist">
                <span><i class="fa-solid fa-check" aria-hidden="true"></i> A2S_INFO</span>
                <span><i class="fa-solid fa-check" aria-hidden="true"></i> Player count</span>
                <span><i class="fa-solid fa-check" aria-hidden="true"></i> Cache & timeout</span>
            </div>
        </section>
    </div>

    <div class="sl-player-modal" id="slPlayerModal" aria-hidden="true" hidden>
        <button type="button" class="sl-player-modal-backdrop" data-sl-modal-dismiss aria-label="بستن پنجره"></button>
        <section
            class="sl-player-modal-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="slPlayerModalTitle"
            aria-describedby="slPlayerModalMeta"
            tabindex="-1"
        >
            <header class="sl-player-modal-header">
                <div class="sl-player-modal-heading">
                    <span class="sl-section-kicker">LIVE A2S_PLAYER</span>
                    <h3 id="slPlayerModalTitle">بازیکنان سرور</h3>
                    <p id="slPlayerModalMeta"></p>
                </div>
                <div class="sl-player-modal-actions">
                    <span class="sl-player-count" id="slPlayerModalCount">—</span>
                    <button type="button" class="sl-player-modal-close" data-sl-modal-dismiss aria-label="بستن">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            </header>

            <div class="sl-player-modal-content">
                <div class="sl-player-loading" id="slPlayerLoading" hidden>
                    <span class="sl-player-spinner" aria-hidden="true"></span>
                    <strong>در حال دریافت بازیکنان و رتبه FACEIT...</strong>
                    <small>نام‌های A2S با SimpleAdmin به SteamID متصل می‌شوند و سپس سطح FACEIT بررسی می‌شود.</small>
                </div>

                <div class="sl-player-error" id="slPlayerError" role="alert" hidden>
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <strong>دریافت اطلاعات بازیکنان ناموفق بود</strong>
                    <span id="slPlayerErrorMessage">سرور فعلاً به درخواست Query پاسخ نمی‌دهد.</span>
                    <button type="button" id="slPlayerRetry">تلاش دوباره</button>
                </div>

                <div class="sl-player-empty" id="slPlayerEmpty" hidden>
                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                    <strong>بازیکنی در سرور پیدا نشد</strong>
                    <span>پاسخ A2S_PLAYER خالی بود.</span>
                </div>

                <div class="sl-player-table-wrap" id="slPlayerTableWrap" hidden>
                    <table class="sl-player-table">
                        <thead>
                            <tr>
                                <th scope="col">اسم</th>
                                <th scope="col">استیم آیدی</th>
                                <th scope="col">مدت اتصال</th>
                                <th scope="col">امتیاز</th>
                                <th scope="col" class="sl-player-faceit-heading">لول FACEIT</th>
                            </tr>
                        </thead>
                        <tbody id="slPlayerTableBody"></tbody>
                    </table>
                </div>
            </div>

            <footer class="sl-player-modal-footer">
                <span><i class="fa-solid fa-satellite-dish" aria-hidden="true"></i> منابع: A2S_PLAYER + SimpleAdmin + FACEIT</span>
                <span>اطلاعات در لحظه‌ی باز کردن مودال Query می‌شوند.</span>
            </footer>
        </section>
    </div>

    <div class="sl-toast" id="slToast" role="status" aria-live="polite"></div>
</section>
