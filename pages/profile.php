<?php
// pages/profile.php - نسخه جداشده از frontend با پشتیبانی از هر دو فرمت SteamID
if (!isset($_SESSION['user_logged_in'])) {
    header('Location: index.php?page=login');
    exit;
}

$steamid = $_SESSION['steamid'] ?? '';
$username = $_SESSION['username'] ?? $steamid;

// Normalize either supported SteamID format to SteamID64 for Community lookups.
if (!function_exists('steamid64_to_steam2')) {
    function steamid64_to_steam2($communityId) {
        $accountId = (int)$communityId - 76561197960265728;
        $authserver = $accountId % 2;
        $authid = (int)(($accountId - $authserver) / 2);
        return "STEAM_1:{$authserver}:{$authid}";
    }
}
if (!function_exists('steam2_to_steamid64')) {
    function steam2_to_steamid64($steam2) {
        if (!preg_match('/^STEAM_\d+:([01]):(\d+)$/D', (string)$steam2, $matches)) return null;
        return (string)(((int)$matches[2] * 2) + (int)$matches[1] + 76561197960265728);
    }
}

$steamid64 = '';
$steam2 = '';
if (preg_match('/^STEAM_\d+:[01]:\d+$/D', (string)$steamid)) {
    $steam2 = (string)$steamid;
    $steamid64 = (string)(steam2_to_steamid64($steamid) ?? '');
} elseif (preg_match('/^[0-9]{17}$/D', (string)$steamid)) {
    $steamid64 = (string)$steamid;
    $steam2 = steamid64_to_steam2($steamid64);
}
if (!preg_match('/^[0-9]{17}$/D', $steamid64)) $steamid64 = '';

$profileCsrfToken = $_SESSION['profile_csrf_token'] ?? '';
if (!is_string($profileCsrfToken) || !preg_match('/^[a-f0-9]{64}$/D', $profileCsrfToken)) {
    $profileCsrfToken = bin2hex(random_bytes(32));
    $_SESSION['profile_csrf_token'] = $profileCsrfToken;
}

if (!function_exists('profileRespond')) {
    function profileRespond(string $message, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) break;
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store');
        }
        echo $message;
        exit;
    }
}

if (!function_exists('profileSaveJpegBytes')) {
    function profileSaveJpegBytes(string $bytes, string $target, int $maxDimension): bool
    {
        if (
            strlen($bytes) > 1024 * 1024
            || !function_exists('imagecreatefromstring')
            || !function_exists('imagejpeg')
        ) return false;

        $info = @getimagesizefromstring($bytes);
        if (!is_array($info)) return false;
        $mime = (string)($info['mime'] ?? '');
        $width = (int)($info[0] ?? 0);
        $height = (int)($info[1] ?? 0);
        if (
            !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $width < 1 || $height < 1
            || $width > 6000 || $height > 6000
            || ($width * $height) > 30000000
        ) return false;

        $source = @imagecreatefromstring($bytes);
        if ($source === false) return false;
        $scale = min(1, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int)round($width * $scale));
        $targetHeight = max(1, (int)round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            imagedestroy($source);
            return false;
        }

        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 11, 15, 24));
        $resampled = imagecopyresampled(
            $canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height
        );
        $temporary = tempnam(dirname($target), '.fragcity-profile-');
        $saved = $resampled && is_string($temporary) && imagejpeg($canvas, $temporary, 88);
        imagedestroy($source);
        imagedestroy($canvas);

        if (!$saved || !is_string($temporary)) {
            if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
            return false;
        }
        @chmod($temporary, 0644);
        if (!@rename($temporary, $target)) {
            @unlink($temporary);
            return false;
        }
        @chmod($target, 0644);
        return true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['banner']) || array_key_exists('avatar_data', $_POST))) {
    $submittedToken = (string)($_SERVER['HTTP_X_PROFILE_CSRF'] ?? '');
    if (!hash_equals($profileCsrfToken, $submittedToken)) profileRespond('درخواست نامعتبر است؛ صفحه را تازه‌سازی کنید.', 400);
    if ($steamid64 === '') profileRespond('شناسه Steam معتبر نیست.', 400);

    $avatarsDir = __DIR__ . '/../img/avatars/';
    $bannersDir = __DIR__ . '/../img/banners/';
    foreach ([$avatarsDir, $bannersDir] as $directory) {
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            profileRespond('مسیر ذخیرهٔ تصویر قابل ساخت نیست.', 500);
        }
    }

    if (isset($_FILES['banner'])) {
        $file = $_FILES['banner'];
        if (
            !is_array($file)
            || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !isset($file['tmp_name'])
            || !is_uploaded_file((string)$file['tmp_name'])
            || (int)($file['size'] ?? 0) > 1024 * 1024
        ) profileRespond('آپلود بنر نامعتبر است یا حجم آن بیشتر از ۱ مگابایت است.', 400);

        $bytes = file_get_contents((string)$file['tmp_name']);
        if (!is_string($bytes) || !profileSaveJpegBytes($bytes, $bannersDir . 'banner_' . $steamid64 . '.jpg', 1920)) {
            profileRespond('فرمت تصویر بنر پشتیبانی نمی‌شود یا ذخیرهٔ آن ناموفق بود.', 400);
        }
        profileRespond('بنر با موفقیت ذخیره شد.');
    }

    if (array_key_exists('avatar_data', $_POST)) {
        $payload = json_decode((string)$_POST['avatar_data'], true);
        $dataUrl = is_array($payload) ? (string)($payload['img'] ?? '') : '';
        if (
            strlen($dataUrl) > 2 * 1024 * 1024
            || !preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#D', $dataUrl, $matches)
        ) profileRespond('دادهٔ آواتار معتبر نیست.', 400);

        $bytes = base64_decode($matches[1], true);
        if (
            !is_string($bytes)
            || strlen($bytes) > 1024 * 1024
            || !profileSaveJpegBytes($bytes, $avatarsDir . 'avatar_' . $steamid64 . '.jpg', 800)
        ) profileRespond('فرمت تصویر آواتار معتبر نیست یا ذخیرهٔ آن ناموفق بود.', 400);
        profileRespond('آواتار با موفقیت ذخیره شد.');
    }
}

