<?php
require_once __DIR__ . '/../settings/loader.php';
$c=fragcity_settings('files.class.config',array());
$db=fragcity_database_settings(isset($c['database_profile'])?$c['database_profile']:'weaponpaints');
$images=isset($c['images'])&&is_array($c['images'])?$c['images']:array();
$steam=fragcity_settings('integrations.steam',array());
$values=array(
 'SKIN_LANGUAGE'=>isset($c['skin_language'])?$c['skin_language']:'skins_en',
 'DB_HOST'=>isset($db['host'])?$db['host']:'127.0.0.1','DB_PORT'=>isset($db['port'])?(string)$db['port']:'3306',
 'DB_NAME'=>isset($db['name'])?$db['name']:'','DB_USER'=>isset($db['username'])?$db['username']:'','DB_PASS'=>isset($db['password'])?$db['password']:'',
 'WEB_STYLE_DARK'=>!empty($c['web_style_dark']),'SITE_BASE'=>isset($c['site_base'])?$c['site_base']:'/',
 'IMAGE_DEFAULT_GLOVE_T'=>isset($images['default_glove_t'])?$images['default_glove_t']:'img/weapon/glove_default_t.png',
 'IMAGE_DEFAULT_GLOVE_CT'=>isset($images['default_glove_ct'])?$images['default_glove_ct']:'img/weapon/glove_default_ct.png',
 'IMAGE_BASE_SKINS'=>isset($images['base_skins'])?$images['base_skins']:'img/skins/',
 'IMAGE_BASE_WEAPONS'=>isset($images['base_weapons'])?$images['base_weapons']:'img/weapon/',
 'IMAGE_BASE_AGENTS'=>isset($images['base_agents'])?$images['base_agents']:'img/',
 'STEAM_API_KEY'=>isset($steam['api_key'])?$steam['api_key']:'','STEAM_DOMAIN_NAME'=>isset($steam['domain_name'])?$steam['domain_name']:'',
 'STEAM_LOGOUT_PAGE'=>isset($steam['logout_page'])?$steam['logout_page']:'','STEAM_LOGIN_PAGE'=>isset($steam['login_page'])?$steam['login_page']:''
);
foreach($values as $name=>$value)if(!defined($name))define($name,$value);
