<?php
// panel/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   Helpers
========================================================= */

if (!function_exists('panelHeaderEscape')) {

    function panelHeaderEscape($value)
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }

}


/* =========================================================
   Main Website
========================================================= */

$mainSiteBaseUrl =
    'https://fragcity.ir';


/* =========================================================
   Language
========================================================= */

if (
    isset($_GET['lang']) &&
    in_array(
        $_GET['lang'],
        ['fa', 'en'],
        true
    )
) {
    $_SESSION['lang'] =
        $_GET['lang'];
}

$lang =
    $_SESSION['lang']
    ?? 'fa';

$is_fa =
    ($lang === 'fa');


/* =========================================================
   Page Title
========================================================= */

if (
    !isset($page_title) ||
    empty($page_title)
) {

    $page_title =
        $is_fa
            ? 'داشبورد مدیریت سرور'
            : 'CS2 Admin Panel';

}


/* =========================================================
   Login State
========================================================= */

$isLoggedIn =
    isset(
        $_SESSION['user_logged_in']
    );


$displayName =
    $_SESSION['username']
    ?? '';


$displaySteamID =
    $_SESSION['steamid']
    ?? '';


$role =
    $_SESSION['role']
    ?? 'user';


$steamShort =
    !empty($displaySteamID)
        ? substr(
            $displaySteamID,
            -8
        )
        : '';


/* =========================================================
   User Avatar
========================================================= */

function getUserAvatarHeader($steamid)
{

    global $mainSiteBaseUrl;

    if (
        empty($steamid)
    ) {

        return '';

    }


    return
        rtrim(
            $mainSiteBaseUrl,
            '/'
        ) .
        '/img/avatars/avatar_' .
        rawurlencode(
            $steamid
        ) .
        '.jpg';

}


/* =========================================================
   Avatar URLs
========================================================= */

$avatarSrc =
    !empty($displaySteamID)
        ? getUserAvatarHeader(
            $displaySteamID
        )
        : '';


$steamFallbackAvatar =
    !empty($displaySteamID)
        ? 'https://avatars.steamstatic.com/' .
          rawurlencode(
              $displaySteamID
          ) .
          '?size=medium'
        : '';


/* =========================================================
   Language URLs
   Preserve Current Query Parameters
========================================================= */

$langFaParams =
    $_GET;

$langFaParams['lang'] =
    'fa';


$langEnParams =
    $_GET;

$langEnParams['lang'] =
    'en';


$langFaUrl =
    '?' .
    http_build_query(
        $langFaParams
    );


$langEnUrl =
    '?' .
    http_build_query(
        $langEnParams
    );

?>

