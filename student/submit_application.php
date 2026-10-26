<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/../backend/data.php';
require_once __DIR__ . '/../backend/security.php';

if (!isset($_SESSION['student_id'])) {
    ait_redirect('login');
    exit();
}

ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ait_redirect('dashboard?pane=apply');
    exit();
}

ait_validate_csrf_post();
ait_rate_limit('application-submit', 5, 900, 'student:' . (int) $_SESSION['student_id']);

$studentId = (int) $_SESSION['student_id'];
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$requiredFields = [
    'full_name',
    'father_name',
    'father_cnic',
    'cnic',
    'dob',
    'gender',
    'nationality',
    'domicile_province',
    'district_city',
    'phone',
    'email',
    'permanent_address',
    'postal_address',
    'matric_board',
    'matric_roll',
    'matric_year',
    'matric_group',
    'matric_total_marks',
    'matric_obtained_marks',
    'matric_percentage',
    'inter_board',
    'inter_roll',
    'inter_year',
    'inter_group',
    'inter_total_marks',
    'inter_obtained_marks',
    'inter_percentage',
    'campus',
    'faculty',
    'degree_level',
    'shift',
    'pref_1'
];

foreach ($requiredFields as $field) {
    if (trim((string) ($_POST[$field] ?? '')) === '') {
        ait_abort(422, 'Please complete all required application fields.');
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || ait_is_disposable_email($email)) {
    ait_abort(422, 'Temporary, disposable, and burner email addresses are not accepted.');
}

if (!preg_match('/^\d{5}-\d{7}-\d{1}$/', (string) $_POST['cnic']) || !preg_match('/^\d{5}-\d{7}-\d{1}$/', (string) $_POST['father_cnic'])) {
    ait_abort(422, 'CNIC values must use the format XXXXX-XXXXXXX-X.');
}

$percentage = static function (string $value): string {
    $value = trim(str_replace('%', '', $value));
    return is_numeric($value) && (float) $value >= 0 && (float) $value <= 100 ? $value : '';
};

$matricPercentage = $percentage((string) $_POST['matric_percentage']);
$interPercentage = $percentage((string) $_POST['inter_percentage']);
if ($matricPercentage === '' || $interPercentage === '') {
    ait_abort(422, 'Percentages must be numbers between 0 and 100.');
}

$post = static function (string $key): string {
    return trim((string) ($_POST[$key] ?? ''));
};

$applicationFields = [
    'full_name' => $post('full_name'),
    'father_name' => $post('father_name'),
    'father_cnic' => $post('father_cnic'),
    'cnic' => $post('cnic'),
    'dob' => $post('dob'),
    'gender' => $post('gender'),
    'religion' => $post('religion'),
    'nationality' => $post('nationality'),
    'domicile_province' => $post('domicile_province'),
    'district_city' => $post('district_city'),
    'phone' => $post('phone'),
    'alt_phone' => $post('alt_phone'),
    'email' => $email,
    'permanent_address' => $post('permanent_address'),
    'postal_address' => $post('postal_address'),
    'matric_board' => $post('matric_board'),
    'matric_roll' => $post('matric_roll'),
    'matric_reg' => $post('matric_reg'),
    'matric_year' => $post('matric_year'),
    'matric_group' => $post('matric_group'),
    'matric_total_marks' => $post('matric_total_marks'),
    'matric_obtained_marks' => $post('matric_obtained_marks'),
    'matric_percentage' => $matricPercentage,
    'inter_board' => $post('inter_board'),
    'inter_roll' => $post('inter_roll'),
    'inter_reg' => $post('inter_reg'),
    'inter_year' => $post('inter_year'),
    'inter_group' => $post('inter_group'),
    'inter_total_marks' => $post('inter_total_marks'),
    'inter_obtained_marks' => $post('inter_obtained_marks'),
    'inter_percentage' => $interPercentage,
    'campus' => $post('campus'),
    'faculty' => $post('faculty'),
    'degree_level' => $post('degree_level'),
    'shift' => $post('shift'),
    'pref_1' => $post('pref_1'),
    'pref_2' => $post('pref_2'),
    'pref_3' => $post('pref_3'),
    'test_roll_no' => $post('test_roll_no'),
    'test_date' => $post('test_date')
];

$validationError = (static function (array $f): ?string {
    if (!in_array($f['gender'], ['Male', 'Female', 'Other'], true)) return 'Please choose a gender from the list.';
    if (!in_array($f['shift'], ['Morning', 'Evening'], true)) return 'Please choose the Morning or Evening shift.';
    $dob = DateTimeImmutable::createFromFormat('!Y-m-d', $f['dob']);
    if (!$dob || $dob->format('Y-m-d') !== $f['dob'] || $dob > new DateTimeImmutable('-15 years') || $dob < new DateTimeImmutable('-60 years')) return 'Please enter a valid date of birth.';
    if (!preg_match('/^\+?\d{10,15}$/', preg_replace('/[\s-]/', '', $f['phone']))) return 'Please enter a valid mobile number, for example +923001234567.';
    if ($f['alt_phone'] !== '' && !preg_match('/^\+?\d{10,15}$/', preg_replace('/[\s-]/', '', $f['alt_phone']))) return 'The alternate phone number is not valid.';
    $thisYear = (int) date('Y');
    foreach (['matric', 'inter'] as $level) {
        $year = (int) $f[$level . '_year'];
        $total = (float) $f[$level . '_total_marks'];
        $obtained = (float) $f[$level . '_obtained_marks'];
        if ($year < $thisYear - 40 || $year > $thisYear) return 'Please check the passing year for your ' . ($level === 'matric' ? 'matric' : 'intermediate') . ' record.';
        if (!is_numeric($f[$level . '_total_marks']) || !is_numeric($f[$level . '_obtained_marks']) || $total <= 0 || $obtained < 0 || $obtained > $total) return 'Obtained marks must be a number no higher than total marks.';
    }
    if ((int) $f['inter_year'] < (int) $f['matric_year']) return 'Your intermediate year cannot be earlier than your matric year.';
    $prefs = array_filter([$f['pref_1'], $f['pref_2'], $f['pref_3']], static fn(string $p): bool => $p !== '');
    if (count($prefs) !== count(array_unique($prefs))) return 'Each program preference must be different.';
    foreach ($f as $key => $value) {
        if (mb_strlen($value) > (str_contains($key, 'address') ? 500 : 180)) return 'One of the fields is longer than allowed. Please shorten it.';
    }
    return null;
})($applicationFields);
if ($validationError !== null) {
    ait_abort(422, $validationError);
}
$documentLabels = [
    'profile_pic' => 'Passport photograph', 'doc_bform' => 'B-Form / CNIC', 'doc_father_cnic' => "Father's CNIC",
    'doc_10th' => 'Matric marksheet', 'doc_12th' => 'Intermediate marksheet', 'doc_domicile' => 'Domicile',
    'doc_equivalence' => 'Equivalence certificate', 'doc_entry_test' => 'Entry test result', 'doc_hafiz' => 'Hafiz-e-Quran certificate',
    'doc_disability' => 'Disability certificate', 'doc_sports' => 'Sports certificate', 'doc_minority' => 'Minority certificate',
    'doc_experience' => 'Experience letter', 'doc_publications' => 'Publications',
];
foreach (['religion', 'alt_phone', 'matric_reg', 'inter_reg', 'pref_2', 'pref_3', 'test_roll_no', 'test_date'] as $optional) {
    if ($applicationFields[$optional] === '') {
        $applicationFields[$optional] = null;
    }
}
if ($applicationFields['test_date'] !== null && !DateTimeImmutable::createFromFormat('!Y-m-d', (string) $applicationFields['test_date'])) {
    ait_abort(422, 'Please enter the entry test date as a valid date, or leave it blank.');
}
$requiredFiles = ['profile_pic', 'doc_bform', 'doc_father_cnic', 'doc_10th', 'doc_12th', 'doc_domicile'];
$fileTypes = [
    'profile_pic' => 'profile_picture',
    'doc_bform' => 'b_form',
    'doc_father_cnic' => 'other',
    'doc_10th' => '10th_marksheet',
    'doc_12th' => '12th_marksheet',
    'doc_domicile' => 'domicile',
    'doc_equivalence' => 'other',
    'doc_entry_test' => 'other',
    'doc_hafiz' => 'other',
    'doc_disability' => 'other',
    'doc_sports' => 'other',
    'doc_minority' => 'other',
    'doc_experience' => 'other',
    'doc_publications' => 'other'
];

foreach ($requiredFiles as $field) {
    if (empty($_FILES[$field]) || is_array($_FILES[$field]['error'] ?? null) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        ait_abort(422, 'Please upload all required documents.');
    }
}

foreach ($_FILES as $field => $file) {
    if (is_array($file['name'] ?? null) || is_array($file['tmp_name'] ?? null) || is_array($file['error'] ?? null)) {
        ait_abort(422, 'Only one file may be uploaded in each document field.');
    }
}

$conn->begin_transaction();
$storedFiles = [];

try {
    $lock = $conn->prepare('SELECT id FROM students WHERE id = ? FOR UPDATE');
    $lock->bind_param('i', $studentId);
    $lock->execute();
    if ($lock->get_result()->num_rows !== 1) {
        throw new RuntimeException('Student account not found.');
    }
    $lock->close();

    $existing = $conn->prepare('SELECT id FROM applications WHERE student_id = ? LIMIT 1 FOR UPDATE');
    $existing->bind_param('i', $studentId);
    $existing->execute();
    if ($existing->get_result()->num_rows > 0) {
        throw new RuntimeException('An application has already been submitted for this account.');
    }
    $existing->close();

    $columns = array_keys($applicationFields);
    $columns[] = 'student_id';
    $columns[] = 'status';
    $values = array_values($applicationFields);
    $values[] = (string) $studentId;
    $values[] = 'applied';
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $conn->prepare('INSERT INTO applications (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
    $types = str_repeat('s', count($values));
    $bindValues = [$types];
    foreach ($values as $key => $value) {
        $bindValues[] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);
    $stmt->execute();
    $applicationId = $conn->insert_id;
    $stmt->close();

    $docStmt = $conn->prepare('INSERT INTO documents (application_id, doc_type, file_url, original_name, mime_type, file_size, checksum) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($fileTypes as $field => $docType) {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $extensionOptions = $field === 'profile_pic' ? ['jpg', 'jpeg', 'png', 'webp'] : ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        try {
            $stored = ait_store_uploaded_asset($_FILES[$field], 'documents', [
                'allowed_extensions' => $extensionOptions,
                'max_image_size' => 5 * 1024 * 1024,
                'max_pdf_size' => 10 * 1024 * 1024,
            ]);
        } catch (RuntimeException $uploadError) {
            throw new AitUserFacingError(($documentLabels[$field] ?? 'Document') . ': ' . $uploadError->getMessage(), 0, $uploadError);
        }
        $storedFiles[] = $stored['key'];
        $docStmt->bind_param('issssis', $applicationId, $docType, $stored['key'], $stored['original_name'], $stored['mime'], $stored['size'], $stored['sha256']);
        $docStmt->execute();
    }
    $docStmt->close();

    $challanNo = 'AIT-' . date('Y') . '-' . str_pad((string) $applicationId, 5, '0', STR_PAD_LEFT);
    $challanStmt = $conn->prepare("INSERT INTO challans (application_id, bank_name, challan_no, amount, due_date, status) VALUES (?, 'HBL', ?, 3500.00, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'unpaid')");
    $challanStmt->bind_param('is', $applicationId, $challanNo);
    $challanStmt->execute();
    $challanStmt->close();

    $conn->commit();
    ait_redirect('dashboard?status=success&pane=dashboard');
    exit();
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($storedFiles as $storedPath) {
        ait_storage_delete($storedPath);
    }
    error_log('Application submission failed: ' . $e->getMessage());
    ait_abort(422, $e instanceof AitUserFacingError || str_starts_with($e->getMessage(), 'An application has already') ? $e->getMessage() : 'Your application could not be submitted. Please review your details and try again.');
}
