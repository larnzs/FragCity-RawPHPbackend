<?php
// panel/backups.php

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

        'title' =>
            'پشتیبان‌گیری‌ها | پنل مدیریت CS2',

        'nav_title' =>
            'داشبورد مدیریت سرور',

        'page_title' =>
            'پشتیبان‌گیری‌ها',

        'page_desc' =>
            'ایجاد، دانلود و بازنشانی پشتیبان‌ها و آرشیوهای سرور.',

        'list_title' =>
            'آرشیوهای پشتیبان',

        'list_desc' =>
            'فایل‌های پشتیبان ذخیره شده سرور.',

        'create_btn' =>
            'ایجاد پشتیبان',

        'th_name' =>
            'نام',

        'th_size' =>
            'حجم',

        'th_date' =>
            'تاریخ ایجاد',

        'th_actions' =>
            'عملیات',

        'no_backups' =>
            'هیچ فایل پشتیبانی موجود نیست. برای شروع یک پشتیبان ایجاد کنید.'

    ],

    'en' => [

        'title' =>
            'Backups | CS2 Admin Panel',

        'nav_title' =>
            'CS2 Admin Panel',

        'page_title' =>
            'Backups',

        'page_desc' =>
            'Create, download, and restore server backups and archives.',

        'list_title' =>
            'Backup Archives',

        'list_desc' =>
            'Stored server backups.',

        'create_btn' =>
            'Create Backup',

        'th_name' =>
            'NAME',

        'th_size' =>
            'SIZE',

        'th_date' =>
            'CREATED AT',

        'th_actions' =>
            'ACTIONS',

        'no_backups' =>
            'No backups available. Create a backup to get started.'

    ]

];


$t = $translations[$lang];

$current_page = 'backups';
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
    <link rel="stylesheet" href="css/backups.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'header.php'; ?>

    <main class="main-content backups-page">

        <div class="page-header backups-page-header">

            <h1 class="page-title">
                <?php echo $t['page_title']; ?>
            </h1>

            <div class="page-desc">
                <?php echo $t['page_desc']; ?>
            </div>

        </div>


        <div class="backups-toolbar">

            <div>

                <h2 class="backups-list-title">
                    <?php echo $t['list_title']; ?>
                </h2>

                <p class="backups-list-desc">
                    <?php echo $t['list_desc']; ?>
                </p>

            </div>


            <button
                type="button"
                class="dashboard-btn backups-create-btn"
                id="createBackupButton"
            >

                <i class="fa-solid fa-box-archive"></i>

                <?php echo $t['create_btn']; ?>

            </button>

        </div>


        <section class="servers-card backups-card">

            <div class="backups-table-header">

                <div>
                    <?php echo $t['th_name']; ?>
                </div>

                <div>
                    <?php echo $t['th_size']; ?>
                </div>

                <div>
                    <?php echo $t['th_date']; ?>
                </div>

                <div>
                    <?php echo $t['th_actions']; ?>
                </div>

            </div>


            <div class="backups-empty-state">

                <div class="backups-empty-icon">

                    <i class="fa-solid fa-box-archive"></i>

                </div>

                <div class="empty-title">

                    <?php echo $t['no_backups']; ?>

                </div>

            </div>

        </section>

    </main>

</div>

<script
    src="js/backups.js"
    defer
></script>

</body>
</html>