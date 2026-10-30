<?php
// panel/sidebar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   Current Page
========================================================= */

$current_page =
    $current_page
    ?? '';


/* =========================================================
   Language
========================================================= */

$lang =
    $_SESSION['lang']
    ?? 'fa';


if (
    !in_array(
        $lang,
        ['fa', 'en'],
        true
    )
) {
    $lang =
        'fa';
}


/* =========================================================
   Translations
========================================================= */

$sidebarTranslations = [

    'fa' => [

        'overview' =>
            'نمای کلی',

        'monitoring' =>
            'مانیتورینگ',

        'servers' =>
            'سرورها',

        'players' =>
            'بازیکنان',

        'maps' =>
            'نقشه‌ها',

        'config' =>
            'پیکربندی',

        'automations' =>
            'خودکارسازی‌ها',

        'files' =>
            'فایل‌ها',

        'plugins' =>
            'پلاگین‌ها',

        'backups' =>
            'پشتیبان‌ها',

        'logs' =>
            'لاگ‌ها',

        'admins' =>
            'مدیران',

        'webhooks' =>
            'وب‌هوک‌ها',

        'settings' =>
            'تنظیمات'

    ],


    'en' => [

        'overview' =>
            'Overview',

        'monitoring' =>
            'Monitoring',

        'servers' =>
            'Servers',

        'config' =>
            'Config',

        'players' =>
            'Players',

        'maps' =>
            'Maps',

        'automations' =>
            'Automations',

        'files' =>
            'Files',

        'plugins' =>
            'Plugins',

        'backups' =>
            'Backups',

        'logs' =>
            'Logs',

        'admins' =>
            'Admins',

        'webhooks' =>
            'Webhooks',

        'settings' =>
            'Settings'

    ]

];


$sidebarText =
    $sidebarTranslations[$lang];


/* =========================================================
   Sidebar Items
========================================================= */

$sidebarItems = [

    /*
     * Servers باید اولین گزینه باشد.
     */

    [
        'key' =>
            'servers',

        'href' =>
            'servers.php',

        'icon' =>
            'fa-solid fa-server',

        'active_pages' => [
            'servers',
            'server'
        ]
    ],


    /*
     * Overview دومین گزینه است.
     */

    [
        'key' =>
            'overview',

        'href' =>
            'overview.php',

        'icon' =>
            'fa-solid fa-table-cells-large',

        'active_pages' => [
            'overview',
            'dashboard'
        ]
    ],

    [
        'key' =>
            'monitoring',

        'href' =>
            'monitoring.php',

        'icon' =>
            'fa-solid fa-chart-line',

        'active_pages' => [
            'monitoring'
        ]
    ],


    [
        'key' =>
            'players',

        'href' =>
            'players.php',

        'icon' =>
            'fa-solid fa-users',

        'active_pages' => [
            'players'
        ]
    ],


    [
        'key' =>
            'maps',

        'href' =>
            'maps.php',

        'icon' =>
            'fa-solid fa-map',

        'active_pages' => [
            'maps'
        ]
    ],


    [
        'key' =>
            'config',

        'href' =>
            'config.php',

        'icon' =>
            'fa-solid fa-sliders',

        'active_pages' => [
            'config'
        ]
    ],


    [
        'key' =>
            'automations',

        'href' =>
            'automations.php',

        'icon' =>
            'fa-solid fa-bolt',

        'active_pages' => [
            'automations'
        ]
    ],


    [
        'key' =>
            'files',

        'href' =>
            'files.php',

        'icon' =>
            'fa-solid fa-folder-open',

        'active_pages' => [
            'files'
        ]
    ],


    [
        'key' =>
            'plugins',

        'href' =>
            'plugins.php',

        'icon' =>
            'fa-solid fa-puzzle-piece',

        'active_pages' => [
            'plugins'
        ]
    ],


    [
        'key' =>
            'backups',

        'href' =>
            'backups.php',

        'icon' =>
            'fa-solid fa-box-archive',

        'active_pages' => [
            'backups'
        ]
    ],


    [
        'key' =>
            'logs',

        'href' =>
            'logs.php',

        'icon' =>
            'fa-solid fa-file-lines',

        'active_pages' => [
            'logs'
        ]
    ],


    /*
     * آیکون اصلاح‌شده Admins
     */

    [
        'key' =>
            'admins',

        'href' =>
            'admins.php',

        'icon' =>
            'fa-solid fa-user-shield',

        'active_pages' => [
            'admins'
        ]
    ],


    [
        'key' =>
            'webhooks',

        'href' =>
            'webhooks.php',

        'icon' =>
            'fa-solid fa-bell',

        'active_pages' => [
            'webhooks'
        ]
    ],


    [
        'key' =>
            'settings',

        'href' =>
            'settings.php',

        'icon' =>
            'fa-solid fa-gear',

        'active_pages' => [
            'settings'
        ]
    ]

];


/* =========================================================
   Brand Logo
========================================================= */

$brandLogo =
    'assets/images/logo/fragcity-logo.png';


function panelSidebarEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<aside class="sidebar">


    <!-- =====================================================
         Brand
    ====================================================== -->

    <div class="sidebar-brand">


        <a
            href="servers.php"
            class="sidebar-brand-link"
            aria-label="FragCity CS2 Admin Panel"
        >


            <span class="sidebar-brand-logo-wrap">


                <img
                    src="<?= panelSidebarEscape(
                        $brandLogo
                    ) ?>"
                    alt="FragCity"
                    class="sidebar-brand-logo"
                >


                <span class="sidebar-brand-fallback">

                    <span>
                        FC
                    </span>

                </span>


            </span>


            <span class="sidebar-brand-content">


                <strong>
                    FragCity
                </strong>


                <small>
                    CS2 Admin Panel
                </small>


            </span>


        </a>


    </div>


    <!-- =====================================================
         Navigation
    ====================================================== -->

    <nav
        class="sidebar-menu"
        aria-label="Panel Navigation"
    >


        <?php foreach ($sidebarItems as $sidebarItem): ?>


            <?php

            if ($sidebarItem['key'] === 'monitoring'
                && (string)($_SESSION['role'] ?? '') !== 'admin') {
                continue;
            }

            $itemKey =
                $sidebarItem['key'];


            $isActive =
                in_array(
                    $current_page,
                    $sidebarItem['active_pages'],
                    true
                );


            $itemLabel =
                $sidebarText[$itemKey]
                ?? ucfirst($itemKey);

            ?>


            <a
                href="<?= panelSidebarEscape(
                    $sidebarItem['href']
                ) ?>"
                class="sidebar-item<?= $isActive
                    ? ' active'
                    : '' ?>"
                data-sidebar-item
                title="<?= panelSidebarEscape(
                    $itemLabel
                ) ?>"
                <?= $isActive
                    ? 'aria-current="page"'
                    : '' ?>
            >


                <i
                    class="<?= panelSidebarEscape(
                        $sidebarItem['icon']
                    ) ?>"
                    aria-hidden="true"
                ></i>


                <span>

                    <?= panelSidebarEscape(
                        $itemLabel
                    ) ?>

                </span>


            </a>


        <?php endforeach; ?>


    </nav>


</aside>


<script
    src="js/sidebar.js"
    defer
></script>