<?php
// panel/maps.php

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

$lang =
    $_SESSION['lang']
    ?? 'fa';

$is_fa =
    ($lang === 'fa');


/* =========================================================
   Translations
========================================================= */

$translations = [

    'fa' => [

        'title' =>
            'نقشه‌ها | پنل مدیریت CS2',

        'nav_title' =>
            'داشبورد مدیریت سرور',

        'page_title' =>
            'مدیریت نقشه‌ها',

        'page_desc' =>
            'تغییر مپ فعال سرور، افزودن نقشه‌های ورکشاپ و مدیریت چرخش نقشه‌ها (Map Cycle).',

        'active_map_title' =>
            'نقشه فعال فعلی',

        'change_map_btn' =>
            'تغییر نقشه',

        'list_title' =>
            'لیست نقشه‌های محبوب و ورکشاپ',

        'list_desc' =>
            'برای تغییر سریع نقشه روی دکمه بارگذاری کلیک کنید.',

        'th_map' =>
            'نام نقشه',

        'th_type' =>
            'نوع / گروه',

        'th_status' =>
            'وضعیت',

        'th_actions' =>
            'عملیات',

        'load_btn' =>
            'بارگذاری نقشه',

        'active_badge' =>
            'فعال'

    ],


    'en' => [

        'title' =>
            'Maps | CS2 Admin Panel',

        'nav_title' =>
            'CS2 Admin Panel',

        'page_title' =>
            'Map Management',

        'page_desc' =>
            'Change active server map, add workshop maps, and manage map cycles.',

        'active_map_title' =>
            'Current Active Map',

        'change_map_btn' =>
            'Change Map',

        'list_title' =>
            'Popular & Workshop Maps',

        'list_desc' =>
            'Click load to instantly switch the server map.',

        'th_map' =>
            'MAP NAME',

        'th_type' =>
            'TYPE / GROUP',

        'th_status' =>
            'STATUS',

        'th_actions' =>
            'ACTIONS',

        'load_btn' =>
            'Load Map',

        'active_badge' =>
            'ACTIVE'

    ]

];


$t =
    $translations[$lang];


$current_page =
    'maps';


$page_title =
    $t['nav_title'];

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


    <link
        rel="stylesheet"
        href="css/panel.css"
    >

    <link
        rel="stylesheet"
        href="css/panel-motion.css"
    >

    <link
        rel="stylesheet"
        href="css/maps.css"
    >

    <link
        rel="stylesheet"
        href="css/header.css"
    >

    <link
        rel="stylesheet"
        href="css/sidebar.css"
    >

</head>


<body>


<?php include 'sidebar.php'; ?>


<div class="main-content-wrapper">


    <?php include 'header.php'; ?>


    <main class="main-content maps-page">


        <!-- Page Header -->

        <div class="page-header maps-page-header">

            <h1 class="page-title">

                <?php echo $t['page_title']; ?>

            </h1>

            <div class="page-desc">

                <?php echo $t['page_desc']; ?>

            </div>

        </div>


        <!-- Active Map -->

        <section class="panel-card active-map-card">


            <div class="active-map-info">


                <div class="game-icon-badge active-map-icon">

                    <i class="fa-solid fa-map"></i>

                </div>


                <div>


                    <div class="active-map-label">

                        <?php echo $t['active_map_title']; ?>

                    </div>


                    <div class="active-map-name">

                        de_mirage

                    </div>


                </div>


            </div>


            <div class="active-map-status">


                <span class="status-badge online">

                    <span class="dot"></span>

                    <?php echo $t['active_badge']; ?>

                </span>


            </div>


        </section>


        <!-- List Header -->

        <div class="maps-list-header">


            <div>

                <h2>

                    <?php echo $t['list_title']; ?>

                </h2>


                <p>

                    <?php echo $t['list_desc']; ?>

                </p>

            </div>


        </div>


        <!-- Maps Table -->

        <div class="servers-card maps-card">


            <div class="maps-table-header">


                <div>
                    <?php echo $t['th_map']; ?>
                </div>


                <div>
                    <?php echo $t['th_type']; ?>
                </div>


                <div>
                    <?php echo $t['th_status']; ?>
                </div>


                <div>
                    <?php echo $t['th_actions']; ?>
                </div>


            </div>


            <!-- Mirage -->

            <div
                class="maps-table-row active-map-row"
                data-map-row
                data-map="de_mirage"
            >


                <div class="map-name-cell">


                    <i class="fa-solid fa-mountain-sun"></i>


                    <strong>
                        de_mirage
                    </strong>


                </div>


                <div class="map-type-cell">

                    Defusal (Competitive)

                </div>


                <div>

                    <span class="status-badge online">

                        <span class="dot"></span>

                        <?php echo $t['active_badge']; ?>

                    </span>

                </div>


                <div>

                    <button
                        type="button"
                        class="map-load-btn"
                        disabled
                    >

                        <i class="fa-solid fa-check"></i>

                        <?php echo $t['active_badge']; ?>

                    </button>

                </div>


            </div>


            <!-- Inferno -->

            <div
                class="maps-table-row"
                data-map-row
                data-map="de_inferno"
            >


                <div class="map-name-cell">


                    <i class="fa-solid fa-mountain-sun"></i>


                    <strong>
                        de_inferno
                    </strong>


                </div>


                <div class="map-type-cell">

                    Defusal (Competitive)

                </div>


                <div class="map-status-muted">

                    —

                </div>


                <div>

                    <button
                        type="button"
                        class="map-load-btn"
                        data-map-load="de_inferno"
                    >

                        <?php echo $t['load_btn']; ?>

                    </button>

                </div>


            </div>


            <!-- Nuke -->

            <div
                class="maps-table-row"
                data-map-row
                data-map="de_nuke"
            >


                <div class="map-name-cell">


                    <i class="fa-solid fa-mountain-sun"></i>


                    <strong>
                        de_nuke
                    </strong>


                </div>


                <div class="map-type-cell">

                    Defusal (Competitive)

                </div>


                <div class="map-status-muted">

                    —

                </div>


                <div>

                    <button
                        type="button"
                        class="map-load-btn"
                        data-map-load="de_nuke"
                    >

                        <?php echo $t['load_btn']; ?>

                    </button>

                </div>


            </div>


            <!-- Dust 2 -->

            <div
                class="maps-table-row"
                data-map-row
                data-map="de_dust2"
            >


                <div class="map-name-cell">


                    <i class="fa-solid fa-mountain-sun"></i>


                    <strong>
                        de_dust2
                    </strong>


                </div>


                <div class="map-type-cell">

                    Defusal (Competitive)

                </div>


                <div class="map-status-muted">

                    —

                </div>


                <div>

                    <button
                        type="button"
                        class="map-load-btn"
                        data-map-load="de_dust2"
                    >

                        <?php echo $t['load_btn']; ?>

                    </button>

                </div>


            </div>


        </div>


    </main>


</div>


<script
    src="js/maps.js"
    defer
></script>


</body>
</html>