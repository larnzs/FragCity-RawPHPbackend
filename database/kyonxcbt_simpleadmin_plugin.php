<?php

require_once __DIR__ . '/../settings/loader.php';
class RemoteBanlistDatabase {

    private $pdo;



    public function __construct() {

        $dbSettings=fragcity_settings('files.database.simpleadmin_plugin',array());
        $dbProfile=isset($dbSettings['database_profile'])?$dbSettings['database_profile']:'simpleadmin_plugin';



        try {

            $this->pdo=fragcity_create_pdo($dbProfile);
            if(!$this->pdo)throw new PDOException('Database unavailable or not configured.');

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