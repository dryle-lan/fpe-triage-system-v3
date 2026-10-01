<?php
declare(strict_types=1);

/**
 * Native PHP session-based auth.
 *
 * Note: PROJECT_PLAN.md originally specced JWT auth, written for a
 * decoupled Cloudflare Worker API. Now that frontend + backend are
 * one same-origin PHP app under XAMPP, sessions are simpler and just
 * as secure for this setup — no token signing/expiry to hand-roll.
 * Revisit this if the frontend and backend are ever split again.
 */
class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function user(): ?array
    {
        self::start();
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'   => $_SESSION['user_id'],
            'role' => $_SESSION['role'],
            'name' => $_SESSION['name'],
        ];
    }

    /**
     * Call at the top of any API endpoint that requires login.
     * Pass allowed roles to also restrict by role, e.g. ['doctor', 'admin'].
     * Exits with 401/403 JSON if the check fails.
     */
    public static function require(array $allowedRoles = []): array
    {
        $user = self::user();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        if ($allowedRoles && !in_array($user['role'], $allowedRoles, true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorized']);
            exit;
        }

        return $user;
    }
}
