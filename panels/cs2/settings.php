<?php
require_once __DIR__ . '/../../settings/loader.php';
// General options are read from the central JSON config.
$panelSettingsOptions=fragcity_settings('files.panels.cs2.settings',array());
$panelSettingsAllowedLanguages=isset($panelSettingsOptions['allowed_languages'])&&is_array($panelSettingsOptions['allowed_languages'])?$panelSettingsOptions['allowed_languages']:array('fa','en');
$panelSettingsDefaultLanguage=isset($panelSettingsOptions['default_language'])?$panelSettingsOptions['default_language']:'fa';
if(session_status()===PHP_SESSION_NONE)session_start();
if(isset($_GET['lang'])&&in_array($_GET['lang'],$panelSettingsAllowedLanguages,true))$_SESSION['lang']=$_GET['lang'];
$lang=isset($_SESSION['lang'])?$_SESSION['lang']:$panelSettingsDefaultLanguage;$is_fa=($lang==='fa');


/* =========================================================
   Translations
========================================================= */

$translations = [

    'fa' => [

        'title' =>
            'تنظیمات | پنل مدیریت CS2',

        'nav_title' =>
            'داشبورد مدیریت سرور',

        'page_title' =>
            'تنظیمات پنل',

        'page_desc' =>
            'ظاهر پنل، اعلان‌ها و رفتارهای عمومی داشبورد را مدیریت کنید.',

        'appearance_title' =>
            'ظاهر و رابط کاربری',

        'appearance_desc' =>
            'نحوه نمایش و حرکت عناصر پنل را مشخص کنید.',

        'motion_title' =>
            'انیمیشن‌های رابط کاربری',

        'motion_desc' =>
            'ورود نرم صفحه‌ها و Transition دکمه‌ها و کارت‌ها فعال باشد.',

        'compact_sidebar_title' =>
            'سایدبار جمع‌وجور',

        'compact_sidebar_desc' =>
            'سایدبار در دسکتاپ فقط آیکون‌های منو را نمایش دهد.',

        'notifications_title' =>
            'اعلان‌ها',

        'notifications_desc' =>
            'مشخص کنید چه رویدادهایی در پنل به شما اعلام شوند.',

        'server_notifications_title' =>
            'تغییر وضعیت سرور',

        'server_notifications_desc' =>
            'هنگام روشن، خاموش یا ری‌استارت شدن سرور اعلان نمایش داده شود.',

        'backup_notifications_title' =>
            'وضعیت پشتیبان‌گیری',

        'backup_notifications_desc' =>
            'پس از تکمیل یا شکست عملیات Backup اعلان دریافت کنید.',

        'security_title' =>
            'امنیت و تأیید عملیات',

        'security_desc' =>
            'رفتار پنل هنگام اجرای عملیات حساس را کنترل کنید.',

        'confirm_actions_title' =>
            'تأیید عملیات خطرناک',

        'confirm_actions_desc' =>
            'قبل از خاموش کردن، ری‌استارت یا حذف اطلاعات تأیید گرفته شود.',

        'hints_title' =>
            'نمایش راهنماها',

        'hints_desc' =>
            'Tooltipها و توضیحات کوتاه رابط کاربری نمایش داده شوند.',

        'local_note' =>
            'این تنظیمات فعلاً در مرورگر فعلی ذخیره می‌شوند.',

        'save' =>
            'ذخیره تنظیمات',

        'reset' =>
            'بازنشانی',

        'saved' =>
            'تنظیمات با موفقیت ذخیره شد.',

        'reset_done' =>
            'تنظیمات به حالت پیش‌فرض بازگشت.',

        'unsaved' =>
            'تغییرات ذخیره‌نشده دارید.'

    ],


    'en' => [

        'title' =>
            'Settings | CS2 Admin Panel',

        'nav_title' =>
            'CS2 Admin Panel',

        'page_title' =>
            'Panel Settings',

        'page_desc' =>
            'Manage panel appearance, notifications, and general dashboard behavior.',

        'appearance_title' =>
            'Appearance & Interface',

        'appearance_desc' =>
            'Configure how panel elements look and move.',

        'motion_title' =>
            'Interface Animations',

        'motion_desc' =>
            'Enable page entrance effects and smooth button and card transitions.',

        'compact_sidebar_title' =>
            'Compact Sidebar',

        'compact_sidebar_desc' =>
            'Show only navigation icons in the desktop sidebar.',

        'notifications_title' =>
            'Notifications',

        'notifications_desc' =>
            'Choose which panel events should notify you.',

        'server_notifications_title' =>
            'Server Status Changes',

        'server_notifications_desc' =>
            'Show notifications when the server starts, stops, or restarts.',

        'backup_notifications_title' =>
            'Backup Status',

        'backup_notifications_desc' =>
            'Receive notifications when a backup completes or fails.',

        'security_title' =>
            'Security & Confirmation',

        'security_desc' =>
            'Control panel behavior for sensitive operations.',

        'confirm_actions_title' =>
            'Confirm Dangerous Actions',

        'confirm_actions_desc' =>
            'Ask for confirmation before shutdown, restart, or deletion.',

        'hints_title' =>
            'Show Interface Hints',

        'hints_desc' =>
            'Display tooltips and short interface explanations.',

        'local_note' =>
            'These preferences are currently stored in this browser.',

        'save' =>
            'Save Settings',

        'reset' =>
            'Reset',

        'saved' =>
            'Settings saved successfully.',

        'reset_done' =>
            'Settings were restored to defaults.',

        'unsaved' =>
            'You have unsaved changes.'

    ]

];


