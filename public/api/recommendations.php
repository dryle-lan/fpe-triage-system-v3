<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/AuditLogger.php';
require_once __DIR__ . '/../../src/GeminiClient.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::connection();

switch ($method) {
    case 'GET':
        Auth::require();
        $missionId = (int) ($_GET['mission_id'] ?? 0);

        if ($missionId) {
            $stmt = $db->prepare(
                'SELECT * FROM ai_recommendations WHERE mission_id = ? ORDER BY generated_at DESC'
            );
            $stmt->execute([$missionId]);
        } else {
            $stmt = $db->query('SELECT * FROM ai_recommendations ORDER BY generated_at DESC');
        }
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        // Only doctor/admin can trigger a Gemini call.
        $user = Auth::require(['doctor', 'admin']);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $missionId = (int) ($input['mission_id'] ?? 0);

        if (!$missionId) {
            http_response_code(422);
            echo json_encode(['error' => 'mission_id is required']);
            break;
        }

        $stmt = $db->prepare('SELECT * FROM missions WHERE id = ?');
        $stmt->execute([$missionId]);
        $mission = $stmt->fetch();

        if (!$mission) {
            http_response_code(404);
            echo json_encode(['error' => 'Mission not found']);
            break;
        }

        $snapshot = buildMissionSnapshot($db, $mission);

        if ($snapshot['total_encounters'] === 0) {
            http_response_code(422);
            echo json_encode(['error' => 'No encounters have been logged for this mission yet — nothing to summarize.']);
            break;
        }

        $prompt = buildPrompt($snapshot);

        // Retries + fallback models can take longer than XAMPP's default 30s PHP limit.
        set_time_limit(150);

        try {
            $result = GeminiClient::generate($prompt);
        } catch (Throwable $e) {
            http_response_code(502);
            echo json_encode(['error' => 'Gemini request failed: ' . $e->getMessage()]);
            break;
        }

        $outputText = $result['text'];
        $snapshot['model_used'] = $result['model']; // stored alongside the stats for traceability

        $stmt = $db->prepare(
            'INSERT INTO ai_recommendations (mission_id, prompt_snapshot_json, output_text, generated_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$missionId, json_encode($snapshot), $outputText, $user['id']]);
        $id = (int) $db->lastInsertId();

        AuditLogger::log('ai_recommendations', $id, 'create', $user['id'], [
            'mission_id' => $missionId,
            'model'      => $result['model'],
        ]);

        echo json_encode(['id' => $id, 'output_text' => $outputText, 'model' => $result['model']]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Pulls this mission's encounters + patient sex/DOB and reduces them to
 * summary counts only — no names, case numbers, or free-text explain
 * fields are included, since this is what gets sent to Gemini.
 */
function buildMissionSnapshot(PDO $db, array $mission): array
{
    $stmt = $db->prepare(
        'SELECT e.triage_color, e.chief_complaint, e.pmh_json, e.personal_social_history_json,
                p.sex, p.date_of_birth
         FROM encounters e
         JOIN patients p ON p.id = e.patient_id
         WHERE e.mission_id = ?'
    );
    $stmt->execute([$mission['id']]);
    $rows = $stmt->fetchAll();

    $triageCounts = ['red' => 0, 'orange' => 0, 'yellow' => 0, 'green' => 0];
    $sexCounts    = ['M' => 0, 'F' => 0, 'Unspecified' => 0];
    $ageCounts    = ['0-4' => 0, '5-17' => 0, '18-39' => 0, '40-59' => 0, '60+' => 0, 'Unknown' => 0];
    $complaintCounts = [];
    $pmhCounts = [];
    $smokerCount = 0;
    $drinkerCount = 0;

    foreach ($rows as $row) {
        if (isset($triageCounts[$row['triage_color']])) {
            $triageCounts[$row['triage_color']]++;
        }

        $sexKey = in_array($row['sex'], ['M', 'F'], true) ? $row['sex'] : 'Unspecified';
        $sexCounts[$sexKey]++;

        $ageCounts[ageBracket(computeAgeInYears($row['date_of_birth'], $mission['mission_date']))]++;

        $complaint = trim((string) $row['chief_complaint']);
        if ($complaint !== '') {
            $key = mb_strtolower($complaint);
            if (!isset($complaintCounts[$key])) {
                $complaintCounts[$key] = ['label' => $complaint, 'count' => 0];
            }
            $complaintCounts[$key]['count']++;
        }

        $pmh = json_decode((string) $row['pmh_json'], true) ?: [];
        foreach ($pmh as $k => $v) {
            if ($v === true) {
                $pmhCounts[$k] = ($pmhCounts[$k] ?? 0) + 1;
            }
        }

        $psh = json_decode((string) $row['personal_social_history_json'], true) ?: [];
        if (!empty($psh['smokes'])) {
            $smokerCount++;
        }
        if (!empty($psh['drinks_alcohol'])) {
            $drinkerCount++;
        }
    }

    $topComplaints = array_values($complaintCounts);
    usort($topComplaints, fn($a, $b) => $b['count'] <=> $a['count']);
    $topComplaints = array_slice($topComplaints, 0, 5);

    return [
        'mission' => [
            'site_name'    => $mission['site_name'],
            'location'     => $mission['location'],
            'mission_date' => $mission['mission_date'],
        ],
        'total_encounters'     => count($rows),
        'triage_counts'        => $triageCounts,
        'sex_counts'           => $sexCounts,
        'age_bracket_counts'   => $ageCounts,
        'top_chief_complaints' => $topComplaints,
        'pmh_counts'           => $pmhCounts,
        'smoker_count'         => $smokerCount,
        'drinker_count'        => $drinkerCount,
    ];
}

function computeAgeInYears(?string $dob, string $asOfDate): ?int
{
    if (!$dob) {
        return null;
    }
    try {
        $birth = new DateTime($dob);
        $asOf  = new DateTime($asOfDate);
    } catch (Exception $e) {
        return null;
    }
    return $birth->diff($asOf)->y;
}

function ageBracket(?int $age): string
{
    if ($age === null) return 'Unknown';
    if ($age < 5) return '0-4';
    if ($age < 18) return '5-17';
    if ($age < 40) return '18-39';
    if ($age < 60) return '40-59';
    return '60+';
}

function buildPrompt(array $s): string
{
    $ageSummary = implode(', ', array_map(
        fn($k, $v) => "$k: $v",
        array_keys($s['age_bracket_counts']),
        array_values($s['age_bracket_counts'])
    ));

    $complaintSummary = $s['top_chief_complaints']
        ? implode('; ', array_map(fn($c) => "{$c['label']} ({$c['count']})", $s['top_chief_complaints']))
        : 'none recorded';

    $pmhSummary = $s['pmh_counts']
        ? implode(', ', array_map(
            fn($k, $v) => "$k: $v",
            array_keys($s['pmh_counts']),
            array_values($s['pmh_counts'])
        ))
        : 'none recorded';

    return
        "You are assisting a Philippine community health outreach team (PhilHealth Konsulta-style " .
        "First Patient Encounter program) plan future missions. Given the de-identified summary " .
        "statistics below from one completed mission, write short, practical recommendations covering: " .
        "1) staffing (e.g. whether more doctors/nurses seem needed given the acuity mix), " .
        "2) medicine/supplies to stock based on the conditions and complaints seen, and " .
        "3) any other notable patterns worth flagging for the next visit to this site. " .
        "Keep it to roughly 150-250 words, in plain language, with short headers or bullet points. " .
        "Do not invent specific patient details beyond what's given below.\n\n" .
        "Mission: {$s['mission']['site_name']} ({$s['mission']['location']}), {$s['mission']['mission_date']}\n" .
        "Total encounters: {$s['total_encounters']}\n" .
        "Triage distribution: Red {$s['triage_counts']['red']}, Orange {$s['triage_counts']['orange']}, " .
        "Yellow {$s['triage_counts']['yellow']}, Green {$s['triage_counts']['green']}\n" .
        "Sex: Male {$s['sex_counts']['M']}, Female {$s['sex_counts']['F']}, Unspecified {$s['sex_counts']['Unspecified']}\n" .
        "Age brackets: {$ageSummary}\n" .
        "Top chief complaints: {$complaintSummary}\n" .
        "Chronic condition prevalence (patient count per condition): {$pmhSummary}\n" .
        "Smokers: {$s['smoker_count']}, Alcohol use: {$s['drinker_count']}\n";
}
