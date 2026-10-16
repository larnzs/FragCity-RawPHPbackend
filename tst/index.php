<?php
require_once __DIR__ . '/../settings/loader.php';
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================================================
 | Database
 ========================================================== */
$tstSettings=fragcity_settings('files.tst.index',array());
$tstProfile=isset($tstSettings['database_profile'])?$tstSettings['database_profile']:'main';
$pdo=fragcity_create_pdo($tstProfile);

/* =========================================================
 | Helpers
 ========================================================== */
if (!function_exists('steamid64_to_steam')) {
    function steamid64_to_steam($steamid64): string
    {
        $steamid64 = (string) $steamid64;

        if (!preg_match('/^[0-9]{17}$/', $steamid64)) {
            return '';
        }

        $accountId = (int) $steamid64 - 76561197960265728;
        $y = intdiv($accountId, 2);
        $v = $accountId % 2;

        return "STEAM_1:{$v}:{$y}";
    }
}

if (!function_exists('asset_version')) {
    function asset_version(string $absolutePath): string
    {
        return is_file($absolutePath)
            ? (string) filemtime($absolutePath)
            : (string) time();
    }
}

if (!function_exists('safe_local_redirect')) {
    function safe_local_redirect(?string $target, string $fallback = 'index.php'): string
    {
        $target = trim((string) $target);

        if ($target === '') {
            return $fallback;
        }

        // Only accept local paths. Never redirect to an external host.
        if (
            preg_match('#^https?://#i', $target) ||
            str_starts_with($target, '//') ||
            preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)
        ) {
            return $fallback;
        }

        if (!str_starts_with($target, '/')) {
            return $target;
        }

        // Convert absolute same-site paths to a relative path for Location headers.
        return ltrim($target, '/');
    }
}

if (!function_exists('redirect_back')) {
    function redirect_back(string $fallback = 'index.php'): never
    {
        $target = safe_local_redirect(
            $_SERVER['REQUEST_URI'] ?? '',
            $fallback
        );

        header('Location: ' . $target, true, 303);
        exit;
    }
}

/* =========================================================
 | Authentication: Login / Register
 ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_action'])) {
    $authAction = (string) $_POST['auth_action'];

    /* ------------------------- Login ------------------------- */
    if ($authAction === 'login') {
        $loginId = trim((string) ($_POST['login_id'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$pdo) {
            $_SESSION['auth_error'] = 'خطا در اتصال به دیتابیس.';
            redirect_back();
        }

        if (preg_match('/^[0-9]{17}$/', $loginId)) {
            $field = 'steamid64';
        } elseif (preg_match('/^09[0-9]{9}$/', $loginId)) {
            $field = 'phone';
        } else {
            $field = 'username';
        }

        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE {$field} = ? LIMIT 1"
        );
        $stmt->execute([$loginId]);
        $user = $stmt->fetch();

        if (
            $user &&
            password_verify(
                $password,
                (string) ($user['password_hash'] ?? '')
            )
        ) {
            session_regenerate_id(true);

            $_SESSION['user_logged_in'] = true;
            $_SESSION['steamid'] = (string) ($user['steamid64'] ?? '');
            $_SESSION['username'] = !empty($user['username'])
                ? (string) $user['username']
                : (string) ($user['steamid64'] ?? '');
            $_SESSION['role'] = !empty($user['role'])
                ? (string) $user['role']
                : 'user';

            $redirect = safe_local_redirect(
                $_SESSION['login_return_url'] ?? '',
                'index.php'
            );

            unset($_SESSION['login_return_url']);

            header('Location: ' . $redirect, true, 303);
            exit;
        }

        $_SESSION['auth_error'] = 'اطلاعات وارد شده صحیح نیست.';
        redirect_back();
    }

    /* ------------------------- Register ------------------------- */
    if ($authAction === 'register') {
        $isSteamRegister =
            isset($_POST['steam_register']) &&
            (string) $_POST['steam_register'] === '1';

        $steamid = $isSteamRegister
            ? (string) ($_SESSION['steam_temp']['steamid64'] ?? '')
            : trim((string) ($_POST['steamid64'] ?? ''));

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = $isSteamRegister
            ? ''
            : (string) ($_POST['password'] ?? '');
        $passwordConfirm = $isSteamRegister
            ? ''
            : (string) ($_POST['password_confirm'] ?? '');

        $hasError = false;

        if (!preg_match('/^[0-9]{17}$/', $steamid)) {
            $_SESSION['auth_error'] = 'SteamID باید ۱۷ رقم باشد.';
            $hasError = true;
        } elseif (!preg_match('/^09[0-9]{9}$/', $phone)) {
            $_SESSION['auth_error'] = 'شماره موبایل معتبر نیست.';
            $hasError = true;
        } elseif (!$isSteamRegister && $password !== $passwordConfirm) {
            $_SESSION['auth_error'] = 'رمز عبور و تکرار آن مطابقت ندارند.';
            $hasError = true;
        } elseif (!$isSteamRegister && strlen($password) < 6) {
            $_SESSION['auth_error'] = 'رمز عبور حداقل باید ۶ کاراکتر باشد.';
            $hasError = true;
        } elseif (!$pdo) {
            $_SESSION['auth_error'] = 'خطا در اتصال به دیتابیس.';
            $hasError = true;
        }

        if (!$hasError) {
            $stmt = $pdo->prepare(
                'SELECT id FROM users WHERE steamid64 = ? LIMIT 1'
            );
            $stmt->execute([$steamid]);

            if ($stmt->fetch()) {
                $_SESSION['auth_error'] = 'این SteamID قبلاً ثبت‌نام کرده است.';
                $hasError = true;
            }
        }

        if (!$hasError) {
            $hash = $isSteamRegister
                ? password_hash(
                    bin2hex(random_bytes(16)),
                    PASSWORD_BCRYPT
                )
                : password_hash(
                    $password,
                    PASSWORD_BCRYPT
                );

            $steam = steamid64_to_steam($steamid);

            $stmt = $pdo->prepare(
                'INSERT INTO users
                    (steamid64, steam, username, email, phone, password_hash)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $steamid,
                $steam,
                $username !== '' ? $username : null,
                $email !== '' ? $email : null,
                $phone,
                $hash,
            ]);

            session_regenerate_id(true);

            $_SESSION['user_logged_in'] = true;
            $_SESSION['steamid'] = $steamid;
            $_SESSION['username'] = $username !== ''
                ? $username
                : $steamid;
            $_SESSION['role'] = 'user';

            unset($_SESSION['steam_temp']);
            unset($_SESSION['login_return_url']);

            header('Location: index.php', true, 303);
            exit;
        }

        redirect_back();
    }
}

