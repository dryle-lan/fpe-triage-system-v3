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
        // Only admins manage accounts.
        Auth::require(['admin']);

        $stmt = $db->query(
            'SELECT id, name, email, role, is_active, created_at
             FROM users
             ORDER BY is_active DESC, name ASC'
        );
        $users = $stmt->fetchAll();
        foreach ($users as &$u) {
            $u['is_active'] = (bool) $u['is_active'];
        }
        unset($u);
        echo json_encode($users);
        break;

    case 'POST':
        // Admin creates an account for a health worker, doctor, or another admin.
        // No public self-signup — matches PROJECT_PLAN.md's auth model.
        $admin = Auth::require(['admin']);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $errors = validateUserInput($input, $db);
        if ($errors) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            break;
        }

        $stmt = $db->prepare(
            'INSERT INTO users (name, email, password_hash, role)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($input['name']),
            trim(strtolower($input['email'])),
            password_hash($input['password'], PASSWORD_DEFAULT),
            $input['role'],
        ]);
        $userId = (int) $db->lastInsertId();

        // Never write the plaintext password to the audit trail.
        AuditLogger::log('users', $userId, 'create', $admin['id'], [
            'name'  => $input['name'],
            'email' => $input['email'],
            'role'  => $input['role'],
        ]);

        echo json_encode(['id' => $userId]);
        break;

    case 'PUT':
        // Admin activates/deactivates an account: PUT /api/users.php?id=5  body: {"is_active": false}
        $admin = Auth::require(['admin']);
        $id = (int) ($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!$id) {
            http_response_code(422);
            echo json_encode(['errors' => ['id' => 'Missing or invalid user id']]);
            break;
        }

        if (!array_key_exists('is_active', $input) || !is_bool($input['is_active'])) {
            http_response_code(422);
            echo json_encode(['errors' => ['is_active' => 'is_active must be true or false']]);
            break;
        }

        if ($id === $admin['id'] && $input['is_active'] === false) {
            http_response_code(422);
            echo json_encode(['error' => 'You cannot deactivate your own account']);
            break;
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if (!$target) {
            http_response_code(404);
            echo json_encode(['error' => 'Account not found']);
            break;
        }

        $stmt = $db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$input['is_active'] ? 1 : 0, $id]);

        AuditLogger::log('users', $id, 'update', $admin['id'], [
            'is_active' => ['from' => (bool) $target['is_active'], 'to' => $input['is_active']],
        ]);

        echo json_encode(['id' => $id, 'is_active' => $input['is_active']]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

/**
 * @return array<string,string> field => error message, empty if valid
 */
function validateUserInput(array $input, PDO $db): array
{
    $errors = [];

    if (empty(trim((string) ($input['name'] ?? '')))) {
        $errors['name'] = 'Name is required';
    }

    $email = trim((string) ($input['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email is required';
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([strtolower($email)]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with this email already exists';
        }
    }

    if (strlen((string) ($input['password'] ?? '')) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }

    if (!in_array($input['role'] ?? '', ['health_worker', 'doctor', 'admin'], true)) {
        $errors['role'] = 'Role must be health_worker, doctor, or admin';
    }

    return $errors;
}
