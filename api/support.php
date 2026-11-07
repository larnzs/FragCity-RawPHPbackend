<?php
declare(strict_types=1);

/**
 * Customer-facing support JSON endpoint.
 *
 * Steam identity is taken only from the authenticated FragCity session.
 * Paymenter credentials remain in private server-side settings.
 */

require_once dirname(__DIR__) . '/settings/session.php';
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

try {
    $client = new PaymenterSupportApiClient();

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
            $subject = is_string($payload['subject'] ?? null)
                ? trim($payload['subject'])
                : '';
            $message = is_string($payload['message'] ?? null)
                ? trim($payload['message'])
                : '';
            if (
                $subject === ''
                || mb_strlen($subject, 'UTF-8') > 255
                || $message === ''
                || mb_strlen($message, 'UTF-8') > 5000
            ) {
                $respond(['success' => false, 'error' => 'validation_failed'], 422);
            }

            $created = $client->createTicket($steamId64, $subject, null, 'medium');
            $ticketId = (int) $created['ticket_id'];

            try {
                $client->replyTicket($steamId64, $ticketId, $message);
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
        'paymenter_support_ticket_closed' => 409,
        'paymenter_api_permission_denied' => 502,
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
        'paymenter_support_ticket_closed',
        'paymenter_api_permission_denied',
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
