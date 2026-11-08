<?php
require_once __DIR__ . '/settings/session.php';
require_once __DIR__ . '/includes/routes.php';
ob_start();
fragcity_start_session();

if (
    !isset($_SESSION['auth_csrf_token']) ||
    !is_string($_SESSION['auth_csrf_token']) ||
    !preg_match('/^[a-f0-9]{64}$/D', $_SESSION['auth_csrf_token'])
) {
    $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));
}
$authCsrfToken = $_SESSION['auth_csrf_token'];

/* =========================================================
 | Database
 ========================================================== */
$indexSettings=fragcity_settings('files.index',array());
$indexProfile=isset($indexSettings['database_profile'])?$indexSettings['database_profile']:'main';
$pdo=fragcity_create_pdo($indexProfile);

/* =========================================================
 | Explicit Remember-Me Restoration
 ========================================================== */
require_once __DIR__ . '/class/RememberMeService.php';
require_once __DIR__ . '/class/PaymenterAccountSyncService.php';

$activeSteamId = (
    isset($_SESSION['user_logged_in']) &&
    $_SESSION['user_logged_in'] === true
)
    ? (string) ($_SESSION['steamid'] ?? '')
    : null;

$rememberedUser = $pdo instanceof PDO
    ? RememberMeService::restore($pdo, $activeSteamId)
    : null;

