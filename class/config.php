<?php
require_once __DIR__ . '/../settings/loader.php';

$c = fragcity_settings('files.class.config', []);
$c = is_array($c) ? $c : [];
$images = isset($c['images']) && is_array($c['images']) ? $c['images'] : [];
$steam = fragcity_settings('integrations.steam', []);
$steam = is_array($steam) ? $steam : [];

$values = [
    'SKIN_LANGUAGE' => isset($c['skin_language']) ? (string)$c['skin_language'] : 'skins_en',
    'WEB_STYLE_DARK' => !empty($c['web_style_dark']),
    'SITE_BASE' => isset($c['site_base']) ? (string)$c['site_base'] : '/',
    'IMAGE_DEFAULT_GLOVE_T' => isset($images['default_glove_t']) ? (string)$images['default_glove_t'] : 'img/weapon/glove_default_t.png',
    'IMAGE_DEFAULT_GLOVE_CT' => isset($images['default_glove_ct']) ? (string)$images['default_glove_ct'] : 'img/weapon/glove_default_ct.png',
    'IMAGE_BASE_SKINS' => isset($images['base_skins']) ? (string)$images['base_skins'] : 'img/skins/',
    'IMAGE_BASE_WEAPONS' => isset($images['base_weapons']) ? (string)$images['base_weapons'] : 'img/weapon/',
    'IMAGE_BASE_AGENTS' => isset($images['base_agents']) ? (string)$images['base_agents'] : 'img/',
    'STEAM_API_KEY' => isset($steam['api_key']) ? (string)$steam['api_key'] : '',
    'STEAM_DOMAIN_NAME' => isset($steam['domain_name']) ? (string)$steam['domain_name'] : '',
    'STEAM_LOGOUT_PAGE' => isset($steam['logout_page']) ? (string)$steam['logout_page'] : '',
    'STEAM_LOGIN_PAGE' => isset($steam['login_page']) ? (string)$steam['login_page'] : '',
];

foreach ($values as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}
