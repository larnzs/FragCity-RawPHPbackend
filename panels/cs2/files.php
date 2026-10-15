<?php
// panel/files.php

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
            'مدیریت فایل‌ها | پنل مدیریت CS2',

        'nav_title' =>
            'داشبورد مدیریت سرور',

        'page_title' =>
            'مدیریت فایل',

        'page_desc' =>
            'مرور، آپلود و ویرایش فایل‌ها در دایرکتوری سرور خود.',

        'upload' =>
            'آپلود',

        'new_folder' =>
            'پوشه جدید',

        'th_name' =>
            'نام',

        'th_size' =>
            'حجم',

        'th_date' =>
            'تاریخ ویرایش',

        'th_actions' =>
            'عملیات',

        'today' =>
            'امروز، ۱۰:۲۲ ق.ظ'

    ],

    'en' => [

        'title' =>
            'Files | CS2 Admin Panel',

        'nav_title' =>
            'CS2 Admin Panel',

        'page_title' =>
            'File Manager',

        'page_desc' =>
            'Browse, upload, and edit files on your server directory.',

        'upload' =>
            'Upload',

        'new_folder' =>
            'New Folder',

        'th_name' =>
            'NAME',

        'th_size' =>
            'SIZE',

        'th_date' =>
            'DATE MODIFIED',

        'th_actions' =>
            'ACTIONS',

        'today' =>
            'Today, 10:22 AM'

    ]

];


$t = $translations[$lang];

$current_page = 'files';
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
    <link rel="stylesheet" href="css/files.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'header.php'; ?>

    <main class="main-content files-page">

        <div class="page-header files-page-header">

            <h1 class="page-title">
                <?php echo $t['page_title']; ?>
            </h1>

            <div class="page-desc">
                <?php echo $t['page_desc']; ?>
            </div>

        </div>


        <!-- Path Toolbar -->

        <section class="panel-card files-path-card">

            <div class="files-path">

                <i class="fa-solid fa-folder-open"></i>

                <span>
                    /home/container/
                </span>

            </div>


            <div class="files-actions">

                <button
                    type="button"
                    class="btn-secondary files-upload-btn"
                    id="filesUploadButton"
                >

                    <i class="fa-solid fa-upload"></i>

                    <?php echo $t['upload']; ?>

                </button>


                <button
                    type="button"
                    class="dashboard-btn files-folder-btn"
                    id="filesNewFolderButton"
                >

                    <i class="fa-solid fa-folder-plus"></i>

                    <?php echo $t['new_folder']; ?>

                </button>

            </div>

        </section>


        <input
            type="file"
            id="filesUploadInput"
            hidden
        >


        <!-- File Table -->

        <section class="servers-card files-card">

            <div class="files-table-header">

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


            <div
                class="files-table-row"
                data-file-row
            >

                <div class="file-name-cell">

                    <i class="fa-solid fa-folder"></i>

                    <span>
                        csgo
                    </span>

                </div>

                <div class="file-muted">
                    -
                </div>

                <div class="file-muted">
                    <?php echo $t['today']; ?>
                </div>

                <div>

                    <button
                        type="button"
                        class="file-more-btn"
                        aria-label="More actions"
                    >

                        <i class="fa-solid fa-ellipsis"></i>

                    </button>

                </div>

            </div>


            <div
                class="files-table-row"
                data-file-row
            >

                <div class="file-name-cell">

                    <i class="fa-solid fa-folder"></i>

                    <span>
                        700
                    </span>

                </div>

                <div class="file-muted">
                    -
                </div>

                <div class="file-muted">
                    <?php echo $t['today']; ?>
                </div>

                <div>

                    <button
                        type="button"
                        class="file-more-btn"
                        aria-label="More actions"
                    >

                        <i class="fa-solid fa-ellipsis"></i>

                    </button>

                </div>

            </div>

        </section>

    </main>

</div>

<script
    src="js/files.js"
    defer
></script>

</body>
</html>