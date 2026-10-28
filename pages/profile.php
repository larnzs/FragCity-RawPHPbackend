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
if ($avatarUrl === '') $avatarUrl = (string)($steamProfile['avatarfull'] ?? '');
$faceitLevel = 0;

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

    <section class="steam-profile-card" aria-label="اطلاعات پروفایل Steam">
        <div class="steam-profile-card__heading">
            <div class="steam-profile-card__identity">
                <?php if ($steamProfileUrl !== ''): ?>
                    <a class="steam-profile-card__name" href="<?= htmlspecialchars($steamProfileUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($steamPersonaname, ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                    <span class="steam-profile-card__name"><?= htmlspecialchars($steamPersonaname, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="steam-profile-card__eyebrow"><i class="fa-brands fa-steam" aria-hidden="true"></i> Steam Community</span>
            </div>
            <span class="steam-profile-card__status" data-state="<?= (int)($steamProfile['personastate'] ?? 0) ?>">
                <span class="steam-profile-card__status-dot" aria-hidden="true"></span><?= htmlspecialchars($steamStatus, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <div class="steam-profile-card__details">
            <div class="steam-profile-card__detail"><span>SteamID64</span><strong dir="ltr"><?= htmlspecialchars($steamid64 !== '' ? $steamid64 : '—', ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="steam-profile-card__detail"><span>وضعیت پروفایل</span><strong><?= htmlspecialchars($steamVisibility, ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="steam-profile-card__detail"><span>تاریخ ساخت حساب</span><strong><?= htmlspecialchars($steamCreatedDate, ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="steam-profile-card__detail"><span>کد کشور</span><strong dir="ltr"><?= htmlspecialchars($steamCountry !== '' ? $steamCountry : '—', ENT_QUOTES, 'UTF-8') ?></strong></div>
        </div>
        <?php if (!$steamProfile): ?><p class="steam-profile-card__note">اطلاعات کامل Steam فعلاً در دسترس نیست. در صورت وجود، آواتار محلی یا اطلاعات ذخیره‌شده در نشست استفاده می‌شود.</p><?php endif; ?>
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