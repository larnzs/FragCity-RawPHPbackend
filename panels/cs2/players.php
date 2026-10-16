<?php
require_once __DIR__ . '/../../settings/loader.php';
// panel/players.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   Language
========================================================= */
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fa', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang = $_SESSION['lang'] ?? 'fa';
$is_fa = ($lang === 'fa');

/* =========================================================
   Translations
========================================================= */
$translations = [
    'fa' => [
        'title' => 'بازیکنان | پنل مدیریت CS2',
        'nav_title' => 'داشبورد مدیریت سرور',
        'page_title' => 'بازیکنان',
        'page_desc' => 'لیست زنده بازیکنان سرور انتخاب‌شده و عملیات مدیریتی.',
        'tab_players' => 'بازیکنان',
        'tab_bots' => 'ربات‌ها',
        'tab_bans' => 'تاریخچه بن',
        'search_placeholder' => 'جستجو بر اساس نام یا استیم‌آیدی...',
        'th_name' => 'نام',
        'th_steam' => 'استیم‌آیدی',
        'th_score' => 'امتیاز',
        'th_ping' => 'پینگ',
        'th_ip' => 'آی‌پی',
        'th_actions' => 'عملیات',
        'no_players' => 'هیچ بازیکنی آنلاین نیست.',
        'no_search_results' => 'بازیکنی مطابق جستجو پیدا نشد.',
        'footer_count' => 'بازیکن آنلاین',
        'no_server_selected' => 'اول از صفحه سرورها یک سرور را انتخاب کن.',
        'refresh' => 'به‌روزرسانی',
        'loading' => 'در حال دریافت بازیکنان...',
        'unknown' => 'نامشخص'
    ],
    'en' => [
        'title' => 'Players | CS2 Admin Panel',
        'nav_title' => 'CS2 Admin Panel',
        'page_title' => 'Players',
        'page_desc' => 'Live players for the selected server and admin actions.',
        'tab_players' => 'Players',
        'tab_bots' => 'Bots',
        'tab_bans' => 'Ban History',
        'search_placeholder' => 'Search by name or Steam ID...',
        'th_name' => 'NAME',
        'th_steam' => 'STEAM ID',
        'th_score' => 'SCORE',
        'th_ping' => 'PING',
        'th_ip' => 'IP',
        'th_actions' => 'ACTIONS',
        'no_players' => 'No players online.',
        'no_search_results' => 'No matching player found.',
        'footer_count' => 'players online',
        'no_server_selected' => 'Please choose a server from the servers page first.',
        'refresh' => 'Refresh',
        'loading' => 'Loading players...',
        'unknown' => 'Unknown'
    ],
];
$t = $translations[$lang];

$current_page = 'players';
$page_title = $t['nav_title'];

/* =========================================================
   Database connection (services DB)
========================================================= */
function connectServicesDb(): PDO
{
    $cfg=fragcity_settings('files.panels.cs2.players',array());
    $profile=isset($cfg['database_profile'])?$cfg['database_profile']:'main';
    $pdo=fragcity_create_pdo($profile);
    if(!$pdo)throw new PDOException('Database unavailable or not configured.');
    return $pdo;
}

/* =========================================================
   Helpers
========================================================= */
function esc(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDuration(float|int $seconds): string
{
    $seconds = max(0, (int)floor((float)$seconds));
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%dh %02dm', $hours, $minutes);
    }

    if ($minutes > 0) {
        return sprintf('%dm %02ds', $minutes, $secs);
    }

    return sprintf('%ds', $secs);
}

function a2sReadCString(string $buffer, int &$offset): string
{
    $pos = strpos($buffer, "\x00", $offset);
    if ($pos === false) {
        $pos = strlen($buffer);
    }
    $value = substr($buffer, $offset, $pos - $offset);
    $offset = min($pos + 1, strlen($buffer));
    return $value;
}

function a2sSend(string $ip, int $port, string $payload, float $timeout = 1.2): string|false
{
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen("udp://{$ip}", $port, $errno, $errstr, $timeout);

    if (!$fp) {
        return false;
    }

    stream_set_timeout($fp, (int)$timeout, (int)(($timeout - (int)$timeout) * 1000000));
    fwrite($fp, $payload);

    $response = '';
    $start = microtime(true);

    while ((microtime(true) - $start) < $timeout) {
        $chunk = fread($fp, 4096);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $response .= $chunk;
        if (strlen($chunk) < 4096) {
            break;
        }
    }

    fclose($fp);

    return $response !== '' ? $response : false;
}