/* =========================================================
 | Logout
 ========================================================== */
if (isset($_GET['logout'])) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: index.php', true, 303);
    exit;
}

/* =========================================================
 | Router
 ========================================================== */
$allowedPages = [
    'servers',
    'skins',
    'buyserver',
    'buyserveropen',
    'launcher',
    'usermanual',
    'about',
    'support',
    'login',
    'register',
    'profile',
    'adminprofile',
    'vip',
    'leaderboard',
    'forgot_password',
    'reset_password',
    'servers_vps',
    'skins_vps',
    'leaderboard_vps',
    'rules',
    'privacy',
    'deadlock_servers_vps',
    'banlist',
    'wallet',
    'donations',
];

$requestedPage = trim(
    (string) ($_GET['page'] ?? 'home')
);

$page = in_array(
    $requestedPage,
    $allowedPages,
    true
)
    ? $requestedPage
    : 'home';

$current_page = $page;

$isLoggedIn =
    isset($_SESSION['user_logged_in']) &&
    $_SESSION['user_logged_in'] === true;

/* Pages that require authentication. */
$protectedPages = [
    'skins',
    'skins_vps',
    'adminprofile',
    'profile',
];

if (
    in_array($page, $protectedPages, true) &&
    !$isLoggedIn
) {
    $_SESSION['login_return_url'] =
        $_SERVER['REQUEST_URI'] ?? 'index.php';

    header(
        'Location: index.php?page=login',
        true,
        303
    );
    exit;
}

$displayName = (string) (
    $_SESSION['username'] ?? ''
);

$displaySteamID = (string) (
    $_SESSION['steamid'] ?? ''
);

/* =========================================================
 | Shared Assets
 ========================================================== */
$stylePath = __DIR__ . '/css/style.css';
$headerStylePath = __DIR__ . '/css/header.css';
$headerScriptPath = __DIR__ . '/js/header.js';
$globalScriptPath = __DIR__ . '/js/script.js';
$legacyGlobalScriptPath = __DIR__ . '/assets/js/script.js';

$styleVersion = asset_version($stylePath);
$headerStyleVersion = asset_version($headerStylePath);
$headerScriptVersion = asset_version($headerScriptPath);

