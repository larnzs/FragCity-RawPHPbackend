<?php
require_once __DIR__ . '/../settings/loader.php';
// pages/vip.php
$steamid64 = $_SESSION['steamid'] ?? '';
$isLoggedIn = isset($_SESSION['user_logged_in']);

$userVip = ['vip_level' => 0, 'vip_expire' => null];
$userXp = 0;

if ($isLoggedIn) {
    try {
        // دریافت وضعیت VIP از دیتابیس سایت
        $vipSettings=fragcity_settings('files.pages.vip',array());
        $vipProfile=isset($vipSettings['database_profile'])?$vipSettings['database_profile']:'main';
        $pdo=fragcity_create_pdo($vipProfile);
        if(!$pdo)throw new PDOException('Database unavailable or not configured.');
        $stmt = $pdo->prepare("SELECT vip_level, vip_expire FROM users WHERE steamid64 = ?");
        $stmt->execute([$steamid64]);
        $userVip = $stmt->fetch(PDO::FETCH_ASSOC) ?: $userVip;

        // دریافت XP از دیتابیس ranks_plugin روی VPS
        require_once __DIR__ . '/../database/kyonxcbt_ranks_plugin.php';
        $db = new RemoteRanksDatabase();

        // تبدیل SteamID64 به فرمت STEAM_1:... (مطابق ساختار lvl_base)
        function steamid64_to_steamid($steamid64) {
            $accountId = $steamid64 - 76561197960265728;
            $y = floor($accountId / 2);
            $v = $accountId % 2;
            return "STEAM_1:{$v}:{$y}";
        }
        $steamFormatted = steamid64_to_steamid($steamid64);

        // کوئری با bind parameter
        $sql = "SELECT value FROM lvl_base WHERE steam = :steam LIMIT 1";
        $bindings = [':steam' => $steamFormatted];
        $xpResult = $db->select($sql, $bindings);
        $userXp = isset($xpResult[0]['value']) ? (int)$xpResult[0]['value'] : 0;

    } catch (Exception $e) {
        $userXp = 0;
    }
}

$vipNames = [0 => 'ندارد', 1 => 'برنزی', 2 => 'نقره‌ای', 3 => 'طلایی', 4 => 'پلاتینیوم', 5 => 'امرالد', 6 => 'دایموند'];
$vipIcons = [0=>'',1=>'medal',2=>'medal',3=>'crown',4=>'gem',5=>'leaf',6=>'gem'];
$vipActive = ($userVip && $userVip['vip_expire'] && new DateTime($userVip['vip_expire']) > new DateTime());

$maxXp = 5000; // بیشترین XP قابل انتظار
$xpPercent = $maxXp > 0 ? min(100, round(($userXp / $maxXp) * 100)) : 0;

$vipLevels = [
    1 => ['name' => 'برنزی', 'fa_icon' => 'fas fa-medal', 'color' => '#cd7f32', 'gradient' => 'linear-gradient(135deg, #cd7f32, #a0522d)'],
    2 => ['name' => 'نقره‌ای', 'fa_icon' => 'fas fa-medal', 'color' => '#c0c0c0', 'gradient' => 'linear-gradient(135deg, #c0c0c0, #808080)'],
    3 => ['name' => 'طلایی', 'fa_icon' => 'fas fa-crown', 'color' => '#F5B042', 'gradient' => 'linear-gradient(135deg, #F5B042, #E68A2E)'],
    4 => ['name' => 'پلاتینیوم', 'fa_icon' => 'fas fa-gem', 'color' => '#3498db', 'gradient' => 'linear-gradient(135deg, #3498db, #2980b9)'],
    5 => ['name' => 'امرالد', 'fa_icon' => 'fas fa-leaf', 'color' => '#2ecc71', 'gradient' => 'linear-gradient(135deg, #2ecc71, #27ae60)'],
    6 => ['name' => 'دایموند', 'fa_icon' => 'fas fa-gem', 'color' => '#9b59b6', 'gradient' => 'linear-gradient(135deg, #9b59b6, #8e44ad)']
];