/**
 * A2S_INFO
 */
function a2sQueryInfo(string $ip, int $port, float $timeout = 1.2): array|false
{
    $packet = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";
    $response = a2sSend($ip, $port, $packet, $timeout);

    if ($response === false || strlen($response) < 6) {
        return false;
    }

    if (substr($response, 0, 5) !== "\xFF\xFF\xFF\xFF\x49") {
        return false;
    }

    $data = substr($response, 5);
    $offset = 0;

    $protocol = ord($data[$offset] ?? "\x00");
    $offset += 1;

    $hostname = a2sReadCString($data, $offset);
    $map = a2sReadCString($data, $offset);
    $folder = a2sReadCString($data, $offset);
    $game = a2sReadCString($data, $offset);

    if ($offset + 2 > strlen($data)) {
        return false;
    }

    $appId = unpack('v', substr($data, $offset, 2))[1] ?? 0;
    $offset += 2;

    $players = ord($data[$offset] ?? "\x00");
    $maxPlayers = ord($data[$offset + 1] ?? "\x00");
    $bots = ord($data[$offset + 2] ?? "\x00");
    $offset += 3;

    $serverType = chr(ord($data[$offset] ?? "\x00"));
    $offset += 1;

    $serverOsByte = ord($data[$offset] ?? "\x00");
    $offset += 1;

    $visibility = ord($data[$offset] ?? "\x00");
    $offset += 1;

    $vac = ord($data[$offset] ?? "\x00");

    return [
        'Protocol' => $protocol,
        'HostName' => $hostname,
        'Map' => $map,
        'GameFolder' => $folder,
        'GameDescription' => $game,
        'AppID' => $appId,
        'Players' => $players,
        'MaxPlayers' => $maxPlayers,
        'Bots' => $bots,
        'ServerType' => $serverType,
        'ServerOS' => $serverOsByte === ord('l') ? 'Linux' : ($serverOsByte === ord('w') ? 'Windows' : 'Unknown'),
        'Visibility' => $visibility,
        'VAC' => $vac,
    ];
}

/**
 * A2S_PLAYER
 *
 * Packet shape you pasted:
 * count -> index -> name -> score -> total time
 */
function a2sQueryPlayers(string $ip, int $port, float $timeout = 1.2): array|false
{
    // Step 1: request challenge
    $challengePacket = "\xFF\xFF\xFF\xFFU\xFF\xFF\xFF\xFF";
    $challengeResponse = a2sSend($ip, $port, $challengePacket, $timeout);

    if ($challengeResponse === false || strlen($challengeResponse) < 9) {
        return false;
    }

    if (substr($challengeResponse, 0, 5) !== "\xFF\xFF\xFF\xFF\x41") {
        return false;
    }

    $challengeToken = unpack('V', substr($challengeResponse, 5, 4))[1] ?? null;
    if ($challengeToken === null) {
        return false;
    }

    // Step 2: request player list
    $playerPacket = "\xFF\xFF\xFF\xFFU" . pack('V', $challengeToken);
    $response = a2sSend($ip, $port, $playerPacket, $timeout);

    if ($response === false || strlen($response) < 6) {
        return false;
    }

    if (substr($response, 0, 5) !== "\xFF\xFF\xFF\xFF\x44") {
        return false;
    }

    $data = substr($response, 5);
    $offset = 0;
    $count = ord($data[$offset] ?? "\x00");
    $offset += 1;

    $players = [];

    for ($i = 0; $i < $count; $i++) {
        if ($offset >= strlen($data)) {
            break;
        }

        $index = ord($data[$offset]);
        $offset += 1;

        $name = a2sReadCString($data, $offset);

        if ($offset + 8 > strlen($data)) {
            break;
        }

        $score = unpack('l', substr($data, $offset, 4))[1] ?? 0;
        $offset += 4;

        $duration = unpack('g', substr($data, $offset, 4))[1] ?? 0.0;
        $offset += 4;

        $players[] = [
            'index' => $index,
            'Name' => $name,
            'Score' => $score,
            'Duration' => $duration,
            'DurationFormatted' => formatDuration($duration),
        ];
    }

    return $players;
}

