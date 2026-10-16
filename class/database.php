<?php
require_once __DIR__ . '/config.php';
class DataBase {
 private $PDO=null;
 public function __construct(){
  if(DB_NAME===''||DB_USER===''){error_log('[FragCity] Weapon-paints DB is not configured.');return;}
  try{$o=array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false);if(defined('PDO::MYSQL_ATTR_INIT_COMMAND'))$o[PDO::MYSQL_ATTR_INIT_COMMAND]='SET NAMES utf8mb4';$this->PDO=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,$o);}
  catch(PDOException $e){error_log('[FragCity] Weapon-paints DB connection failed: '.$e->getMessage());}
 }
 public function select($q,$b=array()){if(!$this->PDO)return false;try{$s=$this->PDO->prepare($q);$s->execute($b);return $s->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $e){error_log('[FragCity] Weapon-paints SELECT failed: '.$e->getMessage());return false;}}
 public function query($q,$b=array()){if(!$this->PDO)return false;try{$s=$this->PDO->prepare($q);return $s->execute($b);}catch(PDOException $e){error_log('[FragCity] Weapon-paints query failed: '.$e->getMessage());return false;}}
}
