<?php
declare(strict_types=1);

/**
 * Attach safe, customer-owned server summaries to Paymenter invoice lines.
 *
 * This class deliberately does not infer a server for generic manual invoice
 * lines. Only explicit Service / ServiceUpgrade references can be matched.
 */
final class PaymenterInvoiceContext
{
    /**
     * @param list<array<string,mixed>> $invoices
     * @param list<array<string,mixed>> $serverRows
     * @return list<array<string,mixed>>
     */
    public static function attach(array $invoices, array $serverRows, bool $lookupAvailable): array
    {
        $services = [];
        $upgradeToService = [];

        foreach ($serverRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $serviceId = filter_var(
                $row['service_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($serviceId === false) {
                continue;
            }

            $rawServer = is_array($row['server'] ?? null) ? $row['server'] : [];
            $host = trim((string) (
                $rawServer['allocation_alias'] ?? $rawServer['allocation_ip'] ?? ''
            ));
            if ($host === '') {
                $host = trim((string) ($rawServer['allocation_ip'] ?? ''));
            }

            $port = filter_var(
                $rawServer['allocation_port'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 65535]]
            );

            $services[(int) $serviceId] = [
                'service_id' => (int) $serviceId,
                'product_name' => self::safeText($row['product_name'] ?? '', 160),
                'server_name' => self::safeText($rawServer['name'] ?? '', 160),
                'service_status' => self::safeText($row['service_status'] ?? '', 40),
                'state' => self::safeText($row['state'] ?? '', 40),
                'server_status' => self::safeText($rawServer['server_status'] ?? '', 40),
                'host' => self::safeText($host, 255),
                'port' => $port === false ? null : (int) $port,
                'node_name' => self::safeText($rawServer['node_name'] ?? '', 120),
                'location' => self::safeText($rawServer['location_long'] ?? '', 120),
                'expires_at' => self::safeText(
                    $rawServer['expires_at'] ?? $row['expires_at'] ?? '',
                    64
                ),
            ];

            $upgradeIds = $row['service_upgrade_ids'] ?? [];
            if (is_array($upgradeIds)) {
                foreach ($upgradeIds as $upgradeIdRaw) {
                    $upgradeId = filter_var(
                        $upgradeIdRaw,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    );
                    if ($upgradeId !== false) {
                        $upgradeToService[(int) $upgradeId] = (int) $serviceId;
                    }
                }
            }
        }

        foreach ($invoices as &$invoice) {
            if (!is_array($invoice) || !is_array($invoice['items'] ?? null)) {
                continue;
            }

            foreach ($invoice['items'] as &$item) {
                if (!is_array($item)) {
                    continue;
                }

                $kind = (string) ($item['reference_kind'] ?? 'manual');
                $referenceId = filter_var(
                    $item['reference_id'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                $relatedServiceId = null;
                if ($kind === 'service' && $referenceId !== false) {
                    $relatedServiceId = (int) $referenceId;
                } elseif ($kind === 'service_upgrade' && $referenceId !== false) {
                    $relatedServiceId = $upgradeToService[(int) $referenceId] ?? null;
                }

                $item['related_service_id'] = $relatedServiceId;
                $item['server'] = $relatedServiceId !== null
                    ? ($services[$relatedServiceId] ?? null)
                    : null;

                if ($kind === 'manual') {
                    $item['association_status'] = 'manual_unlinked';
                } elseif ($relatedServiceId !== null && isset($services[$relatedServiceId])) {
                    $item['association_status'] = 'linked';
                } elseif (!$lookupAvailable) {
                    $item['association_status'] = 'lookup_unavailable';
                } else {
                    $item['association_status'] = 'not_in_current_server_list';
                }

                // Internal reference IDs are not needed by the browser after
                // the safe service mapping has been resolved.
                unset($item['reference_id']);
            }
            unset($item);
        }
        unset($invoice);

        return $invoices;
    }

    private static function safeText(mixed $value, int $maxLength): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return mb_substr(trim((string) $value), 0, $maxLength);
    }
}