require_once __DIR__ . '/../class/SteamProfileService.php';
$steamProfile = [];
if ($steamid64 !== '') {
    try { $steamProfile = SteamProfileService::getPlayerSummary($steamid64); }
    catch (Throwable $e) { error_log('[FragCity] Steam profile lookup failed for profile page.'); }
}

// Keep Steam details usable even when the API is temporarily unavailable.
// This SELECT intentionally omits binary images: users stores the local avatar path, not the bytes.
$storedSteamAvatarPath = '';
$storedSteamProfile = [];
if ($steamid64 !== '' && isset($pdo) && $pdo instanceof PDO) {
    try {
        $storedStatement = $pdo->prepare(
            'SELECT steam_personaname, steam_profileurl, steam_avatar_url, steam_avatar_path,
                    steam_personastate, steam_communityvisibilitystate, steam_profilestate,
                    steam_timecreated, steam_lastlogoff, steam_loccountrycode,
                    steam_gameextrainfo, steam_gameid
             FROM users WHERE steamid64 = ? LIMIT 1'
        );
        $storedStatement->execute([$steamid64]);
        $storedRow = $storedStatement->fetch(PDO::FETCH_ASSOC);
        if (is_array($storedRow)) {
            $storedSteamAvatarPath = (string)($storedRow['steam_avatar_path'] ?? '');
            $storedSteamProfile = [
                'steamid' => $steamid64,
                'personaname' => (string)($storedRow['steam_personaname'] ?? ''),
                'profileurl' => (string)($storedRow['steam_profileurl'] ?? ''),
                'avatarfull' => (string)($storedRow['steam_avatar_url'] ?? ''),
                'personastate' => isset($storedRow['steam_personastate']) ? (int)$storedRow['steam_personastate'] : null,
                'communityvisibilitystate' => isset($storedRow['steam_communityvisibilitystate']) ? (int)$storedRow['steam_communityvisibilitystate'] : null,
                'profilestate' => isset($storedRow['steam_profilestate']) ? (int)$storedRow['steam_profilestate'] : null,
                'timecreated' => isset($storedRow['steam_timecreated']) ? (int)$storedRow['steam_timecreated'] : null,
                'lastlogoff' => isset($storedRow['steam_lastlogoff']) ? (int)$storedRow['steam_lastlogoff'] : null,
                'loccountrycode' => (string)($storedRow['steam_loccountrycode'] ?? ''),
                'gameextrainfo' => (string)($storedRow['steam_gameextrainfo'] ?? ''),
                'gameid' => (string)($storedRow['steam_gameid'] ?? ''),
            ];
            $hasStoredProfileData = false;
            foreach (['personaname', 'profileurl', 'avatarfull', 'loccountrycode', 'gameextrainfo', 'gameid'] as $field) {
                if (trim((string)($storedSteamProfile[$field] ?? '')) !== '') {
                    $hasStoredProfileData = true;
                    break;
                }
            }
            if (!$hasStoredProfileData) $storedSteamProfile = [];
        }
    } catch (Throwable $e) {
        // A missing migration should not break the rest of the profile page.
        error_log('[FragCity] Stored Steam profile is unavailable; check the users profile migration.');
        $storedSteamProfile = [];
    }
}

