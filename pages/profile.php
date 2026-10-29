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
$steamProfileUrl = (string)($steamProfile['profileurl'] ?? '');
$steamStatusLabels = [0 => 'آفلاین', 1 => 'آنلاین', 2 => 'مشغول', 3 => 'غایب', 4 => 'خواب', 5 => 'مایل به معامله', 6 => 'مایل به بازی'];
$steamStatus = isset($steamProfile['personastate'])
    ? ($steamStatusLabels[(int)$steamProfile['personastate']] ?? 'نامشخص')
    : 'در دسترس نیست';
$visibility = (int)($steamProfile['communityvisibilitystate'] ?? 0);
$steamVisibility = $visibility === 3 ? 'عمومی' : ($visibility === 1 ? 'خصوصی' : 'نامشخص');
$steamCountry = (string)($steamProfile['loccountrycode'] ?? '');
$steamCreatedAt = (int)($steamProfile['timecreated'] ?? 0);
$steamCreatedDate = $steamCreatedAt > 0 ? date('Y-m-d', $steamCreatedAt) : '—';

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
$faceitLevel = 0;

// Load this player's saved Paint configurations through the same signed Linux API
// boundary used by the WeaponPaints page. Never connect to the plugin database here.
$profileInventoryItems = [];
$profileInventoryTotal = 0;
$profileInventoryUnavailable = false;
try {
    require_once __DIR__ . '/../class/WeaponPaintsRepository.php';
    require_once __DIR__ . '/../class/utils.php';

    $profileWeaponPaints = new WeaponPaintsRepository();
    $profileSelection = $profileWeaponPaints->getPlayerSelection($steamid64);
    $profileSavedPaintRows = is_array($profileSelection['skin_inventory'] ?? null)
        ? $profileSelection['skin_inventory']
        : [];
    $profileInventoryTotal = count($profileSavedPaintRows);

    $profileSkinCatalog = UtilsClass::skinsFromJson();
    $profileWeaponCatalog = UtilsClass::getWeaponsFromArray();

    foreach (array_slice($profileSavedPaintRows, 0, 10) as $profileSavedPaint) {
        if (!is_array($profileSavedPaint)) {
            continue;
        }

        $profileDefindex = (int)($profileSavedPaint['weapon_defindex'] ?? 0);
        $profilePaintId = (int)($profileSavedPaint['weapon_paint_id'] ?? 0);
        if ($profileDefindex < 1 || $profilePaintId < 0) {
            continue;
        }

        $profileWeaponEntry = $profileWeaponCatalog[$profileDefindex]
            ?? $profileWeaponCatalog[(string)$profileDefindex]
            ?? [];
        $profilePaintEntry = $profileSkinCatalog[$profileDefindex][$profilePaintId]
            ?? $profileSkinCatalog[(string)$profileDefindex][(string)$profilePaintId]
            ?? [];

        $profileWeaponRawName = (string)($profileWeaponEntry['weapon_name'] ?? ('weapon_' . $profileDefindex));
        $profileWeaponLabel = preg_replace('/^weapon_/', '', $profileWeaponRawName) ?: $profileWeaponRawName;
        $profileWeaponLabel = str_replace('_', ' ', $profileWeaponLabel);
        $profilePaintName = trim((string)($profilePaintEntry['paint_name'] ?? ''));
        if ($profilePaintName === '') {
            $profilePaintName = 'Paint #' . $profilePaintId;
        }

        $profileImageUrl = trim((string)($profilePaintEntry['image_url'] ?? ''));
        if ($profileImageUrl !== ''
            && !preg_match('#^(?:https?://|/|[A-Za-z0-9_.-])#i', $profileImageUrl)) {
            $profileImageUrl = '';
        }

        $profileTeam = (int)($profileSavedPaint['weapon_team'] ?? 0);
        $profileTeamLabel = $profileTeam === 2 ? 'T' : ($profileTeam === 3 ? 'CT' : 'ANY');

        $profileStickerCount = 0;
        for ($profileStickerSlot = 0; $profileStickerSlot < 5; $profileStickerSlot++) {
            $profileStickerValue = (string)($profileSavedPaint['weapon_sticker_' . $profileStickerSlot] ?? '0;0;0;0;0.001;1;0');
            $profileStickerParts = explode(';', $profileStickerValue, 2);
            if ((int)($profileStickerParts[0] ?? 0) > 0) {
                $profileStickerCount++;
            }
        }

        $profileInventoryItems[] = [
            'weapon_name' => $profileWeaponLabel,
            'paint_name' => $profilePaintName,
            'image_url' => $profileImageUrl,
            'team' => $profileTeamLabel,
            'sticker_count' => $profileStickerCount,
        ];
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
                <button type="button" class="avatar-edit-btn" id="editAvatarBtn" aria-label="تغییر آواتار">
                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                </button>
            </div>
            <div class="rank-badge"><?= htmlspecialchars($hasRankStats ? $currentRank : 'بدون رنک', ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="name-and-xp">
            <h1><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="steam-id">SteamID: <?= htmlspecialchars($steamid, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="xp-bar-container">
                <div
                    class="xp-bar"
                    data-progress="<?= htmlspecialchars((string) round($xpProgress, 2), ENT_QUOTES, 'UTF-8') ?>"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?= htmlspecialchars((string) round($xpProgress, 2), ENT_QUOTES, 'UTF-8') ?>"
                ></div>
                <span class="xp-text">
                    <?= $hasRankStats ? number_format($points) . ' XP / ' . number_format($nextThreshold) . ' XP' : 'اطلاعات XP در دسترس نیست' ?>
                </span>
            </div>
        </div>
    </div>

    <section class="profile-inventory-card" aria-labelledby="profileInventoryTitle">
        <div class="profile-inventory-card__topline">
            <div class="profile-inventory-card__brand">
                <span class="profile-inventory-card__brand-icon" aria-hidden="true">
                    <i class="fa-solid fa-layer-group"></i>
                </span>
                <div class="profile-inventory-card__title-wrap">
                    <span class="profile-inventory-card__eyebrow">PLAYER LOADOUT</span>
                    <h2 id="profileInventoryTitle">اسکین‌های ذخیره‌شده شما</h2>
                    <p>پیش‌نمایش Paintهایی که در Inventory خود ذخیره کرده‌اید</p>
                </div>
            </div>
            <div class="profile-inventory-card__actions">
                <span class="profile-inventory-card__count">
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                    <?= number_format($profileInventoryTotal) ?> آیتم
                </span>
                <a class="profile-inventory-card__view-all" href="?page=skins" data-ajax-nav data-slug="skins">
                    مدیریت Inventory
                    <i class="fa-solid fa-arrow-up-left-from-circle" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div class="profile-inventory-card__body">
            <?php if ($profileInventoryUnavailable): ?>
                <div class="profile-inventory-state profile-inventory-state--error" role="status">
                    <span class="profile-inventory-state__icon"><i class="fa-solid fa-plug-circle-xmark" aria-hidden="true"></i></span>
                    <div>
                        <strong>Inventory موقتاً در دسترس نیست</strong>
                        <p>بقیهٔ پروفایل در دسترس است؛ سرویس اسکین‌ها را بعداً دوباره امتحان کنید.</p>
                    </div>
                </div>
            <?php elseif ($profileInventoryItems): ?>
                <div class="profile-loadout-grid">
                    <?php foreach ($profileInventoryItems as $profileItem): ?>
                        <article class="profile-loadout-item">
                            <div class="profile-loadout-item__media">
                                <?php if ($profileItem['image_url'] !== ''): ?>
                                    <img
                                        src="<?= htmlspecialchars($profileItem['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        onerror="this.onerror=null;this.hidden=true;this.nextElementSibling.hidden=false;"
                                    >
                                <?php endif; ?>
                                <span class="profile-loadout-item__fallback" <?= $profileItem['image_url'] !== '' ? 'hidden' : '' ?> aria-hidden="true">
                                    <i class="fa-solid fa-gun"></i>
                                </span>
                                <span class="profile-loadout-item__team" data-team="<?= htmlspecialchars($profileItem['team'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($profileItem['team'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="profile-loadout-item__copy">
                                <span class="profile-loadout-item__kicker">SAVED PAINT</span>
                                <strong title="<?= htmlspecialchars($profileItem['paint_name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($profileItem['paint_name'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <span class="profile-loadout-item__weapon">
                                    <?= htmlspecialchars($profileItem['weapon_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <?php if ((int)$profileItem['sticker_count'] > 0): ?>
                                <span class="profile-loadout-item__stickers">
                                    <i class="fa-solid fa-certificate" aria-hidden="true"></i>
                                    <?= (int)$profileItem['sticker_count'] ?> استیکر
                                </span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="profile-inventory-card__footer">
                    <span><i class="fa-solid fa-sparkles" aria-hidden="true"></i> نمایش <?= count($profileInventoryItems) ?> مورد از <?= number_format($profileInventoryTotal) ?> Paint ذخیره‌شده</span>
                    <a href="?page=skins" data-ajax-nav data-slug="skins">دیدن همهٔ اسکین‌ها <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                </div>
            <?php else: ?>
                <div class="profile-inventory-state">
                    <span class="profile-inventory-state__icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                    <div>
                        <strong>هنوز Paint ذخیره‌شده‌ای نداری</strong>
                        <p>از بخش Inventory اسکین‌ها را انتخاب کن و تنظیماتشان را ذخیره کن تا اینجا نمایش داده شوند.</p>
                    </div>
                    <a href="?page=skins" data-ajax-nav data-slug="skins">رفتن به Inventory <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </section>

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
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-gamepad" aria-hidden="true"></i> FACEIT Level</span>
            <span class="stat-value"><?= $faceitLevel ?></span>
        </div>
    </div>
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