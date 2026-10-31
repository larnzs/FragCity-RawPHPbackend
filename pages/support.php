<?php
// pages/support.php
// Public page – contact tab always visible; tickets require login to view/submit

require_once __DIR__ . '/../settings/loader.php';

$isLoggedIn = (($_SESSION['user_logged_in'] ?? false) === true);
$steamid    = $_SESSION['steamid'] ?? '';
$isAdmin    = ($_SESSION['role'] ?? '') === 'admin';
$message    = '';
$error      = '';

$supportSettings = fragcity_settings('files.pages.support', []);
$supportProfile = isset($supportSettings['database_profile'])
    ? (string) $supportSettings['database_profile']
    : 'main';
$pdo = fragcity_create_pdo($supportProfile);

// Handle new ticket submission (only if logged in)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_ticket'])) {
    if (!$isLoggedIn) {
        $error = 'برای ارسال تیکت باید وارد شوید.';
    } elseif ($pdo) {
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['ticket_body'] ?? '');
        if (empty($subject) || empty($body)) {
            $error = 'موضوع و متن تیکت نمی‌تواند خالی باشد.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO tickets (steamid, subject) VALUES (?, ?)");
                $stmt->execute([$steamid, $subject]);
                $ticketId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, steamid, message) VALUES (?, ?, ?)");
                $stmt->execute([$ticketId, $steamid, $body]);
                $pdo->commit();
                $message = '✅ تیکت شما با موفقیت ثبت شد.';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[FragCity] Support ticket creation failed (SQLSTATE ' . $e->getCode() . ').');
                $error = 'در حال حاضر امکان ثبت تیکت وجود ندارد.';
            }
        }
    } else {
        $error = 'خطا در اتصال به دیتابیس.';
    }
}