/*
 * Page-specific assets.
 * Paths are root-relative so AJAX navigation and nested pages
 * never accidentally resolve them to /pages/... or another folder.
 */
$pageAssets = [

    'skins' => [
        'css' => [
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css',
            '/css/weaponpaints.css?v=' . asset_version(
                __DIR__ . '/css/weaponpaints.css'
            ),
        ],
        'js' => [
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            '/js/weaponpaints.js?v=' . asset_version(
                __DIR__ . '/js/weaponpaints.js'
            ),
            '/js/weaponpaints-fix.js?v=' . asset_version(
                __DIR__ . '/js/weaponpaints-fix.js'
            ),
        ],
    ],

    'profile' => [
        'css' => [
            '/css/profile.css?v=' . asset_version(
                __DIR__ . '/css/profile.css'
            ),
            'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css',
        ],
        'js' => [
            'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js',
            '/js/profile.js?v=' . asset_version(
                __DIR__ . '/js/profile.js'
            ),
        ],
    ],

    'wallet' => [
        'css' => [
            '/css/wallet.css?v=' . asset_version(
                __DIR__ . '/css/wallet.css'
            ),
        ],
        'js' => [
            '/js/wallet.js?v=' . asset_version(
                __DIR__ . '/js/wallet.js'
            ),
        ],
    ],

    'login' => [
        'css' => [
            '/css/auth.css?v=' . asset_version(
                __DIR__ . '/css/auth.css'
            ),
        ],
        'js' => [
            '/js/auth.js?v=' . asset_version(
                __DIR__ . '/js/auth.js'
            ),
        ],
    ],

    'register' => [
        'css' => [
            '/css/auth.css?v=' . asset_version(
                __DIR__ . '/css/auth.css'
            ),
        ],
        'js' => [
            '/js/auth.js?v=' . asset_version(
                __DIR__ . '/js/auth.js'
            ),
        ],
    ],

    'forgot_password' => [
        'css' => [
            '/css/auth.css?v=' . asset_version(
                __DIR__ . '/css/auth.css'
            ),
        ],
        'js' => [
            '/js/auth.js?v=' . asset_version(
                __DIR__ . '/js/auth.js'
            ),
        ],
    ],

    'reset_password' => [
        'css' => [
            '/css/auth.css?v=' . asset_version(
                __DIR__ . '/css/auth.css'
            ),
        ],
        'js' => [
            '/js/auth.js?v=' . asset_version(
                __DIR__ . '/js/auth.js'
            ),
        ],
    ],

    'buyserveropen' => [
        'css' => [
            '/css/buyserveropen.css?v=' . asset_version(
                __DIR__ . '/css/buyserveropen.css'
            ),
        ],
        'js' => [
            '/js/buyserveropen.js?v=' . asset_version(
                __DIR__ . '/js/buyserveropen.js'
            ),
        ],
    ],

    'banlist' => [
        'css' => [
            '/css/banlist.css?v=' . asset_version(
                __DIR__ . '/css/banlist.css'
            ),
        ],
        'js' => [
            '/js/banlist.js?v=' . asset_version(
                __DIR__ . '/js/banlist.js'
            ),
        ],
    ],

    'leaderboard' => [
        'css' => [
            '/css/leaderboard.css?v=' . asset_version(
                __DIR__ . '/css/leaderboard.css'
            ),
        ],
        'js' => [
            '/js/leaderboard.js?v=' . asset_version(
                __DIR__ . '/js/leaderboard.js'
            ),
        ],
    ],
];

$pageAssetsJson = json_encode(
    $pageAssets,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
);

$currentPageAssets =
    $pageAssets[$page]
    ?? [
        'css' => [],
        'js' => [],
    ];

$pageFile = $page === 'skins'
    ? __DIR__ . '/pages/weaponpaints.php'
    : __DIR__ . '/pages/' . $page . '.php';

/* =========================================================
 | HTML
 ========================================================== */
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <meta
        name="color-scheme"
        content="dark"
    >

    <title>FragCity | سرور CS2</title>

    <!-- Font Awesome: CDN only -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    >

    <!-- Global site styles -->
    <link
        rel="stylesheet"
        href="/css/style.css?v=<?= htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8') ?>"
    >

    <link
        rel="stylesheet"
        href="/css/header.css?v=<?= htmlspecialchars($headerStyleVersion, ENT_QUOTES, 'UTF-8') ?>"
    >

    <!-- bootstrap -->
    <link
        rel="stylesheet"
        href="/css/bootstrap/bootstrap.min.css"
    >

    <?php foreach ($currentPageAssets['css'] as $pageCss): ?>
        <link
            rel="stylesheet"
            href="<?= htmlspecialchars($pageCss, ENT_QUOTES, 'UTF-8') ?>"
            data-page-style
        >
    <?php endforeach; ?>
