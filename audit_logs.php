<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_role(['super_admin', 'admin']);

$pdo = get_pdo();
$auditLogs = $pdo->query(
    'SELECT audit_logs.audit_log_id, audit_logs.action_name, audit_logs.table_name, audit_logs.record_id,
            audit_logs.details, audit_logs.logged_at, users.username
     FROM audit_logs
     LEFT JOIN users ON users.user_id = audit_logs.user_id
     ORDER BY audit_logs.audit_log_id DESC
     LIMIT 200'
)->fetchAll();

render_header('Audit Logs');
?>
<table>
    <tr>
        <th>ID</th>
        <th>Time</th>
        <th>User</th>
        <th>Action</th>
        <th>Table</th>
        <th>Record ID</th>
        <th>Details</th>
    </tr>
    <?php foreach ($auditLogs as $auditLog): ?>
        <tr>
            <td><?php echo e((string) $auditLog['audit_log_id']); ?></td>
            <td><?php echo e($auditLog['logged_at']); ?></td>
            <td><?php echo e($auditLog['username']); ?></td>
            <td><?php echo e($auditLog['action_name']); ?></td>
            <td><?php echo e($auditLog['table_name']); ?></td>
            <td><?php echo e((string) $auditLog['record_id']); ?></td>
            <td><?php echo e($auditLog['details']); ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php
render_footer();
