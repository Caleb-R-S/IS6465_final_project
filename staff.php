<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_login(['personnel', 'admin']);
$definitions = [
    'donors' => [
        'title' => 'Donors',
        'table' => 'donors',
        'fields' => [
            ['first_name', 'First name', 'text', true],
            ['last_name', 'Last name', 'text', true],
            ['date_of_birth', 'Date of birth', 'date', true],
            ['ssn', 'Social Security number (hashed)', 'ssn', false],
            ['user_id', 'Linked donor login', 'donor_users', false],
            ['email', 'Email', 'email', true],
            ['phone', 'Phone', 'text', true],
            ['address_line', 'Street address', 'text', true],
            ['city', 'City', 'text', true],
            ['state', 'State', 'text', true],
            ['postal_code', 'Postal code', 'text', true],
            ['emergency_contact_name', 'Emergency contact', 'text', false],
            ['emergency_contact_phone', 'Emergency contact phone', 'text', false],
            ['emergency_contact_email', 'Emergency contact email', 'email', false],
            ['blood_type', 'Blood type', 'blood_types', true],
            ['weight_lbs', 'Weight (lb)', 'number', false],
            ['height_inches', 'Height (in)', 'number', false],
        ],
    ],
    'personnel' => [
        'title' => 'Medical personnel',
        'table' => 'medical_personnel',
        'fields' => [
            ['user_id', 'Linked staff login', 'staff_users', false],
            ['first_name', 'First name', 'text', true],
            ['last_name', 'Last name', 'text', true],
            ['title', 'Job title', 'text', true],
            ['personnel_type', 'Personnel type', 'personnel_types', true],
        ],
    ],
    'donations' => [
        'title' => 'Donations',
        'table' => 'donations',
        'fields' => [
            ['donor_id', 'Donor', 'donors', true],
            ['center_id', 'Donation center', 'centers', true],
            ['doctor_id', 'Doctor', 'doctors', false],
            ['nurse_id', 'Nurse', 'nurses', false],
            ['blood_type', 'Blood type', 'blood_types', true],
            ['amount_ml', 'Amount (mL)', 'number', true],
            ['donated_at', 'Donation date and time (UTC)', 'datetime-local', true],
        ],
    ],
    'appointments' => [
        'title' => 'Appointments',
        'table' => 'appointments',
        'fields' => [
            ['donor_id', 'Donor', 'donors', true],
            ['center_id', 'Donation center', 'centers', true],
            ['donation_id', 'Related donation', 'donations', false],
            ['starts_at', 'Appointment date and time (UTC)', 'datetime-local', true],
            ['status', 'Status', 'appointment_statuses', true],
        ],
    ],
    'compensations' => [
        'title' => 'Compensation',
        'table' => 'compensations',
        'fields' => [
            ['donation_id', 'Donation', 'donations_without_compensation', true],
            ['amount_cents', 'Amount (cents)', 'integer', true],
            ['paid_at', 'Payment date and time', 'datetime-local', true],
        ],
    ],
    'users' => [
        'title' => 'Login accounts',
        'table' => 'users',
        'fields' => [
            ['email', 'Email', 'email', true],
            ['role', 'Role', 'roles', true],
            ['password', 'Password (leave blank to keep current)', 'password', true],
        ],
    ],
];

$allowedEntities = $user['role'] === 'admin'
    ? array_keys($definitions)
    : ['donors', 'personnel', 'donations', 'appointments', 'compensations'];
$entity = (string) ($_POST['entity'] ?? $_GET['entity'] ?? 'donors');

if (!in_array($entity, $allowedEntities, true)) {
    http_response_code(403);
    exit('You do not have permission to manage this record type.');
}

