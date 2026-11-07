<?php
declare(strict_types=1);

/**
 * Dedicated wallet-only entry point for VIP checkout.
 * The shared checkout processor performs the atomic wallet debit, records the
 * wallet transaction and VIP order, then provisions the membership. Forcing
 * the method here prevents this endpoint from entering the ZarinPal branch.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'روش ارسال درخواست معتبر نیست.';
    exit;
}

$_POST['payment_method'] = 'wallet';
require __DIR__ . '/../zarinpal/zarinpal_vip_request.php';
