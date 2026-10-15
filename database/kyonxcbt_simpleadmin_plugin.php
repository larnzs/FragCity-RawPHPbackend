<?php
class RemoteBanlistDatabase {
    private $pdo;

    public function __construct() {
        $db_host = 'localhost';
        $db_port = '3306';
        $db_name = 'kyonxcbt_simpleadmin_plugin';
        $db_user = 'kyonxcbt_admin';
        $db_pass = 'Atrisayeafsorde824@';

        try {
            $this->pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function validateSql($sql) {
        $allowed_tables = [
            'sa_bans', 'sa_mutes', 'sa_warns', 'sa_unbans', 'sa_unmutes',
            'sa_players_ips', 'sa_admins', 'sa_servers', 'sa_groups',
            'sa_groups_flags', 'sa_groups_servers', 'sa_admins_flags'
        ];

        $table_found = false;
        foreach ($allowed_tables as $table) {
            if (stripos($sql, $table) !== false) {
                $table_found = true;
                break;
            }
        }
        if (!$table_found) {
            throw new Exception("Query not allowed for this table");
        }
    }

    private function executeQuery($sql, $bindings = []) {
        $this->validateSql($sql);
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) {
            $paramType = PDO::PARAM_STR;
            if (is_int($value)) $paramType = PDO::PARAM_INT;
            elseif (is_bool($value)) $paramType = PDO::PARAM_BOOL;
            $stmt->bindValue($key, $value, $paramType);
        }
        $stmt->execute();
        return $stmt;
    }

    public function select($sql, $bindings = []) {
        try {
            $stmt = $this->executeQuery($sql, $bindings);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    public function query($sql, $bindings = []) {
        try {
            $this->executeQuery($sql, $bindings);
            return true;
        } catch (Exception $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
}