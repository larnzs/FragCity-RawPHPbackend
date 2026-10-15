<?php
// pages/serverlist.php
// FragCity Server List
// - Reads servers from the registered_servers table (no JSON, no
//   manual "add your own server" form - these are servers customers
//   provisioned through the site's own hosting/billing system).
// - Live info (map, players, VAC, password) is fetched via the A2S
//   Source Engine Query protocol, only for servers whose DB status
//   is 'Running' (querying an offline server would just time out).
// - CSS/JS live in their own files: css/serverlist.css, js/serverlist.js

/*
|--------------------------------------------------------------------------
| Canonical route
|--------------------------------------------------------------------------
| This file is rendered through index.php?page=servers_vps. Direct
| requests are sent back to the canonical route - except the small
| AJAX sub-endpoint at the bottom (?ajax=1), which is meant to be
| fetched directly by serverlist.js.
*/
$isAjaxRequest =
    isset($_GET['ajax']) &&
    $_GET['ajax'] === '1';

if (
    !defined('FRAGCITY_INDEX_RENDER') &&
    !$isAjaxRequest
) {
    header('Location: /index.php?page=servers_vps', true, 303);
    exit;
}

define('SERVERLIST_QUERY_TIMEOUT', 1);

/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
| Direct PDO connection to the site's own database - no external
| API/class dependency needed for this page.
*/
function serverlist_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $db_host = 'localhost';
        $db_port = '3306';
        $db_name = 'kyonxcbt_fragcity_services';
        $db_user = 'kyonxcbt_admin';
        $db_pass = 'Atrisayeafsorde824@';

        $pdo = new PDO(
            "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    return $pdo;
}

/**
 * @param array<string, mixed> $params
 * @return array<int, array<string, mixed>>
 */
function serverlist_select(string $sql, array $params = []): array
{
    $stmt = serverlist_db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| A2S (Source Engine Query) helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('a2s_query')) {
    function a2s_query(string $ip, int $port, string $packet, float $timeout = 1): string|false
    {
        $socket = @fsockopen("udp://{$ip}", $port, $errno, $errstr, $timeout);

        if (!$socket) {
            return false;
        }

        stream_set_timeout($socket, (int) $timeout);
        fwrite($socket, $packet);

        $response = '';
        $start = microtime(true);

        while (
            microtime(true) - $start < $timeout &&
            ($chunk = fread($socket, 1400)) !== false &&
            $chunk !== ''
        ) {
            $response .= $chunk;
        }

        fclose($socket);

        return $response !== '' ? $response : false;
    }
}

if (!function_exists('a2s_build_info_packet')) {
    function a2s_build_info_packet(): string
    {
        return "\xFF\xFF\xFF\xFFTSource Engine Query\x00";
    }
}

if (!function_exists('a2s_parse_info_response')) {
    function a2s_parse_info_response(string $data): ?array
    {
        if (substr($data, 0, 5) !== "\xFF\xFF\xFF\xFF\x49") {
            return null;
        }

        $data = substr($data, 5);
        $info = [];

        $info['Protocol'] = ord($data[0]);

        $end = strpos($data, "\x00", 1);
        $info['HostName'] = substr($data, 1, $end - 1);
        $data = substr($data, $end + 1);

        $end = strpos($data, "\x00");
        $info['Map'] = substr($data, 0, $end);
        $data = substr($data, $end + 1);

        $end = strpos($data, "\x00");
        $info['GameFolder'] = substr($data, 0, $end);
        $data = substr($data, $end + 1);

        $end = strpos($data, "\x00");
        $info['GameDescription'] = substr($data, 0, $end);
        $data = substr($data, $end + 1);

        $info['AppID'] = unpack('v', substr($data, 0, 2))[1];
        $data = substr($data, 2);

        $info['Players'] = ord($data[0]);
        $info['MaxPlayers'] = ord($data[1]);
        $info['Bots'] = ord($data[2]);
        $data = substr($data, 3);

        $info['ServerType'] = chr(ord($data[0]));
        $data = substr($data, 1);

        $info['ServerOS'] =
            ord($data[0]) === 108 ? 'Linux' :
            (ord($data[0]) === 119 ? 'Windows' : 'Unknown');
        $data = substr($data, 1);

        $info['Visibility'] = ord($data[0]);
        $data = substr($data, 1);

        $info['VAC'] = ord($data[0]);

        return $info;
    }
}

