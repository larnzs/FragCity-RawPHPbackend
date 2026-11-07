<?php
// Existing FragCity customer support page.
// Customer ticket data is always loaded from Paymenter through /api/support.php.

require_once __DIR__ . '/../settings/loader.php';

$isLoggedIn = (($_SESSION['user_logged_in'] ?? false) === true);

$supportCsrfToken = $_SESSION['auth_csrf_token'] ?? '';
if (
    !is_string($supportCsrfToken)
    || !preg_match('/^[a-f0-9]{64}$/D', $supportCsrfToken)
) {
    $supportCsrfToken = bin2hex(random_bytes(32));
    $_SESSION['auth_csrf_token'] = $supportCsrfToken;
}

$activeTab = is_string($_GET['tab'] ?? null) ? $_GET['tab'] : 'contact';
if (!in_array($activeTab, ['contact', 'tickets'], true)) {
    $activeTab = 'contact';
}

$requestedTicketId = filter_var(
    $_GET['ticket_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$requestedTicketId = $requestedTicketId === false ? 0 : (int) $requestedTicketId;
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
        <a href="/support/?tab=contact" class="support-tab <?= $activeTab === 'contact' ? 'active' : '' ?>">
            <i class="fas fa-address-book"></i> تماس با ما
        </a>
        <a href="/support/?tab=tickets" class="support-tab <?= $activeTab === 'tickets' ? 'active' : '' ?>">
            <i class="fas fa-ticket-alt"></i> تیکت‌ها
        </a>
    </div>

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

    <?php elseif ($activeTab === 'tickets'): ?>
        <div
            class="ticket-section"
            id="fragcitySupportApp"
            data-endpoint="/api/support.php"
            data-csrf="<?= htmlspecialchars($supportCsrfToken, ENT_QUOTES, 'UTF-8') ?>"
            data-initial-ticket-id="<?= $requestedTicketId ?>"
        >
            <?php if (!$isLoggedIn): ?>
                <div class="auth-message error support-login-message">
                    <i class="fas fa-lock"></i> برای مشاهده و ارسال تیکت باید <a href="/login/" class="support-login-link">وارد شوید</a>.
                </div>
            <?php else: ?>
                <div class="toast-message" id="supportToast" role="status" aria-live="polite" hidden></div>

                <section class="ticket-create-card" id="supportCreateCard">
                    <h3><i class="fas fa-plus-circle"></i> ارسال تیکت جدید</h3>
                    <form id="supportCreateForm" class="ticket-form">
                        <div class="form-group">
                            <label for="supportSubject">موضوع تیکت</label>
                            <input
                                id="supportSubject"
                                name="subject"
                                type="text"
                                maxlength="255"
                                placeholder="موضوع را خلاصه بنویسید"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label for="supportMessage">متن تیکت</label>
                            <textarea
                                id="supportMessage"
                                name="message"
                                rows="5"
                                maxlength="5000"
                                placeholder="توضیح کامل مشکل یا سوال خود را بنویسید..."
                                required
                            ></textarea>
                        </div>
                        <button type="submit" class="gold-btn" id="supportCreateButton">
                            <i class="fas fa-paper-plane"></i> ارسال تیکت
                        </button>
                    </form>
                </section>

                <section class="ticket-list-card" id="supportListCard">
                    <div class="support-card-heading">
                        <h3><i class="fas fa-list-ul"></i> تیکت‌های شما</h3>
                        <button type="button" class="support-refresh-btn" id="supportRefreshButton" aria-label="به‌روزرسانی فهرست">
                            <i class="fas fa-rotate"></i>
                        </button>
                    </div>
                    <div class="ticket-list" id="supportTicketList" aria-live="polite">
                        <p class="text-muted">در حال دریافت تیکت‌ها...</p>
                    </div>
                </section>

                <section class="ticket-list-card support-ticket-detail" id="supportTicketDetail" hidden>
                    <div class="support-detail-heading">
                        <button type="button" class="support-back-btn" id="supportBackButton">
                            <i class="fas fa-arrow-right"></i> بازگشت به فهرست
                        </button>
                        <span class="ticket-status" id="supportDetailStatus"></span>
                    </div>
                    <h3 id="supportDetailSubject">جزئیات تیکت</h3>
                    <p class="ticket-date" id="supportDetailMeta"></p>
                    <div class="support-conversation" id="supportConversation" aria-live="polite">
                        <p class="text-muted">در حال دریافت مکالمه...</p>
                    </div>
                    <div class="support-detail-actions" id="supportDetailActions"></div>
                    <form class="support-reply-form" id="supportReplyForm">
                        <div class="form-group">
                            <label for="supportReplyMessage">پاسخ شما</label>
                            <textarea
                                id="supportReplyMessage"
                                name="message"
                                rows="4"
                                maxlength="5000"
                                placeholder="پیام خود را بنویسید..."
                                required
                            ></textarea>
                        </div>
                        <button type="submit" class="gold-btn" id="supportReplyButton">
                            <i class="fas fa-reply"></i> ارسال پاسخ
                        </button>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
