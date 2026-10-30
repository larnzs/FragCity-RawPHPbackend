<?php
declare(strict_types=1);

/**
 * Read-only Pterodactyl 1.x Application API client.
 * It only returns configured CS2 servers and each server's default allocation.
 */
final class FragCityPterodactylApplicationClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $nodeId;
    private int $maxServers;
    private array $allowedEggIds;
    private array $allowedImages;

    public function __construct(array $config)
    {
        $baseUrl = trim((string)($config['base_url'] ?? ''));
        $apiKey = trim((string)($config['application_api_key'] ?? ''));
        $parts = parse_url($baseUrl);

        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            throw new RuntimeException('Pterodactyl base_url must be an HTTPS origin.');
        }

        if ($apiKey === '' || strlen($apiKey) > 200) {
            throw new RuntimeException('Pterodactyl Application API key is not configured.');
        }

        $this->nodeId = (int)($config['node_id'] ?? 0);
        if ($this->nodeId < 1) {
            throw new RuntimeException('Pterodactyl node_id must be configured.');
        }
        $this->maxServers = max(1, min(100, (int)($config['max_servers'] ?? 40)));

        $this->allowedEggIds = array_values(array_filter(
            array_map('intval', is_array($config['allowed_egg_ids'] ?? null) ? $config['allowed_egg_ids'] : []),
            static fn(int $id): bool => $id > 0
        ));
        $this->allowedImages = array_values(array_filter(
            array_map(
                static fn($value): string => strtolower(trim((string)$value)),
                is_array($config['allowed_docker_images'] ?? null) ? $config['allowed_docker_images'] : []
            ),
            static fn(string $value): bool => $value !== ''
        ));

        if ($this->allowedEggIds === [] && $this->allowedImages === []) {
            throw new RuntimeException('Configure allowed_egg_ids or allowed_docker_images to restrict discovery to CS2.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Pterodactyl discovery.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function listCs2Servers(): array
    {
        $results = [];
        $page = 1;
        $perPage = 100;

        do {
            $query = http_build_query([
                'per_page' => $perPage,
                'page' => $page,
                'include' => 'allocations',
            ], '', '&', PHP_QUERY_RFC3986);

            $payload = $this->get('/api/application/servers?' . $query);
            $items = $payload['data'] ?? null;
            if (!is_array($items)) {
                throw new RuntimeException('Pterodactyl returned an invalid server list.');
            }

            foreach ($items as $item) {
                $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
                $candidate = $this->toCandidate($attributes);
                if ($candidate !== null) {
                    $results[] = $candidate;
                    if (count($results) >= $this->maxServers) {
                        break 2;
                    }
                }
            }

            $pagination = $payload['meta']['pagination'] ?? [];
            $totalPages = isset($pagination['total_pages']) ? (int)$pagination['total_pages'] : null;

            if ($totalPages !== null) {
                if ($page >= $totalPages) {
                    break;
                }
            } elseif (count($items) < $perPage) {
                break;
            }

            ++$page;
            if ($page > 50) {
                throw new RuntimeException('Pterodactyl server pagination exceeded the safety limit.');
            }
        } while (true);

        return $results;
    }

    private function toCandidate(array $attributes): ?array
    {
        $nodeId = (int)($attributes['node'] ?? 0);
        if ($nodeId !== $this->nodeId) {
            return null;
        }

        $container = is_array($attributes['container'] ?? null) ? $attributes['container'] : [];
        $image = strtolower(trim((string)($container['image'] ?? '')));
        $eggId = (int)($attributes['egg'] ?? 0);

        $eggAllowed = in_array($eggId, $this->allowedEggIds, true);
        $imageAllowed = false;
        foreach ($this->allowedImages as $allowedImage) {
            if ($image === $allowedImage || str_starts_with($image, rtrim($allowedImage, ':') . ':')) {
                $imageAllowed = true;
                break;
            }
        }

        if (!$eggAllowed && !$imageAllowed) {
            return null;
        }

        // Application API server records expose the primary Allocation ID in
        // attributes.allocation. The Application API AllocationTransformer does
        // not provide the Client API's is_default flag, so match the IDs instead.
        $primaryAllocationId = filter_var(
            $attributes['allocation'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($primaryAllocationId === false) {
            return null;
        }

        $relationships = is_array($attributes['relationships'] ?? null)
            ? $attributes['relationships']
            : [];
        $allocationRelationship = $relationships['allocations'] ?? null;
        $allocationData = is_array($allocationRelationship)
            ? ($allocationRelationship['data'] ?? null)
            : null;

        if (!is_array($allocationData)) {
            throw new RuntimeException(
                'Pterodactyl Application API did not return allocations; grant the API key read access to Allocations.'
            );
        }

        $defaultAllocation = null;
        foreach ($allocationData as $allocation) {
            $allocationAttributes = is_array($allocation['attributes'] ?? null)
                ? $allocation['attributes']
                : [];
            $allocationId = filter_var(
                $allocationAttributes['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($allocationId !== false && (int)$allocationId === (int)$primaryAllocationId) {
                $defaultAllocation = $allocationAttributes;
                break;
            }
        }

        if ($defaultAllocation === null) {
            throw new RuntimeException(
                'Pterodactyl did not return the server primary Allocation; verify Allocations read permission and the server allocation.'
            );
        }

        $port = filter_var(
            $defaultAllocation['port'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]
        );
        $allocationId = (int)($defaultAllocation['id'] ?? 0);
        if ($port === false || $allocationId < 1) {
            return null;
        }

        $uuid = trim((string)($attributes['uuid'] ?? ''));
        $identifier = strtolower(trim((string)($attributes['identifier'] ?? '')));
        if ($uuid === '' || !preg_match('/^[a-z0-9]{8}$/D', $identifier)) {
            return null;
        }

        $installed = array_key_exists('installed', $container)
            ? (bool)$container['installed']
            : false;

        return [
            'server_uuid' => $uuid,
            'server_identifier' => $identifier,
            'server_name' => trim((string)($attributes['name'] ?? 'CS2 Community Server')),
            'server_id' => (int)($attributes['id'] ?? 0),
            'node_id' => $nodeId,
            'allocation_id' => $allocationId,
            'game_port' => (int)$port,
            'suspended' => !empty($attributes['suspended']),
            'installed' => $installed,
        ];
    }

    private function get(string $path): array
    {
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize Pterodactyl API request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: Application/vnd.pterodactyl.v1+json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $handle = null;

        if ($response === false || $curlError !== 0) {
            throw new RuntimeException('Pterodactyl API transport failed; cURL code=' . $curlError);
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Pterodactyl API returned invalid JSON; HTTP=' . $status);
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Pterodactyl API request failed; HTTP=' . $status);
        }

        return $decoded;
    }
}
