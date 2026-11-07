<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

/**
 * Server-side adapter for the Paymenter support bridge and native Admin API.
 *
 * The HMAC secret is used only for SteamID64 identity resolution.
 * The Paymenter Admin API key is used only by this PHP class and is never
 * returned to the browser.
 */
final class PaymenterSupportApiClient
{
    private string $baseUrl;
    private string $adminApiKey;
    private string $sharedSecret;

    public function __construct(
        ?string $baseUrl = null,
        ?string $adminApiKey = null,
        ?string $sharedSecret = null
    ) {
        $baseUrl = trim($baseUrl ?? (string) fragcity_settings('integrations.paymenter_support.base_url', ''));
        $adminApiKey = trim($adminApiKey ?? (string) fragcity_settings('integrations.paymenter_support.admin_api_key', ''));
        $sharedSecret = trim($sharedSecret ?? (string) fragcity_settings('integrations.paymenter_support.shared_secret', ''));

        $parts = parse_url($baseUrl);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
        ) {
            throw new RuntimeException('Paymenter support base URL must be an HTTPS origin without a path.');
        }

        if (
            $adminApiKey === ''
            || preg_match('/[\r\n\x00-\x1F\x7F]/', $adminApiKey)
        ) {
            throw new RuntimeException('Paymenter Admin API key is missing or invalid.');
        }

