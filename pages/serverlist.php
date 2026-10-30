<?php
/**
 * Public CS2 server browser.
 *
 * The retired Windows-Agent catalog is intentionally not queried here.
 * Public server inventory can be supplied by settings/public-servers.php
 * (runtime-only, not tracked by Git). Live A2S values will be connected
 * to this view in the next integration step.
 */
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php?page=servers_vps', true, 303);
    exit;
}

$serverInventoryPath = dirname(__DIR__) . '/settings/public-servers.php';
$serverInventory = is_file($serverInventoryPath)
    ? require $serverInventoryPath
    : [];

if (!is_array($serverInventory)) {
    $serverInventory = [];
}

$escapeServerValue = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

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

    $validIpv4 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    $validIpv6 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    $validHostname = (bool) preg_match(
        '/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*$/D',
        $host
    );

    if ($name === '' || (!$validIpv4 && !$validIpv6 && !$validHostname) || $gamePort === false) {
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
        ? max(0, (int) $entry['query_rtt_ms'])
        : null;

    $serverRows[] = [
        'id' => (string) ($entry['id'] ?? count($serverRows) + 1),
        'name' => $name,
        'host' => $host,
        'game_port' => (int) $gamePort,
        'address' => $address,
        'connect_url' => 'steam://connect/' . $connectHost . ':' . $gamePort,
        'mode' => $mode,
        'map' => $map,
        'status' => $status,
        'players' => $players,
        'max_players' => $maxPlayers,
        'query_rtt_ms' => $queryRtt,
        'vac' => array_key_exists('vac', $entry) ? (bool) $entry['vac'] : null,
        'password' => array_key_exists('password', $entry) ? (bool) $entry['password'] : null,
        'region' => trim((string) ($entry['region'] ?? '')),
        'country' => strtoupper(trim((string) ($entry['country'] ?? ''))),
    ];
}

$onlineServers = array_values(array_filter(
    $serverRows,
    static fn (array $server): bool => $server['status'] === 'online'
));
$totalPlayers = array_sum(array_column($onlineServers, 'players'));
$uniqueMaps = array_values(array_unique(array_map(
    static fn (array $server): string => $server['map'],
    $serverRows
)));
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
?>