function renderPlayerRow(array $player, int $rank): string
{
    $name = esc($player['Name'] ?? 'Unknown');
    $score = number_format((int)($player['Score'] ?? 0));
    $duration = esc($player['DurationFormatted'] ?? formatDuration((float)($player['Duration'] ?? 0)));

    // A2S_PLAYER does not provide SteamID/IP. Keep placeholders until plugin/RCON data is added.
    $steamShort = '—';
    $ip = '—';

    return '
        <div class="players-row server-row" data-player-row data-search="' . esc(mb_strtolower((string)($player['Name'] ?? ''), 'UTF-8')) . '">
            <div class="sl-player-name">
                <span class="rank-pill">' . $rank . '</span>
                <span class="sl-player-main">' . $name . '</span>
            </div>

            <div class="sl-player-steam">' . $steamShort . '</div>

            <div class="sl-player-score">' . $score . '</div>

            <div class="sl-player-ping">—</div>

            <div class="sl-player-ip">' . $ip . '</div>

            <div class="sl-player-actions sl-col-actions">
                <button type="button" class="sl-action-btn" data-simple-admin-action="info" data-player-name="' . esc($player['Name'] ?? '') . '">
                    <i class="fa-solid fa-circle-info"></i>
                </button>
                <button type="button" class="sl-action-btn" data-simple-admin-action="kick" data-player-name="' . esc($player['Name'] ?? '') . '" disabled>
                    <i class="fa-solid fa-user-xmark"></i>
                </button>
            </div>
        </div>
    ';
}

/* =========================================================
   Selected server context
========================================================= */
$steamid = (string)($_SESSION['steamid'] ?? $_SESSION['steam_id'] ?? $_SESSION['user']['steamid'] ?? $_SESSION['user_steamid'] ?? '');
$serverId = (int)($_GET['server_id'] ?? $_GET['id'] ?? $_SESSION['current_server_id'] ?? $_SESSION['selected_server_id'] ?? 0);

$selectedServer = null;
$serverInfo = null;
$playerList = [];
$errorMsg = '';

try {
    $pdo = connectServicesDb();

    if ($serverId > 0 && $steamid !== '') {
        $stmt = $pdo->prepare("SELECT * FROM pending_servers WHERE server_id = ? AND steam_id = ? LIMIT 1");
        $stmt->execute([$serverId, $steamid]);
        $selectedServer = $stmt->fetch() ?: null;
    }

    // Fallbacks from session / query if server lookup is unavailable
    $fallbackIp = trim((string)($_SESSION['current_server_ip'] ?? $_SESSION['selected_server_ip'] ?? $_GET['ip'] ?? ''));
    $fallbackPort = (int)($_SESSION['current_server_port'] ?? $_SESSION['selected_server_port'] ?? $_GET['port'] ?? 0);

    if ($selectedServer) {
        $serverInfo = [
            'server_id' => (int)($selectedServer['server_id'] ?? 0),
            'server_name' => (string)($selectedServer['server_name'] ?? $t['unknown']),
            'server_ip' => (string)($selectedServer['server_ip'] ?? $fallbackIp),
            'port' => (int)($selectedServer['port'] ?? ($fallbackPort ?: 27015)),
            'status' => (string)($selectedServer['status'] ?? 'Unknown'),
            'node_name' => (string)($selectedServer['node_name'] ?? ''),
            'slots' => (int)($selectedServer['slots'] ?? 0),
        ];
    } elseif ($fallbackIp !== '' && $fallbackPort > 0) {
        $serverInfo = [
            'server_id' => $serverId,
            'server_name' => $t['unknown'],
            'server_ip' => $fallbackIp,
            'port' => $fallbackPort,
            'status' => 'Unknown',
            'node_name' => '',
            'slots' => 0,
        ];
    }

    if ($serverInfo && !empty($serverInfo['server_ip']) && !empty($serverInfo['port'])) {
        $serverIp = (string)$serverInfo['server_ip'];
        $serverPort = (int)$serverInfo['port'];

        $_SESSION['current_server_id'] = (int)($serverInfo['server_id'] ?? 0);
        $_SESSION['current_server_ip'] = $serverIp;
        $_SESSION['current_server_port'] = $serverPort;

        $serverInfo['a2s'] = a2sQueryInfo($serverIp, $serverPort, 1.2);
        $players = a2sQueryPlayers($serverIp, $serverPort, 1.2);
        $playerList = is_array($players) ? $players : [];
    }
} catch (Throwable $e) {
    $errorMsg = $e->getMessage();
}