if ($storedSteamProfile !== []) {
    if ($steamProfile === []) {
        $steamProfile = $storedSteamProfile;
    } else {
        foreach ($storedSteamProfile as $field => $storedValue) {
            $currentValue = $steamProfile[$field] ?? null;
            if (
                $currentValue === null
                || (is_string($currentValue) && trim($currentValue) === '' && trim((string)$storedValue) !== '')
            ) {
                $steamProfile[$field] = $storedValue;
            }
        }
    }
}

$steamPersonaname = trim((string)($steamProfile['personaname'] ?? ''));
if ($steamPersonaname === '') $steamPersonaname = (string)$username;

$steamStateValue = isset($steamProfile['personastate']) ? (int)$steamProfile['personastate'] : -1;
$steamStatusLabels = [0 => 'آفلاین', 1 => 'آنلاین', 2 => 'مشغول', 3 => 'غایب', 4 => 'خواب', 5 => 'مایل به معامله', 6 => 'مایل به بازی'];
$steamStatus = $steamStatusLabels[$steamStateValue] ?? 'نامشخص';

$visibility = (int)($steamProfile['communityvisibilitystate'] ?? 0);
$steamVisibility = $visibility === 3
    ? 'عمومی'
    : ($visibility === 1 ? 'خصوصی' : ($visibility === 2 ? 'فقط دوستان' : 'نامشخص'));

$steamProfileStateValue = isset($steamProfile['profilestate']) ? (int)$steamProfile['profilestate'] : null;
$steamProfileState = $steamProfileStateValue === null
    ? 'نامشخص'
    : ($steamProfileStateValue === 1 ? 'تنظیم‌شده' : 'کامل نشده');

$steamCountry = strtoupper(trim((string)($steamProfile['loccountrycode'] ?? '')));
if (!preg_match('/^[A-Z]{2}$/D', $steamCountry)) $steamCountry = '';

$steamCreatedAt = (int)($steamProfile['timecreated'] ?? 0);
$steamCreatedDate = $steamCreatedAt > 0 ? date('Y-m-d', $steamCreatedAt) : '—';
$steamLastLogoff = (int)($steamProfile['lastlogoff'] ?? 0);
$steamLastLogoffDate = $steamLastLogoff > 0 ? date('Y-m-d', $steamLastLogoff) : '—';
$steamGame = trim((string)($steamProfile['gameextrainfo'] ?? ''));

$steamProfileUrl = trim((string)($steamProfile['profileurl'] ?? ''));
$steamProfileUrlParts = $steamProfileUrl !== '' ? parse_url($steamProfileUrl) : false;
if (
    !is_array($steamProfileUrlParts)
    || strtolower((string)($steamProfileUrlParts['scheme'] ?? '')) !== 'https'
    || !in_array(strtolower((string)($steamProfileUrlParts['host'] ?? '')), ['steamcommunity.com', 'www.steamcommunity.com'], true)
) {
    $steamProfileUrl = '';
}

$steamIdForDisplay = $steamid64 !== '' ? $steamid64 : (string)$steamid;