        if (
            !preg_match('/^[a-f0-9]{64,256}$/iD', $sharedSecret)
            || preg_match('/[\r\n\x00-\x1F\x7F]/', $sharedSecret)
        ) {
            throw new RuntimeException('Paymenter support HMAC secret is missing or invalid.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Paymenter support integration.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->adminApiKey = $adminApiKey;
        $this->sharedSecret = $sharedSecret;
    }

    /**
     * @return array{http_status:int,service:string,version:string}
     */
    public function testBridgeConnection(): array
    {
        $response = $this->hmacPost('/api/v1/fragcity/support/ping', []);

        return [
            'http_status' => 200,
            'service' => (string) ($response['service'] ?? ''),
            'version' => (string) ($response['version'] ?? ''),
        ];
    }

    /**
     * Verify read permissions without changing Paymenter records.
     *
     * @return array{tickets:bool,invoices:bool}
     */
    public function testNativeApiAccess(): array
    {
        $tickets = $this->nativeRequest('GET', '/tickets', [
            'per_page' => 1,
        ]);
        $messages = $this->nativeRequest('GET', '/ticket-messages', [
            'per_page' => 1,
        ]);
        $invoices = $this->nativeRequest('GET', '/invoices', [
            'per_page' => 1,
            'include' => 'items',
        ]);

        if (
            !array_key_exists('data', $tickets)
            || !array_key_exists('data', $messages)
            || !array_key_exists('data', $invoices)
        ) {
            throw new RuntimeException('paymenter_support_invalid_api_response');
        }

        return [
            'tickets' => true,
            'ticket_messages' => true,
            'invoices' => true,
            'invoice_items' => true,
        ];
    }

    public function resolvePaymenterUserId(string $steamId64): int
    {
        $this->assertSteamId($steamId64);

        $response = $this->hmacPost(
            '/api/v1/fragcity/support/identity/resolve',
            ['steamid64' => $steamId64]
        );

        $userId = filter_var(
            $response['data']['paymenter_user_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($userId === false) {
            throw new RuntimeException('paymenter_support_invalid_identity_response');
        }

        return (int) $userId;
    }

    /**
     * @return array{tickets:list<array<string,mixed>>,page:int,per_page:int}
     */
    public function listTickets(string $steamId64, int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        $userId = $this->resolvePaymenterUserId($steamId64);
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));

        $query = [
            'filter' => ['user_id' => $userId],
            'include' => 'user',
            'sort' => '-updated_at',
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($status !== null && in_array($status, ['open', 'closed', 'replied'], true)) {
            $query['filter']['status'] = $status;
        }

        $response = $this->nativeRequest('GET', '/tickets', $query);
        $tickets = [];

        foreach ($this->resourceList($response) as $resource) {
            // The Admin API key can see every customer's tickets. Never trust
            // collection filters alone: verify each returned owner server-side.
            // Missing owner identity is rejected, not treated as a match.
            if ($this->resourceOwnerUserId($resource) !== $userId) {
                continue;
            }

            $tickets[] = $this->normaliseTicket($resource, $response['included'] ?? []);
        }

        return [
            'tickets' => $tickets,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Paymenter API keys are administrative. The collection filter is only a
     * lookup optimization; exact ticket ownership is checked against the
     * resource owner before and after the direct show request.
     *
     * @return array<string,mixed>
     */
    public function getTicket(string $steamId64, int $ticketId): array
    {
        $userId = $this->resolvePaymenterUserId($steamId64);
        $this->assertOwnedResource('/tickets', $ticketId, $userId);

        $response = $this->nativeRequest('GET', '/tickets/' . $ticketId, [
            'include' => 'messages,user',
        ]);
        $resource = $response['data'] ?? null;

        if (
            !is_array($resource)
            || !isset($resource['id'])
            || $this->resourceOwnerUserId($resource) !== $userId
        ) {
            throw new RuntimeException('paymenter_support_ticket_not_found');
        }

        return $this->normaliseTicket($resource, $response['included'] ?? []);
    }

    /**
     * Creates a native Paymenter ticket. The first message is a separate API
     * call and is deliberately handled separately by the website endpoint.
     *
     * @return array<string,mixed>
     */
    public function createTicket(
        string $steamId64,
        string $subject,
        ?string $department = null,
        string $priority = 'medium'
    ): array {
        $userId = $this->resolvePaymenterUserId($steamId64);
        $subject = trim($subject);

        if ($subject === '' || mb_strlen($subject, 'UTF-8') > 255) {
            throw new RuntimeException('validation_failed');
        }

        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            throw new RuntimeException('validation_failed');
        }

        $payload = [
            'subject' => $subject,
            'user_id' => $userId,
            'priority' => $priority,
            'status' => 'open',
        ];

        if ($department !== null && trim($department) !== '') {
            $department = trim($department);
            if (mb_strlen($department, 'UTF-8') > 100) {
                throw new RuntimeException('validation_failed');
            }
            $payload['department'] = $department;
        }

        $response = $this->nativeRequest('POST', '/tickets', [], $payload);
        $resource = $response['data'] ?? null;
        $ticketId = is_array($resource) ? (int) ($resource['id'] ?? 0) : 0;

        if ($ticketId < 1) {
            throw new RuntimeException('paymenter_support_invalid_ticket_response');
        }

        return [
            'ticket_id' => $ticketId,
            // Internal server-side handoff only. This field is never returned
            // by api/support.php to the browser.
            'owner_user_id' => $userId,
            'subject' => $subject,
            'status' => (string) ($resource['attributes']['status'] ?? 'open'),
            'priority' => (string) ($resource['attributes']['priority'] ?? $priority),
        ];
    }

    /**
     * Add the first message to a ticket created moments earlier in the same
     * server-side request. The owner ID is supplied only from createTicket(),
     * never from the browser, and is rebound to the current Steam session.
     *
     * @return array{message_id:int}
     */
    public function replyToNewTicket(
        string $steamId64,
        int $ticketId,
        int $createdForUserId,
        string $message
    ): array {
        $this->assertSteamId($steamId64);
        $message = trim($message);

        if (
            $ticketId < 1
            || $createdForUserId < 1
            || $message === ''
            || mb_strlen($message, 'UTF-8') > 5000
        ) {
            throw new RuntimeException('validation_failed');
        }

        $userId = $this->resolvePaymenterUserId($steamId64);
        if ($createdForUserId !== $userId) {
            throw new RuntimeException('paymenter_support_ticket_not_found');
        }

        // The ticket ID came directly from the successful createTicket()
        // response in this same request. No second Admin API read is needed.
        return $this->postTicketMessage($userId, $ticketId, $message);
    }

    /**
     * @return array{message_id:int}
     */
    public function replyTicket(string $steamId64, int $ticketId, string $message): array
    {
        $message = trim($message);
        if ($ticketId < 1 || $message === '' || mb_strlen($message, 'UTF-8') > 5000) {
            throw new RuntimeException('validation_failed');
        }

        $userId = $this->resolvePaymenterUserId($steamId64);
        $ticket = $this->getTicketForUser($ticketId, $userId);

        if (($ticket['status'] ?? '') === 'closed') {
            throw new RuntimeException('paymenter_support_ticket_closed');
        }

        return $this->postTicketMessage($userId, $ticketId, $message);
    }

    /**
     * @return array{message_id:int}
     */
    private function postTicketMessage(int $userId, int $ticketId, string $message): array
    {
        $response = $this->nativeRequest('POST', '/ticket-messages', [], [
            'message' => $message,
            'user_id' => $userId,
            'ticket_id' => $ticketId,
        ]);

        $resource = $response['data'] ?? null;
        $messageId = is_array($resource) ? (int) ($resource['id'] ?? 0) : 0;

        if ($messageId < 1) {
            throw new RuntimeException('paymenter_support_invalid_message_response');
        }

        return ['message_id' => $messageId];
    }

    public function closeTicket(string $steamId64, int $ticketId): array
    {
        return $this->setTicketStatus($steamId64, $ticketId, 'closed');
    }

    public function reopenTicket(string $steamId64, int $ticketId): array
    {
        return $this->setTicketStatus($steamId64, $ticketId, 'open');
    }

    /**
     * @return array<string,mixed>
     */
    public function listInvoices(string $steamId64, int $page = 1, int $perPage = 20): array
    {
        $userId = $this->resolvePaymenterUserId($steamId64);
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));

        $response = $this->nativeRequest('GET', '/invoices', [
            'filter' => ['user_id' => $userId],
            'include' => 'items',
            'sort' => '-created_at',
            'page' => $page,
            'per_page' => $perPage,
        ]);

        $invoices = [];
        foreach ($this->resourceList($response) as $resource) {
            $invoices[] = $this->normaliseInvoice($resource, $response['included'] ?? []);
        }

        return [
            'invoices' => $invoices,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function getInvoice(string $steamId64, int $invoiceId): array
    {
        $userId = $this->resolvePaymenterUserId($steamId64);
        $this->assertOwnedResource('/invoices', $invoiceId, $userId);

        $response = $this->nativeRequest('GET', '/invoices/' . $invoiceId, [
            'include' => 'items',
        ]);
        $resource = $response['data'] ?? null;

        if (!is_array($resource) || !isset($resource['id'])) {
            throw new RuntimeException('paymenter_support_invoice_not_found');
        }

        return $this->normaliseInvoice($resource, $response['included'] ?? []);
    }

    private function setTicketStatus(string $steamId64, int $ticketId, string $status): array
    {
        if ($ticketId < 1 || !in_array($status, ['open', 'closed'], true)) {
            throw new RuntimeException('validation_failed');
        }

        $userId = $this->resolvePaymenterUserId($steamId64);
        $this->assertOwnedResource('/tickets', $ticketId, $userId);

        $response = $this->nativeRequest('PATCH', '/tickets/' . $ticketId, [], [
            'status' => $status,
        ]);

        $resource = $response['data'] ?? null;

        return [
            'ticket_id' => $ticketId,
            'status' => is_array($resource)
                ? (string) ($resource['attributes']['status'] ?? $status)
                : $status,
        ];
    }

    private function getTicketForUser(int $ticketId, int $userId): array
    {
        $this->assertOwnedResource('/tickets', $ticketId, $userId);

        $response = $this->nativeRequest('GET', '/tickets/' . $ticketId, [
            'include' => 'messages,user',
        ]);
        $resource = $response['data'] ?? null;

        if (
            !is_array($resource)
            || !isset($resource['id'])
            || $this->resourceOwnerUserId($resource) !== $userId
        ) {
            throw new RuntimeException('paymenter_support_ticket_not_found');
        }

        return $this->normaliseTicket($resource, $response['included'] ?? []);
    }

    private function assertOwnedResource(string $collectionPath, int $resourceId, int $userId): void
    {
        $query = [
            'filter' => [
                'id' => $resourceId,
                'user_id' => $userId,
            ],
            'per_page' => 1,
        ];

        if ($collectionPath === '/tickets') {
            $query['include'] = 'user';
        }

        $response = $this->nativeRequest('GET', $collectionPath, $query);

        $resources = $this->resourceList($response);
        if ($resources === []) {
            throw new RuntimeException(
                $collectionPath === '/tickets'
                    ? 'paymenter_support_ticket_not_found'
                    : 'paymenter_support_invoice_not_found'
            );
        }

        $matchedId = (int) ($resources[0]['id'] ?? 0);
        $matchedUserId = $collectionPath === '/tickets'
            ? $this->resourceOwnerUserId($resources[0])
            : (int) ($resources[0]['attributes']['user_id'] ?? 0);

        // Ticket ownership must be explicit. Admin API collection filters are
        // not an authorization boundary and a missing owner ID fails closed.
        $ownerMismatch = $collectionPath === '/tickets'
            ? $matchedUserId !== $userId
            : ($matchedUserId > 0 && $matchedUserId !== $userId);

        if ($matchedId !== $resourceId || $ownerMismatch) {
            throw new RuntimeException(
                $collectionPath === '/tickets'
                    ? 'paymenter_support_ticket_not_found'
                    : 'paymenter_support_invoice_not_found'
            );
        }
    }

    /**
     * Read the authoritative Paymenter owner ID from a ticket resource.
     *
     * Paymenter may expose user_id as an attribute or as a JSON:API user
     * relationship. No owner value means "not verifiably owned" and callers
     * must reject the resource.
     */
    private function resourceOwnerUserId(array $resource): int
    {
        $attributes = is_array($resource['attributes'] ?? null)
            ? $resource['attributes']
            : [];

        $candidate = $attributes['user_id'] ?? $resource['user_id'] ?? null;

        if ($candidate === null) {
            $candidate = $resource['relationships']['user']['data']['id'] ?? null;
        }

        $userId = filter_var(
            $candidate,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $userId === false ? 0 : (int) $userId;
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
     * @return array<string,mixed>
     */
    private function normaliseTicket(array $resource, array $included): array
    {
        $attributes = is_array($resource['attributes'] ?? null)
            ? $resource['attributes']
            : [];
        $ticketId = (int) ($resource['id'] ?? $attributes['id'] ?? 0);
        $includedMap = $this->includedMap($included);
        $ticketOwnerId = $this->resourceOwnerUserId($resource);
        $messageRefs = $resource['relationships']['messages']['data'] ?? [];
        $messages = [];

        if (is_array($messageRefs)) {
            foreach ($messageRefs as $messageRef) {
                if (!is_array($messageRef)) {
                    continue;
                }

                $type = (string) ($messageRef['type'] ?? 'ticket-messages');
                $id = (string) ($messageRef['id'] ?? '');
                $messageResource = $includedMap[$type . ':' . $id] ?? $messageRef;
                $messageAttributes = is_array($messageResource['attributes'] ?? null)
                    ? $messageResource['attributes']
                    : [];
                $authorRef = $messageResource['relationships']['user']['data'] ?? null;

                $messages[] = [
                    'message_id' => (int) ($messageResource['id'] ?? $id),
                    'message' => (string) ($messageAttributes['message'] ?? ''),
                    'created_at' => (string) ($messageAttributes['created_at'] ?? ''),
                    'user_id' => is_array($authorRef) && isset($authorRef['id'])
                        ? (int) $authorRef['id']
                        : null,
                    'is_customer' => is_array($authorRef) && isset($authorRef['id']) && $ticketOwnerId > 0
                        ? (int) $authorRef['id'] === $ticketOwnerId
                        : null,
                ];
            }
        }

        usort($messages, static fn (array $a, array $b): int =>
            strcmp($a['created_at'], $b['created_at'])
        );

        return [
            'ticket_id' => $ticketId,
            'subject' => (string) ($attributes['subject'] ?? ''),
            'status' => (string) ($attributes['status'] ?? 'open'),
            'priority' => (string) ($attributes['priority'] ?? 'medium'),
            'department' => (string) ($attributes['department'] ?? ''),
            'created_at' => (string) ($attributes['created_at'] ?? ''),
            'updated_at' => (string) ($attributes['updated_at'] ?? ''),
            'messages' => $messages,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function normaliseInvoice(array $resource, array $included): array
    {
        $attributes = is_array($resource['attributes'] ?? null)
            ? $resource['attributes']
            : [];
        $includedMap = $this->includedMap($included);
        $itemRefs = $resource['relationships']['items']['data'] ?? [];
        $items = [];

        if (is_array($itemRefs)) {
            foreach ($itemRefs as $itemRef) {
                if (!is_array($itemRef)) {
                    continue;
                }
                $type = (string) ($itemRef['type'] ?? 'invoice-items');
                $id = (string) ($itemRef['id'] ?? '');
                $itemResource = $includedMap[$type . ':' . $id] ?? $itemRef;
                $itemAttributes = is_array($itemResource['attributes'] ?? null)
                    ? $itemResource['attributes']
                    : [];

                $items[] = [
                    'item_id' => (int) ($itemResource['id'] ?? $id),
                    'description' => (string) ($itemAttributes['description'] ?? ''),
                    'quantity' => (string) ($itemAttributes['quantity'] ?? '1'),
                    'price' => (string) ($itemAttributes['price'] ?? '0'),
                ];
            }
        }

        return [
            'invoice_id' => (int) ($resource['id'] ?? $attributes['id'] ?? 0),
            'status' => (string) ($attributes['status'] ?? ''),
            'currency_code' => (string) ($attributes['currency_code'] ?? ''),
            'due_at' => (string) ($attributes['due_at'] ?? ''),
            'created_at' => (string) ($attributes['created_at'] ?? ''),
            'items' => $items,
        ];
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
            if (!is_array($resource) || !isset($resource['type'], $resource['id'])) {
                continue;
            }
            $map[(string) $resource['type'] . ':' . (string) $resource['id']] = $resource;
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function hmacPost(string $path, array $payload): array
    {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $message = $timestamp . "\n" . $nonce . "\n" . $body;
        $signature = hash_hmac('sha256', $message, $this->sharedSecret);

        return $this->sendRequest(
            'POST',
            $this->baseUrl . $path,
            [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-FragCity-Timestamp: ' . $timestamp,
                'X-FragCity-Nonce: ' . $nonce,
                'X-FragCity-Signature: ' . $signature,
            ],
            $body
        );
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    private function nativeRequest(
        string $method,
        string $path,
        array $query = [],
        ?array $payload = null
    ): array {
        $url = $this->baseUrl . '/api/v1/admin' . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->adminApiKey,
        ];
        $body = null;

        if ($payload !== null) {
            $body = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $headers[] = 'Content-Type: application/json';
        }

        return $this->sendRequest($method, $url, $headers, $body);
    }

    /**
     * @param list<string> $headers
     * @return array<string,mixed>
     */
    private function sendRequest(string $method, string $url, array $headers, ?string $body = null): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('paymenter_unreachable');
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options);
        $rawResponse = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($rawResponse === false || $curlError !== 0) {
            error_log('[FragCity] Paymenter support transport failed; cURL code=' . $curlError . '.');
            throw new RuntimeException('paymenter_unreachable');
        }

        if ($status === 204 || trim((string) $rawResponse) === '') {
            if ($status >= 200 && $status < 300) {
                return [];
            }
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            error_log('[FragCity] Paymenter support API returned invalid JSON; HTTP ' . $status . '.');
            throw new RuntimeException('paymenter_support_invalid_response');
        }

        if ($status < 200 || $status >= 300) {
            $bridgeError = is_string($decoded['error'] ?? null)
                ? $decoded['error']
                : '';
            $knownBridgeErrors = [
                'fragcity_account_not_linked',
                'linked_paymenter_user_missing',
                'validation_failed',
                'identity_lookup_failed',
                'support_api_not_configured',
                'source_ip_not_allowed',
                'invalid_ip_allowlist_configuration',
                'invalid_signature_headers',
                'invalid_signature',
                'request_replayed',
                'invalid_json_body',
                'request_too_large',
            ];

            if (in_array($bridgeError, $knownBridgeErrors, true)) {
                error_log('[FragCity] Support identity bridge rejected a request; HTTP ' . $status . ', error=' . $bridgeError . '.');
                throw new RuntimeException($bridgeError);
            }

            $safeError = match ($status) {
                401, 403 => 'paymenter_api_permission_denied',
                404 => 'paymenter_support_resource_not_found',
                422 => 'paymenter_support_validation_failed',
                429 => 'paymenter_support_rate_limited',
                default => 'paymenter_support_request_failed',
            };

            error_log('[FragCity] Paymenter support API rejected request; HTTP ' . $status . '.');
            throw new RuntimeException($safeError);
        }

        return $decoded;
    }

    private function assertSteamId(string $steamId64): void
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
            throw new RuntimeException('invalid_steamid');
        }
    }
}
