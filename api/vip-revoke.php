<?php
declare(strict_types=1);

/**
 * Revoke the authenticated account's current VIP membership and refund every
 * unused paid second to the FragCity wallet.
 *
 * The VIPCore deletion is performed through the signed Linux API. The website
 * wallet credit and order-state changes are then committed atomically. A
 * deterministic wallet transaction authority makes retries idempotent if the
 * Linux API has already removed the membership but the website transaction
 * previously failed.
 */
require_once dirname(__DIR__) . '/settings/session.php';
require_once dirname(__DIR__) . '/class/VipCoreRepository.php';

fragcity_start_session();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

$normaliseToman = static function ($value): string {
    $formatted = number_format(max(0.0, (float)$value), 8, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted === '' ? '0' : $formatted;
};

$formatTomanUnits = static function (int $units): string {
    $whole = intdiv(max(0, $units), 100000000);
    $fraction = str_pad((string)(max(0, $units) % 100000000), 8, '0', STR_PAD_LEFT);
    $fraction = rtrim($fraction, '0');
    return (string)$whole . ($fraction !== '' ? '.' . $fraction : '');
};

$formatTomanLabel = static function (string $decimal): string {
    $parts = explode('.', $decimal, 2);
    $whole = number_format((int)($parts[0] ?? 0));
    return isset($parts[1]) && $parts[1] !== ''
        ? $whole . '.' . $parts[1]
        : $whole;
};

/**
 * Convert a paid Rial amount to 1e-8 Toman units using integer arithmetic.
 * This avoids binary-float rounding and retains fractional Toman precisely.
 */
$refundUnitsForWindow = static function (int $amountRial, int $remainingSeconds, int $windowSeconds): int {
    if ($amountRial < 1 || $remainingSeconds < 1 || $windowSeconds < 1) {
        return 0;
    }

    // Current catalogue values are far below this bound. Keep the operation
    // inside signed 64-bit arithmetic rather than silently overflowing.
    $maxSafeRial = intdiv(PHP_INT_MAX, 10000000);
    if ($amountRial > $maxSafeRial
        || $amountRial > intdiv(PHP_INT_MAX, max(1, $windowSeconds))) {
        throw new RuntimeException('vip_refund_amount_out_of_safe_range');
    }

    $eligibleSeconds = min($remainingSeconds, $windowSeconds);
    $product = $amountRial * $eligibleSeconds;
    $wholeRial = intdiv($product, $windowSeconds);
    $remainder = $product % $windowSeconds;

    // 1 Rial = 0.1 Toman; scale Toman by 1e8.
    $wholeUnits = $wholeRial * 10000000;
    $fractionUnits = intdiv(
        ($remainder * 10000000) + intdiv($windowSeconds, 2),
        $windowSeconds
    );

    if ($wholeUnits > PHP_INT_MAX - $fractionUnits) {
        throw new RuntimeException('vip_refund_amount_overflow');
    }

    return $wholeUnits + $fractionUnits;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (($_SESSION['user_logged_in'] ?? false) !== true) {
    $respond(['success' => false, 'error' => 'authentication_required'], 401);
}

$steamId64 = (string)($_SESSION['steamid'] ?? '');
if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
    $respond(['success' => false, 'error' => 'invalid_session'], 401);
}

$sessionCsrf = $_SESSION['auth_csrf_token'] ?? '';
$submittedCsrf = (string)($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? '');
if (!is_string($sessionCsrf)
    || !preg_match('/^[a-f0-9]{64}$/D', $sessionCsrf)
    || !hash_equals($sessionCsrf, $submittedCsrf)) {
    $respond(['success' => false, 'error' => 'csrf_failed'], 419);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength < 2 || $contentLength > 2048) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 2048) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

