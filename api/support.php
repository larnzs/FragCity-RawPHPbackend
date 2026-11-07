<?php
declare(strict_types=1);

/**
 * Customer-facing support JSON endpoint.
 *
 * Steam identity is taken only from the authenticated FragCity session.
 * Paymenter credentials remain in private server-side settings.
 */

require_once dirname(__DIR__) . '/settings/session.php';
require_once dirname(__DIR__) . '/settings/loader.php';
require_once dirname(__DIR__) . '/class/PaymenterSupportApiClient.php';

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (($_SESSION['user_logged_in'] ?? false) !== true) {
    $respond(['success' => false, 'error' => 'authentication_required'], 401);
}

$steamId64 = is_string($_SESSION['steamid'] ?? null)
    ? (string) $_SESSION['steamid']
    : '';

if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
    $respond(['success' => false, 'error' => 'invalid_session'], 401);
}

$csrf = $_SESSION['auth_csrf_token'] ?? '';
$submittedCsrf = (string) ($_SERVER['HTTP_X_FRAGCITY_CSRF'] ?? '');

if (
    !is_string($csrf)
    || !preg_match('/^[a-f0-9]{64}$/D', $csrf)
    || !hash_equals($csrf, $submittedCsrf)
) {
    $respond(['success' => false, 'error' => 'csrf_failed'], 419);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 64 * 1024) {
    $respond(['success' => false, 'error' => 'request_too_large'], 413);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 64 * 1024) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

