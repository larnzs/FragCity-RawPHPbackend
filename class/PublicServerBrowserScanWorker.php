<?php
declare(strict_types=1);

/**
 * Orchestrates bounded A2S batches and persists their results.
 *
 * The worker is independent of the website bootstrap. A CLI entrypoint only
 * needs to supply a PDO connection and the scanner/repository classes.
 */
final class PublicServerBrowserScanWorker
{
    private PublicServerBrowserScanStoreInterface $repository;

    public function __construct(PublicServerBrowserScanStoreInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array{
     *   skipped: bool,
     *   processed: int,
     *   online: int,
     *   offline: int,
     *   not_cs2: int,
     *   errors: int,
     *   skipped_targets: int,
     *   elapsed_seconds: float,
     *   warnings: array<int, string>
     * }
     */
    public function run(int $maxPortsPerRun = 256, int $batchSize = 32): array
    {
        $maxPortsPerRun = max(1, min(4096, $maxPortsPerRun));
        $batchSize = max(1, min(128, $batchSize));

        if (!$this->repository->acquireLock()) {
            return [
                'skipped' => true,
                'processed' => 0,
                'online' => 0,
                'offline' => 0,
                'not_cs2' => 0,
                'errors' => 0,
                'skipped_targets' => 0,
                'elapsed_seconds' => 0.0,
                'warnings' => [],
            ];
        }

        $startedAt = microtime(true);
        $summary = [
            'skipped' => false,
            'processed' => 0,
            'online' => 0,
            'offline' => 0,
            'not_cs2' => 0,
            'errors' => 0,
            'skipped_targets' => 0,
            'elapsed_seconds' => 0.0,
            'warnings' => [],
        ];

        try {
            foreach ($this->repository->getEnabledTargets() as $target) {
                if ($summary['processed'] >= $maxPortsPerRun) {
                    break;
                }

                $targetId = (int)$target['id'];
                $host = trim((string)$target['host']);
                $portStart = (int)$target['port_start'];
                $portEnd = (int)$target['port_end'];

                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
                    || $portStart < 1 || $portEnd > 65535 || $portStart > $portEnd
                    || ($portEnd - $portStart) > 4095) {
                    ++$summary['skipped_targets'];
                    $summary['warnings'][] = 'Skipped invalid target #' . $targetId;
                    continue;
                }

                $this->repository->ensureTargetInventory($target);
                $remaining = $maxPortsPerRun - $summary['processed'];
                $dueRows = $this->repository->getDuePorts(
                    $targetId,
                    $portStart,
                    $portEnd,
                    $remaining
                );
                if ($dueRows === []) {
                    continue;
                }

                $scanner = new PublicServerBrowserA2SScanner(
                    (int)($target['query_timeout_ms'] ?? 1500)
                );
                $offlineRetry = max(
                    300,
                    min(86400, (int)($target['retry_offline_seconds'] ?? 3600))
                );
                $onlineRefresh = max(
                    30,
                    min(3600, (int)($target['refresh_online_seconds'] ?? 90))
                );

                foreach (array_chunk($dueRows, $batchSize) as $batch) {
                    $ports = array_map(
                        static fn(array $row): int => (int)$row['port'],
                        $batch
                    );
                    $batchResults = $scanner->queryBatch($host, $ports);

                    foreach ($batch as $row) {
                        $port = (int)$row['port'];
                        $result = $batchResults[$port] ?? [
                            'status' => 'offline',
                            'error' => 'no_result',
                        ];
                        $storedStatus = $this->repository->saveResult(
                            (int)$row['id'],
                            $result,
                            $offlineRetry,
                            $onlineRefresh
                        );

                        if ($storedStatus === 'online') {
                            ++$summary['online'];
                        } elseif ($storedStatus === 'offline') {
                            ++$summary['offline'];
                        } elseif ($storedStatus === 'not_cs2') {
                            ++$summary['not_cs2'];
                        } else {
                            ++$summary['errors'];
                        }
                    }

                    $summary['processed'] += count($batch);
                }
            }

            $summary['elapsed_seconds'] = round(microtime(true) - $startedAt, 2);
            return $summary;
        } finally {
            $this->repository->releaseLock();
        }
    }
}