$definition = $definitions[$entity];
$errors = [];
$formValues = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);

    try {
        if ($action === 'delete') {
            if ($recordId === false || $recordId === null || $recordId < 1) {
                throw new InvalidArgumentException('Choose a valid record to delete.');
            }

            if ($entity === 'users') {
                if ($recordId === (int) $user['id']) {
                    throw new InvalidArgumentException('You cannot delete the account you are currently using.');
                }

                $roleStatement = db()->prepare('SELECT role FROM users WHERE id = :id');
                $roleStatement->execute(['id' => $recordId]);
                if ($roleStatement->fetchColumn() === 'admin') {
                    $adminCount = (int) db()->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();
                    if ($adminCount <= 1) {
                        throw new InvalidArgumentException('The last administrator account cannot be deleted.');
                    }
                }
            }

            $deleteStatement = db()->prepare('DELETE FROM ' . $definition['table'] . ' WHERE id = :id');
            $deleteStatement->execute(['id' => $recordId]);
            if ($deleteStatement->rowCount() !== 1) {
                throw new InvalidArgumentException('The selected record no longer exists.');
            }
            flash('success', $definition['title'] . ' record deleted.');
            redirect('staff.php?entity=' . rawurlencode($entity));
        }

        if ($action !== 'create' && $action !== 'update') {
            throw new InvalidArgumentException('Choose a valid staff action.');
        }

        if ($action === 'update' && ($recordId === false || $recordId === null || $recordId < 1)) {
            throw new InvalidArgumentException('Choose a valid record to update.');
        }

        $data = [];
        foreach ($definition['fields'] as $field) {
            [$name, $label, $type, $required] = $field;
            $rawValue = trim((string) ($_POST[$name] ?? ''));
            $formValues[$name] = $rawValue;
            $isOptionalSecret = in_array($type, ['ssn', 'password'], true);

            if ($rawValue === '') {
                if ($required && !($action === 'update' && $isOptionalSecret)) {
                    $errors[] = $label . ' is required.';
                } elseif ($type === 'number' || $type === 'integer' || in_array($type, ['donor_users', 'staff_users', 'donors', 'centers', 'doctors', 'nurses', 'donations', 'donations_without_compensation'], true)) {
                    $data[$name] = null;
                }
                continue;
            }

            if ($type === 'email' && !filter_var($rawValue, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid email address for ' . strtolower($label) . '.';
                continue;
            }
            if ($type === 'password' && strlen($rawValue) < 10) {
                $errors[] = 'Passwords must contain at least 10 characters.';
                continue;
            }
            if ($type === 'ssn') {
                $digits = preg_replace('/\D/', '', $rawValue);
                if (
                    !is_string($digits)
                    || strlen($digits) !== 9
                    || preg_match('/^(?:\d{9}|\d{3}-\d{2}-\d{4})$/D', $rawValue) !== 1
                ) {
                    $errors[] = 'Enter nine digits for the Social Security number.';
                } else {
                    $data['ssn_hash'] = ssn_hmac($digits);
                }
                continue;
            }
            if ($type === 'password') {
                $data['password_hash'] = password_hash($rawValue, PASSWORD_DEFAULT);
                continue;
            }
            if ($type === 'datetime-local') {
                $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $rawValue);
                $dateErrors = DateTimeImmutable::getLastErrors();
                if (
                    $dateTime === false
                    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                    || $dateTime->format('Y-m-d\TH:i') !== $rawValue
                ) {
                    $errors[] = 'Enter a valid date and time for ' . strtolower($label) . '.';
                    continue;
                }
                $data[$name] = $dateTime->format('Y-m-d H:i:s');
                continue;
            }
            if ($type === 'date') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawValue);
                $dateErrors = DateTimeImmutable::getLastErrors();
                if (
                    $date === false
                    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                    || $date->format('Y-m-d') !== $rawValue
                ) {
                    $errors[] = 'Enter a valid date for ' . strtolower($label) . '.';
                    continue;
                }
                if ($entity === 'donors' && $name === 'date_of_birth' && $date > new DateTimeImmutable('-18 years')) {
                    $errors[] = 'Donors must be at least 18 years old.';
                    continue;
                }
                $data[$name] = $rawValue;
                continue;
            }
            if ($type === 'number' || $type === 'integer') {
                if (
                    !is_numeric($rawValue)
                    || (float) $rawValue < 0
                    || ($type === 'number' && $required && (float) $rawValue <= 0)
                ) {
                    $errors[] = $label . ' must be a valid positive number.';
                    continue;
                }
                $data[$name] = $type === 'integer' ? (int) $rawValue : (float) $rawValue;
                continue;
            }
            if (in_array($type, ['donor_users', 'staff_users', 'donors', 'centers', 'doctors', 'nurses', 'donations', 'donations_without_compensation'], true)) {
                if (filter_var($rawValue, FILTER_VALIDATE_INT) === false || (int) $rawValue < 1) {
                    $errors[] = 'Choose a valid option for ' . strtolower($label) . '.';
                    continue;
                }
                $data[$name] = (int) $rawValue;
                continue;
            }

            $options = [
                'blood_types' => blood_types(),
                'personnel_types' => ['doctor', 'nurse', 'staff'],
                'appointment_statuses' => ['booked', 'cancelled', 'completed'],
                'roles' => ['donor', 'personnel', 'admin'],
            ];
            if (isset($options[$type]) && !in_array($rawValue, $options[$type], true)) {
                $errors[] = 'Choose a valid option for ' . strtolower($label) . '.';
                continue;
            }
            $data[$name] = $rawValue;
        }

        if ($errors === []) {
            if ($entity === 'users' && $action === 'update' && isset($data['role']) && $data['role'] !== 'admin') {
                $currentRoleStatement = db()->prepare('SELECT role FROM users WHERE id = :id');
                $currentRoleStatement->execute(['id' => $recordId]);
                if ($currentRoleStatement->fetchColumn() === 'admin') {
                    $adminCount = (int) db()->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();
                    if ($recordId === (int) $user['id'] || $adminCount <= 1) {
                        throw new InvalidArgumentException('You cannot remove the last administrator or change your own administrator role.');
                    }
                }
            }

            $databaseFields = array_keys($data);
            if ($databaseFields === []) {
                throw new InvalidArgumentException('Enter at least one value to save.');
            }

            if ($action === 'create') {
                $columns = implode(', ', $databaseFields);
                $placeholders = implode(', ', array_map(static function (string $field): string {
                    return ':' . $field;
                }, $databaseFields));
                $saveStatement = db()->prepare(
                    'INSERT INTO ' . $definition['table'] . ' (' . $columns . ')
                     VALUES (' . $placeholders . ')'
                );
            } else {
                $assignments = implode(', ', array_map(static function (string $field): string {
                    return $field . ' = :' . $field;
                }, $databaseFields));
                $saveStatement = db()->prepare(
                    'UPDATE ' . $definition['table'] . ' SET ' . $assignments . ' WHERE id = :record_id'
                );
                $data['record_id'] = $recordId;
            }

            $saveStatement->execute($data);
            flash('success', $definition['title'] . ' record ' . ($action === 'create' ? 'created.' : 'updated.'));
            redirect('staff.php?entity=' . rawurlencode($entity));
        }
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Biomat staff action failed: ' . $exception->getMessage());
        $errors[] = 'The record could not be saved. Check the email address, required fields, and linked records.';
    }
}

