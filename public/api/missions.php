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
        // Any logged-in role can list or look up a mission.
        Auth::require();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id) {
            $stmt = $db->prepare('SELECT * FROM missions WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch() ?: null);
            break;
        }

        $stmt = $db->query(
            "SELECT m.*,
                    COUNT(e.id) AS encounter_count,
                    SUM(CASE WHEN e.triage_color = 'red' THEN 1 ELSE 0 END) AS red_count,
                    SUM(CASE WHEN e.triage_color = 'orange' THEN 1 ELSE 0 END) AS orange_count
             FROM missions m
             LEFT JOIN encounters e ON e.mission_id = m.id
             GROUP BY m.id
             ORDER BY (m.status = 'open') DESC, m.mission_date DESC"
        );
        $missions = $stmt->fetchAll();
        foreach ($missions as &$m) {
            $m['encounter_count'] = (int) $m['encounter_count'];
            $m['red_count']       = (int) $m['red_count'];
            $m['orange_count']    = (int) $m['orange_count'];
        }
        unset($m);
        echo json_encode($missions);
        break;

    case 'POST':
        // Only admins create missions.
        $user = Auth::require(['admin']);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $errors = validateMissionInput($input);
        if ($errors) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            break;
        }

        $stmt = $db->prepare(
            'INSERT INTO missions (site_name, location, mission_date, created_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($input['site_name']),
            isset($input['location']) ? trim($input['location']) : null,
            $input['mission_date'],
            $user['id'],
        ]);
        $missionId = (int) $db->lastInsertId();

        AuditLogger::log('missions', $missionId, 'create', $user['id'], $input);

        echo json_encode(['id' => $missionId]);
        break;

    case 'PUT':
        // Admin closes a mission: PUT /api/missions.php?id=5  body: {"action":"close"}
        $user = Auth::require(['admin']);
        $id = (int) ($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!$id) {
            http_response_code(422);
            echo json_encode(['errors' => ['id' => 'Missing or invalid mission id']]);
            break;
        }

        $stmt = $db->prepare('SELECT * FROM missions WHERE id = ?');
        $stmt->execute([$id]);
        $mission = $stmt->fetch();

        if (!$mission) {
            http_response_code(404);
            echo json_encode(['error' => 'Mission not found']);
            break;
        }

        if (($input['action'] ?? '') === 'close') {
            if ($mission['status'] === 'closed') {
                http_response_code(422);
                echo json_encode(['error' => 'Mission is already closed']);
                break;
            }

            $stmt = $db->prepare(
                "UPDATE missions SET status = 'closed', closed_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$id]);

            AuditLogger::log('missions', $id, 'update', $user['id'], [
                'status' => ['from' => $mission['status'], 'to' => 'closed'],
            ]);

            echo json_encode(['id' => $id, 'status' => 'closed']);
            break;
        }

        http_response_code(422);
        echo json_encode(['error' => 'Unsupported or missing action']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

/**
 * @return array<string,string> field => error message, empty if valid
 */
function validateMissionInput(array $input): array
{
    $errors = [];

    if (empty(trim((string) ($input['site_name'] ?? '')))) {
        $errors['site_name'] = 'Site name is required';
    }

    $date = $input['mission_date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
        $errors['mission_date'] = 'Mission date must be in YYYY-MM-DD format';
    }

    return $errors;
}
