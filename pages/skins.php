<?php
// pages/skins.php

require_once __DIR__ . '/../settings/session.php';
fragcity_start_session();

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['steamid'])
) {
    $_SESSION['login_return_url'] =
        $_SERVER['REQUEST_URI'] ?? 'index.php?page=skins';

    header('Location: index.php?page=login', true, 303);
    exit;
}
?>

<div class="skins-page-shell">
    <iframe
        class="skins-iframe"
        src="/pages/weaponpaints.php"
        title="FragCity Weapon Customization"
        allow="clipboard-write"
        loading="eager"
    ></iframe>
</div>
