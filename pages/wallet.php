<?php
// pages/wallet.php
$pageTitle = 'کیف پول و جوایز | FragCity';

require_once __DIR__ . '/../class/customer_db.php';
$db = new CustomerDB();

$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$steamid = (string) ($_SESSION['steamid'] ?? '');

$balance = 0;
$transactions = [];

if ($isLoggedIn && $steamid !== '') {
    // دریافت موجودی
    $wallet = $db->select(
        'SELECT balance FROM user_wallets WHERE steamid = ?',
        [$steamid]
    );

    $balance = $wallet ? (int) ($wallet[0]['balance'] ?? 0) : 0;

    // تاریخچه تراکنش‌ها
    $transactions = $db->select(
        'SELECT * FROM wallet_transactions WHERE steamid = ? ORDER BY created_at DESC LIMIT 20',
        [$steamid]
    ) ?: [];
}

function formatToman($amount): string
{
    return number_format((int) $amount) . ' تومان';
}

$typeMeta = [
    'deposit' => [
        'icon' => 'fa-arrow-down',
        'class' => 'transaction-positive',
        'prefix' => '+',
        'label' => 'شارژ',
    ],
    'purchase' => [
        'icon' => 'fa-shopping-cart',
        'class' => 'transaction-negative',
        'prefix' => '-',
        'label' => 'خرید',
    ],
    'withdraw' => [
        'icon' => 'fa-arrow-up',
        'class' => 'transaction-negative',
        'prefix' => '-',
        'label' => 'برداشت',
    ],
    'reward' => [
        'icon' => 'fa-gift',
        'class' => 'transaction-positive',
        'prefix' => '+',
        'label' => 'جایزه',
    ],
];

$statusLabels = [
    'pending' => 'در انتظار',
    'success' => 'موفق',
    'failed' => 'ناموفق',
    'completed' => 'تکمیل',
];
?>

