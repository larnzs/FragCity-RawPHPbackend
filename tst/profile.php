<?php
// pages/profile.php - نسخه جداشده از frontend با پشتیبانی از هر دو فرمت SteamID
if (!isset($_SESSION['user_logged_in'])) {
    header('Location: index.php?page=login');
    exit;
}

$steamid = $_SESSION['steamid'] ?? '';
$username = $_SESSION['username'] ?? $steamid;

// ========== اتصال به دیتابیس رنکینگ ==========
require_once __DIR__ . '/../database/kyonxcbt_ranks_plugin.php';
$ranksDb = new RemoteRanksDatabase();

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// توابع تبدیل فرمت‌های SteamID
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
if (!function_exists('steamid64_to_steam2')) {
    function steamid64_to_steam2($communityId) {
        $universe = 1;
        $accountId = $communityId - 76561197960265728;
        $authserver = $accountId % 2;
        $authid = ($accountId - $authserver) / 2;
        return "STEAM_{$universe}:{$authserver}:{$authid}";
    }
}

if (!function_exists('steam2_to_steamid64')) {
    function steam2_to_steamid64($steam2) {
        if (!preg_match('/^STEAM_(\d+):(\d+):(\d+)$/', $steam2, $matches)) {
            return null;
        }
        $universe = (int)$matches[1];
        $authserver = (int)$matches[2];
        $authid = (int)$matches[3];
        return ($authid * 2) + $authserver + 76561197960265728;
    }
}

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// تشخیص فرمت‌ها و آماده‌سازی برای کوئری
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
$steamid64 = null;
$steam2 = null;

if (preg_match('/^STEAM_/', $steamid)) {
    $steam2 = $steamid;
    $steamid64 = steam2_to_steamid64($steamid);
} elseif (is_numeric($steamid)) {
    $steamid64 = (string)$steamid;
    $steam2 = steamid64_to_steam2((int)$steamid);
} else {
    $steamid64 = $steamid;
    $steam2 = $steamid;
}

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// کوئری با پشتیبانی از هر دو فرمت
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
$playerStats = null;
$sql = "SELECT steam, name, value AS points, kills, deaths, assists, headshots, round_win AS wins, playtime
        FROM lvl_base WHERE steam = :steam LIMIT 1";

$result = $ranksDb->select($sql, [':steam' => $steamid64]);
if (!empty($result)) {
    $playerStats = $result;
} else {
    $result = $ranksDb->select($sql, [':steam' => $steam2]);
    if (!empty($result)) {
        $playerStats = $result;
    }
}

$stats = $playerStats[0] ?? null;

$points = $stats['points'] ?? 0;
$kills = $stats['kills'] ?? 0;
$deaths = $stats['deaths'] ?? 0;
$assists = $stats['assists'] ?? 0;
$headshots = $stats['headshots'] ?? 0;
$wins = $stats['wins'] ?? 0;
$playtime = $stats['playtime'] ?? 0;
$kd = ($deaths > 0) ? round($kills / $deaths, 2) : $kills;
$hsPercent = ($kills > 0) ? round(($headshots / $kills) * 100) : 0;

// ========== رنک و XP ==========
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

if ($points >= 4500) {
    $xpProgress = 100;
} else {
    $xpProgress = ($points - $currentThreshold) / ($nextThreshold - $currentThreshold) * 100;
}

// رتبه جهانی
$sqlPlace = "SELECT COUNT(*) + 1 as place FROM lvl_base WHERE value > :points";
$placeResult = $ranksDb->select($sqlPlace, [':points' => $points]);
$globalPlace = $placeResult[0]['place'] ?? 0;

// ========== مسیرهای ذخیره‌سازی ==========
$bannersDir = __DIR__ . '/../img/banners/';
$avatarsDir = __DIR__ . '/../img/avatars/';
if (!is_dir($bannersDir)) mkdir($bannersDir, 0755, true);
if (!is_dir($avatarsDir)) mkdir($avatarsDir, 0755, true);

$bannerFile = $bannersDir . 'banner_' . $steamid . '.jpg';
$avatarFile = $avatarsDir . 'avatar_' . $steamid . '.jpg';
$bannerUrl = file_exists($bannerFile) ? 'img/banners/banner_' . $steamid . '.jpg?' . filemtime($bannerFile) : '';
$avatarUrl = file_exists($avatarFile) ? 'img/avatars/avatar_' . $steamid . '.jpg?' . filemtime($avatarFile) : '';

// آواتار پیش‌فرض Steam
$defaultAvatar = '';
if (defined('STEAM_API_KEY') && !empty(STEAM_API_KEY) && !empty($steamid64)) {
    $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key=" . STEAM_API_KEY . "&steamids=" . $steamid64;
    $json = @file_get_contents($apiUrl);
    if ($json) {
        $data = json_decode($json, true);
        $player = $data['response']['players'][0] ?? null;
        if ($player && !empty($player['avatarfull'])) {
            $defaultAvatar = $player['avatarfull'];
        }
    }
}

if (empty($avatarUrl) && !empty($defaultAvatar)) {
    $avatarUrl = $defaultAvatar;
}

// مقدار موقت برای Faceit Level (فعلاً 0)
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

<div class="profile-fullscreen">
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
            <div class="rank-badge"><?= htmlspecialchars($currentRank, ENT_QUOTES, 'UTF-8') ?></div>
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
                    <?= number_format($points) ?> XP / <?= number_format($nextThreshold) ?> XP
                </span>
            </div>
        </div>
    </div>

    <!-- کارت‌های آمار -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-earth-americas" aria-hidden="true"></i> رتبه جهانی</span>
            <span class="stat-value">#<?= number_format($globalPlace) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-trophy" aria-hidden="true"></i> رنک</span>
            <span class="stat-value"><?= htmlspecialchars($currentRank, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i> K/D</span>
            <span class="stat-value"><?= $kd ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-skull" aria-hidden="true"></i> کشت</span>
            <span class="stat-value"><?= number_format($kills) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-skull-crossbones" aria-hidden="true"></i> مرگ</span>
            <span class="stat-value"><?= number_format($deaths) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-handshake" aria-hidden="true"></i> اسیست</span>
            <span class="stat-value"><?= number_format($assists) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-bullseye" aria-hidden="true"></i> هدشات</span>
            <span class="stat-value"><?= number_format($headshots) ?> (<?= $hsPercent ?>%)</span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-trophy" aria-hidden="true"></i> برد</span>
            <span class="stat-value"><?= number_format($wins) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><i class="fa-solid fa-clock" aria-hidden="true"></i> زمان بازی</span>
            <span class="stat-value"><?= formatPlaytime($playtime) ?></span>
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