$vipFeaturesData = [
    1 => [
        ['icon' => 'fas fa-shield', 'title' => 'زره اضافه', 'desc' => '۵۰ واحد زره'],
        ['icon' => 'fas fa-heart', 'title' => 'سلامتی', 'desc' => '۱۱۰ واحد'],
        ['icon' => 'fas fa-coins', 'title' => 'پول شروع', 'desc' => '۱۰۰۰ دلار'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ', 'desc' => 'VIP : BRONZE']
    ],
    2 => [
        ['icon' => 'fas fa-shield', 'title' => 'زره کامل', 'desc' => '۱۰۰ واحد'],
        ['icon' => 'fas fa-heart', 'title' => 'سلامتی', 'desc' => '۱۲۰ واحد'],
        ['icon' => 'fas fa-coins', 'title' => 'پول شروع', 'desc' => '۱۵۰۰ دلار'],
        ['icon' => 'fas fa-bomb', 'title' => 'دیفیوزر', 'desc' => 'رایگان'],
        ['icon' => 'fas fa-rabbit', 'title' => 'Bunny Hop', 'desc' => 'پرش متوالی'],
        ['icon' => 'fas fa-bolt', 'title' => 'ریلود سریع', 'desc' => 'سرعت ریلود'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ', 'desc' => 'VIP : SILVER']
    ],
    3 => [
        ['icon' => 'fas fa-shield', 'title' => 'زره کامل', 'desc' => '۱۰۰ واحد'],
        ['icon' => 'fas fa-heart', 'title' => 'سلامتی', 'desc' => '۱۳۰ واحد'],
        ['icon' => 'fas fa-coins', 'title' => 'پول شروع', 'desc' => '۲۰۰۰ دلار'],
        ['icon' => 'fas fa-bomb', 'title' => 'دیفیوزر', 'desc' => 'رایگان'],
        ['icon' => 'fas fa-rabbit', 'title' => 'Bunny Hop', 'desc' => 'فعال'],
        ['icon' => 'fas fa-feather', 'title' => 'بدون آسیب سقوط', 'desc' => 'فعال'],
        ['icon' => 'fas fa-bolt', 'title' => 'ریلود سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'دیفیوز سریع', 'desc' => 'سرعت دیفیوز'],
        ['icon' => 'fas fa-globe', 'title' => 'جاذبه کمتر', 'desc' => '۰.۹۵'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ', 'desc' => 'VIP : GOLD']
    ],
    4 => [
        ['icon' => 'fas fa-shield', 'title' => 'زره', 'desc' => '۱۵۰ واحد'],
        ['icon' => 'fas fa-heart', 'title' => 'سلامتی', 'desc' => '۱۵۰ واحد'],
        ['icon' => 'fas fa-coins', 'title' => 'پول شروع', 'desc' => '۳۰۰۰ دلار'],
        ['icon' => 'fas fa-bomb', 'title' => 'دیفیوزر', 'desc' => 'رایگان'],
        ['icon' => 'fas fa-rabbit', 'title' => 'Bunny Hop', 'desc' => 'فعال'],
        ['icon' => 'fas fa-feather', 'title' => 'بدون آسیب سقوط', 'desc' => 'فعال'],
        ['icon' => 'fas fa-bolt', 'title' => 'ریلود سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'دیفیوز سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-bomb', 'title' => 'بمب‌گذاری سریع', 'desc' => 'سرعت نصب'],
        ['icon' => 'fas fa-globe', 'title' => 'جاذبه', 'desc' => '۰.۹'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'بازیابی سلامتی', 'desc' => '۲ HP/s'],
        ['icon' => 'fas fa-shield-virus', 'title' => 'بازیابی زره', 'desc' => '۲ AP/s'],
        ['icon' => 'fas fa-lightbulb', 'title' => 'مقاومت فلش', 'desc' => '۳۰٪'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ', 'desc' => 'VIP : PLATINUM']
    ],
    5 => [
        ['icon' => 'fas fa-shield', 'title' => 'زره', 'desc' => '۲۰۰ واحد'],
        ['icon' => 'fas fa-heart', 'title' => 'سلامتی', 'desc' => '۱۸۰ واحد'],
        ['icon' => 'fas fa-coins', 'title' => 'پول شروع', 'desc' => '۵۰۰۰ دلار'],
        ['icon' => 'fas fa-bomb', 'title' => 'دیفیوزر', 'desc' => 'رایگان'],
        ['icon' => 'fas fa-rabbit', 'title' => 'Bunny Hop', 'desc' => 'فعال'],
        ['icon' => 'fas fa-feather', 'title' => 'بدون آسیب سقوط', 'desc' => 'فعال'],
        ['icon' => 'fas fa-bolt', 'title' => 'ریلود سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'دیفیوز سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-bomb', 'title' => 'بمب‌گذاری سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-globe', 'title' => 'جاذبه', 'desc' => '۰.۸۵'],
        ['icon' => 'fas fa-infinity', 'title' => 'مهمات نامحدود', 'desc' => 'فعال'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'بازیابی سلامتی', 'desc' => '۴ HP/s'],
        ['icon' => 'fas fa-shield-virus', 'title' => 'بازیابی زره', 'desc' => '۴ AP/s'],
        ['icon' => 'fas fa-droplet', 'title' => 'خون‌آشامی', 'desc' => '۱۵٪'],
        ['icon' => 'fas fa-lightbulb', 'title' => 'مقاومت فلش', 'desc' => '۵۰٪'],
        ['icon' => 'fas fa-gun', 'title' => 'منوی اسلحه', 'desc' => 'اختصاصی'],
        ['icon' => 'fas fa-bolt-lightning', 'title' => 'شوکر نامحدود', 'desc' => 'Zeus'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ', 'desc' => 'VIP : EMERALD']
    ],
    6 => [
        ['icon' => 'fas fa-shield', 'title' => 'زره', 'desc' => '۳۰۰ واحد'],
        ['icon' => 'fas fa-heart', 'title' => 'سلامتی', 'desc' => '۳۰۰ واحد'],
        ['icon' => 'fas fa-coins', 'title' => 'پول شروع', 'desc' => '۲۰,۰۰۰ دلار'],
        ['icon' => 'fas fa-bomb', 'title' => 'دیفیوزر', 'desc' => 'رایگان'],
        ['icon' => 'fas fa-rabbit', 'title' => 'Bunny Hop', 'desc' => 'فعال'],
        ['icon' => 'fas fa-feather', 'title' => 'بدون آسیب سقوط', 'desc' => 'فعال'],
        ['icon' => 'fas fa-bolt', 'title' => 'ریلود سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-stopwatch', 'title' => 'دیفیوز سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-bomb', 'title' => 'بمب‌گذاری سریع', 'desc' => 'فعال'],
        ['icon' => 'fas fa-globe', 'title' => 'جاذبه', 'desc' => '۰.۷۵'],
        ['icon' => 'fas fa-infinity', 'title' => 'مهمات نامحدود', 'desc' => 'فعال'],
        ['icon' => 'fas fa-heart-pulse', 'title' => 'بازیابی سلامتی', 'desc' => '۱۵ HP/s'],
        ['icon' => 'fas fa-shield-virus', 'title' => 'بازیابی زره', 'desc' => '۱۵ AP/s'],
        ['icon' => 'fas fa-droplet', 'title' => 'خون‌آشامی', 'desc' => '۴۰٪'],
        ['icon' => 'fas fa-lightbulb', 'title' => 'مقاومت کامل فلش', 'desc' => '۱۰۰٪'],
        ['icon' => 'fas fa-gun', 'title' => 'منوی اسلحه', 'desc' => 'اختصاصی'],
        ['icon' => 'fas fa-bolt-lightning', 'title' => 'شوکر نامحدود', 'desc' => 'Zeus'],
        ['icon' => 'fas fa-rotate', 'title' => 'ریسپاون', 'desc' => 'بعد از مرگ'],
        ['icon' => 'fas fa-burst', 'title' => 'افزایش آسیب', 'desc' => '۱.۳۵x'],
        ['icon' => 'fas fa-rainbow', 'title' => 'مدل رنگین‌کمان', 'desc' => 'بازیکن'],
        ['icon' => 'fas fa-smog', 'title' => 'دود رنگین‌کمان', 'desc' => 'Smoke'],
        ['icon' => 'fas fa-desktop', 'title' => 'صفحه کشتن', 'desc' => 'Kill Screen'],
        ['icon' => 'fas fa-moon', 'title' => 'دید در شب', 'desc' => 'Night Vision'],
        ['icon' => 'fas fa-handshake', 'title' => 'درمان هم‌تیمی', 'desc' => '۱۰ HP'],
        ['icon' => 'fas fa-tag', 'title' => 'تگ', 'desc' => 'VIP : DIAMOND']
    ]
];
?>

<link rel="stylesheet" href="css/pages-base.css">
<link rel="stylesheet" href="css/vip.css">
<div class="vip-page">
    <!-- نوار XP -->
    <div class="vip-xp-card">
        <div class="vip-xp-header">
            <h3 class="vip-xp-title"><i class="fas fa-star"></i> تجربه شما (XP)</h3>
            <button type="button" id="syncVipButton" class="vip-sync-btn">
                <i class="fas fa-sync-alt"></i> بروزرسانی وضعیت VIP
            </button>
        </div>
        <div class="vip-xp-meta">
            <span><?= number_format($userXp) ?> XP</span>
            <span>حداکثر XP: <?= number_format($maxXp) ?></span>
        </div>
        <div class="vip-xp-bar">
            <div class="vip-xp-fill" data-xp-percent="<?= (int)$xpPercent ?>"></div>
        </div>
        <div class="vip-xp-footer">
            <span><i class="fas fa-medal"></i> وضعیت فعلی: <strong class="vip-current-level"><?= $isLoggedIn ? $vipNames[$userVip['vip_level']??0] : 'وارد شوید' ?></strong></span>
            <span id="syncResult" class="vip-sync-result"></span>
        </div>
    </div>

    <!-- تب‌ها و ویژگی‌ها -->
    <div class="info-section vip-levels-card">
        <div class="vip-levels-list">
            <?php foreach ($vipLevels as $lvl => $data): ?>
                <button type="button" class="vip-level-tab <?= ($lvl === 1) ? 'selected' : '' ?>" data-level="<?= $lvl ?>" data-color="<?= htmlspecialchars($data['color'], ENT_QUOTES, 'UTF-8') ?>" data-gradient="<?= htmlspecialchars($data['gradient'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="level-badge"><i class="<?= $data['fa_icon'] ?>"></i></span>
                    <span class="level-name"><?= $data['name'] ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div id="featuresContainer" class="features-container">
            <?php foreach ($vipLevels as $lvl => $data): ?>
                <div class="feature-panel <?= ($lvl === 1) ? 'is-active' : '' ?>" id="panel-level-<?= $lvl ?>" data-level="<?= $lvl ?>" data-color="<?= htmlspecialchars($data['color'], ENT_QUOTES, 'UTF-8') ?>" <?= ($lvl === 1) ? '' : 'hidden' ?>>
                    <h3 class="feature-panel-title"><i class="<?= $data['fa_icon'] ?>"></i> <?= $data['name'] ?></h3>
                    <p class="feature-panel-subtitle">ویژگی‌های این سطح:</p>
                    <div class="feature-grid">
                        <?php foreach ($vipFeaturesData[$lvl] as $f): ?>
                            <div class="feature-card" data-color="<?= htmlspecialchars($data['color'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="feature-icon"><i class="<?= $f['icon'] ?>"></i></div>
                                <div class="feature-text"><span class="feature-title"><?= $f['title'] ?></span><br><?= $f['desc'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script src="js/vip.js" defer></script>