function staff_options(string $type): array
{
    $queries = [
        'donor_users' => 'SELECT id, email AS label FROM users WHERE role = "donor" ORDER BY email',
        'staff_users' => 'SELECT id, email AS label FROM users WHERE role = "personnel" ORDER BY email',
        'donors' => 'SELECT id, first_name || " " || last_name || " · " || email AS label FROM donors ORDER BY last_name, first_name',
        'centers' => 'SELECT id, name || " · " || city AS label FROM donation_centers WHERE active = 1 ORDER BY city, name',
        'doctors' => 'SELECT id, first_name || " " || last_name AS label FROM medical_personnel WHERE personnel_type = "doctor" ORDER BY last_name, first_name',
        'nurses' => 'SELECT id, first_name || " " || last_name AS label FROM medical_personnel WHERE personnel_type = "nurse" ORDER BY last_name, first_name',
        'donations' => 'SELECT id, "Donation #" || id || " · " || donated_at AS label FROM donations ORDER BY donated_at DESC',
        'donations_without_compensation' => 'SELECT donations.id, "Donation #" || donations.id || " · " || donors.first_name || " " || donors.last_name AS label
            FROM donations JOIN donors ON donors.id = donations.donor_id
            LEFT JOIN compensations ON compensations.donation_id = donations.id
            WHERE compensations.id IS NULL ORDER BY donations.donated_at DESC',
    ];

    if (!isset($queries[$type])) {
        return [];
    }

    return db()->query($queries[$type])->fetchAll();
}

function staff_field_value(array $record, array $field): string
{
    [$name, , $type] = $field;

    if ($type === 'password' || $type === 'ssn') {
        return '';
    }
    if (!isset($record[$name]) || $record[$name] === null) {
        return '';
    }
    if ($type === 'datetime-local') {
        return str_replace(' ', 'T', substr((string) $record[$name], 0, 16));
    }

    return (string) $record[$name];
}

