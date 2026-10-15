<?php
// panel/logs.php

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
            'لاگ‌ها | پنل مدیریت CS2',

        'nav_title' =>
            'داشبورد مدیریت سرور',

        'page_title' =>
            'لاگ‌های سیستم',

        'page_desc' =>
            'مشاهده لاگ‌های سرور، خطاها و تاریخچه حسابرسی.',

        'log_output' =>
            'خروجی لاگ سرور',

        'download_log' =>
            'دانلود لاگ کامل',

        'live_status' =>
            'مانیتورینگ فعال',

        'log_count' =>
            '۳ رویداد ثبت شده'

    ],

    'en' => [

        'title' =>
            'Logs | CS2 Admin Panel',

        'nav_title' =>
            'CS2 Admin Panel',

        'page_title' =>
            'System Logs',

        'page_desc' =>
            'View server logs, errors, and audit history.',

        'log_output' =>
            'Server Log Output',

        'download_log' =>
            'Download Full Log',

        'live_status' =>
            'Monitoring Active',

        'log_count' =>
            '3 logged events'

    ]

];


$t = $translations[$lang];

$current_page = 'logs';
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
        <?php echo htmlspecialchars(
            $t['title'],
            ENT_QUOTES,
            'UTF-8'
        ); ?>
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
    <link rel="stylesheet" href="css/logs.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>


<?php include 'sidebar.php'; ?>


<div class="main-content-wrapper">


    <?php include 'header.php'; ?>


    <main class="main-content logs-page">


        <!-- Page Header -->

        <div class="page-header logs-page-header">

            <h1 class="page-title">

                <?php echo $t['page_title']; ?>

            </h1>

            <div class="page-desc">

                <?php echo $t['page_desc']; ?>

            </div>

        </div>


        <!-- Logs Card -->

        <section class="panel-card logs-card">


            <div class="logs-card-header">


                <div class="logs-title-area">


                    <div class="logs-title-icon">

                        <i class="fa-solid fa-file-lines"></i>

                    </div>


                    <div>


                        <h2 class="logs-card-title">

                            <?php echo $t['log_output']; ?>

                        </h2>


                        <div class="logs-card-meta">

                            <span class="logs-live-status">

                                <span class="logs-live-dot"></span>

                                <?php echo $t['live_status']; ?>

                            </span>


                            <span class="logs-meta-divider"></span>


                            <span>

                                <?php echo $t['log_count']; ?>

                            </span>

                        </div>


                    </div>


                </div>


                <button
                    type="button"
                    class="logs-download-btn"
                    id="downloadLogsButton"
                >

                    <i class="fa-solid fa-download"></i>

                    <span>

                        <?php echo $t['download_log']; ?>

                    </span>

                </button>


            </div>


            <!-- Log Output -->

            <div
                class="logs-output"
                id="logsOutput"
            >


                <div
                    class="logs-window-controls"
                    aria-hidden="true"
                >

                    <span></span>
                    <span></span>
                    <span></span>

                </div>


                <div
                    class="log-line log-info"
                    data-log-line
                >

                    <span class="log-time">
                        4/17 10:22:20 PM
                    </span>

                    <span class="log-level">
                        [Info]
                    </span>

                    <span class="log-message">
                        Server initialized successfully.
                    </span>

                </div>


                <div
                    class="log-line log-info"
                    data-log-line
                >

                    <span class="log-time">
                        4/17 10:22:21 PM
                    </span>

                    <span class="log-level">
                        [Info]
                    </span>

                    <span class="log-message">
                        Loading workshop maps and configs...
                    </span>

                </div>


                <div
                    class="log-line log-success"
                    data-log-line
                >

                    <span class="log-time">
                        4/17 10:22:26 PM
                    </span>

                    <span class="log-level">
                        [Info]
                    </span>

                    <span class="log-message">
                        Ready for connections on port 25400.
                    </span>

                </div>


            </div>


        </section>


    </main>


</div>


<script
    src="js/logs.js"
    defer
></script>


</body>
</html>