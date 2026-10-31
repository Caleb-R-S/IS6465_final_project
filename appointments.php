<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/layout.php';

$user = requireLogin(['donor']);

if ($user['donor_id'] === null) {
    http_response_code(404);
    exit('Your donor profile could not be found.');
}

$errors = [];
$formValues = ['center_id' => '', 'starts_at' => ''];
$editingId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editingAppointment = null;

if ($editingId !== null && $editingId !== false) {
    $editStatement = db()->prepare(
        'SELECT id, center_id, starts_at
         FROM appointments
         WHERE id = :id AND donor_id = :donor_id AND status = "booked" AND starts_at > datetime("now")'
    );
    $editStatement->execute(['id' => $editingId, 'donor_id' => $user['donor_id']]);
    $editingAppointment = $editStatement->fetch() ?: null;

    if ($editingAppointment === null) {
        flash('error', 'Only your upcoming booked appointments can be changed.');
        redirect('appointments.php');
    }

    $formValues = [
        'center_id' => (string) $editingAppointment['center_id'],
        'starts_at' => str_replace(' ', 'T', substr($editingAppointment['starts_at'], 0, 16)),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'cancel') {
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        $cancelStatement = db()->prepare(
            'UPDATE appointments
             SET status = "cancelled"
             WHERE id = :id AND donor_id = :donor_id AND status = "booked" AND starts_at > datetime("now")'
        );
        $cancelStatement->execute([
            'id' => $appointmentId === false ? 0 : $appointmentId,
            'donor_id' => $user['donor_id'],
        ]);
        flash(
            $cancelStatement->rowCount() === 1 ? 'success' : 'error',
            $cancelStatement->rowCount() === 1
                ? 'Your appointment has been cancelled.'
                : 'That appointment could not be cancelled. Refresh and try again.'
        );
        redirect('appointments.php');
    }

    if ($action === 'save') {
        $centerId = filter_input(INPUT_POST, 'center_id', FILTER_VALIDATE_INT);
        $startsAtInput = trim((string) ($_POST['starts_at'] ?? ''));
        $formValues = [
            'center_id' => $centerId === false ? '' : (string) $centerId,
            'starts_at' => $startsAtInput,
        ];
        $startsAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startsAtInput);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if ($centerId === false || $centerId === null || $centerId < 1) {
            $errors[] = 'Choose a donation center.';
        }
        if (
            $startsAt === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $startsAt->format('Y-m-d\TH:i') !== $startsAtInput
            || $startsAt <= new DateTimeImmutable()
        ) {
            $errors[] = 'Choose a valid appointment date and time in the future.';
        }

        $centerStatement = db()->prepare('SELECT id FROM donation_centers WHERE id = :id AND active = 1');
        $centerStatement->execute(['id' => $centerId === false ? 0 : $centerId]);
        if ($centerStatement->fetchColumn() === false) {
            $errors[] = 'Choose an available donation center.';
        }

        if ($errors === []) {
            $startValue = $startsAt->format('Y-m-d H:i:s');

            if ($editingAppointment !== null) {
                $saveStatement = db()->prepare(
                    'UPDATE appointments SET center_id = :center_id, starts_at = :starts_at
                     WHERE id = :id AND donor_id = :donor_id AND status = "booked" AND starts_at > datetime("now")'
                );
                $saveStatement->execute([
                    'center_id' => $centerId,
                    'starts_at' => $startValue,
                    'id' => $editingAppointment['id'],
                    'donor_id' => $user['donor_id'],
                ]);
                if ($saveStatement->rowCount() !== 1) {
                    $errors[] = 'This appointment is no longer available to update.';
                }
            } else {
                $saveStatement = db()->prepare(
                    'INSERT INTO appointments (donor_id, center_id, starts_at)
                     VALUES (:donor_id, :center_id, :starts_at)'
                );
                $saveStatement->execute([
                    'donor_id' => $user['donor_id'],
                    'center_id' => $centerId,
                    'starts_at' => $startValue,
                ]);
            }

            if ($errors === []) {
                flash('success', $editingAppointment === null ? 'Your appointment request has been saved.' : 'Your appointment has been updated.');
                redirect('appointments.php');
            }
        }
    }
}

