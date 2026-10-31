<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/layout.php';

if (currentUser() !== null) {
    redirect('index.php');
}

$errors = [];
$selectedBloodType = '';
$values = [
    'first_name' => '',
    'last_name' => '',
    'date_of_birth' => '',
    'email' => '',
    'phone' => '',
    'address_line' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    foreach ($values as $field => $value) {
        $values[$field] = trim((string) ($_POST[$field] ?? $value));
    }
    $values['first_name'] = properCaseName($values['first_name']);
    $values['last_name'] = properCaseName($values['last_name']);

    $ssnInput = trim((string) ($_POST['ssn'] ?? ''));
    $ssn = preg_replace('/\D/', '', $ssnInput);
    $password = (string) ($_POST['password'] ?? '');
    $bloodType = (string) ($_POST['blood_type'] ?? '');
    $selectedBloodType = $bloodType;

    foreach (
        ['first_name', 'last_name', 'date_of_birth', 'email', 'phone', 'address_line', 'city', 'state', 'postal_code']
        as $field
    ) {
        if ($values[$field] === '') {
            $errors[] = 'Please complete all required profile fields.';
            break;
        }
    }

    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($values['state']) > 40 || strlen($values['postal_code']) > 20) {
        $errors[] = 'Check the state and postal code fields.';
    }
    if (
        !is_string($ssn)
        || strlen($ssn) !== 9
        || preg_match('/^(?:\d{9}|\d{3}-\d{2}-\d{4})$/D', $ssnInput) !== 1
    ) {
        $errors[] = 'Enter a nine-digit Social Security number using digits only or the standard hyphenated format.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Choose a password with at least 10 characters.';
    }
    if (!in_array($bloodType, bloodTypes(), true)) {
        $errors[] = 'Choose a valid blood type.';
    }

    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $values['date_of_birth']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        $birthDate === false
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $birthDate->format('Y-m-d') !== $values['date_of_birth']
        || $birthDate > new DateTimeImmutable('-18 years')
    ) {
        $errors[] = 'Donors must be at least 18 years old. Enter a valid date of birth.';
    }

    if ($errors === []) {
        try {
            $database = db();
            $database->beginTransaction();
            $userStatement = $database->prepare(
                'INSERT INTO users (email, password_hash, role)
                 VALUES (:email, :password_hash, "donor")'
            );
            $userStatement->execute([
                'email' => $values['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $database->lastInsertId();
            $donorStatement = $database->prepare(
                'INSERT INTO donors
                    (user_id, first_name, last_name, date_of_birth, ssn_hash, email, phone,
                     address_line, city, state, postal_code, blood_type)
                 VALUES
                    (:user_id, :first_name, :last_name, :date_of_birth, :ssn_hash, :email, :phone,
                     :address_line, :city, :state, :postal_code, :blood_type)'
            );
            $donorStatement->execute([
                'user_id' => $userId,
                'first_name' => $values['first_name'],
                'last_name' => $values['last_name'],
                'date_of_birth' => $values['date_of_birth'],
                'ssn_hash' => ssnHmac($ssn),
                'email' => $values['email'],
                'phone' => $values['phone'],
                'address_line' => $values['address_line'],
                'city' => $values['city'],
                'state' => $values['state'],
                'postal_code' => $values['postal_code'],
                'blood_type' => $bloodType,
            ]);
            $database->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            redirect('appointments.php');
        } catch (PDOException $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            if ($exception->getCode() === '23000') {
                $errors[] = 'An account with that email address already exists.';
            } else {
                throw $exception;
            }
        }
    }
}

renderHeader('Create donor account');
?>
<section class="form-layout">
    <div class="form-intro">
        <p class="eyebrow">Donor registration</p>
        <h1>Create your donor account</h1>
        <p>Use this demo to manage sample profile details and appointments. Donors must be 18 or older.</p>
        <p class="privacy-note"><strong>Privacy:</strong> The Social Security number is stored only as a keyed digest and is never displayed. Use fictional information only.</p>
    </div>
    <div class="form-card">
        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert">
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="post" action="register.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="field-grid">
                <label>First name<input name="first_name" autocomplete="given-name" required value="<?= e($values['first_name']) ?>"></label>
                <label>Last name<input name="last_name" autocomplete="family-name" required value="<?= e($values['last_name']) ?>"></label>
                <label>Date of birth<input name="date_of_birth" type="date" required value="<?= e($values['date_of_birth']) ?>"></label>
                <label>Social Security number<input name="ssn" inputmode="numeric" autocomplete="off" required pattern="[0-9 -]{9,11}" aria-describedby="ssn-help"><small id="ssn-help">Digits and hyphens accepted; never shown after saving.</small></label>
                <label>Email<input name="email" type="email" autocomplete="email" required value="<?= e($values['email']) ?>"></label>
                <label>Phone<input name="phone" type="tel" autocomplete="tel" required value="<?= e($values['phone']) ?>"></label>
                <label class="field-wide">Street address<input name="address_line" autocomplete="street-address" required value="<?= e($values['address_line']) ?>"></label>
                <label>City<input name="city" autocomplete="address-level2" required value="<?= e($values['city']) ?>"></label>
                <label>State / province<input name="state" autocomplete="address-level1" required value="<?= e($values['state']) ?>"></label>
                <label>Postal code<input name="postal_code" autocomplete="postal-code" required value="<?= e($values['postal_code']) ?>"></label>
                <label>Blood type
                    <select name="blood_type" required>
                        <option value="">Select</option>
                        <?php foreach (bloodTypes() as $bloodType): ?>
                            <option value="<?= e($bloodType) ?>" <?= $selectedBloodType === $bloodType ? 'selected' : '' ?>><?= e($bloodType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-wide">Password <span class="field-hint">At least 10 characters</span>
                    <input name="password" type="password" autocomplete="new-password" minlength="10" required>
                </label>
            </div>
            <button class="button" type="submit">Create account</button>
        </form>
    </div>
</section>
<?php renderFooter(); ?>
