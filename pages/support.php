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

$supportCustomerName = trim((string) ($_SESSION['username'] ?? ''));
$supportCustomerEmail = '';
$supportDepartments = fragcity_settings('integrations.paymenter_support.departments', []);
if (!is_array($supportDepartments)) $supportDepartments = [];
$supportDepartments = array_values(array_unique(array_filter(
    array_map(static function ($department): string {
        if (!is_string($department)) return '';
        $department = trim(preg_replace('/[\\r\\n\\x00-\\x1F\\x7F]+/u', ' ', $department) ?? '');
        return mb_strlen($department, 'UTF-8') <= 100 ? $department : '';
    }, $supportDepartments),
    static fn (string $department): bool => $department !== ''
)));
if ($isLoggedIn && preg_match('/^[0-9]{17}$/D', (string) ($_SESSION['steamid'] ?? ''))) {
    try {
        $supportIndexSettings = fragcity_settings('files.index', []);
        $supportDatabaseProfile = is_array($supportIndexSettings) ? (string) ($supportIndexSettings['database_profile'] ?? 'main') : 'main';
        $supportPdo = fragcity_create_pdo($supportDatabaseProfile);
        if ($supportPdo instanceof PDO) {
            $supportAccountStatement = $supportPdo->prepare('SELECT username, email FROM users WHERE steamid64 = ? LIMIT 1');
            $supportAccountStatement->execute([(string) $_SESSION['steamid']]);
            $supportAccount = $supportAccountStatement->fetch(PDO::FETCH_ASSOC);
            if (is_array($supportAccount)) {
                $supportCustomerName = trim((string) ($supportAccount['username'] ?? $supportCustomerName));
                $supportCustomerEmail = trim((string) ($supportAccount['email'] ?? ''));
            }
        }
    } catch (Throwable $supportAccountError) {
        error_log('[FragCity] Support form contact details could not be loaded.');
    }
}
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
                <a href="https://t.me/fragcitychannel" target="_blank" rel="noopener noreferrer" class="social-item" aria-label="کانال تلگرام FragCity">
                    <img src="/assets/images/logo/telegram.png" alt="تلگرام" class="social-icon"
                         onerror="this.src='https://placehold.co/64x64?text=Telegram'">
                    <div class="social-info">
                        <h3>تلگرام</h3>
                        <p><i class="fa-brands fa-telegram"></i> @fragcitychannel | کانال رسمی FragCity</p>
                    </div>
                    <div class="arrow-link"><i class="fas fa-chevron-left"></i></div>
                </a>
                <a href="https://t.me/fragcitysup" target="_blank" rel="noopener noreferrer" class="social-item social-item--telegram-support" aria-label="پشتیبانی تلگرام FragCity">
                    <span class="social-icon social-icon--telegram-support" aria-hidden="true">
                        <i class="fa-brands fa-telegram"></i>
                    </span>
                    <div class="social-info">
                        <h3>پشتیبانی تلگرام</h3>
                        <p><i class="fa-brands fa-telegram"></i> @fragcitysup | ارتباط مستقیم با تیم پشتیبانی</p>
                    </div>
                    <div class="arrow-link"><i class="fas fa-chevron-left"></i></div>
                </a>
                <a href="https://steamcommunity.com/id/larnz/" target="_blank" rel="noopener noreferrer" class="social-item social-item--steam" aria-label="پشتیبانی FragCity در Steam">
                    <span class="social-icon social-icon--steam" aria-hidden="true">
                        <i class="fa-brands fa-steam"></i>
                    </span>
                    <div class="social-info">
                        <h3>پشتیبانی Steam</h3>
                        <p><i class="fa-brands fa-steam"></i> larnz | ارتباط مستقیم با پشتیبانی</p>
                    </div>
                    <div class="arrow-link"><i class="fas fa-chevron-left"></i></div>
                </a>
                <a href="https://discord.gg/s6rFThCDdY" target="_blank" rel="noopener noreferrer" class="social-item social-item--discord" aria-label="ورود به سرور Discord FragCity">
                    <span class="social-icon social-icon--discord" aria-hidden="true">
                        <i class="fa-brands fa-discord"></i>
                    </span>
                    <div class="social-info">
                        <h3>سرور Discord</h3>
                        <p><i class="fa-brands fa-discord"></i> FragCity | ورود به کامیونیتی</p>
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
                    <img src="/assets/images/logo/teamspeak.png" alt="TeamSpeak Logo" class="ts-logo"
                         onerror="this.src='https://placehold.co/110x110?text=TS3'">
                </div>
                <div class="ts-details">
                    <p>به voice چنل‌های اختصاصی Frag City بپیوندید. هماهنگی تیمی، تاکتیک‌های CS2 و ارتباط مستقیم با ادمین‌ها.</p>
                    <div class="server-badge">
                        <i class="fas fa-server"></i>
                        <code dir="ltr">217.18.90.7:2014</code>
                        <button class="copy-btn" id="copyTsAddress"><i class="far fa-copy"></i> کپی</button>
                    </div>
                    <a href="ts3server://217.18.90.7?port=2014" class="btn-join">
                        <i class="fas fa-headset"></i> اتصال به TeamSpeak
                    </a>
                    <div class="helper-note">
                        <i class="fas fa-info-circle"></i> برای اتصال، نرم‌افزار TeamSpeak 3 را نصب داشته باشید. در صورت نیاز به راهنما، از طریق تلگرام @fragcitysup با ما تماس بگیرید.
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

        <section class="support-contact-actions" aria-label="دسترسی سریع پشتیبانی">
            <div class="support-contact-actions-heading">
                <div>
                    <span class="support-form-eyebrow">NEED HELP?</span>
                    <h2>از کجا شروع کنیم؟</h2>
                    <p>برای پیگیری دقیق‌تر، تیکت ثبت کن یا از راه‌های ارتباطی رسمی با ما در تماس باش.</p>
                </div>
                <a href="/support/?tab=tickets" class="support-action-btn support-action-btn--primary">
                    <i class="fas fa-ticket-alt" aria-hidden="true"></i>
                    ثبت تیکت پشتیبانی
                </a>
            </div>
            <div class="support-contact-action-list">
                <a href="https://t.me/fragcitysup" target="_blank" rel="noopener noreferrer" class="support-action-btn support-action-btn--telegram">
                    <i class="fa-brands fa-telegram" aria-hidden="true"></i>
                    پشتیبانی تلگرام
                    <strong dir="ltr">@fragcitysup</strong>
                </a>
                <a href="https://t.me/fragcitychannel" target="_blank" rel="noopener noreferrer" class="support-action-btn support-action-btn--telegram">
                    <i class="fa-brands fa-telegram" aria-hidden="true"></i>
                    کانال تلگرام
                </a>
                <a href="https://discord.gg/s6rFThCDdY" target="_blank" rel="noopener noreferrer" class="support-action-btn support-action-btn--discord">
                    <i class="fa-brands fa-discord" aria-hidden="true"></i>
                    Discord
                </a>
                <a href="https://steamcommunity.com/id/larnz/" target="_blank" rel="noopener noreferrer" class="support-action-btn support-action-btn--steam">
                    <i class="fa-brands fa-steam" aria-hidden="true"></i>
                    پشتیبانی Steam
                </a>
                <a href="ts3server://217.18.90.7?port=2014" class="support-action-btn support-action-btn--teamspeak">
                    <i class="fas fa-headset" aria-hidden="true"></i>
                    اتصال به TeamSpeak
                </a>
                <a href="mailto:support@fragcity.ir" class="support-action-btn">
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    ایمیل پشتیبانی
                </a>
            </div>
        </section>

    <?php elseif ($activeTab === 'tickets'): ?>
        <div class="support-workspace-heading">
            <span class="support-workspace-icon" aria-hidden="true"><i class="fas fa-ticket-alt"></i></span>
            <div class="support-workspace-copy">
                <span class="support-workspace-eyebrow">TICKET MANAGEMENT</span>
                <h2>مرکز ثبت و پیگیری تیکت</h2>
                <p>درخواستت را ثبت کن و تمام پیام‌های تیم پشتیبانی را از همین بخش دنبال کن.</p>
            </div>
            <span class="support-workspace-badge"><i class="fas fa-shield-halved" aria-hidden="true"></i> پشتیبانی FragCity</span>
        </div>
        <div class="support-dashboard-layout<?= $isLoggedIn ? '' : ' is-guest' ?>">
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

                <section class="ticket-create-card support-create-card" id="supportCreateCard">
                    <div class="support-form-heading">
                        <div><span class="support-form-eyebrow">NEW SUPPORT REQUEST</span><h3><i class="fas fa-plus-circle"></i> ثبت درخواست پشتیبانی</h3><p>اطلاعات درخواست را کامل کن تا تیم FragCity سریع‌تر پیگیری کند.</p></div>
                        <span class="support-form-required-note"><i class="fas fa-asterisk"></i> فیلدهای ستاره‌دار الزامی‌اند</span>
                    </div>
                    <form id="supportCreateForm" class="ticket-form support-ticket-form" novalidate>
                        <div class="support-form-grid">
                            <div class="form-group"><label for="supportCustomerName">نام</label><input id="supportCustomerName" name="customer_name" type="text" value="<?= htmlspecialchars($supportCustomerName, ENT_QUOTES, 'UTF-8') ?>" maxlength="100" autocomplete="name" required><small class="support-field-hint">نامی که تیم پشتیبانی برای خطاب‌کردن شما استفاده می‌کند.</small></div>
                            <div class="form-group"><label for="supportCustomerEmail">آدرس ایمیل</label><input id="supportCustomerEmail" type="email" value="<?= htmlspecialchars($supportCustomerEmail, ENT_QUOTES, 'UTF-8') ?>" readonly aria-readonly="true" autocomplete="email"><small class="support-field-hint">برای تغییر ایمیل، از تنظیمات حساب استفاده کن.</small></div>
                            <div class="form-group support-form-full">
                                <label for="supportDepartment">بخش</label>
                                <select id="supportDepartment" name="department"><option value="">پشتیبانی عمومی</option><?php foreach ($supportDepartments as $department): ?><option value="<?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
                                <?php if ($supportDepartments === []): ?><small class="support-field-hint">درخواست به بخش عمومی ارسال می‌شود؛ بخش‌های اختصاصی پس از تنظیم در Paymenter نمایش داده خواهند شد.</small><?php endif; ?>
                            </div>
                            <div class="form-group support-form-full"><label for="supportSubject">موضوع <span class="support-required">*</span></label><input id="supportSubject" name="subject" type="text" maxlength="255" placeholder="موضوع درخواست را کوتاه و دقیق بنویس" required></div>
                            <fieldset class="form-group support-priority-group support-form-full"><legend>اولویت</legend><div class="support-priority-options">
                                <label class="support-priority-option"><input type="radio" name="priority" value="high"><span><i class="fas fa-angle-double-up" aria-hidden="true"></i> زیاد</span></label>
                                <label class="support-priority-option"><input type="radio" name="priority" value="medium" checked><span><i class="fas fa-equals" aria-hidden="true"></i> متوسط</span></label>
                                <label class="support-priority-option"><input type="radio" name="priority" value="low"><span><i class="fas fa-angle-down" aria-hidden="true"></i> کم</span></label>
                            </div></fieldset>
                            <div class="form-group"><label for="supportService">سرویس مربوطه</label><input id="supportService" name="service" type="text" maxlength="160" placeholder="نام سرویس، شناسه یا IP سرور"></div>
                            <div class="form-group"><label for="supportDomain">آدرس دامنه</label><input id="supportDomain" name="domain" type="text" maxlength="255" placeholder="مثلاً server.fragcity.ir" dir="ltr"></div>
                            <div class="form-group support-form-full"><label for="supportMessage">توضیحات تکمیلی <span class="support-required">*</span></label><textarea id="supportMessage" name="message" rows="6" maxlength="4200" placeholder="مشکل یا درخواستت را با جزئیات بنویس؛ مراحل تکرار، متن خطا و زمان رخداد را هم ذکر کن." required></textarea><small class="support-field-hint">حداکثر ۴۲۰۰ نویسه. اطلاعات این بخش به‌همراه مشخصات درخواست در Paymenter ذخیره می‌شود.</small></div>
                            <div class="form-group support-extra-fields-group support-form-full"><div class="support-extra-fields-heading"><label>موارد تکمیلی</label><button type="button" class="support-add-extra-btn" id="supportAddExtraButton"><i class="fas fa-plus" aria-hidden="true"></i> افزودن موارد دیگر</button></div><div id="supportExtraFields" class="support-extra-fields"></div></div>
                            <div class="support-attachments support-form-full" aria-label="ضمیمه‌ها">
                                <div class="support-attachments-heading"><span><i class="fas fa-paperclip" aria-hidden="true"></i> ضمیمه‌ها</span><span class="support-coming-soon">به‌زودی</span></div>
                                <div class="support-attachments-disabled"><i class="fas fa-cloud-arrow-up" aria-hidden="true"></i><div><strong>بارگذاری فایل فعلاً فعال نیست</strong><p>برای جلوگیری از انتخاب فایلی که در Paymenter ذخیره نمی‌شود، امکان پیوست تا زمان راه‌اندازی مسیر امن آپلود غیرفعال است.</p></div></div>
                                <div class="support-notice"><strong><i class="fas fa-circle-info" aria-hidden="true"></i> توجه</strong><p>از ارسال مدارک هویتی، اطلاعات حساس یا اسناد مالکیت در تیکت خودداری کن. برای پیگیری، فقط اطلاعات فنی موردنیاز را بنویس.</p></div>
                            </div>
                            <div class="support-captcha-wrap support-form-full">
                                <div class="support-captcha-copy"><label for="supportCaptchaAnswer">کد امنیتی <span class="support-required">*</span></label><span id="supportCaptchaQuestion" class="support-captcha-question" aria-live="polite">در حال دریافت کد امنیتی...</span></div>
                                <div class="support-captcha-controls"><input id="supportCaptchaAnswer" name="captcha_answer" type="text" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" autocomplete="off" placeholder="پاسخ" required><input id="supportCaptchaToken" type="hidden" name="captcha_token" value=""><button type="button" class="support-captcha-refresh" id="supportCaptchaRefreshButton" aria-label="تولید کد امنیتی جدید"><i class="fas fa-rotate" aria-hidden="true"></i></button></div>
                            </div>
                        </div>
                        <div class="support-form-actions"><p><i class="fas fa-shield-halved" aria-hidden="true"></i> اطلاعات حساب از پروفایل تأییدشده دریافت می‌شود.</p><button type="submit" class="gold-btn" id="supportCreateButton"><i class="fas fa-paper-plane" aria-hidden="true"></i> ثبت درخواست</button></div>
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

        <section class="support-faq" aria-labelledby="supportFaqTitle">
            <div class="support-faq-heading">
                <span class="support-faq-icon" aria-hidden="true"><i class="fas fa-circle-question"></i></span>
                <div>
                    <span class="support-faq-eyebrow">HELP CENTER</span>
                    <h2 id="supportFaqTitle">سؤالات متداول</h2>
                    <p>پاسخ پرسش‌های رایج درباره ثبت و پیگیری درخواست‌های پشتیبانی</p>
                </div>
            </div>

            <div class="support-faq-grid">
                <details class="support-faq-item">
                    <summary>
                        <span>چطور تیکت جدید ثبت کنم؟</span>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </summary>
                    <p>در فرم «ارسال تیکت جدید»، موضوع و توضیح کامل درخواستت را بنویس و دکمه ارسال را بزن. بعد از ثبت، تیکت در فهرست «تیکت‌های شما» نمایش داده می‌شود.</p>
                </details>

                <details class="support-faq-item">
                    <summary>
                        <span>پاسخ تیم پشتیبانی را از کجا ببینم؟</span>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </summary>
                    <p>از فهرست تیکت‌ها، درخواست موردنظرت را باز کن. پیام‌های تو و تیم پشتیبانی در همان صفحه و به ترتیب نمایش داده می‌شوند. برای دریافت آخرین وضعیت فهرست نیز از دکمه به‌روزرسانی استفاده کن.</p>
                </details>

                <details class="support-faq-item">
                    <summary>
                        <span>چطور به تیکت پاسخ بدهم؟</span>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </summary>
                    <p>تیکت را باز کن و متن پاسخ را در بخش «پاسخ شما» بنویس. تا زمانی که تیکت باز باشد، می‌توانی پیام‌های تکمیلی را در همان گفتگو ارسال کنی.</p>
                </details>

                <details class="support-faq-item">
                    <summary>
                        <span>اگر تیکت را بسته باشم، می‌توانم دوباره ادامه بدهم؟</span>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </summary>
                    <p>بله. تیکت بسته‌شده را باز کن و گزینه «بازکردن مجدد تیکت» را بزن. پس از باز شدن، امکان ارسال پاسخ دوباره فعال می‌شود.</p>
                </details>

                <details class="support-faq-item">
                    <summary>
                        <span>امکان ارسال تصویر یا فایل پیوست وجود دارد؟</span>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </summary>
                    <p>بارگذاری ضمیمه هنوز فعال نشده است؛ فعلاً متن خطا، شناسه سرور و جزئیات لازم را داخل توضیحات درخواست بنویس.</p>
                </details>

                <details class="support-faq-item">
                    <summary>
                        <span>چرا تیکت‌هایم را نمی‌بینم؟</span>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </summary>
                    <p>با همان حساب FragCity که هنگام ثبت تیکت وارد آن شده بودی وارد شو. اگر پیام مربوط به متصل نبودن حساب پشتیبانی را دیدی، از بخش تماس با ما با تیم FragCity ارتباط بگیر.</p>
                </details>
            </div>
        </section>
        </div>
    <?php endif; ?>
</div>