/**
 * Queries several servers' A2S_INFO in parallel using non-blocking
 * UDP sockets, so N servers don't cost N x timeout seconds. Also
 * measures each server's response time (round-trip of our own
 * query - a reasonable stand-in for "ping" on a server list, the
 * same approach most server browsers use server-side).
 *
 * @param array<int, array{ip:string, port:int}> $targets
 * @return array<int, array{info: array|null, ping: int|null}> keyed the same as $targets
 */
if (!function_exists('a2s_query_all')) {
    function a2s_query_all(array $targets, float $timeout = 1): array
    {
        $sockets = [];
        $results = [];
        $sentAt = [];
        $packet = a2s_build_info_packet();

        foreach ($targets as $i => $target) {
            $socket = @fsockopen(
                "udp://{$target['ip']}",
                $target['port'],
                $errno,
                $errstr,
                $timeout
            );

            if ($socket) {
                stream_set_blocking($socket, false);
                fwrite($socket, $packet);
                $sockets[$i] = $socket;
                $sentAt[$i] = microtime(true);
            } else {
                $results[$i] = ['info' => null, 'ping' => null];
            }
        }

        $start = microtime(true);

        while (count($sockets) && microtime(true) - $start < $timeout) {
            $read = $sockets;
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 0, 200000) > 0) {
                foreach ($read as $i => $socket) {
                    $response = '';

                    while (
                        ($chunk = fread($socket, 1400)) !== false &&
                        $chunk !== ''
                    ) {
                        $response .= $chunk;
                    }

                    if ($response !== '') {
                        $ping = (int) round(
                            (microtime(true) - $sentAt[$i]) * 1000
                        );

                        $results[$i] = [
                            'info' => a2s_parse_info_response($response),
                            'ping' => $ping,
                        ];
                    }

                    if (feof($socket)) {
                        fclose($socket);
                        unset($sockets[$i]);
                        $results[$i] ??= ['info' => null, 'ping' => null];
                    }
                }
            }
        }

        foreach ($sockets as $i => $socket) {
            fclose($socket);
            $results[$i] ??= ['info' => null, 'ping' => null];
        }

        $final = [];

        foreach (array_keys($targets) as $i) {
            $final[$i] = $results[$i] ?? ['info' => null, 'ping' => null];
        }

        return $final;
    }
}