$recordStatement = db()->query(
    'SELECT * FROM ' . $definition['table'] . ' ORDER BY id DESC LIMIT 100'
);
$records = $recordStatement->fetchAll();

render_header('Staff workspace');
?>
<section class="page-heading">
    <p class="eyebrow"><?= e(ucfirst($user['role'])) ?> workspace</p>
    <h1>Record management</h1>
    <p>Create, review, update, and remove operational records. Login-account management is limited to administrators.</p>
</section>
<nav class="tabs" aria-label="Record type">
    <?php foreach ($allowedEntities as $recordType): ?>
        <a class="<?= $entity === $recordType ? 'tab-active' : '' ?>" href="staff.php?entity=<?= e($recordType) ?>">
            <?= e($definitions[$recordType]['title']) ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php if ($errors !== []): ?>
    <div class="notice notice-error staff-notice" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<section class="staff-layout">
    <article class="form-card staff-create">
        <p class="eyebrow">New record</p>
        <h2>Add <?= e(strtolower(rtrim($definition['title'], 's'))) ?></h2>
        <form method="post" action="staff.php?entity=<?= e($entity) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="entity" value="<?= e($entity) ?>">
            <input type="hidden" name="action" value="create">
            <?php foreach ($definition['fields'] as $field): ?>
                <?php
                [$name, $label, $type, $required] = $field;
                $required = $required && $type !== 'ssn';
                if ($type === 'blood_types') {
                    $choices = array_map(static function (string $value): array {
                        return ['id' => $value, 'label' => $value];
                    }, blood_types());
                } elseif ($type === 'personnel_types') {
                    $choices = array_map(static function (string $value): array {
                        return ['id' => $value, 'label' => ucfirst($value)];
                    }, ['doctor', 'nurse', 'staff']);
                } elseif ($type === 'appointment_statuses') {
                    $choices = array_map(static function (string $value): array {
                        return ['id' => $value, 'label' => ucfirst($value)];
                    }, ['booked', 'cancelled', 'completed']);
                } elseif ($type === 'roles') {
                    $choices = array_map(static function (string $value): array {
                        return ['id' => $value, 'label' => ucfirst($value)];
                    }, ['donor', 'personnel', 'admin']);
                } elseif (in_array($type, ['donor_users', 'staff_users', 'donors', 'centers', 'doctors', 'nurses', 'donations', 'donations_without_compensation'], true)) {
                    $choices = staff_options($type);
                } else {
                    $choices = [];
                }
                ?>
                <label><?= e($label) ?>
                    <?php if ($choices !== [] || in_array($type, ['blood_types', 'personnel_types', 'appointment_statuses', 'roles'], true)): ?>
                        <select name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                            <?php if (!$required): ?><option value="">Not assigned</option><?php endif; ?>
                            <?php foreach ($choices as $choice): ?>
                                <option value="<?= e((string) $choice['id']) ?>" <?= (($formValues[$name] ?? '') === (string) $choice['id']) ? 'selected' : '' ?>>
                                    <?= e($choice['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input
                            name="<?= e($name) ?>"
                            type="<?= e($type === 'ssn' ? 'text' : $type) ?>"
                            <?= $required ? 'required' : '' ?>
                            <?= $type === 'number' ? 'step="0.1" min="0"' : '' ?>
                            <?= $type === 'integer' ? 'step="1" min="0"' : '' ?>
                            <?= $type === 'password' ? 'minlength="10" autocomplete="new-password"' : '' ?>
                            value="<?= e($formValues[$name] ?? '') ?>"
                        >
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <button class="button" type="submit">Add record</button>
        </form>
    </article>
    <div class="staff-records">
        <div class="section-heading">
            <div><p class="eyebrow">Most recent records</p><h2><?= e($definition['title']) ?></h2></div>
            <span class="record-count"><?= count($records) ?> shown</span>
        </div>
        <?php if ($records === []): ?>
            <div class="empty-state"><h3>No records yet</h3><p>Use the form to add the first <?= e(strtolower(rtrim($definition['title'], 's'))) ?> record.</p></div>
        <?php endif; ?>
        <?php foreach ($records as $record): ?>
            <?php
            if ($entity === 'donors') {
                $summary = $record['first_name'] . ' ' . $record['last_name'] . ' · ' . $record['email'];
            } elseif ($entity === 'personnel') {
                $summary = $record['first_name'] . ' ' . $record['last_name'] . ' · ' . $record['title'];
            } elseif ($entity === 'donations') {
                $summary = 'Donation #' . $record['id'] . ' · ' . $record['amount_ml'] . ' mL · ' . $record['donated_at'];
            } elseif ($entity === 'appointments') {
                $summary = 'Appointment #' . $record['id'] . ' · ' . $record['starts_at'] . ' · ' . $record['status'];
            } elseif ($entity === 'compensations') {
                $summary = 'Compensation #' . $record['id'] . ' · ' . number_format((int) $record['amount_cents'] / 100, 2);
            } else {
                $summary = $record['email'] . ' · ' . ucfirst($record['role']);
            }
            ?>
            <details class="record-card">
                <summary>
                    <span><?= e($summary) ?></span>
                    <span class="record-id">#<?= (int) $record['id'] ?> · Edit</span>
                </summary>
                <form method="post" action="staff.php?entity=<?= e($entity) ?>" class="record-edit-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="entity" value="<?= e($entity) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">
                    <div class="field-grid">
                        <?php foreach ($definition['fields'] as $field): ?>
                            <?php
                            [$name, $label, $type, $required] = $field;
                            if ($type === 'blood_types') {
                                $choices = array_map(static function (string $value): array {
                                    return ['id' => $value, 'label' => $value];
                                }, blood_types());
                            } elseif ($type === 'personnel_types') {
                                $choices = array_map(static function (string $value): array {
                                    return ['id' => $value, 'label' => ucfirst($value)];
                                }, ['doctor', 'nurse', 'staff']);
                            } elseif ($type === 'appointment_statuses') {
                                $choices = array_map(static function (string $value): array {
                                    return ['id' => $value, 'label' => ucfirst($value)];
                                }, ['booked', 'cancelled', 'completed']);
                            } elseif ($type === 'roles') {
                                $choices = array_map(static function (string $value): array {
                                    return ['id' => $value, 'label' => ucfirst($value)];
                                }, ['donor', 'personnel', 'admin']);
                            } elseif (in_array($type, ['donor_users', 'staff_users', 'donors', 'centers', 'doctors', 'nurses', 'donations', 'donations_without_compensation'], true)) {
                                $choices = staff_options($type);
                            } else {
                                $choices = [];
                            }
                            $optional = in_array($type, ['ssn', 'password'], true) || !$required;
                            ?>
                            <label><?= e($label) ?>
                                <?php if ($choices !== [] || in_array($type, ['blood_types', 'personnel_types', 'appointment_statuses', 'roles'], true)): ?>
                                    <select name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                                        <?php if ($optional): ?><option value="">Not assigned</option><?php endif; ?>
                                        <?php foreach ($choices as $choice): ?>
                                            <option value="<?= e((string) $choice['id']) ?>" <?= (string) ($record[$name] ?? '') === (string) $choice['id'] ? 'selected' : '' ?>>
                                                <?= e($choice['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input
                                        name="<?= e($name) ?>"
                                        type="<?= e($type === 'ssn' ? 'text' : $type) ?>"
                                        <?= $required && !in_array($type, ['ssn', 'password'], true) ? 'required' : '' ?>
                                        <?= $type === 'number' ? 'step="0.1" min="0"' : '' ?>
                                        <?= $type === 'integer' ? 'step="1" min="0"' : '' ?>
                                        <?= $type === 'password' ? 'minlength="10" autocomplete="new-password"' : '' ?>
                                        value="<?= e(staff_field_value($record, $field)) ?>"
                                    >
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="record-actions">
                        <button class="button button-small" type="submit">Save changes</button>
                    </div>
                </form>
                <form method="post" action="staff.php?entity=<?= e($entity) ?>" class="delete-form" onsubmit="return confirm('Delete this record? Related records may also be removed.');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="entity" value="<?= e($entity) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">
                    <button class="link-button link-danger" type="submit">Delete record</button>
                </form>
            </details>
        <?php endforeach; ?>
    </div>
</section>
<?php render_footer(); ?>
