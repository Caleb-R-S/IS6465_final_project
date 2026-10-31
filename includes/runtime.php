<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Database.php';
require_once __DIR__ . '/bootstrap.php';

date_default_timezone_set('UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}