// Ranking data is optional; its database being unavailable must not hide Steam data.
$playerStats = null;
$globalPlace = 0;
try {
    require_once __DIR__ . '/../database/kyonxcbt_ranks_plugin.php';
    $ranksDb = new RemoteRanksDatabase();
    $sql = "SELECT steam, name, value AS points, kills, deaths, assists, headshots, round_win AS wins, playtime
            FROM lvl_base WHERE steam = :steam LIMIT 1";
    if ($steamid64 !== '') {
        $result = $ranksDb->select($sql, [':steam' => $steamid64]);
        if (!empty($result)) $playerStats = $result;
    }
    if (empty($playerStats) && $steam2 !== '') {
        $result = $ranksDb->select($sql, [':steam' => $steam2]);
        if (!empty($result)) $playerStats = $result;
    }
    $statsForPlace = $playerStats[0] ?? null;
    if (is_array($statsForPlace)) {
        $placeResult = $ranksDb->select(
            "SELECT COUNT(*) + 1 as place FROM lvl_base WHERE value > :points",
            [':points' => (int)($statsForPlace['points'] ?? 0)]
        );
        $globalPlace = (int)($placeResult[0]['place'] ?? 0);
    }
} catch (Throwable $e) {
    error_log('[FragCity] Profile ranking data is unavailable.');
    $playerStats = null;
    $globalPlace = 0;
}

$stats = $playerStats[0] ?? null;
$hasRankStats = is_array($stats);
$points = (int)($stats['points'] ?? 0);
$kills = (int)($stats['kills'] ?? 0);
$deaths = (int)($stats['deaths'] ?? 0);
$assists = (int)($stats['assists'] ?? 0);
$headshots = (int)($stats['headshots'] ?? 0);
$wins = (int)($stats['wins'] ?? 0);
$playtime = (int)($stats['playtime'] ?? 0);
$kd = $deaths > 0 ? round($kills / $deaths, 2) : $kills;
$hsPercent = $kills > 0 ? round(($headshots / $kills) * 100) : 0;

$rankMap = [
    "None" => 0, "Silver I" => 50, "Silver II" => 100, "Silver III" => 150,
    "Silver IV" => 300, "Silver Elite" => 400, "Silver Elite Master" => 500,
    "Gold Nova I" => 600, "Gold Nova II" => 700, "Gold Nova III" => 800,
    "Gold Nova Master" => 900, "Master Guardian I" => 1000,
    "Master Guardian II" => 1400, "Master Guardian Elite" => 1600,
    "BigStar" => 2100, "Legendary Eagle" => 2600,
    "Legendary Eagle Master" => 2900, "Supreme" => 3400,
    "The Global Elite" => 4500
];
$currentRank = "None";
$nextRank = "The Global Elite";
$currentThreshold = 0;
$nextThreshold = 4500;
foreach ($rankMap as $rankName => $threshold) {
    if ($points >= $threshold) {
        $currentRank = $rankName;
        $currentThreshold = $threshold;
    } else {
        $nextRank = $rankName;
        $nextThreshold = $threshold;
        break;
    }
}
$xpProgress = $points >= 4500 ? 100 : (($points - $currentThreshold) / max(1, $nextThreshold - $currentThreshold)) * 100;
$xpProgress = max(0, min(100, $xpProgress));

$bannerKey = $steamid64 !== '' ? $steamid64 : preg_replace('/[^0-9]/', '', (string)$steamid);
$bannerFile = $bannerKey !== '' ? __DIR__ . '/../img/banners/banner_' . $bannerKey . '.jpg' : '';
$avatarFile = $bannerKey !== '' ? __DIR__ . '/../img/avatars/avatar_' . $bannerKey . '.jpg' : '';
$bannerUrl = $bannerFile !== '' && is_file($bannerFile)
    ? 'img/banners/banner_' . rawurlencode($bannerKey) . '.jpg?v=' . filemtime($bannerFile)
    : '';
$avatarUrl = $avatarFile !== '' && is_file($avatarFile)
    ? 'img/avatars/avatar_' . rawurlencode($bannerKey) . '.jpg?v=' . filemtime($avatarFile)
    : '';

$steamAvatarUrl = '';
$allowedStoredSteamPath = $steamid64 !== ''
    && preg_match(
        '#^img/steam-avatars/steam_' . preg_quote($steamid64, '#') . '\\.(?:jpg|png|webp)$#D',
        $storedSteamAvatarPath
    );
if ($allowedStoredSteamPath) {
    $storedSteamDiskPath = __DIR__ . '/../' . $storedSteamAvatarPath;
    if (is_file($storedSteamDiskPath)) {
        $steamAvatarUrl = $storedSteamAvatarPath . '?v=' . filemtime($storedSteamDiskPath);
    }
}
if ($steamAvatarUrl === '' && $steamid64 !== '') {
    foreach (['jpg', 'png', 'webp'] as $steamAvatarExtension) {
        $steamAvatarFilename = 'steam_' . $steamid64 . '.' . $steamAvatarExtension;
        $steamAvatarDiskPath = __DIR__ . '/../img/steam-avatars/' . $steamAvatarFilename;
        if (is_file($steamAvatarDiskPath)) {
            $steamAvatarUrl = 'img/steam-avatars/' . rawurlencode($steamAvatarFilename)
                . '?v=' . filemtime($steamAvatarDiskPath);
            break;
        }
    }
}
$steamCardAvatarUrl = $steamAvatarUrl !== ''
    ? $steamAvatarUrl
    : (string)($steamProfile['avatarfull'] ?? '');
