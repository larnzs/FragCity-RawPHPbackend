<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

/**
 * Dedicated read-only Paymenter Admin API adapter for server invoices.
 *
 * This client uses its own API key (integrations.paymenter_invoices).
 * It does not call the support bridge and exposes no write operations.
 */
final class PaymenterInvoiceApiClient
{
    private string $baseUrl;
    private string $adminApiKey;

    public function __construct(?string $baseUrl = null, ?string $adminApiKey = null)
    {
        $config = fragcity_settings('integrations.paymenter_invoices', []);
        $config = is_array($config) ? $config : [];

        $baseUrl = trim($baseUrl ?? (string) ($config['base_url'] ?? ''));
        $adminApiKey = trim($adminApiKey ?? (string) ($config['admin_api_key'] ?? ''));

        $parts = $baseUrl !== '' ? parse_url($baseUrl) : false;
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || !in_array($path, ['', '/'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new RuntimeException('paymenter_invoices_not_configured');
        }

        if (
            strlen($adminApiKey) < 20
            || strlen($adminApiKey) > 512
            || preg_match('/[\r\n\x00]/', $adminApiKey)
        ) {
            throw new RuntimeException('paymenter_invoices_not_configured');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('paymenter_invoice_curl_required');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->adminApiKey = $adminApiKey;
    }

    /**
     * Return only invoices whose Paymenter user relation matches both the
     * locally linked Paymenter user ID and the authenticated FragCity email.
     *
     * @return array{invoices:list<array<string,mixed>>,count:int,limit:int}
     */
    public function listInvoices(int $paymenterUserId, string $customerEmail, int $limit = 100): array
    {
        $customerEmail = mb_strtolower(trim($customerEmail));

        if (
            $paymenterUserId < 1
            || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException('paymenter_invoice_identity_not_synced');
        }

        $limit = min(100, max(1, $limit));

        $response = $this->nativeRequest('GET', '/invoices', [
            'filter' => ['user_id' => $paymenterUserId],
            'include' => 'items,user',
            'sort' => '-created_at',
            'page' => 1,
            'per_page' => $limit,
        ]);

        $included = $this->includedMap($response['included'] ?? []);
        $resources = $this->resourceList($response);
        $invoices = [];

        foreach ($resources as $resource) {
            $this->assertInvoiceOwner($resource, $included, $paymenterUserId, $customerEmail);
            $invoices[] = $this->normaliseInvoice($resource, $included);
        }

        return [
            'invoices' => $invoices,
            'count' => count($invoices),
            'limit' => $limit,
        ];
    }

    private function assertInvoiceOwner(
        array $resource,
        array $included,
        int $expectedUserId,
        string $expectedEmail
    ): void {
        $userReference = $resource['relationships']['user']['data'] ?? null;

        if (!is_array($userReference) || !isset($userReference['id'], $userReference['type'])) {
            throw new RuntimeException('paymenter_invoice_owner_unverified');
        }

        $userId = filter_var(
            $userReference['id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($userId === false || (int) $userId !== $expectedUserId) {
            throw new RuntimeException('paymenter_invoice_identity_mismatch');
        }

        $userKey = (string) $userReference['type'] . ':' . (string) $userReference['id'];
        $userResource = $included[$userKey] ?? null;
        $userAttributes = is_array($userResource['attributes'] ?? null)
            ? $userResource['attributes']
            : [];
        $paymenterEmail = mb_strtolower(trim((string) ($userAttributes['email'] ?? '')));

        if (
            !is_array($userResource)
            || !filter_var($paymenterEmail, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException('paymenter_invoice_owner_unverified');
        }

        if (!hash_equals($expectedEmail, $paymenterEmail)) {
            throw new RuntimeException('paymenter_invoice_identity_mismatch');
        }
    }

    /**
     * @param array<string,array<string,mixed>> $included
     * @return array<string,mixed>
     */
    private function normaliseInvoice(array $resource, array $included): array
    {
        $attributes = is_array($resource['attributes'] ?? null)
            ? $resource['attributes']
            : [];
        $invoiceId = filter_var(
            $resource['id'] ?? $attributes['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($invoiceId === false) {
            throw new RuntimeException('paymenter_invoice_invalid_response');
        }

        $itemReferences = $resource['relationships']['items']['data'] ?? [];
        if (!is_array($itemReferences)) {
            throw new RuntimeException('paymenter_invoice_invalid_response');
        }

        $items = [];
        $subtotal = 0.0;
        $pricingComplete = true;

        foreach ($itemReferences as $reference) {
            if (
                !is_array($reference)
                || !isset($reference['id'], $reference['type'])
            ) {
                $pricingComplete = false;
                continue;
            }

            $itemKey = (string) $reference['type'] . ':' . (string) $reference['id'];
            $itemResource = $included[$itemKey] ?? null;

            if (!is_array($itemResource)) {
                throw new RuntimeException('paymenter_invoice_items_unavailable');
            }

            $itemAttributes = is_array($itemResource['attributes'] ?? null)
                ? $itemResource['attributes']
                : [];
            $quantityRaw = $itemAttributes['quantity'] ?? 1;
            $priceRaw = $itemAttributes['price'] ?? null;

            if (!is_numeric($quantityRaw) || !is_numeric($priceRaw)) {
                $pricingComplete = false;
                $quantity = is_numeric($quantityRaw) ? (float) $quantityRaw : 1.0;
                $unitPrice = null;
                $lineTotal = null;
            } else {
                $quantity = (float) $quantityRaw;
                $unitPrice = round((float) $priceRaw, 2);
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;
            }

            $items[] = [
                'description' => mb_substr(
                    trim((string) ($itemAttributes['description'] ?? '')),
                    0,
                    500
                ),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'invoice_id' => (int) $invoiceId,
            'status' => strtolower(trim((string) ($attributes['status'] ?? 'unknown'))),
            'currency_code' => strtoupper(trim((string) ($attributes['currency_code'] ?? ''))),
            'due_at' => (string) ($attributes['due_at'] ?? ''),
            'created_at' => (string) ($attributes['created_at'] ?? ''),
            'items' => $items,
            'subtotal' => $pricingComplete ? round($subtotal, 2) : null,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function resourceList(array $response): array
    {
        $data = $response['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        return isset($data['id']) ? [$data] : [];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function includedMap(mixed $included): array
    {
        $map = [];
        if (!is_array($included)) {
            return $map;
        }

        foreach ($included as $resource) {
            if (
                is_array($resource)
                && isset($resource['type'], $resource['id'])
            ) {
                $map[(string) $resource['type'] . ':' . (string) $resource['id']] = $resource;
            }
        }

        return $map;
    }

    /**
     * @return array<string,mixed>
     */
    private function nativeRequest(string $method, string $path, array $query = []): array
    {
        $url = $this->baseUrl . '/api/v1/admin' . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('paymenter_invoice_unreachable');
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->adminApiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $rawResponse = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($rawResponse === false || $curlError !== 0) {
            error_log('[FragCity] Paymenter invoice transport failed; cURL code=' . $curlError . '.');
            throw new RuntimeException('paymenter_invoice_unreachable');
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            error_log('[FragCity] Paymenter invoice API returned invalid JSON; HTTP ' . $status . '.');
            throw new RuntimeException('paymenter_invoice_invalid_response');
        }

        if ($status < 200 || $status >= 300) {
            $safeError = match ($status) {
                400 => 'paymenter_api_permission_denied',
                401, 403 => 'paymenter_api_permission_denied',
                404 => 'paymenter_invoice_resource_not_found',
                422 => 'paymenter_invoice_validation_failed',
                429 => 'paymenter_invoice_rate_limited',
                default => 'paymenter_invoice_request_failed',
            };
            error_log('[FragCity] Paymenter invoice API rejected a request; HTTP ' . $status . '.');
            throw new RuntimeException($safeError);
        }

        return $decoded;
    }
}
