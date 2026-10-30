<?php
// panel/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   Allowed Panel Pages
========================================================= */

$allowedPages = [

    'overview' => [
        'file' => 'overview.php',
        'default' => true
    ],

    'monitoring' => [
        'file' => 'monitoring.php',
        'default' => false
    ],

    'servers' => [
        'file' => 'servers.php',
        'default' => false
    ],

    'server' => [
        'file' => 'server.php',
        'default' => false
    ],

    'players' => [
        'file' => 'players.php',
        'default' => false
    ],

    'console' => [
        'file' => 'console.php',
        'default' => false
    ],

    'maps' => [
        'file' => 'maps.php',
        'default' => false
    ],

    'config' => [
        'file' => 'config.php',
        'default' => false
    ],

    'automations' => [
        'file' => 'automations.php',
        'default' => false
    ],

    'files' => [
        'file' => 'files.php',
        'default' => false
    ],

    'plugins' => [
        'file' => 'plugins.php',
        'default' => false
    ],

    'backups' => [
        'file' => 'backups.php',
        'default' => false
    ],

    'logs' => [
        'file' => 'logs.php',
        'default' => false
    ],

    'admins' => [
        'file' => 'admins.php',
        'default' => false
    ],

    'webhooks' => [
        'file' => 'webhooks.php',
        'default' => false
    ],

    'settings' => [
        'file' => 'settings.php',
        'default' => false
    ]
];

/* =========================================================
   Requested Page
========================================================= */

$page =
    $_GET['page']
    ?? 'servers';


if (
    !isset(
        $allowedPages[$page]
    )
) {

    $page =
        'servers';

}


$targetFile =
    $allowedPages[$page]['file'];


/* =========================================================
   Build Query Parameters
========================================================= */

$queryParams = [];


/*
 * Language
 */

if (
    isset($_GET['lang']) &&
    in_array(
        $_GET['lang'],
        ['fa', 'en'],
        true
    )
) {

    $queryParams['lang'] =
        $_GET['lang'];

}


/*
 * Server ID
 */

if (
    isset($_GET['server_id']) &&
    $_GET['server_id'] !== ''
) {

    $queryParams['server_id'] =
        $_GET['server_id'];

}


/*
 * Legacy ID Support
 */

if (
    isset($_GET['id']) &&
    $_GET['id'] !== ''
) {

    $queryParams['id'] =
        $_GET['id'];

}


/* =========================================================
   Redirect
========================================================= */

$targetUrl =
    $targetFile;


if (
    !empty($queryParams)
) {

    $targetUrl .=
        '?' .
        http_build_query(
            $queryParams
        );

}


header(
    'Location: ' .
    $targetUrl,
    true,
    302
);

exit;