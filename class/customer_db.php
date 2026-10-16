<?php
require_once __DIR__ . '/../settings/loader.php';
class CustomerDB {
 private $PDO=null;
 public function __construct(){$cfg=fragcity_settings('files.class.customer_db',array());$profile=isset($cfg['database_profile'])?$cfg['database_profile']:'main';$this->PDO=fragcity_create_pdo($profile);if(!$this->PDO)error_log('[FragCity] Customer DB is not configured or unavailable.');}
 public function select($query,$bindings=array()){if(!$this->PDO)return array();try{$s=$this->PDO->prepare($query);$s->execute($bindings);return $s->fetchAll(PDO::FETCH_ASSOC)?:array();}catch(PDOException $e){error_log('[FragCity] Customer DB SELECT failed: '.$e->getMessage());return array();}}
 public function query($query,$bindings=array()){if(!$this->PDO)return false;try{$s=$this->PDO->prepare($query);return $s->execute($bindings);}catch(PDOException $e){error_log('[FragCity] Customer DB query failed: '.$e->getMessage());return false;}}
}