/* =========================================================
   AJAX endpoint
========================================================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$serverInfo || empty($serverInfo['server_ip']) || empty($serverInfo['port'])) {
        echo json_encode([
            'error' => $is_fa ? 'اول یک سرور انتخاب کن.' : 'Select a server first.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $action = (string)($_GET['action'] ?? 'players');

    if ($action === 'info') {
        echo json_encode([
            'server' => $serverInfo,
            'info' => $serverInfo['a2s'] ?? false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'players') {
        echo json_encode([
            'server' => $serverInfo,
            'players' => $playerList,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(['error' => 'Unsupported action'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$selectedServerName = $serverInfo['server_name'] ?? null;
$selectedServerIp = $serverInfo['server_ip'] ?? null;
$selectedServerPort = $serverInfo['port'] ?? null;
$initialPlayersCount = count($playerList);
$selectedServerLabel = $selectedServerName && $selectedServerIp && $selectedServerPort
    ? "{$selectedServerName} ({$selectedServerIp}:{$selectedServerPort})"
    : null;

?>
<!DOCTYPE html>
<html lang="<?php echo esc($lang); ?>" dir="<?php echo $is_fa ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc($t['title']); ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/panel-motion.css">
    <link rel="stylesheet" href="css/players.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

    <style>
        .players-selected-server {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(96,165,250,.16);
            background: rgba(11,18,32,.88);
            color: #cbd5e1;
            margin-bottom: 16px;
        }
        .players-selected-server.warning {
            border-color: rgba(248,113,113,.18);
            color: #fecaca;
        }
        .players-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .players-search-box {
            position: relative;
            flex: 1 1 340px;
            max-width: 520px;
        }
        .players-search-box input {
            width: 100%;
            background: #0b1220;
            border: 1px solid #263653;
            border-radius: 13px;
            height: 50px;
            padding: 0 46px 0 44px;
            color: #f8fafc;
            outline: none;
        }
        .players-search-box i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            left: 16px;
            color: #64748b;
        }
        .players-search-clear {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            right: 10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 0;
            background: rgba(148,163,184,.14);
            color: #e2e8f0;
            cursor: pointer;
        }
        .players-refresh-btn {
            min-height: 50px;
            padding: 0 16px;
            border-radius: 13px;
            border: 1px solid #263653;
            background: #0b1220;
            color: #e2e8f0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }
        .players-refresh-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(96,165,250,.3);
            background: rgba(59,130,246,.08);
        }
        .players-card {
            overflow: hidden;
        }
        .players-table-header,
        .players-row {
            display: grid;
            grid-template-columns: 2.1fr 1.2fr .75fr .75fr 1fr .9fr;
            gap: 12px;
            align-items: center;
        }
        .players-table-header {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(51,65,85,.7);
            color: #93c5fd;
            font-weight: 800;
            font-size: .8rem;
            background: rgba(15,23,42,.86);
        }
        .players-list {
            min-height: 240px;
        }
        .players-row {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(51,65,85,.5);
            color: #cbd5e1;
            transition: background .18s ease, transform .18s ease;
        }
        .players-row:hover {
            background: rgba(59,130,246,.05);
        }
        .sl-player-name {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .rank-pill {
            min-width: 30px;
            height: 30px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(59,130,246,.12);
            border: 1px solid rgba(96,165,250,.22);
            color: #93c5fd;
            font-size: .8rem;
            font-weight: 800;
            flex: 0 0 auto;
        }
        .sl-player-main {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 700;
            color: #f8fafc;
        }
        .sl-col-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sl-action-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid rgba(148,163,184,.14);
            background: rgba(15,23,42,.8);
            color: #cbd5e1;
            cursor: pointer;
        }
        .sl-action-btn:disabled {
            cursor: not-allowed;
            opacity: .45;
        }
        .players-empty-state,
        .players-search-empty {
            min-height: 240px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 12px;
            color: #94a3b8;
            padding: 24px;
        }
        .players-empty-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgba(59,130,246,.10);
            border: 1px solid rgba(96,165,250,.16);
            color: #60a5fa;
            font-size: 1.4rem;
        }
        .players-footer-counter {
            margin-top: 14px;
            color: #94a3b8;
            font-size: .85rem;
        }
        .players-loading {
            min-height: 240px;
            display: grid;
            place-items: center;
            color: #93c5fd;
        }
        @media (max-width: 900px) {
            .players-table-header,
            .players-row {
                grid-template-columns: 1.8fr 1fr .7fr .7fr 1fr .9fr;
                gap: 8px;
            }
        }
        @media (max-width: 720px) {
            .players-table-header {
                display: none;
            }
            .players-row {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 14px;
            }
            .sl-col-actions {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content-wrapper">
    <?php include 'header.php'; ?>

    <main class="main-content players-page">
        <div class="page-header players-page-header">
            <h1 class="page-title"><?php echo esc($t['page_title']); ?></h1>
            <div class="page-desc"><?php echo esc($t['page_desc']); ?></div>
        </div>

        <?php if ($selectedServerLabel): ?>
            <div class="players-selected-server">
                <i class="fa-solid fa-server"></i>
                <span><?php echo esc($selectedServerLabel); ?></span>
            </div>
        <?php else: ?>
            <div class="players-selected-server warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo esc($t['no_server_selected']); ?></span>
            </div>
        <?php endif; ?>

        <div class="players-tabs tabs-nav">
            <button type="button" class="tab-btn active" data-player-tab="players">
                <i class="fa-solid fa-users"></i>
                <?php echo esc($t['tab_players']); ?>
            </button>

            <button type="button" class="tab-btn" data-player-tab="bots">
                <i class="fa-solid fa-robot"></i>
                <?php echo esc($t['tab_bots']); ?>
            </button>

            <button type="button" class="tab-btn" data-player-tab="bans">
                <i class="fa-solid fa-ban"></i>
                <?php echo esc($t['tab_bans']); ?>
            </button>
        </div>

        <div class="players-toolbar">
            <div class="players-search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                    type="search"
                    id="playersSearch"
                    placeholder="<?php echo esc($t['search_placeholder']); ?>"
                    autocomplete="off"
                >
                <button
                    type="button"
                    class="players-search-clear"
                    id="clearPlayersSearch"
                    aria-label="Clear search"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <button type="button" class="players-refresh-btn" id="playersRefreshBtn">
                <i class="fa-solid fa-rotate"></i>
                <?php echo esc($t['refresh']); ?>
            </button>
        </div>

        <div class="players-card servers-card">
            <div class="players-table-header">
                <div><?php echo esc($t['th_name']); ?></div>
                <div><?php echo esc($t['th_steam']); ?></div>
                <div><?php echo esc($t['th_score']); ?></div>
                <div><?php echo esc($t['th_ping']); ?></div>
                <div><?php echo esc($t['th_ip']); ?></div>
                <div><?php echo esc($t['th_actions']); ?></div>
            </div>

            <div class="players-list" id="playersList">
                <?php if ($serverInfo && !empty($playerList)): ?>
                    <?php foreach ($playerList as $i => $player): ?>
                        <?php echo renderPlayerRow($player, $i + 1); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="players-empty-state" id="playersEmptyState">
                        <div class="players-empty-icon">
                            <i class="fa-solid fa-users-slash"></i>
                        </div>
                        <div class="empty-title">
                            <?php echo esc($serverInfo ? $t['no_players'] : $t['no_server_selected']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="players-search-empty" id="playersSearchEmpty" hidden>
                    <div class="players-empty-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div class="empty-title">
                        <?php echo esc($t['no_search_results']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-footer-counter players-footer-counter">
            <span id="playersVisibleCount"><?php echo (int)$initialPlayersCount; ?></span>
            <?php echo esc($t['footer_count']); ?>
        </div>
    </main>
</div>

<script>
window.PlayersPageConfig = {
    ajaxUrl: <?php echo json_encode('?page=players&ajax=1', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
    serverId: <?php echo json_encode($serverInfo['server_id'] ?? 0); ?>,
    serverIp: <?php echo json_encode($serverInfo['server_ip'] ?? ''); ?>,
    serverPort: <?php echo json_encode($serverInfo['port'] ?? 0); ?>,
    lang: <?php echo json_encode($lang); ?>
};

window.PlayersInitialData = {
    players: <?php echo json_encode($playerList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    server: <?php echo json_encode($serverInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};

(function () {
    'use strict';

    const cfg = window.PlayersPageConfig || {};
    const initial = window.PlayersInitialData || {};

    const list = document.getElementById('playersList');
    const search = document.getElementById('playersSearch');
    const clearBtn = document.getElementById('clearPlayersSearch');
    const refreshBtn = document.getElementById('playersRefreshBtn');
    const visibleCount = document.getElementById('playersVisibleCount');
    const emptyState = document.getElementById('playersEmptyState');
    const searchEmpty = document.getElementById('playersSearchEmpty');

    let currentPlayers = Array.isArray(initial.players) ? initial.players : [];

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    function rowHtml(player, index) {
        const name = escapeHtml(player.Name || 'Unknown');
        const score = escapeHtml(player.Score ?? 0);
        const duration = escapeHtml(player.DurationFormatted || '');
        return `
            <div class="players-row server-row" data-player-row data-search="${String(player.Name || '').toLowerCase()}">
                <div class="sl-player-name">
                    <span class="rank-pill">${index}</span>
                    <span class="sl-player-main">${name}</span>
                </div>
                <div class="sl-player-steam">—</div>
                <div class="sl-player-score">${score}</div>
                <div class="sl-player-ping">${duration}</div>
                <div class="sl-player-ip">—</div>
                <div class="sl-player-actions sl-col-actions">
                    <button type="button" class="sl-action-btn" data-simple-admin-action="info" data-player-name="${name}">
                        <i class="fa-solid fa-circle-info"></i>
                    </button>
                    <button type="button" class="sl-action-btn" data-simple-admin-action="kick" data-player-name="${name}" disabled>
                        <i class="fa-solid fa-user-xmark"></i>
                    </button>
                </div>
            </div>
        `;
    }

    function renderPlayers(players) {
        if (!list) return;

        const query = (search && search.value ? search.value.trim().toLowerCase() : '');
        const filtered = players.filter(p => {
            const name = String(p.Name || '').toLowerCase();
            return query === '' || name.includes(query);
        });

        const html = filtered.map((player, index) => rowHtml(player, index + 1)).join('');

        list.innerHTML = html || '';

        if (emptyState) {
            emptyState.hidden = filtered.length > 0 || players.length > 0;
        }
        if (searchEmpty) {
            searchEmpty.hidden = !(players.length > 0 && filtered.length === 0);
        }
        if (visibleCount) {
            visibleCount.textContent = String(filtered.length);
        }

        if (players.length === 0) {
            list.innerHTML = `
                <div class="players-empty-state" id="playersEmptyState">
                    <div class="players-empty-icon"><i class="fa-solid fa-users-slash"></i></div>
                    <div class="empty-title"><?php echo esc($t['no_players']); ?></div>
                </div>
            `;
        } else if (filtered.length === 0) {
            list.innerHTML = `
                <div class="players-search-empty" id="playersSearchEmpty">
                    <div class="players-empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                    <div class="empty-title"><?php echo esc($t['no_search_results']); ?></div>
                </div>
            `;
        }
    }

    async function refreshPlayers() {
        if (!cfg.ajaxUrl) return;

        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <?php echo esc($t['loading']); ?>';
        }

        try {
            const url = new URL(cfg.ajaxUrl, window.location.href);
            if (cfg.serverId) url.searchParams.set('server_id', String(cfg.serverId));
            if (cfg.serverIp) url.searchParams.set('ip', String(cfg.serverIp));
            if (cfg.serverPort) url.searchParams.set('port', String(cfg.serverPort));
            url.searchParams.set('action', 'players');

            const res = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            const data = await res.json();
            currentPlayers = Array.isArray(data.players) ? data.players : [];
            renderPlayers(currentPlayers);
        } catch (e) {
            // keep current UI intact if query fails
        } finally {
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="fa-solid fa-rotate"></i> <?php echo esc($t['refresh']); ?>';
            }
        }
    }

    if (search) {
        search.addEventListener('input', function () {
            renderPlayers(currentPlayers);
        });
    }

    if (clearBtn && search) {
        clearBtn.addEventListener('click', function () {
            search.value = '';
            renderPlayers(currentPlayers);
            search.focus();
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshPlayers);
    }

    document.addEventListener('click', function (e) {
        const tab = e.target.closest('.tab-btn[data-player-tab]');
        if (!tab) return;

        document.querySelectorAll('.players-tabs .tab-btn').forEach(btn => btn.classList.remove('active'));
        tab.classList.add('active');
    });

    // Initial render from server response
    renderPlayers(currentPlayers);
})();
</script>

</body>
</html>