try {
    $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

if (!is_array($payload)) {
    $respond(['success' => false, 'error' => 'invalid_request'], 400);
}

$action = is_string($payload['action'] ?? null)
    ? trim($payload['action'])
    : '';

// A short-lived, one-use challenge. The answer is stored only as a hash in
// the server-side session and never sent back to the browser.
if ($action === 'captcha_challenge') {
    $left = random_int(2, 9);
    $right = random_int(1, 9);
    $token = bin2hex(random_bytes(24));
    $_SESSION['support_ticket_captcha'] = [
        'token' => $token,
        'answer_hash' => hash('sha256', (string) ($left + $right)),
        'expires_at' => time() + 300,
    ];
    $respond([
        'success' => true,
        'data' => [
            'question' => $left . ' + ' . $right . ' = ?',
            'token' => $token,
        ],
    ]);
}

if ($action === 'create_ticket') {
    $challenge = $_SESSION['support_ticket_captcha'] ?? [];
    unset($_SESSION['support_ticket_captcha']);
    $submittedToken = is_string($payload['captcha_token'] ?? null) ? $payload['captcha_token'] : '';
    $submittedAnswer = is_string($payload['captcha_answer'] ?? null) ? trim($payload['captcha_answer']) : '';
    $expectedToken = is_array($challenge) ? (string) ($challenge['token'] ?? '') : '';
    $expectedHash = is_array($challenge) ? (string) ($challenge['answer_hash'] ?? '') : '';
    $expiresAt = is_array($challenge) ? (int) ($challenge['expires_at'] ?? 0) : 0;

    if (
        $expectedToken === ''
        || $expectedHash === ''
        || $expiresAt < time()
        || $submittedAnswer === ''
        || !hash_equals($expectedToken, $submittedToken)
        || !hash_equals($expectedHash, hash('sha256', $submittedAnswer))
    ) {
        $respond(['success' => false, 'error' => 'captcha_invalid'], 422);
    }
}

try {
    // Resolve the customer's contact details from FragCity's own database.
    // Neither email, phone nor Paymenter user ID is accepted from the browser.
    $indexSettings = fragcity_settings('files.index', []);
    $databaseProfile = is_array($indexSettings)
        ? (string) ($indexSettings['database_profile'] ?? 'main')
        : 'main';
    $pdo = fragcity_create_pdo($databaseProfile);

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('fragcity_account_unavailable');
    }

    try {
        $accountStatement = $pdo->prepare(
            'SELECT username, email, phone, paymenter_user_id
             FROM users
             WHERE steamid64 = ?
             LIMIT 1'
        );
        $accountStatement->execute([$steamId64]);
        $customerAccount = $accountStatement->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $databaseError) {
        error_log('[FragCity] Support identity lookup failed; SQLSTATE=' . (string) $databaseError->getCode() . '.');
        throw new RuntimeException('fragcity_account_unavailable');
    }

    $localPaymenterUserId = filter_var(
        is_array($customerAccount) ? ($customerAccount['paymenter_user_id'] ?? null) : null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!is_array($customerAccount) || $localPaymenterUserId === false) {
        throw new RuntimeException('paymenter_support_identity_not_synced');
    }

    $client = new PaymenterSupportApiClient();
    $client->verifyCustomerIdentity(
        $steamId64,
        (string) ($customerAccount['email'] ?? ''),
        (string) ($customerAccount['phone'] ?? ''),
        (int) $localPaymenterUserId
    );

    switch ($action) {
        case 'list_tickets':
            $page = filter_var($payload['page'] ?? 1, FILTER_VALIDATE_INT);
            $page = $page === false ? 1 : max(1, $page);
            $result = $client->listTickets($steamId64, $page, 20);
            $respond(['success' => true, 'data' => $result]);

        case 'show_ticket':
            $ticketId = filter_var(
                $payload['ticket_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($ticketId === false) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }
            $ticket = $client->getTicket($steamId64, (int) $ticketId);
            $respond(['success' => true, 'data' => ['ticket' => $ticket]]);

        case 'create_ticket':
            $subject = is_string($payload['subject'] ?? null) ? trim($payload['subject']) : '';
            $description = is_string($payload['message'] ?? null) ? trim($payload['message']) : '';
            $priority = is_string($payload['priority'] ?? null) ? trim($payload['priority']) : 'medium';
            $department = is_string($payload['department'] ?? null) ? trim($payload['department']) : '';

            if (
                $subject === ''
                || mb_strlen($subject, 'UTF-8') > 255
                || $description === ''
                || mb_strlen($description, 'UTF-8') > 4200
                || !in_array($priority, ['low', 'medium', 'high'], true)
            ) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }

            $configuredDepartments = fragcity_settings('integrations.paymenter_support.departments', []);
            if (!is_array($configuredDepartments)) {
                $configuredDepartments = [];
            }
            if ($department !== '' && !in_array($department, $configuredDepartments, true)) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }
            $department = $department !== '' ? $department : null;

            $cleanOptionalField = static function (mixed $value, int $maxLength): string {
                if (!is_string($value)) {
                    return '';
                }
                $value = trim(preg_replace('/[\\x00-\\x1F\\x7F]+/u', ' ', $value) ?? '');
                return mb_strlen($value, 'UTF-8') <= $maxLength ? $value : '';
            };

            $service = $cleanOptionalField($payload['service'] ?? '', 160);
            $domain = $cleanOptionalField($payload['domain'] ?? '', 255);
            if (
                (is_string($payload['service'] ?? null) && trim($payload['service']) !== '' && $service === '')
                || (is_string($payload['domain'] ?? null) && trim($payload['domain']) !== '' && $domain === '')
            ) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }

            $extraFields = $payload['extra_fields'] ?? [];
            if (!is_array($extraFields) || count($extraFields) > 3) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }

            $messageParts = [
                'نام: ' . (trim((string) ($customerAccount['username'] ?? '')) ?: 'ثبت نشده'),
                'آدرس ایمیل: ' . trim((string) ($customerAccount['email'] ?? '')),
            ];
            if ($department !== null) {
                $messageParts[] = 'بخش: ' . $department;
            }
            if ($service !== '') {
                $messageParts[] = 'سرویس مربوطه: ' . $service;
            }
            if ($domain !== '') {
                $messageParts[] = 'آدرس دامنه: ' . $domain;
            }

            foreach ($extraFields as $extraField) {
                if (!is_array($extraField)) {
                    $respond(['success' => false, 'error' => 'validation_failed'], 422);
                }
                $label = $cleanOptionalField($extraField['label'] ?? '', 50);
                $value = $cleanOptionalField($extraField['value'] ?? '', 200);
                if ($label === '' || $value === '') {
                    $respond(['success' => false, 'error' => 'validation_failed'], 422);
                }
                $messageParts[] = $label . ': ' . $value;
            }

            $messageParts[] = "توضیحات تکمیلی:\n" . $description;
            $message = implode("\n", $messageParts);
            if (mb_strlen($message, 'UTF-8') > 5000) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }

            $created = $client->createTicket($steamId64, $subject, $department, $priority);
            $ticketId = (int) $created['ticket_id'];
            $createdForUserId = (int) ($created['owner_user_id'] ?? 0);

            try {
                $client->replyToNewTicket($steamId64, $ticketId, $createdForUserId, $message);
            } catch (Throwable $messageError) {
                error_log(
                    '[FragCity] Paymenter ticket was created but the first message failed; ticket_id='
                    . $ticketId . '; error=' . $messageError->getMessage() . '.'
                );
                $respond([
                    'success' => false,
                    'error' => 'ticket_created_message_failed',
                    'data' => ['ticket_id' => $ticketId],
                ], 502);
            }

            $respond([
                'success' => true,
                'data' => [
                    'ticket_id' => $ticketId,
                    'status' => 'open',
                ],
            ], 201);

        case 'reply_ticket':
            $ticketId = filter_var(
                $payload['ticket_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $message = is_string($payload['message'] ?? null)
                ? trim($payload['message'])
                : '';

            if ($ticketId === false || $message === '' || mb_strlen($message, 'UTF-8') > 5000) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }

            $result = $client->replyTicket($steamId64, (int) $ticketId, $message);
            $respond(['success' => true, 'data' => $result], 201);

        case 'close_ticket':
        case 'reopen_ticket':
            $ticketId = filter_var(
                $payload['ticket_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($ticketId === false) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }

            $result = $action === 'close_ticket'
                ? $client->closeTicket($steamId64, (int) $ticketId)
                : $client->reopenTicket($steamId64, (int) $ticketId);
            $respond(['success' => true, 'data' => $result]);

        case 'list_invoices':
            $page = filter_var($payload['page'] ?? 1, FILTER_VALIDATE_INT);
            $page = $page === false ? 1 : max(1, $page);
            $result = $client->listInvoices($steamId64, $page, 20);
            $respond(['success' => true, 'data' => $result]);

        case 'show_invoice':
            $invoiceId = filter_var(
                $payload['invoice_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($invoiceId === false) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }
            $invoice = $client->getInvoice($steamId64, (int) $invoiceId);
            $respond(['success' => true, 'data' => ['invoice' => $invoice]]);

        default:
            $respond(['success' => false, 'error' => 'invalid_action'], 400);
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    $status = match ($error) {
        'invalid_steamid' => 401,
        'fragcity_account_not_linked',
        'linked_paymenter_user_missing',
        'paymenter_support_ticket_not_found',
        'paymenter_support_invoice_not_found',
        'paymenter_support_resource_not_found' => 404,
        'paymenter_support_identity_not_synced',
        'paymenter_support_identity_mismatch',
        'paymenter_support_ticket_closed' => 409,
        'fragcity_account_unavailable' => 503,
        'paymenter_api_permission_denied' => 502,
        'paymenter_support_invalid_api_query' => 502,
        'paymenter_support_request_failed' => 502,
        'captcha_invalid' => 422,
        'paymenter_support_validation_failed' => 422,
        'paymenter_support_rate_limited' => 429,
        default => 502,
    };

    $safeErrors = [
        'invalid_steamid',
        'fragcity_account_not_linked',
        'linked_paymenter_user_missing',
        'paymenter_support_ticket_not_found',
        'paymenter_support_invoice_not_found',
        'paymenter_support_resource_not_found',
        'paymenter_support_identity_not_synced',
        'paymenter_support_identity_mismatch',
        'fragcity_account_unavailable',
        'paymenter_support_ticket_closed',
        'paymenter_api_permission_denied',
        'paymenter_support_invalid_api_query',
        'paymenter_support_request_failed',
        'captcha_invalid',
        'paymenter_support_validation_failed',
        'paymenter_support_rate_limited',
        'paymenter_unreachable',
        'paymenter_support_invalid_response',
        'paymenter_support_invalid_identity_response',
        'paymenter_support_invalid_ticket_response',
        'paymenter_support_invalid_message_response',
        'validation_failed',
    ];

    if (!in_array($error, $safeErrors, true)) {
        $error = 'paymenter_support_unavailable';
    }

    error_log('[FragCity] Support API action failed; action=' . $action . '; error=' . $error . '.');
    $respond(['success' => false, 'error' => $error], $status);
}
