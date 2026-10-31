<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/layout.php';

requireLogin(['personnel', 'admin']);
$database = db();
$donorCount = (int) $database->query('SELECT COUNT(*) FROM donors')->fetchColumn();
$weekStatement = $database->query(
    'SELECT COUNT(*) AS donation_count, COALESCE(SUM(amount_ml), 0) AS volume_ml
     FROM donations
     WHERE date(donated_at) >= date("now", "-6 days")'
);
$thisWeek = $weekStatement->fetch();
$monthPayoutCents = (int) $database->query(
    'SELECT COALESCE(SUM(compensations.amount_cents), 0)
     FROM compensations
     WHERE date(compensations.paid_at) >= date("now", "start of month")'
)->fetchColumn();
$bloodTypeReport = $database->query(
    'SELECT blood_type, COUNT(*) AS donations, COALESCE(SUM(amount_ml), 0) AS volume_ml
     FROM donations
     WHERE date(donated_at) >= date("now", "-6 days")
     GROUP BY blood_type
     ORDER BY blood_type'
)->fetchAll();
$centerReport = $database->query(
    'SELECT donation_centers.name, donation_centers.city, COUNT(donations.id) AS donation_count,
            COALESCE(SUM(donations.amount_ml), 0) AS volume_ml
     FROM donation_centers
     LEFT JOIN donations ON donations.center_id = donation_centers.id
     GROUP BY donation_centers.id
     ORDER BY donation_count DESC, donation_centers.name'
)->fetchAll();
$donorReport = $database->query(
    'SELECT donors.id, donors.first_name, donors.last_name, donors.email,
            COUNT(donations.id) AS donation_count,
            COALESCE(SUM(compensations.amount_cents), 0) AS compensated_cents
     FROM donors
     LEFT JOIN donations ON donations.donor_id = donors.id
     LEFT JOIN compensations ON compensations.donation_id = donations.id
     GROUP BY donors.id
     ORDER BY donors.last_name, donors.first_name
     LIMIT 100'
)->fetchAll();

renderHeader('Reports');
?>
<section class="page-heading">
    <p class="eyebrow">Operations overview</p>
    <h1>Donation reports</h1>
    <p>Summary data from the project database. Only authorized personnel can access reports.</p>
</section>
<section class="metric-grid" aria-label="Key metrics">
    <article class="metric-card"><span>Donor profiles</span><strong><?= $donorCount ?></strong><small>All demo donor records</small></article>
    <article class="metric-card"><span>Donations this week</span><strong><?= (int) $thisWeek['donation_count'] ?></strong><small><?= number_format((float) $thisWeek['volume_ml']) ?> mL recorded</small></article>
    <article class="metric-card"><span>Compensation this month</span><strong>$<?= number_format($monthPayoutCents / 100, 2) ?></strong><small>Recorded disbursements</small></article>
</section>
<section class="report-grid">
    <article class="panel">
        <div class="section-heading"><div><p class="eyebrow">Last seven days</p><h2>Donations by blood type</h2></div></div>
        <?php if ($bloodTypeReport === []): ?>
            <p class="muted-copy">No donation data has been recorded for this period.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Blood type</th><th>Donations</th><th>Volume</th></tr></thead>
                <tbody><?php foreach ($bloodTypeReport as $row): ?>
                    <tr><td><?= e($row['blood_type']) ?></td><td><?= (int) $row['donations'] ?></td><td><?= number_format((float) $row['volume_ml']) ?> mL</td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </article>
    <article class="panel">
        <div class="section-heading"><div><p class="eyebrow">All activity</p><h2>Donation centers</h2></div></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Center</th><th>Donations</th><th>Volume</th></tr></thead>
            <tbody><?php foreach ($centerReport as $row): ?>
                <tr><td><?= e($row['name']) ?><small class="table-subtitle"><?= e($row['city']) ?></small></td><td><?= (int) $row['donation_count'] ?></td><td><?= number_format((float) $row['volume_ml']) ?> mL</td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </article>
</section>
<section class="panel report-panel">
    <div class="section-heading"><div><p class="eyebrow">Donor activity</p><h2>Donations and compensation by donor</h2></div></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Donor</th><th>Email</th><th>Donations</th><th>Compensation</th></tr></thead>
        <tbody>
        <?php if ($donorReport === []): ?><tr><td colspan="4">No donor records have been added.</td></tr><?php endif; ?>
        <?php foreach ($donorReport as $row): ?>
            <tr>
                <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= e($row['email']) ?></td>
                <td><?= (int) $row['donation_count'] ?></td>
                <td>$<?= number_format((int) $row['compensated_cents'] / 100, 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php renderFooter(); ?>
