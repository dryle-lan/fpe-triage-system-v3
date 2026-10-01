<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Call this after any create/update/delete on an audited table
 * (encounters, missions, ai_recommendations) to keep an accountability trail.
 */
class AuditLogger
{
    public static function log(string $table, int $recordId, string $action, ?int $changedBy, array $diff = []): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_log (table_name, record_id, action, changed_by, diff_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$table, $recordId, $action, $changedBy, json_encode($diff)]);
    }
}
