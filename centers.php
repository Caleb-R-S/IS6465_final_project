<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$centers = db()->query(
    'SELECT id, name, address_line, city, state, postal_code, phone
     FROM donation_centers
     WHERE active = 1
     ORDER BY state, city, name'
)->fetchAll();

render_header('Donation centers');
?>
<section class="page-heading">
    <p class="eyebrow">Locations</p>
    <h1>Find a donation center</h1>
    <p>Explore sample locations and choose a center when you request an appointment.</p>
</section>
<section class="center-grid" aria-label="Donation centers">
    <?php foreach ($centers as $center): ?>
        <article class="center-card">
            <span class="center-icon" aria-hidden="true">⌖</span>
            <p class="eyebrow"><?= e($center['state']) ?> · <?= e($center['postal_code']) ?></p>
            <h2><?= e($center['name']) ?></h2>
            <address>
                <?= e($center['address_line']) ?><br>
                <?= e($center['city']) ?>, <?= e($center['state']) ?> <?= e($center['postal_code']) ?>
            </address>
            <a class="text-link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $center['phone'])) ?>">
                <?= e($center['phone']) ?>
            </a>
            <?php if (current_user() !== null && current_user()['role'] === 'donor'): ?>
                <a class="button button-outline button-full" href="appointments.php?center_id=<?= (int) $center['id'] ?>">Request a visit</a>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php render_footer(); ?>
