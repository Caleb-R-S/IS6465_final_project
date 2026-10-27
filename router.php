<?php

declare(strict_types=1);

$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$relativePath = ltrim($requestPath, '/');

if (
    preg_match('#(^|/)\.#', $relativePath) === 1
    || preg_match('#^(app|bin|data|includes|\.git|\.devcontainer|\.vscode)(/|$)#', $relativePath) === 1
    || $relativePath === 'Final_Project.zip'
) {
    http_response_code(404);
    exit;
}

if ($relativePath === '') {
    return false;
}

$phpPages = [
    'index.php',
    'centers.php',
    'register.php',
    'login.php',
    'logout.php',
    'account.php',
    'appointments.php',
    'reports.php',
    'staff.php',
];

if (substr($relativePath, -4) === '.php') {
    if (!in_array($relativePath, $phpPages, true)) {
        http_response_code(404);
        exit;
    }

    return false;
}

if (preg_match('#^(css|images)/[A-Za-z0-9._/-]+\.(css|png|jpe?g|svg|webp)$#i', $relativePath) !== 1) {
    http_response_code(404);
    exit;
}

$realPath = realpath(__DIR__ . '/' . $relativePath);
if ($realPath === false || strpos($realPath, __DIR__ . DIRECTORY_SEPARATOR) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    exit;
}

return false;