// Fetch tickets (only if logged in)
$tickets = [];
if ($isLoggedIn && $pdo) {
    try {
        if ($isAdmin) {
            $tickets = $pdo->query("SELECT * FROM tickets ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tickets WHERE steamid = ? ORDER BY updated_at DESC");
            $stmt->execute([$steamid]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('[FragCity] Support ticket list failed (SQLSTATE ' . $e->getCode() . ').');
        $error = 'در حال حاضر امکان دریافت فهرست تیکت‌ها وجود ندارد.';
    }
}

$activeTab = $_GET['tab'] ?? 'contact';
?>


<div class="support-page">
    <div class="support-header">
        <h1>مرکز پشتیبانی <span class="glow-text">Frag City</span></h1>
        <div class="section-subhead">
            <i class="fas fa-comments"></i>
            <span>تیم پشتیبانی حرفه‌ای | پاسخگویی سریع در تمامی پلتفرم‌ها</span>
        </div>
    </div>

    <div class="support-tabs">
        <a href="?page=support&tab=contact" class="support-tab <?= $activeTab === 'contact' ? 'active' : '' ?>">
            <i class="fas fa-address-book"></i> تماس با ما
        </a>
        <a href="?page=support&tab=tickets" class="support-tab <?= $activeTab === 'tickets' ? 'active' : '' ?>">
            <i class="fas fa-ticket-alt"></i> تیکت‌ها
        </a>
    </div>

    <!-- ======================== تب تماس با ما (همیشه در دسترس) ======================== -->
    <?php if ($activeTab === 'contact'): ?>
        <p class="support-intro">
            اگر سوالی داری، به مشکل برخوردی یا نیاز به راهنمایی برای سرورهای <strong>Counter-Strike 2</strong> داری، تیم <strong>Frag City</strong> 
            همیشه کنارته. از طریق شبکه‌های اجتماعی رسمی با ما در ارتباط باش.
        </p>

        <div class="card support-card">
            <div class="card-title-icon">
                <i class="fas fa-share-alt"></i>
                <h2>شبکه‌های اجتماعی رسمی</h2>
            </div>
            <div class="social-grid">
                <a href="https://rubika.ir/fragcity_cs2" target="_blank" rel="noopener noreferrer" class="social-item">
                    <img src="./assets/images/logo/rubika.png" alt="روبیکا" class="social-icon" 
                         onerror="this.src='https://placehold.co/64x64?text=Rubika'">
                    <div class="social-info">
                        <h3>روبیکا</h3>
                        <p><i class="fas fa-paper-plane"></i> @fragcity_cs2 | پشتیبانی پیام فوری</p>
                    </div>
                    <div class="arrow-link"><i class="fas fa-chevron-left"></i></div>
                </a>
                <a href="https://t.me/fragcity_support" target="_blank" rel="noopener noreferrer" class="social-item">
                    <img src="./assets/images/logo/telegram.png" alt="تلگرام" class="social-icon" 
                         onerror="this.src='https://placehold.co/64x64?text=Telegram'">
                    <div class="social-info">
                        <h3>تلگرام</h3>
                        <p><i class="fas fa-bolt"></i> کانال اطلاع‌رسانی و تیکت پشتیبانی</p>
                    </div>
                    <div class="arrow-link"><i class="fas fa-chevron-left"></i></div>
                </a>
            </div>
            <p class="response-time">
                <i class="fas fa-clock"></i> میانگین زمان پاسخگویی کمتر از ۲ ساعت در ساعات اوج بازی
            </p>
        </div>

        <div class="divider-custom"></div>

        <div class="card support-card teamspeak-card">
            <div class="ts-header-flex">
                <i class="fas fa-users"></i>
                <h2>سرور TeamSpeak 3</h2>
                <span class="quality-badge"><i class="fas fa-waveform"></i> صوتی با کیفیت بالا</span>
            </div>
            <div class="teamspeak-container">
                <div class="ts-logo-area">
                    <img src="./assets/images/logo/teamspeak.png" alt="TeamSpeak Logo" class="ts-logo" 
                         onerror="this.src='https://placehold.co/110x110?text=TS3'">
                </div>
                <div class="ts-details">
                    <p>به voice چنل‌های اختصاصی Frag City بپیوندید. هماهنگی تیمی، تاکتیک‌های CS2 و ارتباط مستقیم با ادمین‌ها.</p>
                    <div class="server-badge">
                        <i class="fas fa-server"></i>
                        <code>ts.fragcity.ir:9987</code>
                        <button class="copy-btn" id="copyTsAddress"><i class="far fa-copy"></i> کپی</button>
                    </div>
                    <a href="ts3server://ts.fragcity.ir?port=9987" class="btn-join">
                        <i class="fas fa-headset"></i> اتصال به TeamSpeak
                    </a>
                    <div class="helper-note">
                        <i class="fas fa-info-circle"></i> نرم‌افزار TeamSpeak 3 را نصب داشته باشید. در صورت نیاز به راهنما، از طریق روبیکا یا تلگرام با ما تماس بگیرید.
                    </div>
                </div>
            </div>
        </div>

        <div class="support-footer">
            <div class="vip-support">
                <i class="fas fa-shield-alt"></i>
                <span>پشتیبانی VIP برای سرورهای اختصاصی</span>
            </div>
            <div class="email-support">
                <a href="mailto:support@fragcity.ir"><i class="fas fa-envelope"></i> support@fragcity.ir</a>
            </div>
        </div>

    <!-- ======================== تب تیکت‌ها ======================== -->
    <?php elseif ($activeTab === 'tickets'): ?>
        <div class="ticket-section">
            <?php if ($message): ?>
                <div class="toast-message success" id="toast"><?= $message ?></div>
            <?php elseif ($error): ?>
                <div class="toast-message error" id="toast"><?= $error ?></div>
            <?php endif; ?>

            <?php if (!$isLoggedIn): ?>
                <div class="auth-message error support-login-message">
                    <i class="fas fa-lock"></i> برای مشاهده و ارسال تیکت باید <a href="?page=login" class="support-login-link">وارد شوید</a>.
                </div>
            <?php else: ?>
                <!-- فرم ارسال تیکت -->
                <div class="ticket-create-card">
                    <h3><i class="fas fa-plus-circle"></i> ارسال تیکت جدید</h3>
                    <form method="post" class="ticket-form">
                        <input type="hidden" name="new_ticket" value="1">
                        <div class="form-group">
                            <label>موضوع تیکت</label>
                            <input type="text" name="subject" placeholder="موضوع را خلاصه بنویسید" required>
                        </div>
                        <div class="form-group">
                            <label>متن تیکت</label>
                            <textarea name="ticket_body" rows="5" placeholder="توضیح کامل مشکل یا سوال خود را بنویسید..." required></textarea>
                        </div>
                        <button type="submit" class="gold-btn">ارسال تیکت</button>
                    </form>
                </div>

                <!-- لیست تیکت‌ها -->
                <div class="ticket-list-card">
                    <h3><i class="fas fa-list-ul"></i> <?= $isAdmin ? 'همه تیکت‌ها' : 'تیکت‌های شما' ?></h3>
                    <?php if (empty($tickets)): ?>
                        <p class="text-muted">هیچ تیکتی یافت نشد.</p>
                    <?php else: ?>
                        <div class="ticket-list">
                            <?php foreach ($tickets as $t): ?>
                                <a href="?page=support&tab=tickets&ticket_id=<?= $t['id'] ?>" class="ticket-row">
                                    <div class="ticket-info">
                                        <strong>#<?= $t['id'] ?> – <?= htmlspecialchars($t['subject']) ?></strong>
                                        <span class="ticket-date"><?= $t['created_at'] ?></span>
                                    </div>
                                    <span class="ticket-status <?= $t['status'] ?>"><?= $t['status'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