$t =
    $translations[$lang];


$current_page =
    'settings';


$page_title =
    $t['nav_title'];

?>
<!DOCTYPE html>

<html
    lang="<?= htmlspecialchars(
        $lang,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    dir="<?= $is_fa
        ? 'rtl'
        : 'ltr' ?>"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars(
            $t['title'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
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

    <link rel="stylesheet" href="css/settings.css">

    <link rel="stylesheet" href="css/header.css">

    <link rel="stylesheet" href="css/sidebar.css">

</head>

<body>


<?php include 'sidebar.php'; ?>


<div class="main-content-wrapper">


    <?php include 'header.php'; ?>


    <main
        class="main-content settings-page"
        data-saved-message="<?= htmlspecialchars(
            $t['saved'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        data-reset-message="<?= htmlspecialchars(
            $t['reset_done'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        data-unsaved-message="<?= htmlspecialchars(
            $t['unsaved'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >


        <!-- =================================================
             Page Header
        ================================================== -->

        <div class="page-header settings-page-header">


            <h1 class="page-title">

                <?= $t['page_title'] ?>

            </h1>


            <div class="page-desc">

                <?= $t['page_desc'] ?>

            </div>


        </div>


        <form
            class="settings-form"
            id="panelSettingsForm"
        >


            <div class="settings-grid">


                <!-- =========================================
                     Appearance
                ========================================== -->

                <section class="panel-card settings-section">


                    <div class="settings-section-header">


                        <div class="settings-section-icon">

                            <i class="fa-solid fa-wand-magic-sparkles"></i>

                        </div>


                        <div>


                            <h2>

                                <?= $t['appearance_title'] ?>

                            </h2>


                            <p>

                                <?= $t['appearance_desc'] ?>

                            </p>


                        </div>


                    </div>


                    <div class="settings-options">


                        <label class="settings-option">


                            <span class="settings-option-content">


                                <span class="settings-option-icon">

                                    <i class="fa-solid fa-bolt"></i>

                                </span>


                                <span>


                                    <strong>

                                        <?= $t['motion_title'] ?>

                                    </strong>


                                    <small>

                                        <?= $t['motion_desc'] ?>

                                    </small>


                                </span>


                            </span>


                            <input
                                type="checkbox"
                                name="motion"
                                id="settingsMotion"
                            >


                            <span class="settings-switch">

                                <span></span>

                            </span>


                        </label>


                        <label class="settings-option">


                            <span class="settings-option-content">


                                <span class="settings-option-icon">

                                    <i class="fa-solid fa-table-columns"></i>

                                </span>


                                <span>


                                    <strong>

                                        <?= $t['compact_sidebar_title'] ?>

                                    </strong>


                                    <small>

                                        <?= $t['compact_sidebar_desc'] ?>

                                    </small>


                                </span>


                            </span>


                            <input
                                type="checkbox"
                                name="compactSidebar"
                                id="settingsCompactSidebar"
                            >


                            <span class="settings-switch">

                                <span></span>

                            </span>


                        </label>


                    </div>


                </section>


                <!-- =========================================
                     Notifications
                ========================================== -->

                <section class="panel-card settings-section">


                    <div class="settings-section-header">


                        <div class="settings-section-icon">

                            <i class="fa-solid fa-bell"></i>

                        </div>


                        <div>


                            <h2>

                                <?= $t['notifications_title'] ?>

                            </h2>


                            <p>

                                <?= $t['notifications_desc'] ?>

                            </p>


                        </div>


                    </div>


                    <div class="settings-options">


                        <label class="settings-option">


                            <span class="settings-option-content">


                                <span class="settings-option-icon">

                                    <i class="fa-solid fa-server"></i>

                                </span>


                                <span>


                                    <strong>

                                        <?= $t['server_notifications_title'] ?>

                                    </strong>


                                    <small>

                                        <?= $t['server_notifications_desc'] ?>

                                    </small>


                                </span>


                            </span>


                            <input
                                type="checkbox"
                                name="serverNotifications"
                                id="settingsServerNotifications"
                            >


                            <span class="settings-switch">

                                <span></span>

                            </span>


                        </label>


                        <label class="settings-option">


                            <span class="settings-option-content">


                                <span class="settings-option-icon">

                                    <i class="fa-solid fa-box-archive"></i>

                                </span>


                                <span>


                                    <strong>

                                        <?= $t['backup_notifications_title'] ?>

                                    </strong>


                                    <small>

                                        <?= $t['backup_notifications_desc'] ?>

                                    </small>


                                </span>


                            </span>


                            <input
                                type="checkbox"
                                name="backupNotifications"
                                id="settingsBackupNotifications"
                            >


                            <span class="settings-switch">

                                <span></span>

                            </span>


                        </label>


                    </div>


                </section>


                <!-- =========================================
                     Security
                ========================================== -->

                <section class="panel-card settings-section settings-section-wide">


                    <div class="settings-section-header">


                        <div class="settings-section-icon">

                            <i class="fa-solid fa-shield"></i>

                        </div>


                        <div>


                            <h2>

                                <?= $t['security_title'] ?>

                            </h2>


                            <p>

                                <?= $t['security_desc'] ?>

                            </p>


                        </div>


                    </div>


                    <div class="settings-options settings-options-columns">


                        <label class="settings-option">


                            <span class="settings-option-content">


                                <span class="settings-option-icon">

                                    <i class="fa-solid fa-triangle-exclamation"></i>

                                </span>


                                <span>


                                    <strong>

                                        <?= $t['confirm_actions_title'] ?>

                                    </strong>


                                    <small>

                                        <?= $t['confirm_actions_desc'] ?>

                                    </small>


                                </span>


                            </span>


                            <input
                                type="checkbox"
                                name="confirmActions"
                                id="settingsConfirmActions"
                            >


                            <span class="settings-switch">

                                <span></span>

                            </span>


                        </label>


                        <label class="settings-option">


                            <span class="settings-option-content">


                                <span class="settings-option-icon">

                                    <i class="fa-solid fa-circle-info"></i>

                                </span>


                                <span>


                                    <strong>

                                        <?= $t['hints_title'] ?>

                                    </strong>


                                    <small>

                                        <?= $t['hints_desc'] ?>

                                    </small>


                                </span>


                            </span>


                            <input
                                type="checkbox"
                                name="interfaceHints"
                                id="settingsInterfaceHints"
                            >


                            <span class="settings-switch">

                                <span></span>

                            </span>


                        </label>


                    </div>


                </section>


            </div>


            <!-- =============================================
                 Footer
            ============================================== -->

            <div class="settings-footer">


                <div class="settings-footer-info">


                    <i class="fa-solid fa-circle-info"></i>


                    <span>

                        <?= $t['local_note'] ?>

                    </span>


                    <span
                        class="settings-change-status"
                        id="settingsChangeStatus"
                    ></span>


                </div>


                <div class="settings-footer-actions">


                    <button
                        type="button"
                        class="btn-secondary settings-reset-btn"
                        id="resetSettingsButton"
                    >

                        <i class="fa-solid fa-arrow-rotate-left"></i>

                        <?= $t['reset'] ?>

                    </button>


                    <button
                        type="submit"
                        class="dashboard-btn settings-save-btn"
                        id="saveSettingsButton"
                    >

                        <i class="fa-solid fa-floppy-disk"></i>

                        <span>

                            <?= $t['save'] ?>

                        </span>

                    </button>


                </div>


            </div>


        </form>


        <div
            class="settings-toast"
            id="settingsToast"
            role="status"
            aria-live="polite"
        >


            <i class="fa-solid fa-check"></i>


            <span></span>


        </div>


    </main>


</div>


<script
    src="js/settings.js"
    defer
></script>


</body>
</html>