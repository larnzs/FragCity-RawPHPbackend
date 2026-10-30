<?php
declare(strict_types=1);

/**
 * Optional host-level Linux metrics for the node where FragCity Linux API runs.
 *
 * Wings exposes CPU thread count and physical RAM capacity, while this small
 * collector samples local /proc and filesystem values for actual host load,
 * RAM usage and disk usage. It must only be attached to the configured local
 * Pterodactyl node, never to a remote node.
 */
final class PterodactylHostMetrics
{
    /** @return array<string, int|float|null> */
    public function collect(string $diskPath): array
    {
        $before = $this->readCpuCounters();
        usleep(150000);
        $after = $this->readCpuCounters();

        $cpuPercent = null;
        if ($before !== null && $after !== null) {
            $totalDelta = $after['total'] - $before['total'];
            $idleDelta = $after['idle'] - $before['idle'];
            if ($totalDelta > 0) {
                $cpuPercent = round(max(0.0, min(100.0, (($totalDelta - $idleDelta) / $totalDelta) * 100)), 2);
            }
        }

        $memory = $this->readMemory();
        $disk = $this->readDisk($diskPath);

        return [
            'cpu_usage_percent' => $cpuPercent,
            'memory_total_bytes' => $memory['total'],
            'memory_used_bytes' => $memory['used'],
            'memory_available_bytes' => $memory['available'],
            'disk_total_bytes' => $disk['total'],
            'disk_used_bytes' => $disk['used'],
            'disk_free_bytes' => $disk['free'],
        ];
    }

    /** @return array{total:int,idle:int}|null */
    private function readCpuCounters(): ?array
    {
        $raw = @file_get_contents('/proc/stat');
        if (!is_string($raw) || preg_match('/^cpu\s+([0-9 ]+)/m', $raw, $matches) !== 1) {
            return null;
        }

        $values = preg_split('/\s+/', trim($matches[1]));
        if (!is_array($values) || count($values) < 4) {
            return null;
        }

        $ticks = array_map('intval', $values);
        // Guest and guest_nice are already included in user/nice on Linux.
        $ticks = array_slice($ticks, 0, 8);
        $total = array_sum($ticks);
        $idle = (int)$ticks[3] + (int)($ticks[4] ?? 0);

        return ['total' => $total, 'idle' => $idle];
    }

    /** @return array{total:?int,used:?int,available:?int} */
    private function readMemory(): array
    {
        $raw = @file_get_contents('/proc/meminfo');
        if (!is_string($raw)) {
            return ['total' => null, 'used' => null, 'available' => null];
        }

        $values = [];
        foreach (['MemTotal', 'MemAvailable', 'MemFree'] as $key) {
            if (preg_match('/^' . preg_quote($key, '/') . ':\s+(\d+)\s+kB$/m', $raw, $matches) === 1) {
                $values[$key] = (int)$matches[1] * 1024;
            }
        }

        $total = $values['MemTotal'] ?? null;
        $available = $values['MemAvailable'] ?? ($values['MemFree'] ?? null);
        $used = $total !== null && $available !== null
            ? max(0, $total - $available)
            : null;

        return ['total' => $total, 'used' => $used, 'available' => $available];
    }

    /** @return array{total:?int,used:?int,free:?int} */
    private function readDisk(string $path): array
    {
        $path = trim($path);
        if ($path === '' || !is_dir($path)) {
            return ['total' => null, 'used' => null, 'free' => null];
        }

        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if (!is_float($total) && !is_int($total)) {
            return ['total' => null, 'used' => null, 'free' => null];
        }
        if (!is_float($free) && !is_int($free)) {
            return ['total' => (int)$total, 'used' => null, 'free' => null];
        }

        $totalBytes = max(0, (int)$total);
        $freeBytes = max(0, min($totalBytes, (int)$free));

        return [
            'total' => $totalBytes,
            'used' => max(0, $totalBytes - $freeBytes),
            'free' => $freeBytes,
        ];
    }
}
