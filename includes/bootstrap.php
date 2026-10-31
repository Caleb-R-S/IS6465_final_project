<?php

declare(strict_types=1);

use Biomat\Database;

function db(): PDO
{
    return Database::connection();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals(csrf_token(), $submittedToken)) {
        http_response_code(400);
        exit('The form expired. Please go back, reload the page, and try again.');
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;
    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId)) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT users.id, users.email, users.role, donors.id AS donor_id
         FROM users
         LEFT JOIN donors ON donors.user_id = users.id
         WHERE users.id = :id'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch() ?: null;

    if ($user === null) {
        unset($_SESSION['user_id']);
    }

    return $user;
}

function require_login(array $roles = []): array
{
    $user = current_user();

    if ($user === null) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }

    if ($roles !== [] && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }

    return $user;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $message;
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

function blood_types(): array
{
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
}

function ssn_hmac(string $digits): string
{
    $key = getenv('BIOMAT_SSN_HMAC_KEY');

    if ($key === false || $key === '') {
        $databasePath = getenv('BIOMAT_DB_PATH') ?: dirname(__DIR__) . '/data/biomat.sqlite';
        $keyPath = dirname($databasePath) . '/.ssn-hmac-key';

        if (!is_file($keyPath)) {
            $handle = fopen($keyPath, 'x');
            if ($handle === false) {
                if (!is_file($keyPath)) {
                    throw new RuntimeException('The Social Security number protection key could not be created.');
                }
            } else {
                $generatedKey = bin2hex(random_bytes(32));
                $written = fwrite($handle, $generatedKey);
                fclose($handle);

                if ($written !== strlen($generatedKey) || !chmod($keyPath, 0600)) {
                    throw new RuntimeException('The Social Security number protection key could not be secured.');
                }
            }
        }

        $key = file_get_contents($keyPath);
        if (!is_string($key)) {
            throw new RuntimeException('The Social Security number protection key could not be read.');
        }
    }

    if (strlen($key) < 32) {
        throw new RuntimeException('BIOMAT_SSN_HMAC_KEY must contain at least 32 characters.');
    }

    return hash_hmac('sha256', $digits, $key);
}

function render_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $date = new DateTimeImmutable($value);

    return $date->format('M j, Y \a\t g:i A \U\T\C');
}
