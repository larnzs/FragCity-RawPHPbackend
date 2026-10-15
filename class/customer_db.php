<?php
// class/customer_db.php
class CustomerDB {
    private $PDO;

    public function __construct() {
        try {
            $this->PDO = new PDO(
                "mysql:host=localhost;port=3306;dbname=kyonxcbt_fragcity_services;charset=utf8mb4",
                'kyonxcbt_admin',
                'Atrisayeafsorde824@'
            );
            $this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $ex) {
            die("Database connection error: " . $ex->getMessage());
        }
    }

    public function select($query, $bindings = []) {
        $STH = $this->PDO->prepare($query);
        $STH->execute($bindings);
        $result = $STH->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];   // همیشه آرایه برمی‌گرداند (حتی اگر خالی باشد)
    }

    public function query($query, $bindings = []) {
        $STH = $this->PDO->prepare($query);
        return $STH->execute($bindings);
    }
}