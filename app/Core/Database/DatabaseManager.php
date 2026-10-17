<?php

namespace Flute\Core\Database;

use Cycle\Database\Config\DatabaseConfig as CycleDatabaseConfig;
use Cycle\Database\DatabaseManager as CycleDatabaseManager;
use Exception;
use Flute\Core\App;

class DatabaseManager
{
    protected App $app;

    protected CycleDatabaseManager $dbal;

    // Static instance for singleton pattern
    protected static ?self $instance = null;

    /**
     * Avoid repeating the same stale-config warning on every DB manager setup.
     */
    protected static bool $persistentWarningLogged = false;

    /**
     * @throws Exception
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->configure();
    }

    /**
     * Get the singleton instance of DatabaseManager
     *
     * @throws Exception
     */
    public static function getInstance(?App $app = null): self
    {
        if (self::$instance === null) {
            if ($app === null) {
                $app = app();
            }
            self::$instance = new self($app);
        }

        return self::$instance;
    }

    /**
     * Get the Cycle Database Manager instance.
     */
    public function getDbal(): CycleDatabaseManager
    {
        return $this->dbal;
    }

    /**
     * Get a specific database connection.
     *
     * @param string $name The name of the database connection.
     */
    public function database(string $name = 'default'): \Cycle\Database\DatabaseInterface
    {
        return $this->dbal->database($name);
    }

    /**
     * @throws Exception
     */
    protected function configure(): void
    {
        $databaseConfig = config('database');
        $this->disablePersistentPrimaryConnection($databaseConfig);

        $config = new CycleDatabaseConfig($databaseConfig);

        if (!$config) {
            throw new Exception('Database configuration not found.');
        }

        $this->dbal = new CycleDatabaseManager($config);
    }

    /**
     * Persistent PDO connections are unsafe for the primary CMS database under
     * PHP-FPM/shared hosting: idle workers can keep MySQL slots open until the
     * server starts returning "SQLSTATE[HY000] [1040] Too many connections".
     */
    protected function disablePersistentPrimaryConnection(array &$config): void
    {
        $defaultDatabase = $config['default'] ?? 'default';
        $databaseConfig = $config['databases'][$defaultDatabase] ?? null;

        if (!is_array($databaseConfig)) {
            return;
        }

        $connectionName =
            $databaseConfig['connection'] ?? $databaseConfig['write'] ?? $databaseConfig['driver'] ?? null;

        if (!is_string($connectionName) || !isset($config['connections'][$connectionName])) {
            return;
        }

        $driverConfig = $config['connections'][$connectionName];
        $connection = is_object($driverConfig) && isset($driverConfig->connection) ? $driverConfig->connection : null;

        if (is_array($driverConfig)) {
            $connection = &$driverConfig;
        }

        if (!is_array($connection) && !is_object($connection)) {
            return;
        }

        $options = is_array($connection) ? $connection['options'] ?? null : $connection->options ?? null;

        if (!is_array($options) || ( $options[\PDO::ATTR_PERSISTENT] ?? false ) !== true) {
            return;
        }

        if (is_array($connection)) {
            $connection['options'][\PDO::ATTR_PERSISTENT] = false;
            $config['connections'][$connectionName] = $connection;
        } else {
            $connection->options[\PDO::ATTR_PERSISTENT] = false;
        }

        if (function_exists('logs') && !self::$persistentWarningLogged) {
            logs('database')->warning(
                "Disabled persistent PDO for primary database connection '{$connectionName}' to avoid MySQL 1040.",
            );
            self::$persistentWarningLogged = true;
        }
    }
}
