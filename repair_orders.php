<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

function repair_order_status_meta(?string $resolvedAt, int $serviceCount): array
{
    if ($serviceCount <= 0) {
        return [
            'key' => 'unassigned',
            'label' => 'Unassigned',
            'description' => 'No services assigned yet.',
        ];
    }

    if ($resolvedAt !== null && $resolvedAt !== '') {
        return [
            'key' => 'resolved',
            'label' => 'Resolved',
            'description' => 'Assigned work has been marked complete.',
        ];
    }

    return [
        'key' => 'pending',
        'label' => 'Pending',
        'description' => 'Services are assigned and waiting for completion.',
    ];
}

$pdo = get_pdo();
$canManage = has_role(['super_admin', 'admin', 'advisor']);
$customers = $pdo->query('SELECT customer_id, customer_name FROM customers ORDER BY customer_name')->fetchAll();
$advisors = $pdo->query(
    "SELECT users.user_id AS advisor_user_id, users.full_name AS advisor_name
     FROM users
     INNER JOIN roles ON roles.role_id = users.role_id
     WHERE roles.role_name = 'advisor' AND users.is_active = 1
     ORDER BY users.full_name"
)->fetchAll();
$editRepairOrder = [
    'repair_order_id' => '',
    'customer_id' => '',
    'vehicle_id' => '',
    'advisor_user_id' => has_role(['advisor']) ? (string) current_user()['user_id'] : '',
    'service_date' => '',
    'problem_description' => '',
    'resolved_at' => '',
    'service_count' => 0,
];
$errorMessage = '';
$selectedCustomerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['super_admin', 'admin', 'advisor']);

    $action = $_POST['action'] ?? '';
    $selectedCustomerId = (int) ($_POST['customer_id'] ?? 0);

    try {
        if ($action === 'save') {
            $repairOrderId = (int) ($_POST['repair_order_id'] ?? 0);
            $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
            $advisorUserId = (int) ($_POST['advisor_user_id'] ?? 0);
            $serviceDate = trim($_POST['service_date'] ?? '');
            $problemDescription = trim($_POST['problem_description'] ?? '');
            $problemDescription = $problemDescription === '' ? null : $problemDescription;

            if ($selectedCustomerId <= 0) {
                throw new RuntimeException('Customer is required.');
            }

            if ($vehicleId <= 0) {
                throw new RuntimeException('Vehicle is required.');
            }

            if ($advisorUserId <= 0) {
                throw new RuntimeException('Advisor is required.');
            }

            if ($serviceDate === '') {
                throw new RuntimeException('Service date is required.');
            }

            $vehicleStatement = $pdo->prepare('SELECT customer_id FROM vehicles WHERE vehicle_id = ?');
            $vehicleStatement->execute([$vehicleId]);
            $vehicleCustomerId = (int) $vehicleStatement->fetchColumn();

            if ($vehicleCustomerId !== $selectedCustomerId) {
                throw new RuntimeException('Selected vehicle does not belong to the selected customer.');
            }

            if ($repairOrderId > 0) {
                $statement = $pdo->prepare(
                    'UPDATE repair_orders
                     SET vehicle_id = ?, advisor_user_id = ?, service_date = ?, problem_description = ?
                     WHERE repair_order_id = ?'
                );
                $statement->execute([$vehicleId, $advisorUserId, $serviceDate, $problemDescription, $repairOrderId]);
                write_audit_log($pdo, current_user()['user_id'], 'update', 'repair_orders', $repairOrderId, 'Updated repair order.');
                set_flash('Repair order updated.');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO repair_orders (vehicle_id, advisor_user_id, service_date, problem_description)
                     VALUES (?, ?, ?, ?)'
                );
                $statement->execute([$vehicleId, $advisorUserId, $serviceDate, $problemDescription]);
                $newId = (int) $pdo->lastInsertId();
                write_audit_log($pdo, current_user()['user_id'], 'create', 'repair_orders', $newId, 'Created repair order.');
                set_flash('Repair order added.');
            }

            redirect('repair_orders.php');
        }

        if ($action === 'resolve') {
            $repairOrderId = (int) ($_POST['repair_order_id'] ?? 0);

            if ($repairOrderId <= 0) {
                throw new RuntimeException('Repair order is required.');
            }

            $serviceCountStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM repair_order_services
                 WHERE repair_order_id = ?'
            );
            $serviceCountStatement->execute([$repairOrderId]);

            if ((int) $serviceCountStatement->fetchColumn() <= 0) {
                throw new RuntimeException('Assign at least one service before resolving this repair order.');
            }

            $statement = $pdo->prepare(
                'UPDATE repair_orders
                 SET resolved_at = CURRENT_TIMESTAMP
                 WHERE repair_order_id = ?'
            );
            $statement->execute([$repairOrderId]);
            write_audit_log($pdo, current_user()['user_id'], 'update', 'repair_orders', $repairOrderId, 'Resolved repair order.');
            set_flash('Repair order resolved.');
            redirect('repair_orders.php?edit=' . $repairOrderId);
        }

        if ($action === 'delete') {
            $repairOrderId = (int) ($_POST['repair_order_id'] ?? 0);
            $statement = $pdo->prepare('DELETE FROM repair_orders WHERE repair_order_id = ?');
            $statement->execute([$repairOrderId]);
            write_audit_log($pdo, current_user()['user_id'], 'delete', 'repair_orders', $repairOrderId, 'Deleted repair order.');
            set_flash('Repair order deleted.');
            redirect('repair_orders.php');
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $statement = $pdo->prepare(
        'SELECT repair_orders.repair_order_id, vehicles.customer_id, repair_orders.vehicle_id,
                repair_orders.advisor_user_id, repair_orders.service_date, repair_orders.problem_description,
                repair_orders.resolved_at, COUNT(repair_order_services.service_type_id) AS service_count
         FROM repair_orders
         INNER JOIN vehicles ON vehicles.vehicle_id = repair_orders.vehicle_id
         LEFT JOIN repair_order_services ON repair_order_services.repair_order_id = repair_orders.repair_order_id
         WHERE repair_orders.repair_order_id = ?'
        . ' GROUP BY repair_orders.repair_order_id, vehicles.customer_id, repair_orders.vehicle_id,
                  repair_orders.advisor_user_id, repair_orders.service_date, repair_orders.problem_description,
                  repair_orders.resolved_at'
    );
    $statement->execute([(int) $_GET['edit']]);
    $found = $statement->fetch();

    if ($found !== false) {
        $editRepairOrder = $found;
        $selectedCustomerId = (int) $found['customer_id'];
    }
}