</head>

<body class="site-body">

<?php
try {
    require_once __DIR__ . '/includes/header.php';
} catch (\Throwable $e) {
    // Never let a header bug blank the whole page - log it and keep going.
    error_log('[FragCity] header.php failed on page=' . $page . ': ' . $e->getMessage());
}
?>

<div class="app-container">

    <main
        class="main-content"
        id="mainContent"
    >
        <div
            class="content-fader"
            id="contentFader"
        >

            <?php
            if (is_file($pageFile)) {
                if (!defined('FRAGCITY_INDEX_RENDER')) {
                    define('FRAGCITY_INDEX_RENDER', true);
                }

                try {
                    include $pageFile;
                } catch (\Throwable $e) {
                    // Log the real cause and show a friendly fallback
                    // instead of leaving the visitor with a blank white page.
                    error_log(
                        '[FragCity] pages/' . $page . '.php failed: ' .
                        $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')'
                    );
                    ?>
                    <section
                        class="empty-state"
                        aria-labelledby="pageErrorTitle"
                    >
                        <div
                            class="empty-graphic"
                            aria-hidden="true"
                        >
                            <div class="stack-box"></div>
                            <div class="stack-box"></div>
                            <div class="stack-box">
                                <span class="mini-dot"></span>
                                <span class="mini-line"></span>
                            </div>
                        </div>

                        <h1
                            class="empty-title"
                            id="pageErrorTitle"
                        >
                            مشکلی در بارگذاری این بخش پیش آمد
                        </h1>

                        <p class="empty-desc">
                            لطفاً چند لحظه دیگر دوباره تلاش کنید. اگر مشکل
                            ادامه داشت، به تیم پشتیبانی اطلاع دهید.
                        </p>
                    </section>
                    <?php
                }
            } else {
                ?>

                <section
                    class="empty-state"
                    aria-labelledby="missingPageTitle"
                >
                    <div
                        class="empty-graphic"
                        aria-hidden="true"
                    >
                        <div class="stack-box"></div>
                        <div class="stack-box"></div>
                        <div class="stack-box">
                            <span class="mini-dot"></span>
                            <span class="mini-line"></span>
                        </div>
                    </div>

                    <h1
                        class="empty-title"
                        id="missingPageTitle"
                    >
                        این بخش هنوز آماده نشده است
                    </h1>

                    <p class="empty-desc">
                        فایل
                        <code>
                            pages/<?= htmlspecialchars(
                                $page,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>.php
                        </code>
                        پیدا نشد.
                    </p>
                </section>

                <?php
            }
            ?>

        </div>
    </main>

    <?php
    $footerFile = __DIR__ . '/includes/footer.php';

    if (is_file($footerFile)) {
        try {
            include $footerFile;
        } catch (\Throwable $e) {
            error_log('[FragCity] footer.php failed on page=' . $page . ': ' . $e->getMessage());
        }
    }
    ?>

</div>

<script>
window.FragCityPageAssets = <?= $pageAssetsJson ?: '{}' ?>;
window.FragCityCurrentPage = <?= json_encode(
    $page,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
) ?>;
</script>

<?php if (is_file($globalScriptPath)): ?>
    <script
        src="/js/script.js?v=<?= asset_version($globalScriptPath) ?>"
    ></script>
<?php elseif (is_file($legacyGlobalScriptPath)): ?>
    <script
        src="/assets/js/script.js?v=<?= asset_version($legacyGlobalScriptPath) ?>"
    ></script>
<?php endif; ?>

<script
    src="/js/header.js?v=<?= htmlspecialchars(
        $headerScriptVersion,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
></script>

<?php foreach ($currentPageAssets['js'] as $pageJs): ?>
    <script
        src="<?= htmlspecialchars($pageJs, ENT_QUOTES, 'UTF-8') ?>"
        data-page-script
        data-page-script-src="<?= htmlspecialchars($pageJs, ENT_QUOTES, 'UTF-8') ?>"
    ></script>
<?php endforeach; ?>

</body>
</html>