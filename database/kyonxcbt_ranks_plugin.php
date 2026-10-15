<?php
class RemoteRanksDatabase {
    private $pdo;

    public function __construct() {
        $db_host = 'localhost';
        $db_port = '3306';
        $db_name = 'kyonxcbt_ranks_plugin';
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

    private function executeQuery($sql, $bindings = []) {
        if (empty($sql)) {
            throw new Exception("Empty SQL query");
        }
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