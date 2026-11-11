<?php
declare(strict_types=1);

/**
 * Legacy VIP checkout is retired.
 *
 * New VIP orders are created only in Paymenter through /api/vip-store.php.
 * Keep this endpoint as an explicit 410 so stale links, cached pages or direct
 * POST requests cannot debit the legacy website wallet or create vip_orders.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
http_response_code(410);

echo json_encode([
    'success' => false,
    'error' => 'legacy_vip_checkout_retired',
    'message' => 'خرید VIP به Billing منتقل شده است. برای خرید و شارژ کیف پول از Paymenter استفاده کنید.',
    'billing_url' => 'https://billing.fragcity.ir',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