if ($selectedCustomerId > 0) {
    $vehicleStatement = $pdo->prepare(
        'SELECT vehicles.vehicle_id, vehicles.plate_number, vehicle_makes.make_name AS make,
                vehicle_models.model_name AS model
         FROM vehicles
         INNER JOIN vehicle_models ON vehicle_models.model_id = vehicles.model_id
         INNER JOIN vehicle_makes ON vehicle_makes.make_id = vehicle_models.make_id
         WHERE vehicles.customer_id = ?
         ORDER BY vehicles.plate_number'
    );
    $vehicleStatement->execute([$selectedCustomerId]);
    $vehicles = $vehicleStatement->fetchAll();
} else {
    $vehicles = [];
}

$repairOrders = $pdo->query(
    'SELECT repair_orders.repair_order_id, repair_orders.service_date, repair_orders.problem_description,
            repair_orders.resolved_at, vehicles.plate_number, customers.customer_name, users.full_name AS advisor_name,
            COUNT(repair_order_services.service_type_id) AS service_count
     FROM repair_orders
     INNER JOIN vehicles ON vehicles.vehicle_id = repair_orders.vehicle_id
     INNER JOIN customers ON customers.customer_id = vehicles.customer_id
     INNER JOIN users ON users.user_id = repair_orders.advisor_user_id
     LEFT JOIN repair_order_services ON repair_order_services.repair_order_id = repair_orders.repair_order_id
     GROUP BY repair_orders.repair_order_id, repair_orders.service_date, repair_orders.problem_description,
              repair_orders.resolved_at, vehicles.plate_number, customers.customer_name, users.full_name
     ORDER BY repair_orders.service_date DESC, repair_orders.repair_order_id DESC'
)->fetchAll();

