<?php

namespace Flute\Core\SystemHealth\Migrations;

use Throwable;

class EnsureUtf8mb4Migration
{
    public function run(): void
    {
        try {
            $database = db();
            $prefix = $database->getPrefix();
            $databaseName = (string) $database->query('SELECT DATABASE()')->fetchColumn();

            if ($databaseName !== '') {
                try {
                    $database->query(sprintf(
                        'ALTER DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                        self::escapeIdentifier($databaseName),
                    ));
                } catch (Throwable $e) {
                    logs('database')->warning(
                        "Failed to convert database {$databaseName} to utf8mb4: " . $e->getMessage(),
                    );
                }
            }

            $rows = $database->query("SELECT TABLE_NAME, TABLE_COLLATION
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME LIKE '{$prefix}%'
                   AND TABLE_COLLATION NOT LIKE 'utf8mb4%'")->fetchAll();

            foreach ($rows as $row) {
                $table = $row['TABLE_NAME'];

                try {
                    $database->query(sprintf(
                        'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                        self::escapeIdentifier($table),
                    ));
                } catch (Throwable $e) {
                    logs('database')->warning("Failed to convert table {$table} to utf8mb4: " . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            logs('database')->warning('EnsureUtf8mb4Migration failed: ' . $e->getMessage());
        }
    }

    private static function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }
}
