<?php
declare(strict_types=1);

/**
 * Read-only Pterodactyl Application API client for monitoring.
 *
 * The Application API token remains on the Linux API host. Responses from
 * this class are internal and must never be sent to the browser verbatim.
 */
final class FragCityPterodactylMonitoringPanelClient
{
    private string $baseUrl;
    private string $apiKey;
    /** @var int[] */
    private array $allowedNodeIds;
    /** @var int[] */
    private array $allowedEggIds;
    /** @var string[] */
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
            throw new RuntimeException('Pterodactyl monitoring base_url must be an HTTPS origin.');
        }
        if ($apiKey === '' || strlen($apiKey) > 200) {
            throw new RuntimeException('Pterodactyl monitoring Application API key is missing.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Pterodactyl monitoring.');
        }

        $this->allowedNodeIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($config['allowed_node_ids'] ?? null) ? $config['allowed_node_ids'] : []),
            static fn(int $id): bool => $id > 0
        )));
        if ($this->allowedNodeIds === []) {
            $singleNodeId = (int)($config['node_id'] ?? 0);
            if ($singleNodeId > 0) {
                $this->allowedNodeIds = [$singleNodeId];
            }
        }
        if ($this->allowedNodeIds === []) {
            throw new RuntimeException('Configure at least one allowed Pterodactyl node ID.');
        }

        $this->allowedEggIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($config['allowed_egg_ids'] ?? null) ? $config['allowed_egg_ids'] : []),
            static fn(int $id): bool => $id > 0
        )));
        $this->allowedImages = array_values(array_unique(array_filter(
            array_map(
                static fn($value): string => strtolower(trim((string)$value)),
                is_array($config['allowed_docker_images'] ?? null) ? $config['allowed_docker_images'] : []
            ),
            static fn(string $value): bool => $value !== ''
        )));
        if ($this->allowedEggIds === [] && $this->allowedImages === []) {
            throw new RuntimeException('Configure CS2 allowed_egg_ids or allowed_docker_images.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllowedNodes(): array
    {
        $nodes = [];
        foreach ($this->allowedNodeIds as $nodeId) {
            $payload = $this->get('/api/application/nodes/' . $nodeId);
            $attributes = is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [];
            if ((int)($attributes['id'] ?? 0) !== $nodeId) {
                throw new RuntimeException('Pterodactyl returned an unexpected node record.');
            }
            $nodes[] = $attributes;
        }
        return $nodes;
    }

    /**
     * @param int[] $nodeIds
     * @return array<int, array<string, mixed>>
     */
    public function getAllowedCs2Servers(array $nodeIds, int $maxServers = 100): array
    {
        $nodeLookup = array_fill_keys(array_map('intval', $nodeIds), true);
        $maxServers = max(1, min(250, $maxServers));
        $servers = [];
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
                throw new RuntimeException('Pterodactyl returned an invalid server collection.');
            }

            foreach ($items as $item) {
                $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
                $candidate = $this->toCs2Candidate($attributes, $nodeLookup);
                if ($candidate !== null) {
                    $servers[] = $candidate;
                    if (count($servers) >= $maxServers) {
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

        return $servers;
    }

    /** Returns Wings credentials/configuration only to the backend caller. */
    public function getNodeWingsConfiguration(int $nodeId): array
    {
        if (!in_array($nodeId, $this->allowedNodeIds, true)) {
            throw new RuntimeException('Requested Pterodactyl node is not allowlisted.');
        }

        $payload = $this->get('/api/application/nodes/' . $nodeId . '/configuration');
        if (!is_array($payload) || !is_array($payload['api'] ?? null)) {
            throw new RuntimeException('Pterodactyl returned an invalid Wings configuration.');
        }
        if (!is_string($payload['token'] ?? null) || trim($payload['token']) === '') {
            throw new RuntimeException('Pterodactyl did not return the Wings authentication token.');
        }

        return $payload;
    }

    private function toCs2Candidate(array $attributes, array $nodeLookup): ?array
    {
        $nodeId = (int)($attributes['node'] ?? 0);
        if ($nodeId < 1 || !isset($nodeLookup[$nodeId])) {
            return null;
        }

        $eggId = (int)($attributes['egg'] ?? 0);
        $container = is_array($attributes['container'] ?? null) ? $attributes['container'] : [];
        $image = strtolower(trim((string)($container['image'] ?? '')));

        $eggAllowed = in_array($eggId, $this->allowedEggIds, true);
        $imageAllowed = false;
        foreach ($this->allowedImages as $allowedImage) {
            if ($image === $allowedImage
                || str_starts_with($image, rtrim($allowedImage, ':') . ':')) {
                $imageAllowed = true;
                break;
            }
        }
        if (!$eggAllowed && !$imageAllowed) {
            return null;
        }

        $uuid = strtolower(trim((string)($attributes['uuid'] ?? '')));
        $identifier = strtolower(trim((string)($attributes['identifier'] ?? '')));
        if (!preg_match('/^[a-f0-9-]{36}$/D', $uuid)
            || !preg_match('/^[a-z0-9]{8}$/D', $identifier)) {
            return null;
        }

        $limits = is_array($attributes['limits'] ?? null) ? $attributes['limits'] : [];
        return [
            'id' => (int)($attributes['id'] ?? 0),
            'uuid' => $uuid,
            'identifier' => $identifier,
            'name' => trim((string)($attributes['name'] ?? 'CS2 Community Server')),
            'node_id' => $nodeId,
            'egg_id' => $eggId,
            'suspended' => !empty($attributes['suspended']),
            'installed' => array_key_exists('installed', $container) ? (bool)$container['installed'] : false,
            'limits' => [
                'memory_mb' => max(0, (int)($limits['memory'] ?? 0)),
                'disk_mb' => max(0, (int)($limits['disk'] ?? 0)),
                'cpu_percent' => max(0, (float)($limits['cpu'] ?? 0)),
            ],
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
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($handle);
        $curlError = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

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
