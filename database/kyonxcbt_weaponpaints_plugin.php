<?php

require_once __DIR__ . '/../settings/loader.php';
class RemoteDataBase {

    private $pdo;



    public function __construct() {

        $dbSettings=fragcity_settings('files.database.weaponpaints_plugin',array());
        $dbProfile=isset($dbSettings['database_profile'])?$dbSettings['database_profile']:'weaponpaints';



        try {

            $this->pdo=fragcity_create_pdo($dbProfile);
            if(!$this->pdo)throw new PDOException('Database unavailable or not configured.');

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