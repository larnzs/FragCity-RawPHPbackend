<?php
// panel/plugins.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   Language
========================================================= */

if (
    isset($_GET['lang']) &&
    in_array($_GET['lang'], ['fa', 'en'], true)
) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang = $_SESSION['lang'] ?? 'fa';
$is_fa = ($lang === 'fa');


/* =========================================================
   Translations
========================================================= */

$translations = [

    'fa' => [

        'title' => 'پلاگین‌ها | پنل مدیریت CS2',

        'nav_title' => 'داشبورد مدیریت سرور',

        'page_title' => 'پلاگین‌ها',

        'page_desc' =>
            'مدیریت پلاگین‌های سرور، افزونه‌ها و ماژول‌های Metamod/CounterStrikeSharp.',

        'tab_installed' =>
            'پلاگین‌های نصب‌شده',

        'tab_repo' =>
            'مخزن پلاگین‌ها',

        'list_title' =>
            'لیست پلاگین‌ها',

        'list_desc' =>
            'پلاگین‌های فعال و غیرفعال روی سرور شما.',

        'install_btn' =>
            'نصب پلاگین',

        'th_name' =>
            'نام',

        'th_version' =>
            'نسخه',

        'th_author' =>
            'سازنده',

        'th_status' =>
            'وضعیت',

        'th_actions' =>
            'عملیات',

        'empty_msg' =>
            'هیچ پلاگینی نصب نشده است. برای شروع یک پلاگین آپلود یا نصب کنید.',

        'repo_empty' =>
            'مخزن پلاگین‌ها در حال حاضر محتوایی برای نمایش ندارد.'

    ],

    'en' => [

        'title' => 'Plugins | CS2 Admin Panel',

        'nav_title' => 'CS2 Admin Panel',

        'page_title' => 'Plugins',

        'page_desc' =>
            'Manage server plugins, extensions, and Metamod/CounterStrikeSharp modules.',

        'tab_installed' =>
            'Installed Plugins',

        'tab_repo' =>
            'Plugin Repository',

        'list_title' =>
            'Plugin List',

        'list_desc' =>
            'Active and inactive plugins on your server.',

        'install_btn' =>
            'Install Plugin',

        'th_name' =>
            'NAME',

        'th_version' =>
            'VERSION',

        'th_author' =>
            'AUTHOR',

        'th_status' =>
            'STATUS',

        'th_actions' =>
            'ACTIONS',

        'empty_msg' =>
            'No plugins installed. Upload or install a plugin to get started.',

        'repo_empty' =>
            'The plugin repository currently has no content to display.'

    ]

];


$t = $translations[$lang];

$current_page = 'plugins';
$page_title = $t['nav_title'];

?>
<!DOCTYPE html>
<html
    lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>"
    dir="<?php echo $is_fa ? 'rtl' : 'ltr'; ?>"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'); ?>
    </title>

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/panel-motion.css">
    <link rel="stylesheet" href="css/plugins.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'header.php'; ?>

    <main class="main-content plugins-page">

        <div class="page-header plugins-page-header">

            <h1 class="page-title">
                <?php echo $t['page_title']; ?>
            </h1>

            <div class="page-desc">
                <?php echo $t['page_desc']; ?>
            </div>

        </div>


        <!-- Tabs -->

        <div class="plugins-tabs tabs-nav">

            <button
                type="button"
                class="tab-btn active"
                data-plugin-tab="installed"
            >

                <i class="fa-solid fa-puzzle-piece"></i>

                <?php echo $t['tab_installed']; ?>

            </button>


            <button
                type="button"
                class="tab-btn"
                data-plugin-tab="repository"
            >

                <i class="fa-solid fa-box-open"></i>

                <?php echo $t['tab_repo']; ?>

            </button>

        </div>


        <!-- Toolbar -->

        <div class="plugins-toolbar">

            <div>

                <h2 class="plugins-list-title">
                    <?php echo $t['list_title']; ?>
                </h2>

                <p class="plugins-list-desc">
                    <?php echo $t['list_desc']; ?>
                </p>

            </div>


            <button
                type="button"
                class="dashboard-btn plugins-install-btn"
                id="pluginsInstallButton"
            >

                <i class="fa-solid fa-cloud-arrow-up"></i>

                <?php echo $t['install_btn']; ?>

            </button>

        </div>


        <!-- Installed View -->

        <section
            class="servers-card plugins-card"
            data-plugin-view="installed"
        >

            <div class="plugins-table-header">

                <div>
                    <?php echo $t['th_name']; ?>
                </div>

                <div>
                    <?php echo $t['th_version']; ?>
                </div>

                <div>
                    <?php echo $t['th_author']; ?>
                </div>

                <div>
                    <?php echo $t['th_status']; ?>
                </div>

                <div>
                    <?php echo $t['th_actions']; ?>
                </div>

            </div>


            <div class="plugins-empty-state">

                <div class="plugins-empty-icon">

                    <i class="fa-solid fa-puzzle-piece"></i>

                </div>

                <div class="empty-title">
                    <?php echo $t['empty_msg']; ?>
                </div>

            </div>

        </section>


        <!-- Repository View -->

        <section
            class="servers-card plugins-card"
            data-plugin-view="repository"
            hidden
        >

            <div class="plugins-repository-empty">

                <div class="plugins-empty-icon">

                    <i class="fa-solid fa-box-open"></i>

                </div>

                <div class="empty-title">

                    <?php echo $t['repo_empty']; ?>

                </div>

            </div>

        </section>

    </main>

</div>

<script
    src="js/plugins.js"
    defer
></script>

</body>
</html>