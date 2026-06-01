<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$pdo = get_pdo();
$canManage = has_role(['super_admin', 'admin', 'advisor']);
$repairOrders = $pdo->query(
    'SELECT repair_orders.repair_order_id, vehicles.plate_number, customers.customer_name, repair_orders.service_date
     FROM repair_orders
     INNER JOIN vehicles ON vehicles.vehicle_id = repair_orders.vehicle_id
     INNER JOIN customers ON customers.customer_id = vehicles.customer_id
     ORDER BY repair_orders.repair_order_id DESC'
)->fetchAll();
$serviceTypes = $pdo->query('SELECT service_type_id, service_name FROM service_types ORDER BY service_name')->fetchAll();
$editLink = [
    'old_repair_order_id' => '',
    'old_service_type_id' => '',
    'repair_order_id' => '',
    'service_type_id' => '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['super_admin', 'admin', 'advisor']);

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $oldRepairOrderId = (int) ($_POST['old_repair_order_id'] ?? 0);
            $oldServiceTypeId = (int) ($_POST['old_service_type_id'] ?? 0);
            $repairOrderId = (int) ($_POST['repair_order_id'] ?? 0);
            $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);

            if ($repairOrderId <= 0 || $serviceTypeId <= 0) {
                throw new RuntimeException('Repair order and service type are required.');
            }

            if ($oldRepairOrderId > 0 && $oldServiceTypeId > 0) {
                $statement = $pdo->prepare(
                    'UPDATE repair_order_services
                     SET repair_order_id = ?, service_type_id = ?
                     WHERE repair_order_id = ? AND service_type_id = ?'
                );
                $statement->execute([$repairOrderId, $serviceTypeId, $oldRepairOrderId, $oldServiceTypeId]);
                write_audit_log($pdo, current_user()['user_id'], 'update', 'repair_order_services', $repairOrderId, 'Updated repair order service link.');
                set_flash('Assigned service updated.');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO repair_order_services (repair_order_id, service_type_id)
                     VALUES (?, ?)'
                );
                $statement->execute([$repairOrderId, $serviceTypeId]);
                write_audit_log($pdo, current_user()['user_id'], 'create', 'repair_order_services', $repairOrderId, 'Created repair order service link.');
                set_flash('Assigned service added.');
            }

            $affectedRepairOrderIds = array_unique(array_filter([$oldRepairOrderId, $repairOrderId]));
            $resetStatement = $pdo->prepare('UPDATE repair_orders SET resolved_at = NULL WHERE repair_order_id = ?');

            foreach ($affectedRepairOrderIds as $affectedRepairOrderId) {
                $resetStatement->execute([(int) $affectedRepairOrderId]);
            }

            redirect('repair_order_services.php');
        }

        if ($action === 'delete') {
            $repairOrderId = (int) ($_POST['repair_order_id'] ?? 0);
            $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);
            $statement = $pdo->prepare(
                'DELETE FROM repair_order_services
                 WHERE repair_order_id = ? AND service_type_id = ?'
            );
            $statement->execute([$repairOrderId, $serviceTypeId]);
            $resetStatement = $pdo->prepare('UPDATE repair_orders SET resolved_at = NULL WHERE repair_order_id = ?');
            $resetStatement->execute([$repairOrderId]);
            write_audit_log($pdo, current_user()['user_id'], 'delete', 'repair_order_services', $repairOrderId, 'Deleted repair order service link.');
            set_flash('Assigned service removed.');
            redirect('repair_order_services.php');
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

if (isset($_GET['repair_order_id'], $_GET['service_type_id'])) {
    $editLink = [
        'old_repair_order_id' => (int) $_GET['repair_order_id'],
        'old_service_type_id' => (int) $_GET['service_type_id'],
        'repair_order_id' => (int) $_GET['repair_order_id'],
        'service_type_id' => (int) $_GET['service_type_id'],
    ];
}

$orderServices = $pdo->query(
    'SELECT repair_order_services.repair_order_id, repair_order_services.service_type_id,
            repair_orders.service_date, vehicles.plate_number, customers.customer_name, service_types.service_name
     FROM repair_order_services
     INNER JOIN repair_orders ON repair_orders.repair_order_id = repair_order_services.repair_order_id
     INNER JOIN vehicles ON vehicles.vehicle_id = repair_orders.vehicle_id
     INNER JOIN customers ON customers.customer_id = vehicles.customer_id
     INNER JOIN service_types ON service_types.service_type_id = repair_order_services.service_type_id
     ORDER BY repair_order_services.repair_order_id DESC, service_types.service_name'
)->fetchAll();

render_header('Assigned Services');
?>
<?php if ($canManage): ?>
    <h2><?php echo $editLink['old_repair_order_id'] === '' ? 'Add Assigned Service' : 'Edit Assigned Service'; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="old_repair_order_id" value="<?php echo e((string) $editLink['old_repair_order_id']); ?>">
        <input type="hidden" name="old_service_type_id" value="<?php echo e((string) $editLink['old_service_type_id']); ?>">
        <p>
            <label for="repair_order_id">Repair Order</label><br>
            <select name="repair_order_id" id="repair_order_id">
                <option value="">Select a repair order</option>
                <?php foreach ($repairOrders as $repairOrder): ?>
                    <option value="<?php echo e((string) $repairOrder['repair_order_id']); ?>" <?php echo (string) $repairOrder['repair_order_id'] === (string) $editLink['repair_order_id'] ? 'selected' : ''; ?>>
                        <?php echo e('#' . $repairOrder['repair_order_id'] . ' - ' . $repairOrder['plate_number'] . ' - ' . $repairOrder['customer_name'] . ' - ' . $repairOrder['service_date']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="service_type_id">Service Type</label><br>
            <select name="service_type_id" id="service_type_id">
                <option value="">Select a service type</option>
                <?php foreach ($serviceTypes as $serviceType): ?>
                    <option value="<?php echo e((string) $serviceType['service_type_id']); ?>" <?php echo (string) $serviceType['service_type_id'] === (string) $editLink['service_type_id'] ? 'selected' : ''; ?>>
                        <?php echo e($serviceType['service_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <button type="submit">Save Assigned Service</button>
            <a href="repair_order_services.php">Clear Form</a>
        </p>
    </form>
<?php endif; ?>

<?php if ($errorMessage !== ''): ?>
    <p><?php echo e($errorMessage); ?></p>
<?php endif; ?>

<h2>Assigned Services List</h2>
<table>
    <tr>
        <th>Repair Order</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Vehicle</th>
        <th>Service Type</th>
        <?php if ($canManage): ?>
            <th>Actions</th>
        <?php endif; ?>
    </tr>
    <?php foreach ($orderServices as $orderService): ?>
        <tr>
            <td><?php echo e((string) $orderService['repair_order_id']); ?></td>
            <td><?php echo e($orderService['service_date']); ?></td>
            <td><?php echo e($orderService['customer_name']); ?></td>
            <td><?php echo e($orderService['plate_number']); ?></td>
            <td><?php echo e($orderService['service_name']); ?></td>
            <?php if ($canManage): ?>
                <td>
                    <a href="repair_order_services.php?repair_order_id=<?php echo e((string) $orderService['repair_order_id']); ?>&service_type_id=<?php echo e((string) $orderService['service_type_id']); ?>">Edit</a>
                    <form method="post">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="repair_order_id" value="<?php echo e((string) $orderService['repair_order_id']); ?>">
                        <input type="hidden" name="service_type_id" value="<?php echo e((string) $orderService['service_type_id']); ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>
<?php
render_footer();

