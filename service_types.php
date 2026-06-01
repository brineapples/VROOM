<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$pdo = get_pdo();
$canManage = has_role(['super_admin', 'admin']);
$editServiceType = [
    'service_type_id' => '',
    'service_name' => '',
    'standard_hours' => '',
    'hourly_rate' => '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['super_admin', 'admin']);

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);
            $serviceName = trim($_POST['service_name'] ?? '');
            $standardHours = trim($_POST['standard_hours'] ?? '');
            $hourlyRate = trim($_POST['hourly_rate'] ?? '');

            if ($serviceName === '') {
                throw new RuntimeException('Service name is required.');
            }

            if ($standardHours === '' || !is_numeric($standardHours)) {
                throw new RuntimeException('Standard hours must be a number.');
            }

            if ($hourlyRate === '' || !is_numeric($hourlyRate)) {
                throw new RuntimeException('Hourly rate must be a number.');
            }

            if ($serviceTypeId > 0) {
                $statement = $pdo->prepare(
                    'UPDATE service_types
                     SET service_name = ?, standard_hours = ?, hourly_rate = ?
                     WHERE service_type_id = ?'
                );
                $statement->execute([$serviceName, $standardHours, $hourlyRate, $serviceTypeId]);
                write_audit_log($pdo, current_user()['user_id'], 'update', 'service_types', $serviceTypeId, 'Updated service type: ' . $serviceName);
                set_flash('Service type updated.');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO service_types (service_name, standard_hours, hourly_rate)
                     VALUES (?, ?, ?)'
                );
                $statement->execute([$serviceName, $standardHours, $hourlyRate]);
                $newId = (int) $pdo->lastInsertId();
                write_audit_log($pdo, current_user()['user_id'], 'create', 'service_types', $newId, 'Created service type: ' . $serviceName);
                set_flash('Service type added.');
            }

            redirect('service_types.php');
        }

        if ($action === 'delete') {
            $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);

            if ($serviceTypeId <= 0) {
                throw new RuntimeException('Service type is required.');
            }

            $pdo->beginTransaction();

            $repairOrderIdsStatement = $pdo->prepare(
                'SELECT DISTINCT repair_order_id
                 FROM repair_order_services
                 WHERE service_type_id = ?'
            );
            $repairOrderIdsStatement->execute([$serviceTypeId]);
            $repairOrderIds = array_map('intval', $repairOrderIdsStatement->fetchAll(PDO::FETCH_COLUMN));

            $deleteLinksStatement = $pdo->prepare(
                'DELETE FROM repair_order_services
                 WHERE service_type_id = ?'
            );
            $deleteLinksStatement->execute([$serviceTypeId]);

            if ($repairOrderIds !== []) {
                $placeholders = implode(',', array_fill(0, count($repairOrderIds), '?'));
                $resetRepairOrdersStatement = $pdo->prepare(
                    "UPDATE repair_orders
                     SET resolved_at = NULL
                     WHERE repair_order_id IN ($placeholders)"
                );
                $resetRepairOrdersStatement->execute($repairOrderIds);
            }

            $statement = $pdo->prepare('DELETE FROM service_types WHERE service_type_id = ?');
            $statement->execute([$serviceTypeId]);

            $pdo->commit();

            write_audit_log($pdo, current_user()['user_id'], 'delete', 'service_types', $serviceTypeId, 'Deleted service type and related repair order links.');
            set_flash('Service type deleted.');
            redirect('service_types.php');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage = $exception->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $statement = $pdo->prepare(
        'SELECT service_type_id, service_name, standard_hours, hourly_rate
         FROM service_types
         WHERE service_type_id = ?'
    );
    $statement->execute([(int) $_GET['edit']]);
    $found = $statement->fetch();

    if ($found !== false) {
        $editServiceType = $found;
    }
}

$serviceTypes = $pdo->query(
    'SELECT service_type_id, service_name, standard_hours, hourly_rate
     FROM service_types
     ORDER BY service_name'
)->fetchAll();

render_header('Service Types');
?>
<?php if ($errorMessage !== ''): ?>
    <p><?php echo e($errorMessage); ?></p>
<?php endif; ?>

<h2>Service Type List</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Standard Hours</th>
        <th>Hourly Rate</th>
        <?php if ($canManage): ?>
            <th>Actions</th>
        <?php endif; ?>
    </tr>
    <?php foreach ($serviceTypes as $serviceType): ?>
        <tr>
            <td><?php echo e((string) $serviceType['service_type_id']); ?></td>
            <td><?php echo e($serviceType['service_name']); ?></td>
            <td><?php echo e((string) $serviceType['standard_hours']); ?></td>
            <td>PHP <?php echo e((string) $serviceType['hourly_rate']); ?></td>
            <?php if ($canManage): ?>
                <td>
                    <a href="service_types.php?edit=<?php echo e((string) $serviceType['service_type_id']); ?>">Edit</a>
                    <form method="post">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="service_type_id" value="<?php echo e((string) $serviceType['service_type_id']); ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>
<?php if ($canManage): ?>
    <h2><?php echo $editServiceType['service_type_id'] === '' ? 'Add Service Type' : 'Edit Service Type'; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="service_type_id" value="<?php echo e((string) $editServiceType['service_type_id']); ?>">
        <p>
            <label for="service_name">Service Name</label><br>
            <input type="text" name="service_name" id="service_name" value="<?php echo e($editServiceType['service_name']); ?>">
        </p>
        <p>
            <label for="standard_hours">Standard Hours</label><br>
            <input type="number" step="0.01" name="standard_hours" id="standard_hours" value="<?php echo e((string) $editServiceType['standard_hours']); ?>">
        </p>
        <p>
            <label for="hourly_rate">Hourly Rate (PHP)</label><br>
            <input type="number" step="0.01" name="hourly_rate" id="hourly_rate" value="<?php echo e((string) $editServiceType['hourly_rate']); ?>">
        </p>
        <p>
            <button type="submit">Save Service Type</button>
            <a href="service_types.php">Clear Form</a>
        </p>
    </form>
<?php endif; ?>
<?php
render_footer();


