<?php
// panel/admins.php

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
            'مدیریت ادمین‌ها | پنل مدیریت CS2',

        'nav_title' =>
            'داشبورد مدیریت سرور',

        'page_title' =>
            'ادمین‌ها',

        'page_desc' =>
            'مدیریت ادمین‌های سرور، نقش‌ها و دسترسی‌ها.',

        'list_title' =>
            'لیست ادمین‌ها',

        'list_desc' =>
            'کاربران دارای دسترسی مدیریتی روی این سرور.',

        'add_admin' =>
            'افزودن ادمین',

        'th_name' =>
            'نام / نام کاربری',

        'th_steam' =>
            'استیم آی‌دی / شناسه',

        'th_role' =>
            'نقش',

        'th_actions' =>
            'عملیات',

        'no_admins' =>
            'هیچ ادمینی یافت نشد. برای شروع یک ادمین اضافه کنید.'

    ],

    'en' => [

        'title' =>
            'Admins | CS2 Admin Panel',

        'nav_title' =>
            'CS2 Admin Panel',

        'page_title' =>
            'Admins',

        'page_desc' =>
            'Manage server administrators, roles, and permissions.',

        'list_title' =>
            'Administrator List',

        'list_desc' =>
            'Users with administrative privileges on this server.',

        'add_admin' =>
            'Add Admin',

        'th_name' =>
            'NAME / USERNAME',

        'th_steam' =>
            'STEAM ID / IDENTIFIER',

        'th_role' =>
            'ROLE',

        'th_actions' =>
            'ACTIONS',

        'no_admins' =>
            'No administrators found. Add an admin to get started.'

    ]

];


$t = $translations[$lang];

$current_page = 'admins';
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
    <link rel="stylesheet" href="css/admins.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>


<?php include 'sidebar.php'; ?>


<div class="main-content-wrapper">


    <?php include 'header.php'; ?>


    <main class="main-content admins-page">


        <!-- Page Header -->

        <div class="page-header admins-page-header">

            <h1 class="page-title">

                <?php echo $t['page_title']; ?>

            </h1>

            <div class="page-desc">

                <?php echo $t['page_desc']; ?>

            </div>

        </div>


        <!-- Toolbar -->

        <div class="admins-toolbar">


            <div>


                <h2 class="admins-list-title">

                    <?php echo $t['list_title']; ?>

                </h2>


                <p class="admins-list-desc">

                    <?php echo $t['list_desc']; ?>

                </p>


            </div>


            <button
                type="button"
                class="dashboard-btn admins-add-btn"
                id="addAdminButton"
            >

                <i class="fa-solid fa-user-plus"></i>

                <span>

                    <?php echo $t['add_admin']; ?>

                </span>

            </button>


        </div>


        <!-- Admins Table -->

        <section class="servers-card admins-card">


            <div class="admins-table-header">


                <div>

                    <?php echo $t['th_name']; ?>

                </div>


                <div>

                    <?php echo $t['th_steam']; ?>

                </div>


                <div>

                    <?php echo $t['th_role']; ?>

                </div>


                <div>

                    <?php echo $t['th_actions']; ?>

                </div>


            </div>


            <div class="admins-empty-state">


                <div class="admins-empty-icon">

                    <i class="fa-solid fa-user-shield"></i>

                </div>


                <div class="empty-title">

                    <?php echo $t['no_admins']; ?>

                </div>


            </div>


        </section>


    </main>


</div>


<script
    src="js/admins.js"
    defer
></script>


</body>
</html>