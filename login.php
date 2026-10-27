<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (current_user() !== null) {
    redirect('index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $statement = db()->prepare(
        'SELECT id, password_hash FROM users WHERE email = :email COLLATE NOCASE'
    );
    $statement->execute(['email' => $email]);
    $account = $statement->fetch();

    if ($account !== false && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        redirect('index.php');
    }

    $error = 'The email or password was not recognized.';
}

render_header('Sign in');
?>
<section class="auth-layout">
    <div class="auth-art">
        <p class="eyebrow">Welcome back</p>
        <h1>Your donor journey, all in one place.</h1>
        <p>Sign in to review your profile or manage an upcoming visit.</p>
    </div>
    <div class="form-card auth-card">
        <h2>Sign in</h2>
        <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Email address<input name="email" type="email" autocomplete="username" required value="<?= e($email) ?>"></label>
            <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
            <button class="button button-full" type="submit">Sign in</button>
        </form>
        <p class="form-footnote">New to Biomat Donor Services? <a href="register.php">Create an account</a>.</p>
    </div>
</section>
<?php render_footer(); ?>
