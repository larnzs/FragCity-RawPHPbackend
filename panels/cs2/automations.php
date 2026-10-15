<?php
// panel/automations.php

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

        'title' => 'اتوماسیون‌ها | پنل مدیریت CS2',

        'nav_title' => 'داشبورد مدیریت سرور',

        'page_title' => 'اتوماسیون‌ها',

        'page_desc' =>
            'پیکربندی کارهای خودکار، ری‌استارت‌ها و دستورات زمان‌بندی شده سرور.',

        'list_title' =>
            'کارهای زمان‌بندی شده',

        'list_desc' =>
            'عملیات خودکاری که به صورت دوره‌ای اجرا می‌شوند.',

        'add_btn' =>
            'افزودن اتوماسیون',

        'th_name' =>
            'نام تسک',

        'th_schedule' =>
            'زمان‌بندی / محرک',

        'th_status' =>
            'وضعیت',

        'th_actions' =>
            'عملیات',

        'no_automations' =>
            'هیچ اتوماسیونی پیکربندی نشده است. برای شروع یکی بسازید.'

    ],

    'en' => [

        'title' => 'Automations | CS2 Admin Panel',

        'nav_title' => 'CS2 Admin Panel',

        'page_title' => 'Automations',

        'page_desc' =>
            'Configure automated tasks, restarts, and scheduled server commands.',

        'list_title' =>
            'Scheduled Tasks',

        'list_desc' =>
            'Automated actions executed periodically.',

        'add_btn' =>
            'Add Automation',

        'th_name' =>
            'TASK NAME',

        'th_schedule' =>
            'SCHEDULE / TRIGGER',

        'th_status' =>
            'STATUS',

        'th_actions' =>
            'ACTIONS',

        'no_automations' =>
            'No automations configured. Create one to get started.'

    ]

];


$t = $translations[$lang];

$current_page = 'automations';
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
    <link rel="stylesheet" href="css/automations.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'header.php'; ?>

    <main class="main-content automations-page">

        <div class="page-header automations-page-header">

            <h1 class="page-title">
                <?php echo $t['page_title']; ?>
            </h1>

            <div class="page-desc">
                <?php echo $t['page_desc']; ?>
            </div>

        </div>


        <div class="automations-toolbar">

            <div>

                <h2 class="automations-list-title">
                    <?php echo $t['list_title']; ?>
                </h2>

                <p class="automations-list-desc">
                    <?php echo $t['list_desc']; ?>
                </p>

            </div>


            <button
                type="button"
                class="dashboard-btn automation-add-btn"
                id="automationAddButton"
            >

                <i class="fa-solid fa-plus"></i>

                <?php echo $t['add_btn']; ?>

            </button>

        </div>


        <section class="servers-card automations-card">

            <div class="automations-table-header">

                <div>
                    <?php echo $t['th_name']; ?>
                </div>

                <div>
                    <?php echo $t['th_schedule']; ?>
                </div>

                <div>
                    <?php echo $t['th_status']; ?>
                </div>

                <div>
                    <?php echo $t['th_actions']; ?>
                </div>

            </div>


            <div class="automations-empty-state">

                <div class="automations-empty-icon">

                    <i class="fa-solid fa-bolt"></i>

                </div>

                <div class="empty-title">

                    <?php echo $t['no_automations']; ?>

                </div>

            </div>

        </section>

    </main>

</div>

<script
    src="js/automations.js"
    defer
></script>

</body>
</html>