try {
    $payload = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

// No account identifier, order reference, VIP level, expiry or refund amount
// is accepted from the browser. All values are derived from the session,
// website order ledger and VIPCore's signed revocation response.
if (!is_array($payload) || $payload !== []) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

// Do not hold the PHP session lock while server-to-server calls run.
session_write_close();

try {
    $databaseProfile = fragcity_settings(
        'files.pages.zarinpal.vip_request.database_profile',
        'main'
    );
    $databaseProfile = is_string($databaseProfile) && trim($databaseProfile) !== ''
        ? trim($databaseProfile)
        : 'main';

    $ordersPdo = fragcity_create_pdo($databaseProfile);
    if (!$ordersPdo instanceof PDO) {
        throw new RuntimeException('vip_orders_database_unavailable');
    }

    // Fail closed before removing the live VIP entitlement unless the website
    // DB can persist fractional Toman and the exact upgrade-price window.
    $schemaQuery = $ordersPdo->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, NUMERIC_SCALE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND (
                (TABLE_NAME = \'user_wallets\' AND COLUMN_NAME = \'balance\')
                OR (TABLE_NAME = \'wallet_transactions\' AND COLUMN_NAME = \'amount\')
                OR (TABLE_NAME = \'vip_orders\' AND COLUMN_NAME = \'upgrade_remaining_seconds\')
           )'
    );
    $schemaQuery->execute();
    $schemaRows = $schemaQuery->fetchAll(PDO::FETCH_ASSOC);
    $schema = [];
    foreach ($schemaRows as $column) {
        $table = (string)($column['TABLE_NAME'] ?? '');
        $name = (string)($column['COLUMN_NAME'] ?? '');
        $schema[$table . '.' . $name] = [
            'type' => strtolower((string)($column['DATA_TYPE'] ?? '')),
            'scale' => (int)($column['NUMERIC_SCALE'] ?? 0),
        ];
    }

    $walletBalanceSchema = $schema['user_wallets.balance'] ?? null;
    $walletAmountSchema = $schema['wallet_transactions.amount'] ?? null;
    $upgradeWindowSchema = $schema['vip_orders.upgrade_remaining_seconds'] ?? null;

    if (!is_array($walletBalanceSchema)
        || $walletBalanceSchema['type'] !== 'decimal'
        || $walletBalanceSchema['scale'] < 8
        || !is_array($walletAmountSchema)
        || $walletAmountSchema['type'] !== 'decimal'
        || $walletAmountSchema['scale'] < 8
        || !is_array($upgradeWindowSchema)) {
        $respond([
            'success' => false,
            'error' => 'vip_refund_migration_required',
            'message' => 'زیرساخت بازپرداخت دقیق آماده نیست. ابتدا Migration دقت اعشاری کیف پول VIP را روی دیتابیس سایت اجرا کنید.',
        ], 503);
    }

    // VIPCore serializes revocation and records an audit row before deleting
    // vip_users. If the website transaction later fails, the same API request
    // can be retried and the same order reference will be returned.
    $revocation = (new VipCoreRepository())->revokeMembership($steamId64);

    if (($revocation['revoked'] ?? false) !== true) {
        $respond([
            'success' => false,
            'error' => 'membership_not_active',
            'message' => 'در حال حاضر اشتراک VIP فعالی برای ابطال وجود ندارد.',
        ], 409);
    }

    $orderRef = $revocation['order_ref'] ?? null;
    if ($orderRef !== null
        && (!is_string($orderRef) || !preg_match('/^[a-f0-9]{32}$/D', $orderRef))) {
        throw new RuntimeException('vip_revocation_order_reference_invalid');
    }

    $revokedAt = (int)($revocation['revoked_at'] ?? 0);
    $expires = (int)($revocation['expires'] ?? 0);
    if ($revokedAt < 1 || $expires < 0) {
        throw new RuntimeException('vip_revocation_timestamp_invalid');
    }

    $refundAuthority = 'VIPREFUND-' . (
        $orderRef !== null
            ? $orderRef
            : substr(hash('sha256', $steamId64 . '|' . $revokedAt), 0, 32)
    );

    $ordersPdo->beginTransaction();
    try {
        // Stable account-row lock serializes duplicate clicks even when no
        // wallet row exists yet. Refund and transaction writes share this DB
        // transaction, so a retry cannot credit the same cancellation twice.
        $accountLock = $ordersPdo->prepare(
            'SELECT steamid64
             FROM users
             WHERE steamid64 = ?
             LIMIT 1
             FOR UPDATE'
        );
        $accountLock->execute([$steamId64]);
        if ($accountLock->fetchColumn() === false) {
            throw new RuntimeException('vip_refund_account_not_found');
        }

        $existingRefundQuery = $ordersPdo->prepare(
            "SELECT id, amount, type, status
             FROM wallet_transactions
             WHERE steamid = ?
               AND authority = ?
             LIMIT 1
             FOR UPDATE"
        );
        $existingRefundQuery->execute([$steamId64, $refundAuthority]);
        $existingRefund = $existingRefundQuery->fetch(PDO::FETCH_ASSOC);

        $refundAmountToman = '0';
        $refundUnits = 0;
        $remainingSeconds = $expires > 0 ? max(0, $expires - $revokedAt) : 0;
        $subscriptionDurationSeconds = 0;

        if (is_array($existingRefund)) {
            if ((string)($existingRefund['type'] ?? '') !== 'deposit'
                || (string)($existingRefund['status'] ?? '') !== 'success') {
                throw new RuntimeException('vip_refund_authority_collision');
            }
            $refundAmountToman = $normaliseToman($existingRefund['amount'] ?? 0);
        } else {
            $chainOrderRefs = [];
            $cursorOrderRef = $orderRef;
            $depth = 0;

            // A paid upgrade is a chain of actual charges: the original plan
            // plus every incremental upgrade. Each charge is prorated against
            // the exact time window for which that amount was paid.
            while (is_string($cursorOrderRef) && $cursorOrderRef !== '') {
                if ($depth >= 12 || !preg_match('/^[a-f0-9]{32}$/D', $cursorOrderRef)) {
                    throw new RuntimeException('vip_refund_upgrade_chain_invalid');
                }
                if (isset($chainOrderRefs[$cursorOrderRef])) {
                    throw new RuntimeException('vip_refund_upgrade_chain_cycle');
                }
                $chainOrderRefs[$cursorOrderRef] = true;
                $depth++;

                $orderQuery = $ordersPdo->prepare(
                    'SELECT order_ref, steamid64, amount_rial, duration_seconds,
                            purchase_type, upgrade_from_order_ref,
                            upgrade_from_expires, upgrade_remaining_seconds,
                            status, UNIX_TIMESTAMP(created_at) AS created_epoch
                     FROM vip_orders
                     WHERE order_ref = ? AND steamid64 = ?
                     LIMIT 1
                     FOR UPDATE'
                );
                $orderQuery->execute([$cursorOrderRef, $steamId64]);
                $paidOrder = $orderQuery->fetch(PDO::FETCH_ASSOC);

                if (!is_array($paidOrder)) {
                    throw new RuntimeException('vip_refund_paid_order_not_found');
                }

                $orderStatus = strtolower((string)($paidOrder['status'] ?? ''));
                if (!in_array($orderStatus, ['paid', 'provisioned', 'revoked'], true)) {
                    throw new RuntimeException('vip_refund_paid_order_state_invalid');
                }

                $amountRial = (int)($paidOrder['amount_rial'] ?? 0);
                $durationSeconds = (int)($paidOrder['duration_seconds'] ?? 0);
                $purchaseType = strtolower((string)($paidOrder['purchase_type'] ?? ''));

                if ($amountRial < 1
                    || !in_array($purchaseType, ['new', 'replacement', 'upgrade'], true)) {
                    throw new RuntimeException('vip_refund_paid_order_amount_invalid');
                }

                if ($purchaseType === 'upgrade') {
                    $windowSeconds = (int)($paidOrder['upgrade_remaining_seconds'] ?? 0);
                    if ($windowSeconds < 1) {
                        // Legacy upgrades predate the exact window snapshot.
                        // Their checkout-created timestamp is the closest
                        // available server-side representation of that window.
                        $fromExpires = (int)($paidOrder['upgrade_from_expires'] ?? 0);
                        $createdEpoch = (int)($paidOrder['created_epoch'] ?? 0);
                        $windowSeconds = $fromExpires > $createdEpoch
                            ? $fromExpires - $createdEpoch
                            : 0;
                    }
                    $previousOrderRef = $paidOrder['upgrade_from_order_ref'] ?? null;
                    if (!is_string($previousOrderRef)
                        || !preg_match('/^[a-f0-9]{32}$/D', $previousOrderRef)
                        || $windowSeconds < 1) {
                        throw new RuntimeException('vip_refund_upgrade_window_invalid');
                    }
                } else {
                    $windowSeconds = $durationSeconds;
                    $previousOrderRef = null;
                    if ($windowSeconds < 1) {
                        throw new RuntimeException('vip_refund_paid_duration_invalid');
                    }
                    $subscriptionDurationSeconds = $windowSeconds;
                }

                $refundUnitsForOrder = $refundUnitsForWindow(
                    $amountRial,
                    $remainingSeconds,
                    $windowSeconds
                );
                if ($refundUnits > PHP_INT_MAX - $refundUnitsForOrder) {
                    throw new RuntimeException('vip_refund_total_overflow');
                }
                $refundUnits += $refundUnitsForOrder;
                $cursorOrderRef = $previousOrderRef;
            }

            // A paid order that has not yet reached VIPCore was never used.
            // Return its full verified payment as well, instead of leaving it
            // stranded when the customer revokes the currently active tier.
            $pendingPaidQuery = $ordersPdo->prepare(
                "SELECT order_ref, amount_rial
                 FROM vip_orders
                 WHERE steamid64 = ? AND status = 'paid'
                 ORDER BY id
                 FOR UPDATE"
            );
            $pendingPaidQuery->execute([$steamId64]);
            $pendingPaidRows = $pendingPaidQuery->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pendingPaidRows as $pendingPaid) {
                $pendingOrderRef = (string)($pendingPaid['order_ref'] ?? '');
                if ($pendingOrderRef !== '' && isset($chainOrderRefs[$pendingOrderRef])) {
                    continue;
                }

                $pendingAmountRial = (int)($pendingPaid['amount_rial'] ?? 0);
                if ($pendingAmountRial < 1
                    || $pendingAmountRial > intdiv(PHP_INT_MAX, 10000000)) {
                    throw new RuntimeException('vip_refund_pending_order_amount_invalid');
                }

                $fullRefundUnits = $pendingAmountRial * 10000000;
                if ($refundUnits > PHP_INT_MAX - $fullRefundUnits) {
                    throw new RuntimeException('vip_refund_total_overflow');
                }
                $refundUnits += $fullRefundUnits;
            }

            $refundAmountToman = $formatTomanUnits($refundUnits);

            if ($refundUnits > 0) {
                $walletLock = $ordersPdo->prepare(
                    'SELECT steamid
                     FROM user_wallets
                     WHERE steamid = ?
                     LIMIT 1
                     FOR UPDATE'
                );
                $walletLock->execute([$steamId64]);
                $walletExists = $walletLock->fetch(PDO::FETCH_ASSOC) !== false;

                if ($walletExists) {
                    $walletCredit = $ordersPdo->prepare(
                        'UPDATE user_wallets
                         SET balance = balance + CAST(? AS DECIMAL(20,8))
                         WHERE steamid = ?'
                    );
                    $walletCredit->execute([$refundAmountToman, $steamId64]);
                    if ($walletCredit->rowCount() !== 1) {
                        throw new RuntimeException('vip_refund_wallet_credit_failed');
                    }
                } else {
                    $createWallet = $ordersPdo->prepare(
                        'INSERT INTO user_wallets (steamid, balance)
                         VALUES (?, CAST(? AS DECIMAL(20,8)))'
                    );
                    $createWallet->execute([$steamId64, $refundAmountToman]);
                }

                $refundDescription = 'بازگشت اعتبار استفاده‌نشده VIP · ' . (
                    $orderRef !== null ? $orderRef : 'manual-membership'
                );
                $walletTransaction = $ordersPdo->prepare(
                    "INSERT INTO wallet_transactions
                        (steamid, amount, type, description, authority, status)
                     VALUES (?, CAST(? AS DECIMAL(20,8)), 'deposit', ?, ?, 'success')"
                );
                $walletTransaction->execute([
                    $steamId64,
                    $refundAmountToman,
                    $refundDescription,
                    $refundAuthority,
                ]);
            }
        }

        // The financial credit and the checkout-state release commit together.
        // A paid-but-not-provisioned order receives a full credit above;
        // pending ZarinPal orders remain untouched because their payment may
        // still be completed by the gateway.
        if ($orderRef !== null) {
            $markRevoked = $ordersPdo->prepare(
                "UPDATE vip_orders
                 SET status = 'revoked', last_error = 'customer_revoked_membership'
                 WHERE steamid64 = ?
                   AND (
                        status = 'paid'
                        OR (order_ref = ? AND status IN ('paid', 'provisioned'))
                   )"
            );
            $markRevoked->execute([$steamId64, $orderRef]);
        } else {
            $markRevoked = $ordersPdo->prepare(
                "UPDATE vip_orders
                 SET status = 'revoked', last_error = 'customer_revoked_membership'
                 WHERE steamid64 = ? AND status = 'paid'"
            );
            $markRevoked->execute([$steamId64]);
        }

        $ordersPdo->commit();
    } catch (Throwable $e) {
        if ($ordersPdo->inTransaction()) {
            $ordersPdo->rollBack();
        }
        throw $e;
    }

    $refundLabel = $formatTomanLabel($refundAmountToman);
    $successMessage = $refundUnits > 0 || (is_array($existingRefund) && (float)$refundAmountToman > 0)
        ? 'وضعیت VIP باطل شد و ' . $refundLabel . ' تومان اعتبار به کیف پول شما بازگشت داده شد.'
        : 'وضعیت VIP با موفقیت باطل شد؛ برای این اشتراک اعتبار قابل‌بازگشتی باقی نمانده بود.';

    $respond([
        'success' => true,
        'data' => [
            'revoked' => true,
            'already_revoked' => (bool)($revocation['already_revoked'] ?? false),
            'level' => (int)$revocation['level'],
            'group' => (string)$revocation['group'],
            'expires' => $expires,
            'revoked_at' => $revokedAt,
            'order_ref' => $orderRef,
            'refund_amount_toman' => $refundAmountToman,
            'refund_remaining_seconds' => $remainingSeconds,
            'refund_used_seconds' => $subscriptionDurationSeconds > 0
                ? max(0, $subscriptionDurationSeconds - $remainingSeconds)
                : 0,
            'message' => $successMessage,
        ],
    ]);
} catch (FragCityLinuxApiException $e) {
    $messages = [
        'membership_not_active' => 'در حال حاضر اشتراک VIP فعالی برای ابطال وجود ندارد.',
        'vipcore_disabled' => 'اتصال VIPCore روی سرور فعال نیست.',
        'vipcore_not_configured' => 'تنظیمات اتصال VIPCore کامل نیست.',
        'vipcore_unavailable' => 'دیتابیس VIPCore یا جدول تاریخچه ابطال در دسترس نیست.',
        'invalid_request' => 'درخواست ابطال معتبر نیست.',
    ];
    $safeCode = array_key_exists($e->errorCode, $messages)
        ? $e->errorCode
        : 'vipcore_unavailable';
    $status = match ($safeCode) {
        'membership_not_active' => 409,
        'invalid_request' => 422,
        default => 503,
    };
    $respond([
        'success' => false,
        'error' => $safeCode,
        'message' => $messages[$safeCode],
    ], $status);
} catch (Throwable $e) {
    error_log(
        '[FragCity VIP revoke] Revocation/refund failed; steam_hash='
        . substr(hash('sha256', $steamId64), 0, 16)
        . '; type=' . get_class($e)
        . '; code=' . (string)$e->getCode()
    );
    $respond([
        'success' => false,
        'error' => 'vip_revoke_unavailable',
        'message' => 'ابطال یا بازگشت اعتبار VIP کامل نشد. درخواست شما قابل بازیابی است؛ با پشتیبانی تماس بگیرید یا دوباره تلاش کنید.',
    ], 503);
}
