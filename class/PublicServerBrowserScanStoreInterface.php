<?php
declare(strict_types=1);

/**
 * Storage contract for the public A2S scan worker.
 *
 * A PDO/MySQL implementation is provided by PublicServerBrowserScanRepository.
 * Keeping the worker dependent on this interface allows isolated tests or a
 * different persistence adapter without changing A2S or batch orchestration.
 */
interface PublicServerBrowserScanStoreInterface
{
    public function acquireLock(): bool;

    public function releaseLock(): void;

    /** @return array<int, array<string, mixed>> */
    public function getEnabledTargets(): array;

    /** @param array<string, mixed> $target */
    public function ensureTargetInventory(array $target): void;

    /**
     * @return array<int, array{id: int, port: int}>
     */
    public function getDuePorts(
        int $targetId,
        int $portStart,
        int $portEnd,
        int $limit
    ): array;

    /** @param array<string, mixed> $result */
    public function saveResult(
        int $rowId,
        array $result,
        int $retryOfflineSeconds,
        int $refreshOnlineSeconds
    ): string;
}