<section class="wallet-page" aria-labelledby="walletPageTitle">
    <div class="wallet-page-header">
        <div class="wallet-page-heading">
            <div class="wallet-page-icon" aria-hidden="true">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <h1 id="walletPageTitle">کیف پول و جوایز</h1>
                <p>مدیریت موجودی، برداشت و جوایز شما</p>
            </div>
        </div>
    </div>

    <div class="wallet-container">
        <div class="wallet-balance-row">
            <div class="wallet-balance-card">
                <div class="wallet-balance-icon" aria-hidden="true">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div class="wallet-balance-content">
                    <span class="wallet-balance-label">موجودی کیف پول</span>
                    <strong class="wallet-balance-value">
                        <?= $isLoggedIn ? htmlspecialchars(formatToman($balance), ENT_QUOTES, 'UTF-8') : 'وارد شوید' ?>
                    </strong>
                </div>
            </div>

            <?php if ($isLoggedIn): ?>
                <!-- از همان آیدی‌ها و کلاس‌های دکمه عودت وجه استفاده شده تا کدهای JS شما کار کنند -->
                <button
                    type="button"
                    class="wallet-action-btn withdraw-btn"
                    id="openWithdrawBtn"
                >
                    <i class="fa-solid fa-plus-circle"></i>
                    <span>افزایش موجودی</span>
                </button>
            <?php endif; ?>
        </div>

        <div class="wallet-section-divider"></div>

        <section class="wallet-transactions" aria-labelledby="walletTransactionsTitle">
            <div class="wallet-section-header">
                <div>
                    <h2 id="walletTransactionsTitle">تاریخچه تراکنش‌ها</h2>
                    <p>آخرین فعالیت‌های کیف پول شما</p>
                </div>
                <span class="wallet-transaction-count">
                    <?= $isLoggedIn ? count($transactions) . ' تراکنش' : 'ورود لازم است' ?>
                </span>
            </div>

            <?php if (!$isLoggedIn): ?>
                <div class="wallet-empty-state">
                    <div class="wallet-empty-icon" aria-hidden="true">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h3>برای مشاهده تاریخچه وارد شوید</h3>
                    <p>پس از ورود، موجودی و آخرین تراکنش‌های کیف پول شما نمایش داده می‌شود.</p>
                    <a class="wallet-login-link" href="?page=login" data-ajax-nav data-slug="login">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        ورود / ثبت‌نام
                    </a>
                </div>
            <?php elseif (empty($transactions)): ?>
                <div class="wallet-empty-state">
                    <div class="wallet-empty-icon" aria-hidden="true">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <h3>تراکنشی یافت نشد</h3>
                    <p>هنوز فعالیتی برای نمایش در تاریخچه کیف پول شما ثبت نشده است.</p>
                </div>
            <?php else: ?>
                <div class="transaction-list">
                    <?php foreach ($transactions as $trans):
                        $type = (string) ($trans['type'] ?? '');
                        $meta = $typeMeta[$type] ?? [
                            'icon' => 'fa-arrow-right-arrow-left',
                            'class' => 'transaction-neutral',
                            'prefix' => '',
                            'label' => $type !== '' ? $type : 'تراکنش',
                        ];

                        $status = (string) ($trans['status'] ?? '');
                        $statusText = $statusLabels[$status] ?? $status;
                        $description = trim((string) ($trans['description'] ?? ''));
                        $createdAt = (string) ($trans['created_at'] ?? '');
                        $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
                        $dateText = $timestamp ? date('Y/m/d H:i', $timestamp) : '---';
                        $amount = (int) ($trans['amount'] ?? 0);
                    ?>
                        <article class="transaction-item">
                            <div class="transaction-icon <?= htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                <i class="fa-solid <?= htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            </div>

                            <div class="transaction-main">
                                <div class="transaction-title">
                                    <?= htmlspecialchars($description !== '' ? $description : $meta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="transaction-meta">
                                    <span><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="transaction-meta-separator">•</span>
                                    <time datetime="<?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($dateText, ENT_QUOTES, 'UTF-8') ?>
                                    </time>
                                </div>
                            </div>

                            <div class="transaction-amount-wrap">
                                <strong class="transaction-amount <?= htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($meta['prefix'] . formatToman($amount), ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <span class="transaction-status status-<?= htmlspecialchars($status !== '' ? $status : 'unknown', ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($statusText !== '' ? $statusText : 'نامشخص', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<!-- مودال شارژ کیف پول با همان استایل‌های مودال برداشت قبلی -->
<div
    class="withdraw-modal"
    id="withdrawModal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="withdrawModalTitle"
>
    <div class="withdraw-modal-backdrop" data-close-withdraw></div>

    <div class="withdraw-modal-dialog">
        <button
            type="button"
            class="withdraw-modal-close"
            id="closeWithdrawBtn"
            aria-label="بستن"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="withdraw-modal-icon" aria-hidden="true">
            <i class="fa-solid fa-wallet"></i>
        </div>

        <div class="withdraw-modal-heading">
            <h2 id="withdrawModalTitle">افزایش موجودی</h2>
            <p>مبلغ مورد نظر برای شارژ کیف پول خود را وارد کنید.</p>
        </div>

        <!-- فرم به صورت مستقیم به درگاه شما متصل می‌شود -->
        <form id="chargeForm" action="pages/zarinpal/zarinpal_wallet_request.php" method="POST" class="withdraw-form" novalidate>
            <div class="withdraw-field">
                <label for="chargeAmount">مبلغ شارژ (تومان)</label>
                <div class="withdraw-input-wrap">
                    <i class="fa-solid fa-coins" aria-hidden="true"></i>
                    <input
                        type="number"
                        id="chargeAmount"
                        name="amount"
                        placeholder="حداقل ۱,۰۰۰ تومان"
                        min="1000"
                        step="1000"
                        inputmode="numeric"
                        required
                    >
                </div>
            </div>

            <div class="withdraw-form-actions">
                <button type="submit" class="wallet-action-btn withdraw-submit-btn">
                    <i class="fa-solid fa-credit-card"></i>
                    <span>پرداخت</span>
                </button>

                <button type="button" class="wallet-secondary-btn" id="cancelWithdrawBtn">
                    انصراف
                </button>
            </div>
        </form>
    </div>
</div>