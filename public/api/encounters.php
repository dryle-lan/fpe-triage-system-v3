<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/AuditLogger.php';
require_once __DIR__ . '/../../src/TriageEngine.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::connection();

switch ($method) {
    case 'GET':
        Auth::require();
        $missionId = (int) ($_GET['mission_id'] ?? 0);

        if ($missionId) {
            $stmt = $db->prepare(
                'SELECT e.*, p.last_name, p.first_name, p.case_number, p.sex, p.date_of_birth
                 FROM encounters e
                 JOIN patients p ON p.id = e.patient_id
                 WHERE e.mission_id = ?
                 ORDER BY e.created_at DESC'
            );
            $stmt->execute([$missionId]);
        } else {
            $stmt = $db->query(
                'SELECT e.*, p.last_name, p.first_name, p.case_number, p.sex, p.date_of_birth
                 FROM encounters e
                 JOIN patients p ON p.id = e.patient_id
                 ORDER BY e.created_at DESC'
            );
        }
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        // Health workers, doctors, and admins can all log an encounter.
        $user = Auth::require(['health_worker', 'doctor', 'admin']);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $errors = validateEncounterInput($input);
        if ($errors) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            break;
        }

        $stmt = $db->prepare('SELECT status FROM missions WHERE id = ?');
        $stmt->execute([(int) $input['mission_id']]);
        $mission = $stmt->fetch();

        if (!$mission) {
            http_response_code(404);
            echo json_encode(['error' => 'Mission not found']);
            break;
        }
        if ($mission['status'] !== 'open') {
            http_response_code(422);
            echo json_encode(['error' => 'This mission is closed and can no longer accept new encounters']);
            break;
        }

        $ros            = $input['ros'] ?? [];
        $psh            = $input['personal_social_history'] ?? [];
        $pmh            = $input['pmh'] ?? [];
        $vitals         = $input['vitals'] ?? [];
        $survey         = $input['general_survey'];
        $chiefComplaint = trim((string) $input['chief_complaint']);

        $triage = TriageEngine::classify($ros, $pmh, $vitals, $survey, $chiefComplaint);

        $stmt = $db->prepare(
            'INSERT INTO encounters
                (mission_id, patient_id, visit_type, chief_complaint,
                 ros_json, personal_social_history_json, pmh_json, vitals_json,
                 general_survey, triage_color, triage_reason, entered_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $input['mission_id'],
            (int) $input['patient_id'],
            $input['visit_type'] ?? null,
            $chiefComplaint,
            json_encode($ros),
            json_encode($psh),
            json_encode($pmh),
            json_encode($vitals),
            $survey,
            $triage['color'],
            $triage['reason'],
            $user['id'],
        ]);
        $encounterId = (int) $db->lastInsertId();

        AuditLogger::log('encounters', $encounterId, 'create', $user['id'], $input);

        echo json_encode(['id' => $encounterId, 'triage' => $triage]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

/**
 * @return array<string,string> field => error message, empty if valid
 */
function validateEncounterInput(array $input): array
{
    $errors = [];

    if (empty((int) ($input['mission_id'] ?? 0))) {
        $errors['mission_id'] = 'Mission is required';
    }

    if (empty((int) ($input['patient_id'] ?? 0))) {
        $errors['patient_id'] = 'Patient is required';
    }

    if (empty(trim((string) ($input['chief_complaint'] ?? '')))) {
        $errors['chief_complaint'] = 'Chief complaint is required';
    }

    $survey = $input['general_survey'] ?? '';
    if (!in_array($survey, ['awake_alert', 'altered_sensorium'], true)) {
        $errors['general_survey'] = 'General survey must be awake_alert or altered_sensorium';
    }

    // Every Review of Systems question must be explicitly answered — a
    // skipped question must never silently count as "No" and understate
    // the patient's triage color.
    $ros = $input['ros'] ?? [];
    $rosKeys = [
        'general_symptoms', 'cardiopulmonary', 'gastrointestinal',
        'urinary_metabolic', 'genitourinary', 'musculoskeletal',
    ];
    foreach ($rosKeys as $key) {
        if (!isset($ros[$key]['yes']) || !is_bool($ros[$key]['yes'])) {
            $errors['ros'] = 'Every Review of Systems question must be answered Yes or No';
            break;
        }
    }

    return $errors;
}
