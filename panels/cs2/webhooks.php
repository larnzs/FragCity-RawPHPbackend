<?php
// panel/webhooks.php

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
            'وب‌هوک‌ها | پنل مدیریت CS2',

        'nav_title' =>
            'داشبورد مدیریت سرور',

        'page_title' =>
            'وب‌هوک‌ها',

        'page_desc' =>
            'پیکربندی وب‌هوک‌های دیسکورد و یکپارچه‌سازی‌های اعلان خارجی.',

        'active_webhooks' =>
            'وب‌هوک‌های فعال',

        'active_desc' =>
            'اعلان‌های ارسال شده به پلتفرم‌های خارجی.',

        'add_webhook' =>
            'افزودن وب‌هوک',

        'th_name' =>
            'نام / پلتفرم',

        'th_url' =>
            'آدرس / نقطه پایانی',

        'th_status' =>
            'وضعیت',

        'th_actions' =>
            'عملیات',

        'empty_msg' =>
            'هیچ وب‌هوکی یافت نشد. برای شروع یک وب‌هوک اضافه کنید.'

    ],

    'en' => [

        'title' =>
            'Webhooks | CS2 Admin Panel',

        'nav_title' =>
            'CS2 Admin Panel',

        'page_title' =>
            'Webhooks',

        'page_desc' =>
            'Configure Discord webhooks and external notification integrations.',

        'active_webhooks' =>
            'Active Webhooks',

        'active_desc' =>
            'Notifications sent to external platforms.',

        'add_webhook' =>
            'Add Webhook',

        'th_name' =>
            'NAME / PLATFORM',

        'th_url' =>
            'URL / ENDPOINT',

        'th_status' =>
            'STATUS',

        'th_actions' =>
            'ACTIONS',

        'empty_msg' =>
            'No webhooks found. Add a webhook to get started.'

    ]

];


$t = $translations[$lang];

$current_page = 'webhooks';
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
    <link rel="stylesheet" href="css/webhooks.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>


<?php include 'sidebar.php'; ?>


<div class="main-content-wrapper">


    <?php include 'header.php'; ?>


    <main class="main-content webhooks-page">


        <!-- Page Header -->

        <div class="page-header webhooks-page-header">

            <h1 class="page-title">

                <?php echo $t['page_title']; ?>

            </h1>

            <div class="page-desc">

                <?php echo $t['page_desc']; ?>

            </div>

        </div>


        <!-- Toolbar -->

        <div class="webhooks-toolbar">


            <div>


                <h2 class="webhooks-list-title">

                    <?php echo $t['active_webhooks']; ?>

                </h2>


                <p class="webhooks-list-desc">

                    <?php echo $t['active_desc']; ?>

                </p>


            </div>


            <button
                type="button"
                class="dashboard-btn webhooks-add-btn"
                id="addWebhookButton"
            >

                <i class="fa-solid fa-plus"></i>

                <span>

                    <?php echo $t['add_webhook']; ?>

                </span>

            </button>


        </div>


        <!-- Table -->

        <section class="servers-card webhooks-card">


            <div class="webhooks-table-header">


                <div>

                    <?php echo $t['th_name']; ?>

                </div>


                <div>

                    <?php echo $t['th_url']; ?>

                </div>


                <div>

                    <?php echo $t['th_status']; ?>

                </div>


                <div>

                    <?php echo $t['th_actions']; ?>

                </div>


            </div>


            <div class="webhooks-empty-state">


                <div class="webhooks-empty-icon">

                    <i class="fa-brands fa-discord"></i>

                </div>


                <div class="empty-title">

                    <?php echo $t['empty_msg']; ?>

                </div>


            </div>


        </section>


    </main>


</div>


<script
    src="js/webhooks.js"
    defer
></script>


</body>
</html>