$centerStatement = db()->query(
    'SELECT id, name, city, state FROM donation_centers WHERE active = 1 ORDER BY city, name'
);
$centers = $centerStatement->fetchAll();
$appointmentStatement = db()->prepare(
    'SELECT appointments.id, appointments.starts_at, appointments.status,
            donation_centers.name AS center_name, donation_centers.city, donation_centers.state
     FROM appointments
     JOIN donation_centers ON donation_centers.id = appointments.center_id
     WHERE appointments.donor_id = :donor_id
     ORDER BY appointments.starts_at DESC'
);
$appointmentStatement->execute(['donor_id' => $user['donor_id']]);
$appointments = $appointmentStatement->fetchAll();

renderHeader('My appointments');
?>
<section class="page-heading">
    <p class="eyebrow">Plan your next visit</p>
    <h1>My appointments</h1>
    <p>Request a visit at an available center. You can update or cancel upcoming booked appointments.</p>
</section>
<section class="appointment-layout">
    <div class="form-card appointment-form-card">
        <h2><?= $editingAppointment === null ? 'Request an appointment' : 'Update appointment' ?></h2>
        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="<?= $editingAppointment === null ? 'appointments.php' : 'appointments.php?edit=' . (int) $editingAppointment['id'] ?>">
            <input type="hidden" name="csrfToken" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="save">
            <label>Donation center
                <select name="center_id" required>
                    <option value="">Select a center</option>
                    <?php foreach ($centers as $center): ?>
                        <option value="<?= (int) $center['id'] ?>" <?= $formValues['center_id'] === (string) $center['id'] ? 'selected' : '' ?>>
                            <?= e($center['name'] . ' · ' . $center['city'] . ', ' . $center['state']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Date and time (UTC)<input name="starts_at" type="datetime-local" required value="<?= e($formValues['starts_at']) ?>"></label>
            <div class="button-row">
                <button class="button" type="submit"><?= $editingAppointment === null ? 'Request appointment' : 'Save changes' ?></button>
                <?php if ($editingAppointment !== null): ?><a class="button button-outline" href="appointments.php">Cancel edit</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="appointment-list">
        <div class="section-heading">
            <div><p class="eyebrow">Your schedule</p><h2>Appointment history</h2></div>
        </div>
        <?php if ($appointments === []): ?>
            <div class="empty-state"><h3>No appointments yet</h3><p>Your upcoming and past appointments will appear here.</p></div>
        <?php else: ?>
            <?php foreach ($appointments as $appointment): ?>
                <?php $isUpcoming = $appointment['status'] === 'booked' && strtotime($appointment['starts_at']) > time(); ?>
                <article class="appointment-card">
                    <div class="appointment-date">
                        <span><?= e((new DateTimeImmutable($appointment['starts_at']))->format('M')) ?></span>
                        <strong><?= e((new DateTimeImmutable($appointment['starts_at']))->format('j')) ?></strong>
                    </div>
                    <div class="appointment-detail">
                        <span class="status status-<?= e($appointment['status']) ?>"><?= e(ucfirst($appointment['status'])) ?></span>
                        <h3><?= e($appointment['center_name']) ?></h3>
                        <p><?= e(renderDatetime($appointment['starts_at'])) ?> · <?= e($appointment['city']) ?>, <?= e($appointment['state']) ?></p>
                    </div>
                    <?php if ($isUpcoming): ?>
                        <div class="appointment-actions">
                            <a class="text-link" href="appointments.php?edit=<?= (int) $appointment['id'] ?>">Edit</a>
                            <form method="post" action="appointments.php" onsubmit="return confirm('Cancel this appointment?');">
                                <input type="hidden" name="csrfToken" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                <button class="link-button" type="submit">Cancel</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php renderFooter(); ?>