if ($avatarUrl === '') $avatarUrl = $steamCardAvatarUrl;
// FACEIT stats are optional until a verified FACEIT integration populates the session.
$faceitProfileData = $_SESSION['faceit_profile'] ?? [];
$faceitEloCandidate = is_array($faceitProfileData)
    ? ($faceitProfileData['elo'] ?? $faceitProfileData['faceit_elo'] ?? null)
    : null;
$faceitElo = is_numeric($faceitEloCandidate) ? (int)$faceitEloCandidate : null;
if ($faceitElo !== null && $faceitElo < 100) $faceitElo = null;

$faceitLevel = null;
$faceitProgress = 0.0;
$faceitProgressText = 'اتصال FACEIT تنظیم نشده';
if ($faceitElo !== null) {
    // Current CS2 FACEIT level thresholds; Level 10 is open-ended.
    $faceitLevels = [
        1 => ['min' => 100, 'next' => 501],
        2 => ['min' => 501, 'next' => 751],
        3 => ['min' => 751, 'next' => 901],
        4 => ['min' => 901, 'next' => 1051],
        5 => ['min' => 1051, 'next' => 1201],
        6 => ['min' => 1201, 'next' => 1351],
        7 => ['min' => 1351, 'next' => 1531],
        8 => ['min' => 1531, 'next' => 1751],
        9 => ['min' => 1751, 'next' => 2001],
        10 => ['min' => 2001, 'next' => null],
    ];

    foreach ($faceitLevels as $level => $range) {
        if ($faceitElo >= $range['min']) {
            $faceitLevel = $level;
        } else {
            break;
        }
    }

    if ($faceitLevel === 10) {
        // FACEIT Level 10 has no upper Elo limit; the level cap is shown as full.
        $faceitProgress = 100.0;
    } elseif ($faceitLevel !== null) {
        $range = $faceitLevels[$faceitLevel];
        $faceitProgress = (($faceitElo - $range['min']) / max(1, $range['next'] - $range['min'])) * 100;
        $faceitProgress = max(0, min(100, $faceitProgress));
    }
    $faceitProgressText = 'Level ' . (int)$faceitLevel . ' · ' . number_format($faceitElo) . ' ELO';
}

// Load this player's saved Paint configurations through the same signed Linux API
// boundary used by the WeaponPaints page. Never connect to the plugin database here.
// Build a compact loadout overview instead of resolving/rendering large gun-skin image cards.
// All selection data is read through the signed Linux API boundary.
$profileInventoryTotal = 0;
$profileLoadoutConfigured = [
    'knife' => false,
    'agents' => false,
    'music' => false,
    'pins' => false,
];
$profileInventoryUnavailable = false;

try {
    if ($steamid64 === '') {
        throw new RuntimeException('SteamID64 is not available for the profile loadout.');
    }

    require_once __DIR__ . '/../class/WeaponPaintsRepository.php';
    $profileWeaponPaints = new WeaponPaintsRepository();
    $profileSelection = $profileWeaponPaints->getPlayerSelection($steamid64);

    $profileSavedPaintRows = is_array($profileSelection['skin_inventory'] ?? null)
        ? $profileSelection['skin_inventory']
        : [];
    $profileInventoryTotal = count($profileSavedPaintRows);

    foreach (array_keys($profileLoadoutConfigured) as $profileLoadoutCategory) {
        $profileCategoryItems = $profileSelection[$profileLoadoutCategory] ?? [];
        $profileLoadoutConfigured[$profileLoadoutCategory] =
            is_array($profileCategoryItems) && !empty($profileCategoryItems);
    }
} catch (Throwable $profileInventoryError) {
    // Keep profile, stats, and avatar available even while the Linux API is offline.
    error_log('[FragCity] Profile saved-paint inventory is unavailable (' . get_class($profileInventoryError) . ').');
    $profileInventoryUnavailable = true;
}

