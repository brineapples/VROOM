<?php

function write_audit_log(
    PDO $pdo,
    ?int $userId,
    string $actionName,
    ?string $tableName = null,
    ?int $recordId = null,
    ?string $details = null
): void {
    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action_name, table_name, record_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    $statement->execute([
        $userId,
        $actionName,
        $tableName,
        $recordId,
        $details,
    ]);
}
