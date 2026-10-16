<?php

require_once __DIR__ . '/../settings/loader.php';
$cfg=fragcity_settings('files.agent.db',array());
$profile=isset($cfg['database_profile'])?$cfg['database_profile']:'main';
$pdo=fragcity_create_pdo($profile);
if(!$pdo){http_response_code(503);die(json_encode(array('status'=>'error','message'=>'Database unavailable or not configured.')));}
