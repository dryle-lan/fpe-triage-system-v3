<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/AuditLogger.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::connection();

switch ($method) {
    case 'GET':
        // Any logged-in role can search for an existing patient during intake.
        Auth::require();
        $q = trim((string) ($_GET['q'] ?? ''));

        if ($q === '') {
            echo json_encode([]);
            break;
        }

        $like = '%' . $q . '%';
        $stmt = $db->prepare(
            'SELECT * FROM patients
             WHERE case_number LIKE ? OR philhealth_id LIKE ? OR last_name LIKE ? OR first_name LIKE ?
             ORDER BY created_at DESC
             LIMIT 10'
        );
        $stmt->execute([$like, $like, $like, $like]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        // Any logged-in role can register a new patient during intake.
        $user = Auth::require();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $errors = validatePatientInput($input);
        if ($errors) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            break;
        }

        $stmt = $db->prepare(
            'INSERT INTO patients
                (philhealth_id, last_name, first_name, middle_name, extension_name,
                 date_of_birth, sex)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            emptyToNull($input['philhealth_id']  ?? null),
            emptyToNull($input['last_name']      ?? null),
            emptyToNull($input['first_name']     ?? null),
            emptyToNull($input['middle_name']    ?? null),
            emptyToNull($input['extension_name'] ?? null),
            emptyToNull($input['date_of_birth']  ?? null),
            emptyToNull($input['sex']            ?? null),
        ]);
        $patientId = (int) $db->lastInsertId();

        // Case numbers are generated here, not typed in — <year>-<zero-padded id>
        // is unique for free since it's derived from the row's own auto-increment id.
        $caseNumber = date('Y') . '-' . str_pad((string) $patientId, 6, '0', STR_PAD_LEFT);
        $db->prepare('UPDATE patients SET case_number = ? WHERE id = ?')->execute([$caseNumber, $patientId]);

        AuditLogger::log('patients', $patientId, 'create', $user['id'], $input + ['case_number' => $caseNumber]);

        echo json_encode(['id' => $patientId, 'case_number' => $caseNumber]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

/**
 * MySQL's ENUM/DATE columns reject '' in strict mode — normalize blank
 * form fields to null before they hit the query.
 */
function emptyToNull($value)
{
    return ($value === '' || $value === null) ? null : $value;
}

/**
 * @return array<string,string> field => error message, empty if valid
 */
function validatePatientInput(array $input): array
{
    $errors = [];

    if (empty(trim((string) ($input['last_name'] ?? '')))) {
        $errors['last_name'] = 'Last name is required';
    }

    if (!empty($input['sex']) && !in_array($input['sex'], ['M', 'F'], true)) {
        $errors['sex'] = 'Sex must be M or F';
    }

    if (!empty($input['date_of_birth']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['date_of_birth'])) {
        $errors['date_of_birth'] = 'Date of birth must be in YYYY-MM-DD format';
    }

    return $errors;
}
