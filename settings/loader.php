<?php
/** Shared JSON settings loader. Runtime secrets belong in settings/settings.json (Git-ignored),
 * or in the path supplied by FRAGCITY_SETTINGS_FILE. */
if (!function_exists('fragcity_load_settings')) {
 function fragcity_load_settings() {
  static $settings=null;
  if(is_array($settings))return $settings;
  $path=getenv('FRAGCITY_SETTINGS_FILE');
  if(!is_string($path)||trim($path)==='')$path=__DIR__.'/settings.json';
  if(!is_file($path))$path=__DIR__.'/settings.example.json';
  $raw=is_readable($path)?file_get_contents($path):false;
  $decoded=is_string($raw)?json_decode($raw,true):null;
  if(!is_array($decoded)||json_last_error()!==JSON_ERROR_NONE){error_log('[FragCity] Settings JSON missing, unreadable, or invalid; DB access remains disabled.');$settings=array();}
  else $settings=$decoded;
  $timezone=isset($settings['app']['timezone'])&&is_string($settings['app']['timezone'])?trim($settings['app']['timezone']):'UTC';
  $valid=DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
  if($timezone!=='UTC'&&!in_array($timezone,$valid,true)){error_log('[FragCity] Invalid timezone setting; UTC will be used.');$timezone='UTC';}
  date_default_timezone_set($timezone);
  return $settings;
 }
}
if (!function_exists('fragcity_settings')) {
 function fragcity_settings($path=null,$default=null) {
  $value=fragcity_load_settings();
  if($path===null||$path==='')return $value;
  foreach(explode('.',(string)$path) as $segment){if(!is_array($value)||!array_key_exists($segment,$value))return $default;$value=$value[$segment];}
  return $value;
 }
}
if (!function_exists('fragcity_database_settings')) {
 function fragcity_database_settings($profile) {
  $all=fragcity_settings('databases',array());
  if(!is_array($all)||!isset($all[$profile])||!is_array($all[$profile]))return array();
  $config=$all[$profile];
  if(isset($config['extends'])&&is_string($config['extends'])&&isset($all[$config['extends']])&&is_array($all[$config['extends']])){$base=$all[$config['extends']];unset($base['extends'],$config['extends']);$config=array_merge($base,$config);}
  return $config;
 }
}
if (!function_exists('fragcity_create_pdo')) {
 /** Blank config is deliberately fail-closed; never fall back to embedded Production credentials. */
 function fragcity_create_pdo($profile,$databaseOverride=null) {
  $c=fragcity_database_settings((string)$profile);
  $host=isset($c['host'])?trim((string)$c['host']):'';
  $port=isset($c['port'])?(int)$c['port']:3306;
  $db=$databaseOverride!==null?trim((string)$databaseOverride):(isset($c['name'])?trim((string)$c['name']):'');
  $user=isset($c['username'])?trim((string)$c['username']):'';
  $pass=isset($c['password'])?(string)$c['password']:'';
  $charset=isset($c['charset'])?trim((string)$c['charset']):'utf8mb4';
  if($host===''||$db===''||$user==='')return null;
  if($port<1||$port>65535)$port=3306;
  if(!preg_match('/^[a-zA-Z0-9_]+$/',$charset))$charset='utf8mb4';
  try{return new PDO('mysql:host='.$host.';port='.$port.';dbname='.$db.';charset='.$charset,$user,$pass,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false));}
  catch(PDOException $e){error_log('[FragCity] DB connection failed for profile "'.$profile.'" (SQLSTATE '.$e->getCode().').');return null;}
 }
}