<section class="serverlist-page" id="fragcityServerBrowser" dir="rtl">
    <div class="sl-page-shell">
        <header class="sl-hero" data-fc-reveal="up">
            <div class="sl-hero-copy">
                <div class="sl-eyebrow">
                    <span class="sl-eyebrow-mark"></span>
                    FRAGCITY <span>/</span> CS2 COMMUNITY
                </div>
                <h1>سرور بعدی‌ات رو پیدا کن<span>.</span></h1>
                <p>سرورهای کامیونیتی FragCity را ببین، وضعیت و نقشه را بررسی کن و مستقیم وارد بازی شو.</p>

                <div class="sl-hero-actions">
                    <a class="sl-primary-action" href="#slBrowserControls">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        جستجوی سرورها
                    </a>
                    <span class="sl-query-state">
                        <span class="sl-query-state-dot"></span>
                        اتصال A2S در مرحله راه‌اندازی
                    </span>
                </div>
            </div>

            <div class="sl-hero-art" aria-hidden="true">
                <div class="sl-art-grid"></div>
                <div class="sl-art-ring sl-art-ring-one"></div>
                <div class="sl-art-ring sl-art-ring-two"></div>
                <div class="sl-art-center">
                    <span>CS2</span>
                    <i class="fa-solid fa-crosshairs"></i>
                </div>
                <div class="sl-art-tag sl-art-tag-top">COMMUNITY</div>
                <div class="sl-art-tag sl-art-tag-bottom">FIND YOUR MATCH</div>
            </div>
        </header>

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
        </section>

        <section class="sl-browser" id="slBrowserControls">
            <div class="sl-section-heading">
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

            <div class="sl-filter-panel" data-fc-reveal="up">
                <div class="sl-filter-top">
                    <div class="sl-filter-pills" id="slFilterPills" role="group" aria-label="فیلتر وضعیت">
                        <button type="button" class="sl-filter-pill active" data-sl-filter="all" aria-pressed="true">
                            همه <span><?= count($serverRows) ?></span>
                        </button>
                        <button type="button" class="sl-filter-pill" data-sl-filter="online" aria-pressed="false">
                            <i class="fa-solid fa-circle" aria-hidden="true"></i> آنلاین <span><?= count($onlineServers) ?></span>
                        </button>
                        <button type="button" class="sl-filter-pill" data-sl-filter="offline" aria-pressed="false">
                            آفلاین
                        </button>
                    </div>

                    <label class="sl-search" for="slSearchInput">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input id="slSearchInput" type="search" autocomplete="off" placeholder="جستجوی نام سرور یا نقشه..." <?= $serverRows === [] ? 'disabled' : '' ?>>
                        <kbd>/</kbd>
                    </label>
                </div>

                <div class="sl-filter-bottom">
                    <div class="sl-select-filters">
                        <label class="sl-select-wrap">
                            <span>گیم‌مود</span>
                            <select id="slModeFilter" <?= $serverRows === [] ? 'disabled' : '' ?>>
                                <option value="">همه حالت‌ها</option>
                                <?php foreach (array_keys($serverGroups) as $modeName): ?>
                                    <option value="<?= $escapeServerValue($lowerServerValue($modeName)) ?>"><?= $escapeServerValue($modeName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </label>

                        <label class="sl-select-wrap">
                            <span>نقشه</span>
                            <select id="slMapFilter" <?= $serverRows === [] ? 'disabled' : '' ?>>
                                <option value="">همه نقشه‌ها</option>
                                <?php foreach ($uniqueMaps as $mapName): ?>
                                    <option value="<?= $escapeServerValue($lowerServerValue($mapName)) ?>"><?= $escapeServerValue($mapName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </label>

                        <label class="sl-select-wrap sl-sort-wrap">
                            <span>مرتب‌سازی</span>
                            <select id="slSortSelect" <?= $serverRows === [] ? 'disabled' : '' ?>>
                                <option value="default">پیش‌فرض</option>
                                <option value="players-desc">بازیکن بیشتر</option>
                                <option value="query-asc">Query سریع‌تر</option>
                                <option value="name-asc">نام سرور</option>
                            </select>
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </label>
                    </div>

                    <span class="sl-results-label" id="slResultsLabel" aria-live="polite">
                        نمایش <?= count($serverRows) ?> سرور
                    </span>
                </div>
            </div>

            <div class="sl-server-list" id="serverList">
                <?php if ($serverRows !== []): ?>
                    <?php foreach ($serverGroups as $groupName => $groupServers): ?>
                        <section class="sl-server-group" data-sl-group>
                            <div class="sl-group-heading">
                                <div class="sl-group-title">
                                    <span class="sl-group-accent"></span>
                                    <h3><?= $escapeServerValue($groupName) ?></h3>
                                    <span class="sl-group-total"><?= count($groupServers) ?> سرور</span>
                                </div>
                                <span class="sl-group-caption">COMMUNITY SERVERS</span>
                            </div>

                            <div class="sl-server-grid">
                                <?php foreach ($groupServers as $server): ?>
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
                                        class="sl-server-card"
                                        data-sl-card
                                        data-status="<?= $escapeServerValue($status) ?>"
                                        data-name="<?= $escapeServerValue($nameSearch) ?>"
                                        data-map="<?= $escapeServerValue($mapSearch) ?>"
                                        data-mode="<?= $escapeServerValue($modeSearch) ?>"
                                        data-address="<?= $escapeServerValue($server['address']) ?>"
                                        data-players="<?= $server['players'] ?>"
                                        data-query="<?= $server['query_rtt_ms'] !== null ? $server['query_rtt_ms'] : '' ?>"
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
                                                <span class="sl-status-badge <?= $statusClass ?>"><?= $escapeServerValue($statusLabel) ?></span>
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
                                                <div class="sl-metric">
                                                    <span><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Query RTT</span>
                                                    <strong dir="ltr"><?= $server['query_rtt_ms'] !== null ? $server['query_rtt_ms'] . ' ms' : '—' ?></strong>
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
                                                    <a class="sl-connect-btn" href="<?= $escapeServerValue($server['connect_url']) ?>">
                                                        اتصال <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="sl-connect-disabled">در دسترس نیست</span>
                                                <?php endif; ?>
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
                <p>بعد از ثبت سرورهای عمومی و اتصال A2S، اطلاعات زندهٔ نقشه، بازیکنان، ظرفیت و وضعیت هر سرور اینجا نمایش داده می‌شود.</p>
                <div class="sl-empty-status">
                    <span class="sl-empty-status-dot"></span>
                    در انتظار اتصال A2S
                </div>
                <small>فعلاً هیچ IP یا آمار آزمایشی نمایش داده نمی‌شود.</small>
            </div>

            <div class="sl-no-match" id="slNoMatch" hidden>
                <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
                <strong>نتیجه‌ای پیدا نشد</strong>
                <span>فیلترها یا عبارت جستجو را تغییر بده.</span>
                <button type="button" id="slClearFilters">پاک‌کردن فیلترها</button>
            </div>
        </section>

        <section class="sl-query-explainer" data-fc-reveal="up">
            <div class="sl-query-explainer-icon"><i class="fa-solid fa-satellite-dish" aria-hidden="true"></i></div>
            <div>
                <span class="sl-section-kicker">LIVE SERVER DATA</span>
                <h2>اطلاعات زنده با A2S</h2>
                <p>وضعیت آنلاین، نقشه، ظرفیت بازیکنان و زمان پاسخ Query از سرور بازی دریافت می‌شود. زمان پاسخ A2S با پینگ واقعی بازیکن یکی نیست و جداگانه نمایش داده خواهد شد.</p>
            </div>
            <div class="sl-query-checklist">
                <span><i class="fa-solid fa-check" aria-hidden="true"></i> A2S_INFO</span>
                <span><i class="fa-solid fa-check" aria-hidden="true"></i> Player count</span>
                <span><i class="fa-solid fa-check" aria-hidden="true"></i> Cache & timeout</span>
            </div>
        </section>
    </div>

    <div class="sl-toast" id="slToast" role="status" aria-live="polite"></div>
</section>
