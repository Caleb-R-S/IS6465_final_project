<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/layout.php';

$user = requireLogin(['donor']);
$statement = db()->prepare('SELECT * FROM donors WHERE user_id = :user_id');
$statement->execute(['user_id' => $user['id']]);
$donor = $statement->fetch();

if ($donor === false) {
    http_response_code(404);
    exit('Donor profile not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? 'update');

    if ($action === 'delete') {
        $password = (string) ($_POST['password'] ?? '');
        $passwordStatement = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $passwordStatement->execute(['id' => $user['id']]);
        $passwordHash = $passwordStatement->fetchColumn();

        if (!is_string($passwordHash) || !password_verify($password, $passwordHash)) {
            $errors[] = 'Enter your current password to confirm account deletion.';
        } else {
            $database = db();
            $database->beginTransaction();
            $deleteDonorStatement = $database->prepare('DELETE FROM donors WHERE id = :id AND user_id = :user_id');
            $deleteDonorStatement->execute(['id' => $donor['id'], 'user_id' => $user['id']]);
            $deleteStatement = $database->prepare('DELETE FROM users WHERE id = :id');
            $deleteStatement->execute(['id' => $user['id']]);
            $database->commit();
            $_SESSION = [];
            session_regenerate_id(true);
            flash('success', 'Your demo account and associated records have been deleted.');
            redirect('index.php');
        }
    } elseif ($action === 'update') {
        $fields = [
            'first_name',
            'last_name',
            'phone',
            'email',
            'address_line',
            'city',
            'state',
            'postal_code',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_email',
            'blood_type',
        ];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = trim((string) ($_POST[$field] ?? ''));
        }
        $values['first_name'] = properCaseName($values['first_name']);
        $values['last_name'] = properCaseName($values['last_name']);
        $values['emergency_contact_name'] = properCaseName($values['emergency_contact_name']);

        if (in_array('', array_intersect_key($values, array_flip([
            'first_name', 'last_name', 'phone', 'email', 'address_line', 'city', 'state', 'postal_code',
        ])), true)) {
            $errors[] = 'Complete all required contact and profile fields.';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (
            $values['emergency_contact_email'] !== ''
            && !filter_var($values['emergency_contact_email'], FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = 'Enter a valid emergency contact email address.';
        }
        if (!in_array($values['blood_type'], bloodTypes(), true)) {
            $errors[] = 'Choose a valid blood type.';
        }
        $weight = trim((string) ($_POST['weight_lbs'] ?? ''));
        $height = trim((string) ($_POST['height_inches'] ?? ''));
        if (($weight !== '' && (!is_numeric($weight) || (float) $weight <= 0))
            || ($height !== '' && (!is_numeric($height) || (float) $height <= 0))) {
            $errors[] = 'Weight and height must be positive numbers when provided.';
        }

        if ($errors === []) {
            $database = db();
            $database->beginTransaction();
            $updateStatement = $database->prepare(
                'UPDATE donors SET
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    address_line = :address_line,
                    city = :city,
                    state = :state,
                    postal_code = :postal_code,
                    emergency_contact_name = :emergency_contact_name,
                    emergency_contact_phone = :emergency_contact_phone,
                    emergency_contact_email = :emergency_contact_email,
                    blood_type = :blood_type,
                    weight_lbs = :weight_lbs,
                    height_inches = :height_inches,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id'
            );
            $updateStatement->execute([
                'first_name' => $values['first_name'],
                'last_name' => $values['last_name'],
                'email' => $values['email'],
                'phone' => $values['phone'],
                'address_line' => $values['address_line'],
                'city' => $values['city'],
                'state' => $values['state'],
                'postal_code' => $values['postal_code'],
                'emergency_contact_name' => $values['emergency_contact_name'],
                'emergency_contact_phone' => $values['emergency_contact_phone'],
                'emergency_contact_email' => $values['emergency_contact_email'],
                'blood_type' => $values['blood_type'],
                'weight_lbs' => $weight === '' ? null : (float) $weight,
                'height_inches' => $height === '' ? null : (float) $height,
                'id' => $donor['id'],
                'user_id' => $user['id'],
            ]);
            $emailStatement = db()->prepare('UPDATE users SET email = :email WHERE id = :id');
            try {
                $emailStatement->execute(['email' => $values['email'], 'id' => $user['id']]);
                $database->commit();
            } catch (PDOException $exception) {
                $database->rollBack();
                if ($exception->getCode() === '23000') {
                    $errors[] = 'An account with that email address already exists.';
                    $donor = array_merge($donor, $values);
                } else {
                    throw $exception;
                }
            }

            if ($errors === []) {
                flash('success', 'Your donor profile has been updated.');
                redirect('account.php');
            }
        }
        $donor = array_merge($donor, $values, [
            'weight_lbs' => $weight,
            'height_inches' => $height,
        ]);
    }
}

renderHeader('My account');
?>
<section class="page-heading">
    <p class="eyebrow">Donor profile</p>
    <h1>My account</h1>
    <p>Your profile is only available to you and authorized staff in this demo.</p>
</section>
<section class="account-grid">
    <div class="form-card">
        <h2>Personal and contact details</h2>
        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="account.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update">
            <div class="field-grid">
                <label>First name<input name="first_name" required value="<?= e($donor['first_name']) ?>"></label>
                <label>Last name<input name="last_name" required value="<?= e($donor['last_name']) ?>"></label>
                <label>Email<input name="email" type="email" required value="<?= e($donor['email']) ?>"></label>
                <label>Phone<input name="phone" type="tel" required value="<?= e($donor['phone']) ?>"></label>
                <label class="field-wide">Street address<input name="address_line" required value="<?= e($donor['address_line']) ?>"></label>
                <label>City<input name="city" required value="<?= e($donor['city']) ?>"></label>
                <label>State<input name="state" required value="<?= e($donor['state']) ?>"></label>
                <label>Postal code<input name="postal_code" required value="<?= e($donor['postal_code']) ?>"></label>
                <label>Blood type
                    <select name="blood_type" required>
                        <?php foreach (bloodTypes() as $bloodType): ?>
                            <option value="<?= e($bloodType) ?>" <?= $donor['blood_type'] === $bloodType ? 'selected' : '' ?>><?= e($bloodType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Weight (lb, optional)<input name="weight_lbs" type="number" step="0.1" min="0" value="<?= e((string) ($donor['weight_lbs'] ?? '')) ?>"></label>
                <label>Height (in, optional)<input name="height_inches" type="number" step="0.1" min="0" value="<?= e((string) ($donor['height_inches'] ?? '')) ?>"></label>
                <label>Emergency contact name<input name="emergency_contact_name" value="<?= e($donor['emergency_contact_name']) ?>"></label>
                <label>Emergency contact phone<input name="emergency_contact_phone" type="tel" value="<?= e($donor['emergency_contact_phone']) ?>"></label>
                <label class="field-wide">Emergency contact email<input name="emergency_contact_email" type="email" value="<?= e($donor['emergency_contact_email']) ?>"></label>
            </div>
            <button class="button" type="submit">Save changes</button>
        </form>
    </div>
    <aside class="danger-card">
        <p class="eyebrow">Account controls</p>
        <h2>Delete your account</h2>
        <p>This permanently removes your demo account and its associated appointment and donation records.</p>
        <form method="post" action="account.php" onsubmit="return confirm('Delete your account and its associated demo records?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
            <label>Confirm with your password<input name="password" type="password" autocomplete="current-password" required></label>
            <button class="button button-danger" type="submit">Delete my account</button>
        </form>
    </aside>
</section>
<?php renderFooter(); ?>