/*
|--------------------------------------------------------------------------
| AJAX sub-endpoint (player list for one server)
|--------------------------------------------------------------------------
| The ip/port are NOT trusted from the query string directly - they
| are only accepted if they match a server that actually exists in
| registered_servers with status = 'Running'. Otherwise this endpoint
| would be an open UDP query relay for any IP/port the caller wants.
*/
if ($isAjaxRequest) {
    header('Content-Type: application/json');

    $action = $_GET['action'] ?? '';
    $requestedIp = (string) ($_GET['ip'] ?? '');
    $requestedPort = (int) ($_GET['port'] ?? 0);

    $validRows = serverlist_select(
        "SELECT server_ip, port
         FROM registered_servers
         WHERE server_ip = :ip
           AND port = :port
           AND status = 'Running'
         LIMIT 1",
        [
            ':ip' => $requestedIp,
            ':port' => $requestedPort,
        ]
    );

    if (empty($validRows)) {
        echo json_encode(['error' => 'Unknown server']);
        exit;
    }

    if ($action === 'players') {
        try {
            $raw = a2s_query(
                $requestedIp,
                $requestedPort,
                "\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF"
            );

            if ($raw === false) {
                throw new Exception('No response');
            }

            $players = [];
            $data = substr($raw, 5);
            $num = ord($data[0]);
            $data = substr($data, 1);

            for ($i = 0; $i < $num; $i++) {
                $data = substr($data, 1); // player index byte

                $end = strpos($data, "\x00");
                $name = substr($data, 0, $end);
                $data = substr($data, $end + 1);

                $score = unpack('l', substr($data, 0, 4))[1];
                $data = substr($data, 4);

                $duration = unpack('f', substr($data, 0, 4))[1];
                $data = substr($data, 4);

                $players[] = [
                    'Name' => $name,
                    'Score' => $score,
                    'DurationFormatted' => gmdate('i:s', (int) $duration),
                ];
            }

            echo json_encode($players);
        } catch (Exception $e) {
            echo json_encode(['error' => 'خطا در دریافت اطلاعات']);
        }
    } else {
        echo json_encode(['error' => 'Unknown action']);
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| Load servers from the database
|--------------------------------------------------------------------------
*/
$statusOrder = [
    'Running' => 0,
    'Starting' => 1,
    'Suspended' => 2,
    'Stopped' => 3,
];

$statusLabels = [
    'Running' => 'روشن',
    'Starting' => 'گیم مود',
    'Stopped' => 'خاموش',
    'Suspended' => 'معلق',
];

$statusClasses = [
    'Running' => 'running',
    'Starting' => 'starting',
    'Stopped' => 'stopped',
    'Suspended' => 'suspended',
];

$rows = serverlist_select(
    "SELECT server_id, server_name, server_ip, port, node_name, status
     FROM registered_servers
     ORDER BY server_name ASC"
);

usort(
    $rows,
    fn ($a, $b) =>
        ($statusOrder[$a['status']] ?? 99) <=> ($statusOrder[$b['status']] ?? 99)
);

$runningTargets = [];

foreach ($rows as $i => $row) {
    if ($row['status'] === 'Running') {
        $runningTargets[$i] = [
            'ip' => $row['server_ip'],
            'port' => (int) $row['port'],
        ];
    }
}

$liveResults = empty($runningTargets)
    ? []
    : a2s_query_all($runningTargets, SERVERLIST_QUERY_TIMEOUT);

/*
|--------------------------------------------------------------------------
| Build a simple view model per server + status counters for the
| filter pills
|--------------------------------------------------------------------------
*/

if (!function_exists('serverlist_map_label')) {
    function serverlist_map_label(?string $map): string
    {
        $map = trim((string) $map);

        $labels = [
            'de_mirage' => 'Mirage',
            'de_dust2' => 'Dust II',
            'de_inferno' => 'Inferno',
            'de_nuke' => 'Nuke',
            'de_ancient' => 'Ancient',
            'de_anubis' => 'Anubis',
            'de_overpass' => 'Overpass',
            'de_vertigo' => 'Vertigo',
            'de_train' => 'Train',
            'de_cbble' => 'Cobblestone',
            'de_cache' => 'Cache',
            'de_dust' => 'Dust',
            'de_aim' => 'Aim',
            'de_arena' => 'Arena',
        ];

        if ($map === '') {
            return 'Other';
        }

        return $labels[$map] ?? ucwords(str_replace(['_', '-'], ' ', $map));
    }
}

$servers = [];
$statusCounts = [
    'all' => count($rows),
    'Running' => 0,
    'Starting' => 0,
    'Stopped' => 0,
    'Suspended' => 0,
];

foreach ($rows as $idx => $row) {
    $status = $row['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

    $live = $liveResults[$idx] ?? null;
    $info = $live['info'] ?? null;
    $ping = $live['ping'] ?? null;
    $isReachable = $info !== null;

    $servers[] = [
        'id' => $row['server_id'],
        'name' => $row['server_name'],
        'ip' => $row['server_ip'],
        'port' => (int) $row['port'],
        'node' => $row['node_name'],
        'status' => $status,
        'statusLabel' => $statusLabels[$status] ?? $status,
        'statusClass' => $statusClasses[$status] ?? 'stopped',
        'gameMode' => '—',
        'reachable' => $isReachable,
        'map' => $isReachable ? $info['Map'] : null,
        'players' => $isReachable ? (int) $info['Players'] : 0,
        'maxPlayers' => $isReachable ? (int) $info['MaxPlayers'] : 0,
        'locked' => $isReachable ? ((int) $info['Visibility'] === 1) : false,
        'vac' => $isReachable ? ((int) $info['VAC'] === 1) : false,
        'ping' => $ping,
        'mapLabel' => serverlist_map_label($isReachable ? $info['Map'] : null),
    ];
}
?>

<div class="serverlist-page">

    <div class="sl-page-head">
        <div class="sl-page-title">
            <div class="sl-title-icon"><i class="fas fa-server"></i></div>
            <div>
                <span class="sl-eyebrow">COUNTER-STRIKE 2</span>
                <h1>سرورهای پابلیک</h1>
                <p>سرورهای آنلاین را بر اساس نقشه پیدا کنید و با یک کلیک وارد بازی شوید.</p>
            </div>
        </div>
        <div class="sl-live-counter">
            <span class="status-dot running"></span>
            <strong><?= (int) array_sum(array_column($servers, 'players')) ?></strong>
            <span>بازیکن آنلاین</span>
        </div>
    </div>

    <div class="sl-toolbar">
        <div class="sl-filter-pills" id="slFilterPills">
            <button type="button" class="sl-pill active" data-sl-filter="all">
                همه <span class="sl-pill-count"><?= (int) $statusCounts['all'] ?></span>
            </button>
            <button type="button" class="sl-pill" data-sl-filter="Running">
                <span class="status-dot running"></span> روشن
                <span class="sl-pill-count"><?= (int) $statusCounts['Running'] ?></span>
            </button>
            <button type="button" class="sl-pill" data-sl-filter="Starting">
                <span class="status-dot starting"></span> در حال اجرا
                <span class="sl-pill-count"><?= (int) $statusCounts['Starting'] ?></span>
            </button>
            <button type="button" class="sl-pill" data-sl-filter="Stopped">
                <span class="status-dot stopped"></span> خاموش
                <span class="sl-pill-count"><?= (int) $statusCounts['Stopped'] ?></span>
            </button>
            <button type="button" class="sl-pill" data-sl-filter="Suspended">
                <span class="status-dot suspended"></span> معلق
                <span class="sl-pill-count"><?= (int) $statusCounts['Suspended'] ?></span>
            </button>
        </div>

        <div class="sl-toolbar-actions">
            <label class="sl-search" for="slSearchInput">
                <i class="fas fa-search"></i>
                <input type="text" id="slSearchInput" placeholder="جستجوی سرور..." autocomplete="off">
            </label>

            <div class="frag-dropdown sl-sort-dropdown" id="slSortDropdown">
                <button type="button" class="sl-sort-btn" id="slSortButton" aria-expanded="false">
                    <i class="fas fa-arrow-down-wide-short"></i>
                    <span id="slSortLabel">پیش‌فرض</span>
                    <i class="fas fa-chevron-down frag-arrow"></i>
                </button>
                <div class="frag-dropdown-menu sl-sort-menu" id="slSortMenu">
                    <button type="button" class="frag-drop-link active" data-sl-sort="default">پیش‌فرض</button>
                    <button type="button" class="frag-drop-link" data-sl-sort="players-desc">بازیکن بیشتر</button>
                    <button type="button" class="frag-drop-link" data-sl-sort="players-asc">بازیکن کمتر</button>
                    <button type="button" class="frag-drop-link" data-sl-sort="ping-asc">پینگ کمتر</button>
                    <button type="button" class="frag-drop-link" data-sl-sort="ping-desc">پینگ بیشتر</button>
                    <button type="button" class="frag-drop-link" data-sl-sort="name-asc">نام (الف تا ی)</button>
                </div>
            </div>
        </div>
    </div>

    <div class="server-grid" id="serverList">
        <?php if (empty($servers)): ?>
            <div class="sl-empty"><i class="fas fa-server"></i> در حال حاضر سروری ثبت نشده است.</div>
        <?php endif; ?>

        <?php
        $groupedServers = [];
        foreach ($servers as $server) {
            $groupKey = $server['mapLabel'] ?: 'Other';
            $groupedServers[$groupKey][] = $server;
        }
        ksort($groupedServers, SORT_NATURAL | SORT_FLAG_CASE);
        ?>

        <?php foreach ($groupedServers as $groupName => $groupServers): ?>
            <section class="sl-server-group" data-map-group="<?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>">
                <div class="sl-group-head">
                    <div class="sl-group-title">
                        <span class="sl-group-accent"></span>
                        <h2><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="sl-group-count"><?= count($groupServers) ?> سرور</span>
                    </div>
                    <span class="sl-group-sub">Public Servers</span>
                </div>

                <div class="sl-group-grid">
                    <?php foreach ($groupServers as $server):
                        $joinUrl = $server['reachable']
                            ? 'steam://connect/' . $server['ip'] . ':' . $server['port']
                            : null;

                        $pingClass = 'na';
                        if ($server['ping'] !== null) {
                            $pingClass = $server['ping'] < 80 ? 'good' : ($server['ping'] < 150 ? 'ok' : 'bad');
                        }

                        $fullRatio = $server['maxPlayers'] > 0 ? $server['players'] / $server['maxPlayers'] : 0;
                        $playersClass = $fullRatio >= 0.9 ? 'hot' : ($fullRatio >= 0.5 ? 'mid' : 'low');
                    ?>
                        <article class="server-row sl-server-card status-<?= htmlspecialchars($server['statusClass'], ENT_QUOTES, 'UTF-8') ?>"
                                 data-status="<?= htmlspecialchars($server['status'], ENT_QUOTES, 'UTF-8') ?>"
                                 data-name="<?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?>"
                                 data-map="<?= htmlspecialchars($server['mapLabel'], ENT_QUOTES, 'UTF-8') ?>"
                                 data-players="<?= (int) $server['players'] ?>"
                                 data-max-players="<?= (int) $server['maxPlayers'] ?>"
                                 data-vac="<?= $server['vac'] ? '1' : '0' ?>"
                                 data-locked="<?= $server['locked'] ? '1' : '0' ?>"
                                 data-ping="<?= $server['ping'] !== null ? (int) $server['ping'] : 99999 ?>"
                                 data-ip="<?= htmlspecialchars($server['ip'], ENT_QUOTES, 'UTF-8') ?>"
                                 data-port="<?= (int) $server['port'] ?>">
                            <button type="button" class="sl-card-clickarea" data-sl-open-info aria-label="مشاهده اطلاعات <?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?>"></button>

                            <div class="sl-card-top">
                                <div class="sl-card-name-wrap">
                                    <span class="status-dot <?= htmlspecialchars($server['statusClass'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                    <div class="sl-card-name-block">
                                        <h3 class="name-text"><?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <div class="sl-ip-row">
                                            <span class="ip-text"><?= htmlspecialchars($server['ip'], ENT_QUOTES, 'UTF-8') ?>:<?= (int) $server['port'] ?></span>
                                            <button class="sl-copy-btn" data-ip="<?= htmlspecialchars($server['ip'], ENT_QUOTES, 'UTF-8') ?>:<?= (int) $server['port'] ?>" title="کپی آی‌پی">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <span class="status-badge <?= htmlspecialchars($server['statusClass'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($server['statusLabel'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <div class="sl-card-body">
                                <div class="sl-map-box">
                                    <div class="sl-map-icon"><i class="fas fa-map"></i></div>
                                    <div>
                                        <span>نقشه</span>
                                        <strong><?= htmlspecialchars($server['mapLabel'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                </div>

                                <div class="sl-card-stat">
                                    <span>بازیکن</span>
                                    <?php if ($server['reachable']): ?>
                                        <strong class="sl-players <?= $playersClass ?>"><?= (int) $server['players'] ?>/<?= (int) $server['maxPlayers'] ?></strong>
                                    <?php else: ?>
                                        <strong class="sl-muted">-/-</strong>
                                    <?php endif; ?>
                                </div>

                                <div class="sl-card-stat">
                                    <span>پینگ</span>
                                    <?php if ($server['ping'] !== null): ?>
                                        <strong class="sl-ping <?= $pingClass ?>"><?= (int) $server['ping'] ?> ms</strong>
                                    <?php else: ?>
                                        <strong class="sl-muted">---</strong>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="sl-card-bottom">
                                <span class="sl-node"><i class="fas fa-location-dot"></i><?= htmlspecialchars($server['node'] ?: 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="sl-card-actions">
                                    <?php if ($server['reachable']): ?>
                                        <button type="button" class="sl-info-btn" data-sl-open-info>
                                            <i class="fas fa-circle-info"></i>
                                            اطلاعات
                                        </button>
                                        <a class="sl-join-btn" data-sl-stop-card href="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-play"></i>
                                            بازی
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="sl-info-btn" data-sl-open-info>
                                            <i class="fas fa-circle-info"></i>
                                            اطلاعات
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="sl-no-match" id="slNoMatch" style="display:none;">
            <i class="fas fa-magnifying-glass"></i>
            <span>سروری با این مشخصات پیدا نشد.</span>
        </div>
    </div>

    <aside class="sl-sidebar">
        <div class="sl-promo-card">
            <i class="fas fa-server sl-promo-icon"></i>
            <h3>سرور اختصاصی خودتان را بسازید</h3>
            <p>در چند دقیقه یک سرور CS2 اختصاصی راه‌اندازی کنید و آن را مطابق سلیقه‌ی خودتان تنظیم کنید.</p>
            <a href="?page=buyserveropen" class="sl-promo-btn" data-ajax-nav data-slug="buyserveropen">
                خرید سرور <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </aside>
</div>

<div class="sl-modal" id="slInfoModal" aria-hidden="true">
    <div class="sl-modal-backdrop" data-sl-close-info></div>
    <div class="sl-modal-card" role="dialog" aria-modal="true" aria-labelledby="slModalTitle">
        <button type="button" class="sl-modal-close" data-sl-close-info aria-label="بستن">
            <i class="fas fa-xmark"></i>
        </button>

        <div class="sl-modal-head">
            <div class="sl-modal-map">
                <i class="fas fa-map-location-dot"></i>
                <span id="slModalMap">Map</span>
            </div>
            <h2 id="slModalTitle">Server</h2>
            <div class="sl-modal-player-count" id="slModalPlayersCount">0 : 0</div>
        </div>

        <div class="sl-modal-connect">
            <span id="slModalIp">-</span>
            <button type="button" class="sl-modal-copy" id="slModalCopy" title="کپی آی‌پی">
                <i class="fas fa-copy"></i>
            </button>
            <a class="sl-modal-play" id="slModalPlay" href="#">
                <i class="fas fa-play"></i>
            </a>
        </div>

        <div class="sl-modal-meta">
            <span><i class="fas fa-location-dot"></i><b>لوکیشن</b><em id="slModalNode">-</em></span>
            <span><i class="fas fa-signal"></i><b>پینگ</b><em id="slModalPing">---</em></span>
            <span><i class="fas fa-shield-halved"></i><b>VAC</b><em id="slModalVac">-</em></span>
            <span><i class="fas fa-lock"></i><b>سرور</b><em id="slModalLock">-</em></span>
        </div>

        <div class="sl-modal-players-head">
            <div>
                <span class="status-dot running"></span>
                بازیکنان آنلاین
            </div>
            <span id="slModalPlayersState">در حال دریافت...</span>
        </div>
        <div class="sl-modal-players" id="slModalPlayers">
            <div class="sl-modal-loading"><i class="fas fa-spinner fa-spin"></i> در حال دریافت بازیکنان...</div>
        </div>
    </div>
</div>

</div>

