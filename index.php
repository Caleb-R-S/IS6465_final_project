<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/layout.php';

$centerCount = (int) db()->query('SELECT COUNT(*) FROM donation_centers WHERE active = 1')->fetchColumn();
renderHeader('Donor services');
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Plasma donation, made simpler</p>
        <h1>Your time makes a difference.</h1>
        <p class="hero-intro">
            Find a nearby donation center, manage your appointments, and keep your donor
            information together in one place.
        </p>
        <div class="button-row">
            <?php if (currentUser() === null): ?>
                <a class="button" href="register.php">Create a donor account</a>
                <a class="button button-outline" href="centers.php">Explore centers</a>
            <?php elseif (currentUser()['role'] === 'donor'): ?>
                <a class="button" href="appointments.php">Manage appointments</a>
                <a class="button button-outline" href="account.php">View my account</a>
            <?php else: ?>
                <a class="button" href="staff.php">Open staff workspace</a>
                <a class="button button-outline" href="reports.php">View reports</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-art" role="img" aria-label="A simple illustration of a drop of plasma">
        <div class="orbit orbit-one"></div>
        <div class="orbit orbit-two"></div>
        <div class="drop-shape"><span>Give<br>with care</span></div>
        <span class="art-caption">Every visit counts</span>
    </div>
</section>

<section class="section section-muted">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Donor tools</p>
            <h2>Support at every step</h2>
        </div>
        <a class="text-link" href="centers.php">Browse <?= $centerCount ?> demo centers <span aria-hidden="true">→</span></a>
    </div>
    <div class="feature-grid">
        <article class="feature-card">
            <span class="feature-number">01</span>
            <h3>Find a center</h3>
            <p>Review center locations and contact details before planning your visit.</p>
            <a class="text-link" href="centers.php">View locations <span aria-hidden="true">→</span></a>
        </article>
        <article class="feature-card">
            <span class="feature-number">02</span>
            <h3>Plan a visit</h3>
            <p>Request, review, reschedule, or cancel an upcoming appointment.</p>
            <a class="text-link" href="<?= currentUser() === null ? 'login.php' : 'appointments.php' ?>">Manage appointments <span aria-hidden="true">→</span></a>
        </article>
        <article class="feature-card">
            <span class="feature-number">03</span>
            <h3>Keep details current</h3>
            <p>Update your contact details and review your donor profile when needed.</p>
            <a class="text-link" href="<?= currentUser() === null ? 'register.php' : 'account.php' ?>">Visit your account <span aria-hidden="true">→</span></a>
        </article>
    </div>
</section>
<aside class="demo-callout">
    <strong>Course project demo</strong>
    <span>Center details and activity in this site are sample data—not a real appointment or payment service.</span>
</aside>
<?php renderFooter(); ?>