if ($activeSteamId === null && is_array($rememberedUser)) {
    session_regenerate_id(true);

    $_SESSION['user_logged_in'] = true;
    $_SESSION['steamid'] = (string) ($rememberedUser['steamid64'] ?? '');
    $_SESSION['username'] = !empty($rememberedUser['username'])
        ? (string) $rememberedUser['username']
        : (string) ($rememberedUser['steamid64'] ?? '');
    $_SESSION['role'] = !empty($rememberedUser['role'])
        ? (string) $rememberedUser['role']
        : 'user';
    $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));
    $authCsrfToken = $_SESSION['auth_csrf_token'];
}

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

        // A missing or unsafe target must never fall back to a relative URL.
        if (
            $target === '' ||
            preg_match('#^https?://#i', $target) ||
            str_starts_with($target, '//') ||
            preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)
        ) {
            $target = trim($fallback);
        }

        // Fallbacks are normalized and validated by the same rules.
        if (
            $target === '' ||
            preg_match('#^https?://#i', $target) ||
            str_starts_with($target, '//') ||
            preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)
        ) {
            $target = 'index.php';
        }

        // Always anchor local redirects at the site root. Returning "profile/"
        // from /profile/ would resolve to /profile/profile/ in the browser.
        return '/' . ltrim($target, '/');
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
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrfToken) || !hash_equals($authCsrfToken, $submittedCsrfToken)) {
        $_SESSION['auth_error'] = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید و دوباره تلاش کنید.';
        redirect_back();
    }

    $authAction = (string) $_POST['auth_action'];

    /* ------------------------- Logout ------------------------- */
    if ($authAction === 'logout') {
        if ($pdo instanceof PDO) {
            RememberMeService::revokeCurrent($pdo);
        } else {
            RememberMeService::clearCookie();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        header('Location: index.php', true, 303);
        exit;
    }

    /* ------------------------- Login ------------------------- */
    if ($authAction === 'login') {
        $loginId = trim((string) ($_POST['login_id'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$pdo) {
            $_SESSION['auth_error'] = 'خطا در اتصال به دیتابیس.';
            redirect_back();
        }

        if (preg_match('/^[0-9]{17}$/D', $loginId)) {
            $field = 'steamid64';
        } elseif (preg_match('/^09[0-9]{9}$/D', $loginId)) {
            $field = 'phone';
        } elseif (filter_var($loginId, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } else {
            $field = 'username';
            $loginId = strtolower($loginId);
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM users WHERE {$field} = ? LIMIT 1"
            );
            $stmt->execute([$loginId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            error_log('[FragCity] Login query failed (SQLSTATE ' . $e->getCode() . ').');
            $_SESSION['auth_error'] = 'در حال حاضر امکان ورود وجود ندارد؛ اتصال دیتابیس را بررسی کنید.';
            redirect_back();
        }

        if (
            $user &&
            password_verify(
                $password,
                (string) ($user['password_hash'] ?? '')
            )
        ) {
            session_regenerate_id(true);
            $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));

            $_SESSION['user_logged_in'] = true;
            $_SESSION['steamid'] = (string) ($user['steamid64'] ?? '');
            $_SESSION['username'] = !empty($user['username'])
                ? (string) $user['username']
                : (string) ($user['steamid64'] ?? '');
            $_SESSION['role'] = !empty($user['role'])
                ? (string) $user['role']
                : 'user';

            RememberMeService::apply(
                $pdo,
                (string) ($user['steamid64'] ?? ''),
                (string) ($_POST['remember_me'] ?? '') === '1'
            );

            $emailReady = filter_var(trim((string) ($user['email'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
            $phoneReady = preg_match('/^09[0-9]{9}$/D', trim((string) ($user['phone'] ?? ''))) === 1;
            $firstNameReady = trim((string) ($user['first_name'] ?? '')) !== '';
            $lastNameReady = trim((string) ($user['last_name'] ?? '')) !== '';

            if (!$emailReady || !$phoneReady || !$firstNameReady || !$lastNameReady) {
                $_SESSION['auth_error'] = 'برای فعال‌سازی حساب سرور، نام، نام خانوادگی، ایمیل و شماره موبایل را تکمیل کنید.';
                header('Location: index.php?page=register&complete=1', true, 303);
                exit;
            }

            // FragCity is the identity source of truth. The password was just
            // verified locally, so it can be synchronized through the private
            // signed backend channel without being stored in the session.
            $sync = PaymenterAccountSyncService::sync($pdo, $user, $password);
            if (!$sync['success']) {
                $_SESSION['auth_error'] = 'ورود انجام شد، اما همگام‌سازی حساب سرور کامل نشد. اطلاعات و رمز مشترک را دوباره تأیید کنید.';
                header('Location: index.php?page=register&complete=1', true, 303);
                exit;
            }

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

    /* ------------------------- Register / Account Completion ------------------------- */
    if ($authAction === 'register') {
        $isAccountCompletion =
            isset($_POST['account_completion']) &&
            (string) $_POST['account_completion'] === '1';

        $isSteamRegister =
            !$isAccountCompletion &&
            isset($_POST['steam_register']) &&
            (string) $_POST['steam_register'] === '1';

        // Never trust a user-supplied SteamID64 as proof of Steam ownership.
        // New accounts must arrive through a validated Steam OpenID callback.
        if (!$isAccountCompletion && !$isSteamRegister) {
            $_SESSION['auth_error'] = 'برای امنیت حساب، ثبت‌نام جدید باید ابتدا با Steam تأیید شود.';
            header('Location: index.php?page=register', true, 303);
            exit;
        }

        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        $steamid = $isAccountCompletion
            ? (string) ($_SESSION['steamid'] ?? '')
            : ($isSteamRegister
                ? (string) ($_SESSION['steam_temp']['steamid64'] ?? '')
                : trim((string) ($_POST['steamid64'] ?? '')));

        $hasError = false;

        if ($isAccountCompletion && empty($_SESSION['user_logged_in'])) {
            $_SESSION['auth_error'] = 'برای تکمیل حساب ابتدا وارد شوید.';
            header('Location: index.php?page=login', true, 303);
            exit;
        }

        if (!preg_match('/^[0-9]{17}$/D', $steamid)) {
            $_SESSION['auth_error'] = 'SteamID باید ۱۷ رقم باشد.';
            $hasError = true;
        } elseif (!preg_match('/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/D', $username)) {
            $_SESSION['auth_error'] = 'نام کاربری باید ۳ تا ۵۰ کاراکتر، با حروف انگلیسی یا عدد در ابتدا و انتها و فقط شامل حروف انگلیسی، عدد، خط زیر (_) و خط تیره (-) باشد.';
            $hasError = true;
        } elseif ($firstName === '' || mb_strlen($firstName) > 191) {
            $_SESSION['auth_error'] = 'نام کوچک الزامی است و نباید بیشتر از ۱۹۱ کاراکتر باشد.';
            $hasError = true;
        } elseif ($lastName === '' || mb_strlen($lastName) > 191) {
            $_SESSION['auth_error'] = 'نام خانوادگی الزامی است و نباید بیشتر از ۱۹۱ کاراکتر باشد.';
            $hasError = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $_SESSION['auth_error'] = 'ایمیل معتبر و قابل استفاده وارد کنید.';
            $hasError = true;
        } elseif (!preg_match('/^09[0-9]{9}$/D', $phone)) {
            $_SESSION['auth_error'] = 'شماره موبایل معتبر نیست.';
            $hasError = true;
        } elseif ($password !== $passwordConfirm) {
            $_SESSION['auth_error'] = 'رمز عبور و تکرار آن مطابقت ندارند.';
            $hasError = true;
        } elseif (!PaymenterAccountSyncService::isStrongPassword($password)) {
            $_SESSION['auth_error'] = 'رمز عبور باید حداقل ۸ کاراکتر و شامل حروف و عدد باشد.';
            $hasError = true;
        } elseif (!($pdo instanceof PDO)) {
            $_SESSION['auth_error'] = 'خطا در اتصال به دیتابیس.';
            $hasError = true;
        }

        if (!$hasError) {
            try {
                // Existing rows may contain NULL/duplicate legacy values, so
                // check collisions before adding any database-wide email index.
                $emailCheck = $pdo->prepare(
                    'SELECT id FROM users WHERE email = ? AND steamid64 <> ? LIMIT 1'
                );
                $emailCheck->execute([$email, $steamid]);
                if ($emailCheck->fetch()) {
                    $_SESSION['auth_error'] = 'این ایمیل قبلاً برای حساب دیگری در FragCity ثبت شده است.';
                    $hasError = true;
                }

                if (!$hasError) {
                    $usernameCheck = $pdo->prepare(
                        'SELECT id FROM users WHERE LOWER(username) = ? AND steamid64 <> ? LIMIT 1'
                    );
                    $usernameCheck->execute([$username, $steamid]);
                    if ($usernameCheck->fetch()) {
                        $_SESSION['auth_error'] = 'این نام کاربری قبلاً استفاده شده است؛ نام دیگری انتخاب کنید.';
                        $hasError = true;
                    }
                }

                if (!$hasError) {
                    $phoneCheck = $pdo->prepare(
                        'SELECT id FROM users WHERE phone = ? AND steamid64 <> ? LIMIT 1'
                    );
                    $phoneCheck->execute([$phone, $steamid]);
                    if ($phoneCheck->fetch()) {
                        $_SESSION['auth_error'] = 'این شماره موبایل قبلاً برای حساب دیگری ثبت شده است.';
                        $hasError = true;
                    }
                }
            } catch (PDOException $e) {
                error_log('[FragCity] Registration identity lookup failed (SQLSTATE ' . $e->getCode() . ').');
                $_SESSION['auth_error'] = 'در حال حاضر امکان بررسی اطلاعات حساب وجود ندارد.';
                $hasError = true;
            }
        }

        if ($isAccountCompletion && !$hasError) {
            try {
                $update = $pdo->prepare(
                    'UPDATE users
                     SET username = ?, first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ?
                     WHERE steamid64 = ?
                     LIMIT 1'
                );
                $update->execute([
                    $username,
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    password_hash($password, PASSWORD_BCRYPT),
                    $steamid,
                ]);

                $select = $pdo->prepare('SELECT * FROM users WHERE steamid64 = ? LIMIT 1');
                $select->execute([$steamid]);
                $currentUser = $select->fetch(PDO::FETCH_ASSOC);

                if (!$currentUser) {
                    throw new RuntimeException('updated_fragcity_user_not_found');
                }

                $sync = PaymenterAccountSyncService::sync($pdo, $currentUser, $password);
                if (!$sync['success']) {
                    $safeSyncError = (string) ($sync['error'] ?? 'paymenter_sync_failed');
                    $_SESSION['auth_error'] = match ($safeSyncError) {
                        'username_required' => 'نام کاربری واردشده معتبر نیست؛ فقط از حروف انگلیسی، عدد، _ و - استفاده کنید.',
                        'username_already_in_use' => 'این نام کاربری در Paymenter برای حساب دیگری رزرو شده است؛ نام دیگری انتخاب کنید.',
                        'email_already_in_use' => 'این ایمیل در Paymenter قبلاً ثبت شده است. برای اتصال باید رمز همان حساب را وارد کنید یا از پشتیبانی کمک بگیرید.',
                        'email_already_linked_to_another_paymenter_user',
                        'paymenter_user_already_linked_to_another_steam_account' => 'این ایمیل یا حساب Paymenter به یک حساب Steam دیگر متصل است؛ برای جلوگیری از ادغام اشتباه حساب‌ها با پشتیبانی تماس بگیرید.',
                        'paymenter_unreachable' => 'ارتباط با سرویس حساب سرور موقتاً برقرار نشد. دوباره تلاش کنید.',
                        default => 'اطلاعات FragCity ذخیره شد، اما اتصال حساب Paymenter کامل نشد. دوباره تلاش کنید.',
                    };
                    redirect_back('index.php?page=register&complete=1');
                }

                $_SESSION['username'] = $username;
                unset($_SESSION['auth_error'], $_SESSION['steam_temp']);

                header('Location: index.php', true, 303);
                exit;
            } catch (PDOException $e) {
                error_log('[FragCity] Account completion update failed (SQLSTATE ' . $e->getCode() . ').');
                $_SESSION['auth_error'] = 'ذخیره اطلاعات حساب انجام نشد؛ دوباره تلاش کنید.';
                redirect_back('index.php?page=register&complete=1');
            } catch (Throwable $e) {
                error_log('[FragCity] Account completion failed (' . get_class($e) . ').');
                $_SESSION['auth_error'] = 'تکمیل حساب انجام نشد؛ دوباره تلاش کنید.';
                redirect_back('index.php?page=register&complete=1');
            }
        }

        if (!$isAccountCompletion && !$hasError) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT id FROM users WHERE steamid64 = ? LIMIT 1'
                );
                $stmt->execute([$steamid]);
                $existingUser = $stmt->fetch();

                if ($existingUser) {
                    $_SESSION['auth_error'] = 'این SteamID قبلاً ثبت‌نام کرده است؛ وارد شوید تا حساب موجود را تکمیل کنید.';
                    $hasError = true;
                }
            } catch (PDOException $e) {
                error_log('[FragCity] Registration lookup failed (SQLSTATE ' . $e->getCode() . ').');
                $_SESSION['auth_error'] = 'در حال حاضر امکان ثبت‌نام وجود ندارد؛ اتصال دیتابیس را بررسی کنید.';
                $hasError = true;
            }
        }

        if (!$isAccountCompletion && !$hasError) {
            $steam = steamid64_to_steam($steamid);

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users
                        (steamid64, steam, username, first_name, last_name, email, phone, password_hash, paymenter_sync_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                );

                $stmt->execute([
                    $steamid,
                    $steam,
                    $username,
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    password_hash($password, PASSWORD_BCRYPT),
                ]);
            } catch (PDOException $e) {
                error_log('[FragCity] Registration insert failed (SQLSTATE ' . $e->getCode() . ').');
                $_SESSION['auth_error'] = 'ثبت‌نام انجام نشد؛ اتصال دیتابیس یا تکراری بودن اطلاعات را بررسی کنید.';
                redirect_back();
            }

            // Persist the normalized Steam public summary when this signup came
            // through OpenID. A profile API failure must not block registration.
            if ($isSteamRegister) {
                try {
                    require_once __DIR__ . '/class/SteamProfileService.php';
                    $profileForStorage = $_SESSION['steam_profile_cache'][$steamid]['player']
                        ?? ($_SESSION['steam_profile'] ?? []);

                    if (
                        !is_array($profileForStorage)
                        || (string) ($profileForStorage['steamid'] ?? '') !== $steamid
                    ) {
                        $profileForStorage = [];
                    }

                    if ($profileForStorage !== []) {
                        SteamProfileService::persistPlayerSummary($pdo, $steamid, $profileForStorage);
                    }
                } catch (Throwable $profileStoreError) {
                    error_log('[FragCity] Steam profile persistence after registration failed (' . get_class($profileStoreError) . ').');
                }
            }

            session_regenerate_id(true);
            $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['user_logged_in'] = true;
            $_SESSION['steamid'] = $steamid;
            $_SESSION['username'] = $username !== '' ? $username : $steamid;
            $_SESSION['role'] = 'user';

            if ($isSteamRegister) {
                RememberMeService::apply(
                    $pdo,
                    $steamid,
                    !empty($_SESSION['remember_login_requested'])
                );
            }

            $select = $pdo->prepare('SELECT * FROM users WHERE steamid64 = ? LIMIT 1');
            $select->execute([$steamid]);
            $newUser = $select->fetch(PDO::FETCH_ASSOC);

            if (!$newUser) {
                $_SESSION['auth_error'] = 'حساب FragCity ساخته شد، اما خواندن اطلاعات حساب ممکن نشد.';
                header('Location: index.php?page=register&complete=1', true, 303);
                exit;
            }

            $sync = PaymenterAccountSyncService::sync($pdo, $newUser, $password);
            if (!$sync['success']) {
                $_SESSION['auth_error'] = 'حساب FragCity ساخته شد، اما همگام‌سازی Paymenter کامل نشد. اطلاعات را دوباره تأیید کنید.';
                header('Location: index.php?page=register&complete=1', true, 303);
                exit;
            }

            unset($_SESSION['remember_login_requested']);
            unset($_SESSION['steam_temp']);
            unset($_SESSION['login_return_url']);

            header('Location: index.php', true, 303);
            exit;
        }

        if ($isAccountCompletion) {
            redirect_back('index.php?page=register&complete=1');
        }

        redirect_back();
    }
}

/* =========================================================
 | Router
 ========================================================== */
$allowedPages = [
    'servers',
    'skins',
    'buyserver',
    'buyserveropen',
    'minecraft',
    'launcher',
    'usermanual',
    'serverguide',
    'status',
    'about',
    'support',
    'login',
    'register',
    'profile',
    'myservers',
    'orders',
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
    'donations',
];

$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$isHomeRequest = $requestPath === '/' || $requestPath === '/index.php';

/*
 * Resolve canonical clean URLs in PHP as a fallback as well as in .htaccess.
 * Some shared-hosting rewrite configurations forward the request to index.php
 * but do not preserve the internal page query parameter. Without this fallback
 * every clean child route is incorrectly treated as the 404 page.
 */
$cleanRoutePages = [
    'servers' => 'servers_vps',
    'skins' => 'skins',
    'buy-server' => 'buyserveropen',
    'minecraft-hosting' => 'minecraft',
    'launcher' => 'launcher',
    'server-manual' => 'usermanual',
    'serverguide' => 'serverguide',
    'status' => 'status',
    'about' => 'about',
    'support' => 'support',
    'login' => 'login',
    'register' => 'register',
    'profile' => 'profile',
    'my-servers' => 'myservers',
    'orders' => 'orders',
    'vip' => 'vip',
    'leaderboard' => 'leaderboard',
    'forgot-password' => 'forgot_password',
    'reset-password' => 'reset_password',
    'rules' => 'rules',
    'privacy' => 'privacy',
    'deadlock-servers' => 'deadlock_servers_vps',
    'banlist' => 'banlist',
    'donations' => 'donations',
];
$cleanRouteSlug = trim($requestPath, '/');
$cleanRoutePage = $cleanRoutePages[$cleanRouteSlug] ?? null;

$requestedPageValue = $_GET['page'] ?? ($cleanRoutePage ?? 'home');
$requestedPage = is_string($requestedPageValue)
    ? trim($requestedPageValue)
    : '__invalid__';

// The standalone wallet route has been retired; normalize old bookmarks.
if ($requestedPage === 'wallet') {
    header('Location: /', true, 301);
    exit;
}

$hasExplicitPage = array_key_exists('page', $_GET);
$isKnownPage = $requestedPage === 'home' || in_array($requestedPage, $allowedPages, true);
$isValidPageRequest = $isKnownPage && (
    $isHomeRequest ||
    $hasExplicitPage ||
    $cleanRoutePage !== null
);

$page = $isValidPageRequest ? $requestedPage : '404';
if (!$isValidPageRequest) {
    http_response_code(404);
}

$current_page = $page;

$isLoggedIn =
    isset($_SESSION['user_logged_in']) &&
    $_SESSION['user_logged_in'] === true;

/* Keep an authenticated account linked before exposing account/server features.
 * Password login synchronizes with the password just verified. Steam and
 * remember-me sessions without a local plaintext password are sent through the
 * account-completion form to choose/confirm a shared password.
 */
$paymenterSyncRequired = false;
if ($isLoggedIn && $pdo instanceof PDO) {
    try {
        $syncLookup = $pdo->prepare(
            'SELECT username, first_name, last_name, email, phone, paymenter_user_id, paymenter_sync_status
             FROM users WHERE steamid64 = ? LIMIT 1'
        );
        $syncLookup->execute([(string) ($_SESSION['steamid'] ?? '')]);
        $syncState = $syncLookup->fetch(PDO::FETCH_ASSOC);

        $paymenterSyncRequired = !$syncState
            || !preg_match('/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/D', strtolower(trim((string) ($syncState['username'] ?? ''))))
            || trim((string) ($syncState['first_name'] ?? '')) === ''
            || trim((string) ($syncState['last_name'] ?? '')) === ''
            || !filter_var(trim((string) ($syncState['email'] ?? '')), FILTER_VALIDATE_EMAIL)
            || !preg_match('/^09[0-9]{9}$/D', trim((string) ($syncState['phone'] ?? '')))
            || (int) ($syncState['paymenter_user_id'] ?? 0) < 1
            || (string) ($syncState['paymenter_sync_status'] ?? '') !== 'synced';
    } catch (PDOException $e) {
        error_log('[FragCity] Paymenter sync-state lookup failed (SQLSTATE ' . $e->getCode() . ').');
        $paymenterSyncRequired = true;
    }
}

if ($paymenterSyncRequired && $page !== 'register') {
    $_SESSION['auth_error'] = 'برای استفاده از حساب و خرید سرور، همگام‌سازی حساب Paymenter را تکمیل کنید.';
    header('Location: index.php?page=register&complete=1', true, 303);
    exit;
}

/* Pages that require authentication. */
$protectedPages = [
    'skins',
    'skins_vps',
    'profile',
    'myservers',
    'orders',
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

/*
 * Login is rendered as a site-wide modal. Keep a small fragment endpoint
 * for the modal so it can fetch the same PHP form without replacing the
 * current page or moving the footer.
 */
if ($page === 'login' && $isLoggedIn) {
    header('Location: index.php', true, 303);
    exit;
}

if (
    $page === 'login' &&
    !$isLoggedIn &&
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    (string) ($_GET['auth_modal'] ?? '') === '1'
) {
    $returnTo = $_GET['return_to'] ?? null;
    if (is_string($returnTo) && !preg_match('/[\\r\\n]/', $returnTo)) {
        $_SESSION['login_return_url'] = safe_local_redirect($returnTo, 'index.php');
    }

    header('Content-Type: text/html; charset=UTF-8');
    require __DIR__ . '/pages/login.php';
    exit;
}

/* Preserve ?page=login as a deep link, but render the site beneath the modal. */
$openLoginModal = $page === 'login';
if ($openLoginModal) {
    $page = 'home';
    $current_page = 'home';
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

    'home' => [
        'css' => [
            '/css/home.css?v=' . asset_version(
                __DIR__ . '/css/home.css'
            ),
        ],
        'js' => [],
    ],

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

    'servers_vps' => [
        'css' => [
            '/css/serverlist.css?v=' . asset_version(
                __DIR__ . '/css/serverlist.css'
            ),
        ],
        'js' => [
            '/js/serverlist.js?v=' . asset_version(
                __DIR__ . '/js/serverlist.js'
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

    'myservers' => [
        'css' => [
            '/css/myservers.css?v=' . asset_version(
                __DIR__ . '/css/myservers.css'
            ),
        ],
        'js' => [
            '/js/myservers.js?v=' . asset_version(
                __DIR__ . '/js/myservers.js'
            ),
        ],
    ],

    'orders' => [
        'css' => [
            '/css/pages-base.css?v=' . asset_version(__DIR__ . '/css/pages-base.css'),
            '/css/orders.css?v=' . asset_version(__DIR__ . '/css/orders.css'),
        ],
        'js' => [
            '/js/orders.js?v=' . asset_version(__DIR__ . '/js/orders.js'),
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

    'minecraft' => [
        'css' => [
            '/css/buyminecraft.css?v=' . asset_version(
                __DIR__ . '/css/buyminecraft.css'
            ),
        ],
        'js' => [
            '/js/buyminecraft.js?v=' . asset_version(
                __DIR__ . '/js/buyminecraft.js'
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

    'vip' => [
        'css' => [
            '/css/pages-base.css?v=' . asset_version(__DIR__ . '/css/pages-base.css'),
            '/css/fragcity-success-modal.css?v=' . asset_version(__DIR__ . '/css/fragcity-success-modal.css'),
            '/css/vip.css?v=' . asset_version(__DIR__ . '/css/vip.css'),
        ],
        'js' => [
            '/js/fragcity-success-modal.js?v=' . asset_version(__DIR__ . '/js/fragcity-success-modal.js'),
            '/js/vip.js?v=' . asset_version(__DIR__ . '/js/vip.js'),
        ],
    ],

    'about' => [
        'css' => [
            '/css/pages-base.css?v=' . asset_version(__DIR__ . '/css/pages-base.css'),
            '/css/about.css?v=' . asset_version(__DIR__ . '/css/about.css'),
        ],
        'js' => [
            '/js/about.js?v=' . asset_version(__DIR__ . '/js/about.js'),
        ],
    ],

    'support' => [
        'css' => [
            '/css/pages-base.css?v=' . asset_version(__DIR__ . '/css/pages-base.css'),
            '/css/support.css?v=' . asset_version(__DIR__ . '/css/support.css'),
        ],
        'js' => [
            '/js/support.js?v=' . asset_version(__DIR__ . '/js/support.js'),
        ],
    ],

    'usermanual' => [
        'css' => [
            '/css/pages-base.css?v=' . asset_version(__DIR__ . '/css/pages-base.css'),
            '/css/usermanual.css?v=' . asset_version(__DIR__ . '/css/usermanual.css'),
        ],
        'js' => [
            '/js/usermanual.js?v=' . asset_version(__DIR__ . '/js/usermanual.js'),
        ],
    ],

    'serverguide' => [
        'css' => [
            '/css/serverguide.css?v=' . asset_version(__DIR__ . '/css/serverguide.css'),
        ],
        'js' => [
            '/js/serverguide.js?v=' . asset_version(__DIR__ . '/js/serverguide.js'),
        ],
    ],

    'status' => [
        'css' => [
            '/css/status.css?v=' . asset_version(__DIR__ . '/css/status.css'),
        ],
        'js' => [
            '/js/status.js?v=' . asset_version(__DIR__ . '/js/status.js'),
        ],
    ],

    'rules' => [
        'css' => [
            '/css/pages-base.css?v=' . asset_version(__DIR__ . '/css/pages-base.css'),
            '/css/rules.css?v=' . asset_version(__DIR__ . '/css/rules.css'),
        ],
        'js' => [
            '/js/rules.js?v=' . asset_version(__DIR__ . '/js/rules.js'),
        ],
    ],

    'privacy' => [
        'css' => [
            '/css/pages-base.css?v=' . asset_version(__DIR__ . '/css/pages-base.css'),
            '/css/privacy.css?v=' . asset_version(__DIR__ . '/css/privacy.css'),
        ],
        'js' => [
            '/js/privacy.js?v=' . asset_version(__DIR__ . '/js/privacy.js'),
        ],
    ],

    'launcher' => [
        'css' => [
            '/css/launcher.css?v=' . asset_version(__DIR__ . '/css/launcher.css'),
        ],
        'js' => [],
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

// Keep older public route aliases mapped to the same canonical page assets.
$assetPage = match ($page) {
    'servers' => 'servers_vps',
    'skins_vps' => 'skins',
    'leaderboard_vps' => 'leaderboard',
    default => $page,
};

$currentPageAssets = $pageAssets[$assetPage] ?? [
    'css' => [],
    'js' => [],
];

$pageFile = match ($page) {
    'skins', 'skins_vps' => __DIR__ . '/pages/weaponpaints.php',
    'servers', 'servers_vps' => __DIR__ . '/pages/serverlist.php',
    'leaderboard_vps' => __DIR__ . '/pages/leaderboard.php',
    'minecraft' => __DIR__ . '/pages/buyminecraft.php',
    default => __DIR__ . '/pages/' . $page . '.php',
};

/* =========================================================
 | Search metadata and canonical URL
 ========================================================== */
$fcSeoPages = [
    'home' => [
        'title' => 'فرگ‌سیتی | سرور بازی CS2 و کامیونیتی گیمینگ',
        'description' => 'فرگ‌سیتی سرورهای بازی CS2، ابزارهای کامیونیتی، خدمات بازیکنان و منابع مدیریت سرور را در یک پلتفرم گرد هم می‌آورد.',
    ],
    'servers' => [
        'title' => 'لیست سرورهای عمومی CS2 | FragCity',
        'description' => 'سرورهای عمومی کانتر استرایک ۲ فرگ‌سیتی را ببین، مودهای بازی را مقایسه کن و سرور مناسب سبک بازی‌ات را پیدا کن.',
    ],
    'servers_vps' => [
        'title' => 'لیست سرورهای عمومی CS2 | FragCity',
        'description' => 'سرورهای عمومی کانتر استرایک ۲ فرگ‌سیتی را ببین، مودهای بازی را مقایسه کن و سرور مناسب سبک بازی‌ات را پیدا کن.',
    ],
    'serverguide' => [
        'title' => 'راهنمای سرور CS2 | دستورات، مودها و تنظیمات | FragCity',
        'description' => 'در راهنمای سرور CS2 فرگ‌سیتی، دستورات، مودهای بازی، تنظیمات پلاگین‌ها، سطح دسترسی و وضعیت فعال بودن قابلیت‌ها را جست‌وجو کن.',
    ],
    'status' => [
        'title' => 'وضعیت سرویس‌های FragCity',
        'description' => 'وضعیت گزارش‌شده و اطلاعات سرویس‌های فرگ‌سیتی را مشاهده کن.',
    ],
    'about' => [
        'title' => 'درباره FragCity | کامیونیتی کانتر استرایک ۲',
        'description' => 'با فرگ‌سیتی، خدمات کانتر استرایک ۲، قابلیت‌های کامیونیتی و ابزارهای گیمینگ آن آشنا شو.',
    ],
    'minecraft' => [
        'title' => 'خرید سرور Minecraft | FragCity',
        'description' => 'محاسبه قیمت ماهانه سرور Minecraft بر اساس ظرفیت بازیکن و منابع انتخابی در FragCity.',
    ],
    'support' => [
        'title' => 'پشتیبانی و تماس با FragCity',
        'description' => 'برای دریافت راهنمایی درباره حساب کاربری، سرورهای بازی و خدمات کامیونیتی با پشتیبانی فرگ‌سیتی تماس بگیر.',
    ],
    'orders' => [
        'title' => 'سفارش‌های شما | FragCity',
        'description' => 'وضعیت پرداخت و فعال‌سازی سفارش‌های VIP فرگ‌سیتی را در یک صفحه پیگیری کنید.',
    ],
    'vip' => [
        'title' => 'سطح‌های VIP فرگ‌سیتی',
        'description' => 'سطح‌های عضویت VIP فرگ‌سیتی را مقایسه کن و قابلیت‌های پیکربندی‌شده برای هر سطح را ببین.',
    ],
    'leaderboard' => [
        'title' => 'لیدربورد بازیکنان FragCity',
        'description' => 'جدول رتبه‌بندی بازیکنان و جایگاه اعضای کامیونیتی فرگ‌سیتی را مشاهده کن.',
    ],
    'leaderboard_vps' => [
        'title' => 'لیدربورد بازیکنان FragCity',
        'description' => 'جدول رتبه‌بندی بازیکنان و جایگاه اعضای کامیونیتی فرگ‌سیتی را مشاهده کن.',
    ],
    'banlist' => [
        'title' => 'لیست محرومیت‌های FragCity',
        'description' => 'در فهرست عمومی محرومیت‌های فرگ‌سیتی جست‌وجو و سوابق مدیریت بازیکنان را بررسی کن.',
    ],
    'rules' => [
        'title' => 'قوانین سرورهای FragCity',
        'description' => 'قوانین بازی در سرورهای کانتر استرایک ۲ فرگ‌سیتی و حضور در کامیونیتی را مطالعه کن.',
    ],
    'privacy' => [
        'title' => 'سیاست حفظ حریم خصوصی FragCity',
        'description' => 'با نحوه نگهداری اطلاعات حساب و رعایت حریم خصوصی در وب‌سایت و سرویس‌های فرگ‌سیتی آشنا شو.',
    ],
    'usermanual' => [
        'title' => 'راهنمای کاربری سرور FragCity',
        'description' => 'راهنمای کاربری فرگ‌سیتی را بخوان و با قابلیت‌های موجود سرور و کامیونیتی آشنا شو.',
    ],
];

$fcSeoMetadata = $fcSeoPages[$page] ?? null;
$fcRequestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$fcRequestHost = preg_replace('/:\\d+$/', '', $fcRequestHost);
$fcIsProductionHost = $fcRequestHost === 'fragcity.ir';
$fcCanIndexPage = is_array($fcSeoMetadata) && $fcIsProductionHost && !$openLoginModal;
$fcSeoTitle = (string) ($fcSeoMetadata['title'] ?? 'FragCity | Gaming Community');
$fcSeoDescription = (string) ($fcSeoMetadata['description'] ?? 'کامیونیتی گیمینگ فرگ‌سیتی و خدمات سرور کانتر استرایک ۲.');
$fcCanonicalUrl = $fcCanIndexPage
    ? 'https://fragcity.ir' . fragcity_page_url($page)
    : '';

if (!$fcCanIndexPage) {
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
}


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
    <!-- Critical root scrollbar guard: hide the viewport scrollbar before external CSS loads. -->
    <style id="fc-root-scrollbar-guard">
        html,
        body.site-body {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }

        html::-webkit-scrollbar,
        body.site-body::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
    </style>
    <meta
        name="color-scheme"
        content="dark"
    >

    <script>
    (function () {
        try {
            var saved = localStorage.getItem('fragcity:no-animation-mode');
            var prefersReduced = window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (saved === '1' || (saved === null && prefersReduced)) {
                document.documentElement.classList.add('fc-no-animation');
            }
        } catch (error) {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.documentElement.classList.add('fc-no-animation');
            }
        }
    })();
    </script>

    <title><?= htmlspecialchars($fcSeoTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($fcSeoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= $fcCanIndexPage ? 'index,follow,max-image-preview:large' : 'noindex,nofollow,noarchive' ?>">
    <?php if ($fcCanonicalUrl !== ''): ?>
        <link rel="canonical" href="<?= htmlspecialchars($fcCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
        <link rel="alternate" hreflang="fa-IR" href="<?= htmlspecialchars($fcCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
        <meta property="og:url" content="<?= htmlspecialchars($fcCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="FragCity">
    <meta property="og:title" content="<?= htmlspecialchars($fcSeoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($fcSeoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary">

    <!-- Font Awesome: CDN only -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    >

    <!-- Shared design tokens: load before all site and page styles -->
    <link
        rel="stylesheet"
        href="/css/design-tokens.css?v=<?= htmlspecialchars(asset_version(__DIR__ . '/css/design-tokens.css'), ENT_QUOTES, 'UTF-8') ?>"
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

    <link
        rel="stylesheet"
        href="/css/footer.css?v=<?= htmlspecialchars(asset_version(__DIR__ . '/css/footer.css'), ENT_QUOTES, 'UTF-8') ?>"
    >

    <!-- Shared component primitives and motion utilities -->
    <link
        rel="stylesheet"
        href="/css/components.css?v=<?= htmlspecialchars(asset_version(__DIR__ . '/css/components.css'), ENT_QUOTES, 'UTF-8') ?>"
    >
    <link
        rel="stylesheet"
        href="/css/motion.css?v=<?= htmlspecialchars(asset_version(__DIR__ . '/css/motion.css'), ENT_QUOTES, 'UTF-8') ?>"
    >

    <?php foreach ($currentPageAssets['css'] as $pageCss): ?>
        <link
            rel="stylesheet"
            href="<?= htmlspecialchars($pageCss, ENT_QUOTES, 'UTF-8') ?>"
            data-page-style
        >
    <?php endforeach; ?>
</head>

<body class="site-body<?= $page === 'home' ? ' site-body--home' : '' ?>">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="app-container">

    <main
        class="main-content<?= $page === 'home' ? ' main-content--home' : '' ?>"
        id="mainContent"
    >
        <div
            class="content-fader<?= $page === 'home' ? ' content-fader--home' : '' ?>"
            id="contentFader"
        >

            <?php
            if (is_file($pageFile)) {
                if (!defined('FRAGCITY_INDEX_RENDER')) {
                    define('FRAGCITY_INDEX_RENDER', true);
                }

                include $pageFile;
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
        include $footerFile;
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
window.FragCityOpenLoginModal = <?= $openLoginModal ? 'true' : 'false' ?>;
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

<script
    src="/js/wallet-modal.js?v=<?= htmlspecialchars(asset_version(__DIR__ . '/js/wallet-modal.js'), ENT_QUOTES, 'UTF-8') ?>"
></script>

<script
    src="/js/motion.js?v=<?= htmlspecialchars(asset_version(__DIR__ . '/js/motion.js'), ENT_QUOTES, 'UTF-8') ?>"
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