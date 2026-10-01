<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    $stmt = Database::connection()->prepare(
        'SELECT * FROM users WHERE email = ? AND is_active = 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        Auth::login($user);
        echo json_encode([
            'id'   => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;
}

if ($method === 'DELETE') {
    Auth::logout();
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'GET') {
    $user = Auth::user();
    echo json_encode($user ?? ['error' => 'Not authenticated']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