<header class="top-navbar">


    <!-- =====================================================
         Page Title
    ====================================================== -->

    <div class="top-nav-title">

        <?= panelHeaderEscape(
            $page_title
        ) ?>

    </div>


    <!-- =====================================================
         Right Side
    ====================================================== -->

    <div class="top-nav-right">


        <!-- =================================================
             User Area
        ================================================== -->

        <div class="frag-user-area">


            <?php if ($isLoggedIn): ?>


                <div
                    class="frag-dropdown"
                    id="fragUserDropdown"
                >


                    <!-- User Trigger -->

                    <button
                        type="button"
                        class="frag-user-trigger"
                        id="fragUserTrigger"
                        aria-haspopup="true"
                        aria-expanded="false"
                    >


                        <i
                            class="fas fa-chevron-down frag-arrow"
                        ></i>


                        <span class="frag-username">

                            <?= panelHeaderEscape(
                                $displayName !== ''
                                    ? $displayName
                                    : 'User'
                            ) ?>

                        </span>


                        <?php if (!empty($avatarSrc)): ?>


                            <span class="frag-avatar-wrap">


                                <img
                                    src="<?= panelHeaderEscape(
                                        $avatarSrc
                                    ) ?>"
                                    class="frag-avatar frag-avatar-image"
                                    alt="User Avatar"
                                    data-fallback="<?= panelHeaderEscape(
                                        $steamFallbackAvatar
                                    ) ?>"
                                    loading="eager"
                                >


                                <span
                                    class="frag-avatar-fallback"
                                >

                                    <i class="fas fa-user"></i>

                                </span>


                            </span>


                        <?php else: ?>


                            <span
                                class="frag-avatar-wrap frag-avatar-placeholder"
                            >

                                <i class="fas fa-user"></i>

                            </span>


                        <?php endif; ?>


                    </button>


                    <!-- Dropdown -->

                    <div
                        class="frag-dropdown-menu"
                        role="menu"
                    >


                        <!-- User Header -->

                        <div class="frag-drop-header">


                            <?php if (!empty($avatarSrc)): ?>


                                <span class="frag-drop-avatar-wrap">


                                    <img
                                        src="<?= panelHeaderEscape(
                                            $avatarSrc
                                        ) ?>"
                                        class="frag-drop-avatar frag-avatar-image"
                                        alt="User Avatar"
                                        data-fallback="<?= panelHeaderEscape(
                                            $steamFallbackAvatar
                                        ) ?>"
                                    >


                                    <span
                                        class="frag-drop-avatar-fallback"
                                    >

                                        <i class="fas fa-user-shield"></i>

                                    </span>


                                </span>


                            <?php else: ?>


                                <span
                                    class="frag-drop-avatar-wrap frag-drop-avatar-placeholder"
                                >

                                    <i class="fas fa-user-shield"></i>

                                </span>


                            <?php endif; ?>


                            <div class="frag-drop-info">


                                <span class="frag-drop-name">

                                    <?= panelHeaderEscape(
                                        $displayName !== ''
                                            ? $displayName
                                            : 'User'
                                    ) ?>

                                </span>


                                <span class="frag-drop-steam">


                                    <?php

                                    if (
                                        !empty(
                                            $steamShort
                                        )
                                    ) {

                                        echo panelHeaderEscape(
                                            $steamShort
                                        );

                                    } else {

                                        echo $is_fa
                                            ? 'مدیر سیستم'
                                            : 'System Admin';

                                    }

                                    ?>


                                </span>


                            </div>


                        </div>


                        <div class="frag-drop-divider"></div>


                        <!-- Profile -->

                        <a
                            href="<?= panelHeaderEscape(
                                $mainSiteBaseUrl .
                                '/index.php?page=profile'
                            ) ?>"
                            class="frag-drop-link"
                            role="menuitem"
                        >

                            <i class="fas fa-user"></i>

                            <span>

                                <?= $is_fa
                                    ? 'مشاهده پروفایل'
                                    : 'View Profile'
                                ?>

                            </span>

                        </a>


                        <!-- Wallet -->

                        <a
                            href="<?= panelHeaderEscape(
                                $mainSiteBaseUrl .
                                '/index.php?page=wallet'
                            ) ?>"
                            class="frag-drop-link"
                            role="menuitem"
                        >

                            <i class="fas fa-wallet"></i>

                            <span>

                                <?= $is_fa
                                    ? 'کیف پول شما'
                                    : 'Your Wallet'
                                ?>

                            </span>

                        </a>


                        <!-- Main Site -->

                        <a
                            href="<?= panelHeaderEscape(
                                $mainSiteBaseUrl
                            ) ?>"
                            class="frag-drop-link"
                            role="menuitem"
                        >

                            <i class="fas fa-home"></i>

                            <span>

                                <?= $is_fa
                                    ? 'بازگشت به سایت اصلی'
                                    : 'Back to Main Site'
                                ?>

                            </span>

                        </a>


                        <div class="frag-drop-divider"></div>


                        <!-- Logout -->

                        <a
                            href="<?= panelHeaderEscape(
                                $mainSiteBaseUrl .
                                '/index.php?logout=1'
                            ) ?>"
                            class="frag-drop-link logout"
                            role="menuitem"
                        >

                            <i class="fas fa-sign-out-alt"></i>

                            <span>

                                <?= $is_fa
                                    ? 'خروج از حساب'
                                    : 'Logout'
                                ?>

                            </span>

                        </a>


                    </div>


                </div>


            <?php else: ?>


                <!-- Login -->

                <a
                    href="<?= panelHeaderEscape(
                        $mainSiteBaseUrl .
                        '/index.php?page=login'
                    ) ?>"
                    class="frag-login-btn"
                >

                    <i class="fas fa-user-lock"></i>

                    <span>

                        <?= $is_fa
                            ? 'ورود / ثبت‌نام'
                            : 'Login / Register'
                        ?>

                    </span>

                </a>


            <?php endif; ?>


        </div>


        <!-- =================================================
             Language
        ================================================== -->

        <div class="lang-switcher">


            <a
                href="<?= panelHeaderEscape(
                    $langFaUrl
                ) ?>"
                class="lang-btn <?= $is_fa
                    ? 'active'
                    : '' ?>"
            >

                <img
                    src="https://flagcdn.com/w20/ir.png"
                    alt="FA"
                >

                فارسی

            </a>


            <a
                href="<?= panelHeaderEscape(
                    $langEnUrl
                ) ?>"
                class="lang-btn <?= !$is_fa
                    ? 'active'
                    : '' ?>"
            >

                <img
                    src="https://flagcdn.com/w20/us.png"
                    alt="EN"
                >

                EN

            </a>


        </div>


    </div>


</header>


<script
    src="js/header.js"
    defer
></script>