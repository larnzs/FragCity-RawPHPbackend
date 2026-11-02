<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';

final class UptimeKumaStatusNotConfiguredException extends RuntimeException
{
}

/**
 * Read-only adapter for a published Uptime Kuma Status Page.
 *
 * Only public monitor names, group names, current heartbeat states,
 * recent heartbeat timestamps, 24-hour uptime and public incidents leave
 * this class. Monitor URLs, tags, internal settings and credentials are
 * deliberately not forwarded to the website browser.
 */
final class UptimeKumaStatusRepository
{
    private string $baseUrl;
    private string $slug;

    public function __construct()
    {
        $baseUrl = trim((string)fragcity_settings('integrations.uptime_kuma.base_url', ''));
        $slug = strtolower(trim((string)fragcity_settings('integrations.uptime_kuma.status_page_slug', '')));

        if ($baseUrl === '' || $slug === '') {
            throw new UptimeKumaStatusNotConfiguredException('Uptime Kuma Status Page is not configured.');
        }

        $parts = parse_url($baseUrl);
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || trim((string)($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            throw new RuntimeException('Uptime Kuma base URL must be an HTTPS origin.');
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $slug)) {
            throw new RuntimeException('Uptime Kuma Status Page slug is invalid.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required for Uptime Kuma status requests.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->slug = $slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        $page = $this->fetchJson('/api/status-page/' . rawurlencode($this->slug));
        $heartbeat = $this->fetchJson('/api/status-page/heartbeat/' . rawurlencode($this->slug));
        $incidentHistory = $this->fetchJson('/api/status-page/' . rawurlencode($this->slug) . '/incident-history');

        $pageConfig = is_array($page['config'] ?? null) ? $page['config'] : [];
        if (array_key_exists('published', $pageConfig) && $pageConfig['published'] !== true) {
            throw new RuntimeException('Uptime Kuma Status Page is not published.');
        }

        $heartbeatList = is_array($heartbeat['heartbeatList'] ?? null)
            ? $heartbeat['heartbeatList']
            : [];
        $uptimeList = is_array($heartbeat['uptimeList'] ?? null)
            ? $heartbeat['uptimeList']
            : [];

        $groups = [];
        $allStatuses = [];
        $uptimeValues = [];
        $lastChecked = null;

        foreach (($page['publicGroupList'] ?? []) as $groupIndex => $group) {
            if (!is_array($group)) {
                continue;
            }

            $monitors = [];
            foreach (($group['monitorList'] ?? []) as $monitor) {
                if (!is_array($monitor)) {
                    continue;
                }

                $monitorId = (string)($monitor['id'] ?? '');
                if (!preg_match('/^[0-9]{1,12}$/D', $monitorId)) {
                    continue;
                }

                $rawBeats = $heartbeatList[$monitorId] ?? [];
                $rawBeats = is_array($rawBeats) ? array_values($rawBeats) : [];
                $latestBeat = $rawBeats !== [] ? $rawBeats[count($rawBeats) - 1] : null;

                $latestStatus = is_array($latestBeat)
                    ? $this->normalizeStatus($latestBeat['status'] ?? null)
                    : 'unknown';
                $allStatuses[] = $latestStatus;

                $history = [];
                foreach (array_slice($rawBeats, -30) as $beat) {
                    if (!is_array($beat)) {
                        continue;
                    }
                    $history[] = [
                        'status' => $this->normalizeStatus($beat['status'] ?? null),
                        'time' => $this->safeDate($beat['time'] ?? null),
                    ];
                }

                $uptimeRaw = $uptimeList[$monitorId . '_24'] ?? null;
                $uptime24h = is_numeric($uptimeRaw)
                    ? max(0, min(100, (float)$uptimeRaw * 100))
                    : null;
                if ($uptime24h !== null) {
                    $uptimeValues[] = $uptime24h;
                }

                $lastBeatTime = is_array($latestBeat)
                    ? $this->safeDate($latestBeat['time'] ?? null)
                    : null;
                if ($lastBeatTime !== null && ($lastChecked === null || strcmp($lastBeatTime, $lastChecked) > 0)) {
                    $lastChecked = $lastBeatTime;
                }

                $ping = is_array($latestBeat) && isset($latestBeat['ping']) && is_numeric($latestBeat['ping'])
                    ? max(0, min(600000, (float)$latestBeat['ping']))
                    : null;

                $monitors[] = [
                    'id' => $monitorId,
                    'name' => $this->safeText($monitor['name'] ?? 'Service', 100),
                    'type' => $this->safeText($monitor['type'] ?? '', 40),
                    'status' => $latestStatus,
                    'uptime_24h' => $uptime24h !== null ? round($uptime24h, 3) : null,
                    'ping_ms' => $ping !== null ? round($ping, 2) : null,
                    'last_checked' => $lastBeatTime,
                    'history' => $history,
                ];
            }

            if ($monitors !== []) {
                $groups[] = [
                    'id' => (string)($group['id'] ?? ('group-' . (int)$groupIndex)),
                    'name' => $this->safeText($group['name'] ?? 'Services', 80),
                    'monitors' => $monitors,
                ];
            }
        }

        $activeIncidents = [];
        foreach (($page['incidents'] ?? []) as $incident) {
            if (is_array($incident)) {
                $activeIncidents[] = $this->normalizeIncident($incident);
            }
        }

        $recentIncidents = [];
        $historyRows = is_array($incidentHistory['incidents'] ?? null)
            ? $incidentHistory['incidents']
            : [];
        foreach (array_slice($historyRows, 0, 6) as $incident) {
            if (is_array($incident)) {
                $recentIncidents[] = $this->normalizeIncident($incident);
            }
        }

        $maintenance = [];
        foreach (($page['maintenanceList'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $maintenance[] = [
                'title' => $this->safeText($item['title'] ?? 'Scheduled maintenance', 120),
                'description' => $this->safeText($item['description'] ?? '', 220),
                'start_at' => $this->safeDate($item['startDate'] ?? ($item['start_date'] ?? null)),
                'end_at' => $this->safeDate($item['endDate'] ?? ($item['end_date'] ?? null)),
            ];
        }

        $onlineCount = count(array_filter($allStatuses, static fn(string $status): bool => $status === 'up'));
        $overallStatus = $this->overallStatus($allStatuses, count($maintenance) > 0);

        return [
            'title' => $this->safeText($pageConfig['title'] ?? 'FragCity Status', 100),
            'overall_status' => $overallStatus,
            'summary' => [
                'total' => count($allStatuses),
                'operational' => $onlineCount,
                'active_incidents' => count($activeIncidents) + count($maintenance),
                'uptime_24h_average' => $uptimeValues !== []
                    ? round(array_sum($uptimeValues) / count($uptimeValues), 3)
                    : null,
            ],
            'groups' => $groups,
            'active_incidents' => $activeIncidents,
            'maintenance' => $maintenance,
            'incident_history' => $recentIncidents,
            'updated_at' => gmdate('c'),
            'last_checked_at' => $lastChecked,
        ];
    }

    private function fetchJson(string $path): array
    {
        $url = $this->baseUrl . $path;
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize Uptime Kuma request.');
        }

        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'FragCity-Status/1.0',
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 1500000) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        curl_exec($handle);
        $curlError = curl_errno($handle);
        $httpStatus = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        unset($handle);

        if ($curlError !== 0 || $httpStatus !== 200 || $body === '') {
            error_log('[FragCity] Uptime Kuma public status request failed; HTTP=' . $httpStatus . '; cURL=' . $curlError);
            throw new RuntimeException('Uptime Kuma public status endpoint is unavailable.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Uptime Kuma returned invalid JSON.');
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Uptime Kuma returned an invalid data structure.');
        }

        return $decoded;
    }

    private function normalizeStatus(mixed $value): string
    {
        if (!is_numeric($value)) {
            return 'unknown';
        }

        return match ((int)$value) {
            1 => 'up',
            0 => 'down',
            2 => 'pending',
            3 => 'maintenance',
            default => 'unknown',
        };
    }

    private function overallStatus(array $statuses, bool $hasMaintenance): string
    {
        if ($statuses === []) {
            return 'unknown';
        }
        if ($hasMaintenance || in_array('maintenance', $statuses, true)) {
            return 'maintenance';
        }

        $up = count(array_filter($statuses, static fn(string $status): bool => $status === 'up'));
        $down = count(array_filter($statuses, static fn(string $status): bool => $status === 'down'));
        $pending = count(array_filter($statuses, static fn(string $status): bool => $status === 'pending'));
        $unknown = count(array_filter($statuses, static fn(string $status): bool => $status === 'unknown'));

        if ($down > 0) {
            return $up > 0 || $pending > 0 ? 'degraded' : 'down';
        }
        if ($pending > 0 || $unknown > 0) {
            return $up > 0 ? 'degraded' : 'unknown';
        }
        return 'up';
    }

    private function normalizeIncident(array $incident): array
    {
        return [
            'id' => isset($incident['id']) && is_numeric($incident['id']) ? (int)$incident['id'] : null,
            'title' => $this->safeText($incident['title'] ?? 'Incident update', 120),
            'content' => $this->safeText($incident['content'] ?? '', 260),
            'status' => $this->safeText($incident['status'] ?? '', 32),
            'style' => $this->safeText($incident['style'] ?? '', 24),
            'created_at' => $this->safeDate($incident['createdDate'] ?? ($incident['created_date'] ?? null)),
            'updated_at' => $this->safeDate($incident['lastUpdatedDate'] ?? ($incident['last_updated_date'] ?? null)),
        ];
    }

    private function safeText(mixed $value, int $maxLength): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLength, 'UTF-8');
        }
        return substr($text, 0, $maxLength);
    }

    private function safeDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (strlen($value) > 64) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
        } catch (Throwable) {
            return null;
        }
    }
}
