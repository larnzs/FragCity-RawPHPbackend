<?php
// panel/server.php

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
            'سرورهای من | پنل',

        'nav_title' =>
            'داشبورد مدیریت سرور',

        'page_title' =>
            'سرورهای من',

        'page_desc' =>
            'سرورهای شما و سرورهایی که دوستانتان با شما به اشتراک گذاشته‌اند.',

        'search_placeholder' =>
            'جستجوی سرور، مثلاً Minecraft Let\'s Play...',

        'filter_by' =>
            'فیلتر بر اساس',

        'th_name' =>
            'نام',

        'th_game' =>
            'بازی',

        'th_status' =>
            'وضعیت',

        'th_memory' =>
            'حافظه',

        'th_players' =>
            'بازیکنان',

        'empty_title' =>
            'سروری برای نمایش وجود ندارد',

        'empty_desc' =>
            'داشتن یک سرور حس خوبی دارد، همین الان یکی تهیه کنید!'

    ],

    'en' => [

        'title' =>
            'My Servers | Panel',

        'nav_title' =>
            'CS2 Admin Panel',

        'page_title' =>
            'My Servers',

        'page_desc' =>
            'Your servers and servers that your friends have shared with you.',

        'search_placeholder' =>
            'Search for a server, e.g. Minecraft Let\'s Play...',

        'filter_by' =>
            'Filter by',

        'th_name' =>
            'NAME',

        'th_game' =>
            'GAME',

        'th_status' =>
            'STATUS',

        'th_memory' =>
            'MEMORY',

        'th_players' =>
            'PLAYERS',

        'empty_title' =>
            'No servers to show',

        'empty_desc' =>
            'It\'s nice to own a server, get one right now!'

    ]

];


$t = $translations[$lang];

$current_page = 'server';
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
    <link rel="stylesheet" href="css/server.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'header.php'; ?>

    <main class="main-content server-page">

        <div class="page-header server-page-header">

            <h1 class="page-title">
                <?php echo $t['page_title']; ?>
            </h1>

            <div class="page-desc">
                <?php echo $t['page_desc']; ?>
            </div>

        </div>


        <!-- Toolbar -->

        <div class="server-toolbar">

            <div class="server-search-filter">

                <div class="server-search-box">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="search"
                        id="serverSearch"
                        placeholder="<?php echo htmlspecialchars(
                            $t['search_placeholder'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        autocomplete="off"
                    >

                    <button
                        type="button"
                        class="server-search-clear"
                        id="serverSearchClear"
                    >

                        <i class="fa-solid fa-xmark"></i>

                    </button>

                </div>


                <div class="server-filter-wrapper">

                    <button
                        type="button"
                        class="filter-btn server-filter-btn"
                        id="serverFilterButton"
                        aria-expanded="false"
                    >

                        <?php echo $t['filter_by']; ?>

                        <i class="fa-solid fa-chevron-down"></i>

                    </button>


                    <div
                        class="server-filter-menu"
                        id="serverFilterMenu"
                    >

                        <button
                            type="button"
                            data-server-filter="all"
                        >
                            All
                        </button>

                        <button
                            type="button"
                            data-server-filter="online"
                        >
                            Online
                        </button>

                        <button
                            type="button"
                            data-server-filter="offline"
                        >
                            Offline
                        </button>

                    </div>

                </div>

            </div>


            <div class="view-options">

                <button
                    type="button"
                    class="view-btn"
                    data-server-view="grid"
                    title="Grid View"
                >

                    <i class="fa-solid fa-border-all"></i>

                </button>


                <button
                    type="button"
                    class="view-btn active"
                    data-server-view="list"
                    title="List View"
                >

                    <i class="fa-solid fa-bars"></i>

                </button>

            </div>

        </div>


        <!-- Servers Table -->

        <section class="servers-card server-card-container">

            <div class="server-table-header">

                <div>
                    <?php echo $t['th_name']; ?>

                    <i class="fa-solid fa-chevron-down"></i>

                </div>

                <div>
                    <?php echo $t['th_game']; ?>
                </div>

                <div>
                    <?php echo $t['th_status']; ?>
                </div>

                <div>
                    <?php echo $t['th_memory']; ?>
                </div>

                <div>
                    <?php echo $t['th_players']; ?>
                </div>

            </div>


            <div
                class="server-empty-state"
                id="serverEmptyState"
            >

                <div class="empty-graphic">

                    <div class="stack-box"></div>

                    <div class="stack-box"></div>

                    <div class="stack-box">

                        <div class="mini-dot"></div>

                        <div class="mini-line"></div>

                    </div>

                </div>


                <div class="empty-title">

                    <?php echo $t['empty_title']; ?>

                </div>


                <div class="empty-desc">

                    <?php echo $t['empty_desc']; ?>

                </div>

            </div>

        </section>

    </main>

</div>

<script
    src="js/server.js"
    defer
></script>

</body>
</html>