render_header('Repair Orders');
?>
<?php if ($canManage): ?>
    <h2><?php echo $editRepairOrder['repair_order_id'] === '' ? 'Add Repair Order' : 'Edit Repair Order'; ?></h2>

    <?php if ($editRepairOrder['repair_order_id'] !== ''): ?>
        <?php $editStatus = repair_order_status_meta($editRepairOrder['resolved_at'] ?? null, (int) $editRepairOrder['service_count']); ?>
        <div class="app-status-panel">
            <span class="app-section-kicker">Current status</span>
            <span class="app-status-badge <?php echo e($editStatus['key']); ?>"><?php echo e($editStatus['label']); ?></span>
            <span><?php echo e($editStatus['description']); ?></span>
        </div>
    <?php endif; ?>

    <form method="get" class="app-auto-load-form">
        <?php if (isset($_GET['edit'])): ?>
            <input type="hidden" name="edit" value="<?php echo e((string) $_GET['edit']); ?>">
        <?php endif; ?>
        <p>
            <label for="customer_id_filter">Customer</label><br>
            <select name="customer_id" id="customer_id_filter" onchange="this.form.submit()">
                <option value="">Select a customer</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo e((string) $customer['customer_id']); ?>" <?php echo (string) $customer['customer_id'] === (string) $selectedCustomerId ? 'selected' : ''; ?>>
                        <?php echo e($customer['customer_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="app-field-help">Vehicles refresh automatically after you select a customer.</p>
    </form>

    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="repair_order_id" value="<?php echo e((string) $editRepairOrder['repair_order_id']); ?>">
        <input type="hidden" name="customer_id" value="<?php echo e((string) $selectedCustomerId); ?>">
        <p>
            <label for="vehicle_id">Vehicle</label><br>
            <select name="vehicle_id" id="vehicle_id">
                <option value="">Select a vehicle</option>
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?php echo e((string) $vehicle['vehicle_id']); ?>" <?php echo (string) $vehicle['vehicle_id'] === (string) $editRepairOrder['vehicle_id'] ? 'selected' : ''; ?>>
                        <?php echo e($vehicle['plate_number'] . ' - ' . trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? ''))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="advisor_user_id">Advisor</label><br>
            <select name="advisor_user_id" id="advisor_user_id">
                <option value="">Select an advisor</option>
                <?php foreach ($advisors as $advisor): ?>
                    <option value="<?php echo e((string) $advisor['advisor_user_id']); ?>" <?php echo (string) $advisor['advisor_user_id'] === (string) $editRepairOrder['advisor_user_id'] ? 'selected' : ''; ?>>
                        <?php echo e($advisor['advisor_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="service_date">Service Date</label><br>
            <input type="date" name="service_date" id="service_date" value="<?php echo e($editRepairOrder['service_date']); ?>">
        </p>
        <p>
            <label for="problem_description">Problem Description</label><br>
            <textarea name="problem_description" id="problem_description" rows="4" cols="50"><?php echo e($editRepairOrder['problem_description']); ?></textarea>
        </p>
        <p>
            <button type="submit">Save Repair Order</button>
            <a href="repair_orders.php">Clear Form</a>
        </p>
    </form>

    <?php if ($editRepairOrder['repair_order_id'] !== ''): ?>
        <?php
        $editStatus = repair_order_status_meta($editRepairOrder['resolved_at'] ?? null, (int) $editRepairOrder['service_count']);
        $canResolve = $editStatus['key'] === 'pending';
        ?>
        <div class="app-edit-actions">
            <div class="app-edit-actions-copy">
                <span class="app-section-kicker">Repair order actions</span>
                <strong>Resolve or delete this repair order</strong>
                <span>Resolve only after the assigned work is finished in real life. Delete is only available from edit mode.</span>
            </div>
            <div class="app-edit-action-buttons">
                <?php if ($editStatus['key'] !== 'resolved'): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="resolve">
                        <input type="hidden" name="repair_order_id" value="<?php echo e((string) $editRepairOrder['repair_order_id']); ?>">
                        <button class="app-resolve-button" type="submit" <?php echo $canResolve ? '' : 'disabled'; ?>>Resolve Repair Order</button>
                    </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete this repair order?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="repair_order_id" value="<?php echo e((string) $editRepairOrder['repair_order_id']); ?>">
                    <button class="app-danger-button" type="submit">Delete Repair Order</button>
                </form>
            </div>
            <?php if ($editStatus['key'] === 'unassigned'): ?>
                <p class="app-field-help">Assign at least one service before resolving this repair order.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($errorMessage !== ''): ?>
    <p><?php echo e($errorMessage); ?></p>
<?php endif; ?>

<h2>Repair Order List</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Vehicle</th>
        <th>Advisor</th>
        <th>Problem Description</th>
        <th>Status</th>
        <?php if ($canManage): ?>
            <th>Actions</th>
        <?php endif; ?>
    </tr>
    <?php foreach ($repairOrders as $repairOrder): ?>
        <?php $status = repair_order_status_meta($repairOrder['resolved_at'] ?? null, (int) $repairOrder['service_count']); ?>
        <tr>
            <td><?php echo e((string) $repairOrder['repair_order_id']); ?></td>
            <td><?php echo e($repairOrder['service_date']); ?></td>
            <td><?php echo e($repairOrder['customer_name']); ?></td>
            <td><?php echo e($repairOrder['plate_number']); ?></td>
            <td><?php echo e($repairOrder['advisor_name']); ?></td>
            <td><?php echo e($repairOrder['problem_description']); ?></td>
            <td>
                <span class="app-status-badge <?php echo e($status['key']); ?>"><?php echo e($status['label']); ?></span>
            </td>
            <?php if ($canManage): ?>
                <td>
                    <a href="repair_orders.php?edit=<?php echo e((string) $repairOrder['repair_order_id']); ?>">Address</a>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>
<?php
render_footer();