if (!function_exists('formatPlaytime')) {
    function formatPlaytime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        if ($hours > 0) return $hours . 'h ' . $minutes . 'm';
        return $minutes . 'm';
    }
}
?>

<div class="profile-fullscreen" data-profile-csrf="<?= htmlspecialchars($profileCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div id="toast" class="toast-message" role="status" aria-live="polite"></div>

    <!-- بنر -->
    <div
        class="banner-section"
        id="bannerSection"
        data-banner-url="<?= htmlspecialchars($bannerUrl ?: 'assets/images/default_banner.jpg', ENT_QUOTES, 'UTF-8') ?>"
    >
        <div class="banner-overlay"></div>
        <button type="button" class="upload-btn banner-upload" id="uploadBannerBtn" aria-label="تغییر بنر">
            <i class="fa-solid fa-camera" aria-hidden="true"></i>
        </button>
        <input
            type="file"
            id="bannerInput"
            class="profile-file-input"
            accept="image/jpeg,image/png,image/webp"
        >
    </div>

    <!-- بخش آواتار و اطلاعات -->
    <div class="profile-hero">
        <div class="avatar-section">
            <div class="avatar-wrapper" id="avatarWrapper">
                <?php if ($avatarUrl): ?>
                    <img
                        src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="Avatar"
                        class="main-avatar"
                        id="currentAvatar"
                    >
                <?php else: ?>
                    <div class="main-avatar-placeholder" id="currentAvatar" aria-label="آواتار پیش‌فرض">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="name-and-xp">
            <div class="profile-identity-strip" aria-label="اطلاعات حساب Steam">
                <h1 class="profile-identity-name"><?= htmlspecialchars($steamPersonaname !== '' ? $steamPersonaname : $username, ENT_QUOTES, 'UTF-8') ?></h1>
                <span class="profile-identity-id" title="SteamID64">
                    <i class="fa-brands fa-steam" aria-hidden="true"></i>
                    <span>SteamID64:</span>
                    <?= htmlspecialchars($steamIdForDisplay, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="profile-steam-chip profile-steam-status" data-state="<?= (int)$steamStateValue ?>">
                    <span class="profile-steam-status__dot" aria-hidden="true"></span>
                    <?= htmlspecialchars($steamStatus, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($steamGame !== ''): ?>
                    <span class="profile-steam-chip profile-steam-game">
                        <i class="fa-solid fa-gamepad" aria-hidden="true"></i>
                        در حال بازی: <?= htmlspecialchars($steamGame, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
                <span class="profile-steam-chip" title="وضعیت Community">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    Community: <?= htmlspecialchars($steamVisibility, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="profile-steam-chip" title="وضعیت تنظیمات پروفایل">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    پروفایل: <?= htmlspecialchars($steamProfileState, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="profile-steam-chip" title="تاریخ ساخت حساب">
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                    ساخت حساب: <?= htmlspecialchars($steamCreatedDate, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="profile-steam-chip" title="کشور">
                    <i class="fa-solid fa-globe" aria-hidden="true"></i>
                    کشور: <?= htmlspecialchars($steamCountry !== '' ? $steamCountry : '—', ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="profile-steam-chip" title="آخرین فعالیت">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    آخرین فعالیت: <?= htmlspecialchars($steamLastLogoffDate, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($steamProfileUrl !== ''): ?>
                    <a class="profile-steam-link" href="<?= htmlspecialchars($steamProfileUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="مشاهده حساب در Steam" title="مشاهده حساب در Steam">
                        <i class="fa-brands fa-steam" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="profile-progress-stack">
                <div class="profile-progress-item">
                    <div class="profile-progress-head">
                        <span class="profile-progress-label"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Rank Plugin XP</span>
                        <span class="profile-progress-value"><?= $hasRankStats ? number_format($points) . ' / ' . number_format($nextThreshold) . ' XP' : 'اطلاعات XP در دسترس نیست' ?></span>
                    </div>
                    <div class="xp-bar-container">
                        <div
                            class="xp-bar xp-bar--rank"
                            data-progress="<?= htmlspecialchars((string) round($xpProgress, 2), ENT_QUOTES, 'UTF-8') ?>"
                            role="progressbar"
                            aria-label="پیشرفت XP پلاگین رنک"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="<?= htmlspecialchars((string) round($xpProgress, 2), ENT_QUOTES, 'UTF-8') ?>"
                        ></div>
                    </div>
                </div>
                <div class="profile-progress-item">
                    <div class="profile-progress-head">
                        <span class="profile-progress-label"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> FACEIT LEVEL / ELO</span>
                        <span class="profile-progress-value"><?= htmlspecialchars($faceitProgressText, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="xp-bar-container">
                        <div
                            class="xp-bar xp-bar--faceit"
                            data-progress="<?= htmlspecialchars((string) round($faceitProgress, 2), ENT_QUOTES, 'UTF-8') ?>"
                            role="progressbar"
                            aria-label="پیشرفت سطح FACEIT"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="<?= htmlspecialchars((string) round($faceitProgress, 2), ENT_QUOTES, 'UTF-8') ?>"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- کارت‌های آمار -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-earth-americas" aria-hidden="true"></i> رتبه جهانی</span>
            <span class="stat-value"><?= $globalPlace > 0 ? '#' . number_format($globalPlace) : '—' ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-trophy" aria-hidden="true"></i> رنک</span>
            <span class="stat-value"><?= htmlspecialchars($hasRankStats ? $currentRank : '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i> K/D</span>
            <span class="stat-value"><?= $hasRankStats ? htmlspecialchars((string)$kd, ENT_QUOTES, 'UTF-8') : '—' ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-skull" aria-hidden="true"></i> کشت</span>
            <span class="stat-value"><?= $hasRankStats ? number_format($kills) : '—' ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-skull-crossbones" aria-hidden="true"></i> مرگ</span>
            <span class="stat-value"><?= $hasRankStats ? number_format($deaths) : '—' ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-handshake" aria-hidden="true"></i> اسیست</span>
            <span class="stat-value"><?= $hasRankStats ? number_format($assists) : '—' ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-bullseye" aria-hidden="true"></i> هدشات</span>
            <span class="stat-value"><?= $hasRankStats ? number_format($headshots) . ' (' . $hsPercent . '%)' : '—' ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-trophy" aria-hidden="true"></i> برد</span>
            <span class="stat-value"><?= $hasRankStats ? number_format($wins) : '—' ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-clock" aria-hidden="true"></i> زمان بازی</span>
            <span class="stat-value"><?= $hasRankStats ? htmlspecialchars(formatPlaytime($playtime), ENT_QUOTES, 'UTF-8') : '—' ?></span>
        </div>
    </div>

    <section class="profile-inventory-card profile-loadout-overview" aria-labelledby="profileInventoryTitle">
        <div class="profile-inventory-card__topline">
            <div class="profile-inventory-card__brand">
                <span class="profile-inventory-card__brand-icon" aria-hidden="true">
                    <i class="fa-solid fa-sliders"></i>
                </span>
                <div class="profile-inventory-card__title-wrap">
                    <span class="profile-inventory-card__eyebrow">PLAYER LOADOUT</span>
                    <h2 id="profileInventoryTitle">نمای کلی لوداوت</h2>
                    <p>وضعیت آیتم‌هایی که برای حساب شما ذخیره شده‌اند</p>
                </div>
            </div>
            <div class="profile-inventory-card__actions">
                <span class="profile-inventory-card__count">
                    <i class="fa-solid fa-crosshairs" aria-hidden="true"></i>
                    <?= number_format($profileInventoryTotal) ?> اسکین گان
                </span>
                <a class="profile-inventory-card__view-all" href="?page=skins" data-ajax-nav data-slug="skins">
                    مدیریت اسکین‌ها
                    <i class="fa-solid fa-arrow-up-left-from-circle" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div class="profile-inventory-card__body">
            <?php if ($profileInventoryUnavailable): ?>
                <div class="profile-inventory-state profile-inventory-state--error" role="status">
                    <span class="profile-inventory-state__icon"><i class="fa-solid fa-plug-circle-xmark" aria-hidden="true"></i></span>
                    <div>
                        <strong>اطلاعات لوداوت موقتاً در دسترس نیست</strong>
                        <p>بقیهٔ پروفایل همچنان در دسترس است؛ کمی بعد دوباره امتحان کنید.</p>
                    </div>
                    <a href="?page=skins" data-ajax-nav data-slug="skins">باز کردن مدیریت اسکین‌ها <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                </div>
            <?php else: ?>
                <div class="profile-loadout-summary-grid" aria-label="خلاصه آیتم‌های ذخیره‌شده">
                    <article class="profile-loadout-summary profile-loadout-summary--guns">
                        <span class="profile-loadout-summary__icon" aria-hidden="true"><i class="fa-solid fa-crosshairs"></i></span>
                        <span class="profile-loadout-summary__label">اسکین گان</span>
                        <strong class="profile-loadout-summary__value"><?= number_format($profileInventoryTotal) ?></strong>
                        <span class="profile-loadout-summary__meta">آیتم ذخیره‌شده</span>
                    </article>

                    <article class="profile-loadout-summary" data-configured="<?= $profileLoadoutConfigured['knife'] ? 'true' : 'false' ?>">
                        <span class="profile-loadout-summary__icon" aria-hidden="true"><i class="fa-solid fa-khanda"></i></span>
                        <span class="profile-loadout-summary__label">چاقو</span>
                        <strong class="profile-loadout-summary__value"><?= $profileLoadoutConfigured['knife'] ? 'تنظیم شده' : 'تنظیم نشده' ?></strong>
                        <span class="profile-loadout-summary__meta"><?= $profileLoadoutConfigured['knife'] ? 'لوداوت آماده است' : 'هنوز انتخابی ثبت نشده' ?></span>
                    </article>

                    <article class="profile-loadout-summary" data-configured="<?= $profileLoadoutConfigured['agents'] ? 'true' : 'false' ?>">
                        <span class="profile-loadout-summary__icon" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                        <span class="profile-loadout-summary__label">ایجنت‌ها</span>
                        <strong class="profile-loadout-summary__value"><?= $profileLoadoutConfigured['agents'] ? 'تنظیم شده' : 'تنظیم نشده' ?></strong>
                        <span class="profile-loadout-summary__meta"><?= $profileLoadoutConfigured['agents'] ? 'انتخاب ذخیره شده' : 'هنوز انتخابی ثبت نشده' ?></span>
                    </article>

                    <article class="profile-loadout-summary" data-configured="<?= $profileLoadoutConfigured['music'] ? 'true' : 'false' ?>">
                        <span class="profile-loadout-summary__icon" aria-hidden="true"><i class="fa-solid fa-music"></i></span>
                        <span class="profile-loadout-summary__label">موزیک‌کیت</span>
                        <strong class="profile-loadout-summary__value"><?= $profileLoadoutConfigured['music'] ? 'تنظیم شده' : 'تنظیم نشده' ?></strong>
                        <span class="profile-loadout-summary__meta"><?= $profileLoadoutConfigured['music'] ? 'انتخاب ذخیره شده' : 'هنوز انتخابی ثبت نشده' ?></span>
                    </article>

                    <article class="profile-loadout-summary" data-configured="<?= $profileLoadoutConfigured['pins'] ? 'true' : 'false' ?>">
                        <span class="profile-loadout-summary__icon" aria-hidden="true"><i class="fa-solid fa-medal"></i></span>
                        <span class="profile-loadout-summary__label">پین‌ها</span>
                        <strong class="profile-loadout-summary__value"><?= $profileLoadoutConfigured['pins'] ? 'تنظیم شده' : 'تنظیم نشده' ?></strong>
                        <span class="profile-loadout-summary__meta"><?= $profileLoadoutConfigured['pins'] ? 'انتخاب ذخیره شده' : 'هنوز انتخابی ثبت نشده' ?></span>
                    </article>
                </div>
                <div class="profile-inventory-card__footer">
                    <span><i class="fa-solid fa-circle-info" aria-hidden="true"></i> برای افزودن یا تغییر آیتم‌ها وارد مدیریت اسکین‌ها شوید.</span>
                    <a href="?page=skins" data-ajax-nav data-slug="skins">مدیریت لوداوت <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- مودال برش آواتار -->
    <div id="avatarModal" class="avatar-modal" aria-hidden="true">
        <div class="avatar-modal-content" role="dialog" aria-modal="true" aria-labelledby="avatarModalTitle">
            <button type="button" class="close-modal" id="closeAvatarModalBtn" aria-label="بستن">&times;</button>
            <h3 id="avatarModalTitle">برش عکس پروفایل</h3>
            <div class="img-container">
                <img id="cropImage" src="" alt="تصویر انتخاب شده">
            </div>
            <div class="crop-buttons">
                <button type="button" class="btn-crop" id="cropConfirmBtn">ذخیره</button>
                <button type="button" class="btn-cancel" id="cropCancelBtn">انصراف</button>
            </div>
        </div>
    </div>
</div>