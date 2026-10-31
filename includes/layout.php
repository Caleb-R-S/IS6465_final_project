<?php

declare(strict_types=1);

function render_header(string $title): void
{
    $user = current_user();
    $notice = take_flash();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7f2">
    <title><?= e($title) ?> | Biomat Donor Services</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="nav-wrap">
        <a class="brand" href="index.php" aria-label="Biomat Donor Services home">
            <span class="brand-mark" aria-hidden="true">B</span>
            <span>Biomat <span class="brand-light">Donor Services</span></span>
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="centers.php">Centers</a>
            <?php if ($user !== null && $user['role'] === 'donor'): ?>
                <a href="appointments.php">Appointments</a>
                <a href="account.php">My account</a>
            <?php elseif ($user !== null): ?>
                <a href="reports.php">Reports</a>
                <a href="staff.php">Staff workspace</a>
            <?php endif; ?>
            <?php if ($user === null): ?>
                <a href="login.php">Sign in</a>
                <a class="button button-small" href="register.php">Create account</a>
            <?php else: ?>
                <span class="nav-user"><?= e($user['email']) ?></span>
                <form class="inline-form" action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="nav-button" type="submit">Sign out</button>
                </form>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php if ($notice !== null): ?>
    <div class="notice notice-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div>
<?php endif; ?>
<main id="main">
    <?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="site-footer mt-auto">
    <div class="footer-inner">
        <span>Biomat Donor Services</span>
        <span>Student project demonstration · Do not enter real personal or health information.</span>
    </div>
</footer>
</body>
</html>
    <?php
}
