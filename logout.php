<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/runtime.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the sign-out form to end your session.');
}

verifyCsrf();
$_SESSION = [];
session_regenerate_id(true);
flash('success', 'You have been signed out.');
redirect('index.php');
