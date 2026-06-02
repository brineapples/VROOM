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

function normalize_service_type_ids(array $rawIds): array
{
    $serviceTypeIds = [];

    foreach ($rawIds as $rawId) {
        $serviceTypeId = (int) $rawId;

        if ($serviceTypeId > 0) {
            $serviceTypeIds[$serviceTypeId] = $serviceTypeId;
        }
    }

    return array_values($serviceTypeIds);
}

function fetch_repair_order_service_ids(PDO $pdo, int $repairOrderId): array
{
    $statement = $pdo->prepare(
        'SELECT service_type_id
         FROM repair_order_services
         WHERE repair_order_id = ?
         ORDER BY service_type_id'
    );
    $statement->execute([$repairOrderId]);

    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function sync_repair_order_services(PDO $pdo, int $repairOrderId, array $serviceTypeIds): bool
{
    $existingIds = fetch_repair_order_service_ids($pdo, $repairOrderId);
    sort($existingIds);

    $targetIds = $serviceTypeIds;
    sort($targetIds);

    if ($existingIds === $targetIds) {
        return false;
    }

    $deleteStatement = $pdo->prepare('DELETE FROM repair_order_services WHERE repair_order_id = ?');
    $deleteStatement->execute([$repairOrderId]);

    $insertStatement = $pdo->prepare(
        'INSERT INTO repair_order_services (repair_order_id, service_type_id)
         VALUES (?, ?)'
    );

    foreach ($targetIds as $serviceTypeId) {
        $insertStatement->execute([$repairOrderId, $serviceTypeId]);
    }

    return true;
}

$pdo = get_pdo();
$canManage = has_role(['super_admin', 'admin']);
$customers = $pdo->query('SELECT customer_id, customer_name FROM customers ORDER BY customer_name')->fetchAll();
$serviceTypes = $pdo->query(
    'SELECT service_type_id, service_name, standard_hours, hourly_rate
     FROM service_types
     ORDER BY service_name'
)->fetchAll();
$availableServiceTypeIds = array_map('intval', array_column($serviceTypes, 'service_type_id'));

$editRepairOrder = [
    'repair_order_id' => '',
    'customer_id' => '',
    'vehicle_id' => '',
    'service_date' => '',
    'problem_description' => '',
    'resolved_at' => '',
    'service_count' => 0,
];
$selectedServiceTypeIds = [];
$errorMessage = '';
$selectedCustomerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['super_admin', 'admin']);

    $action = $_POST['action'] ?? '';
    $selectedCustomerId = (int) ($_POST['customer_id'] ?? 0);

    try {
        if ($action === 'save') {
            $repairOrderId = (int) ($_POST['repair_order_id'] ?? 0);
            $isUpdate = $repairOrderId > 0;
            $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
            $serviceDate = trim($_POST['service_date'] ?? '');
            $problemDescription = trim($_POST['problem_description'] ?? '');
            $problemDescription = $problemDescription === '' ? null : $problemDescription;
            $selectedServiceTypeIds = normalize_service_type_ids($_POST['service_type_ids'] ?? []);

            $editRepairOrder = [
                'repair_order_id' => $repairOrderId > 0 ? (string) $repairOrderId : '',
                'customer_id' => $selectedCustomerId > 0 ? (string) $selectedCustomerId : '',
                'vehicle_id' => $vehicleId > 0 ? (string) $vehicleId : '',
                'service_date' => $serviceDate,
                'problem_description' => $problemDescription ?? '',
                'resolved_at' => '',
                'service_count' => count($selectedServiceTypeIds),
            ];

            if ($selectedCustomerId <= 0) {
                throw new RuntimeException('Customer is required.');
            }

            if ($vehicleId <= 0) {
                throw new RuntimeException('Vehicle is required.');
            }

            if ($serviceDate === '') {
                throw new RuntimeException('Service date is required.');
            }

            if ($selectedServiceTypeIds === []) {
                throw new RuntimeException('Select at least one service for the repair order.');
            }

            foreach ($selectedServiceTypeIds as $serviceTypeId) {
                if (!in_array($serviceTypeId, $availableServiceTypeIds, true)) {
                    throw new RuntimeException('One or more selected services are invalid.');
                }
            }

            $vehicleStatement = $pdo->prepare('SELECT customer_id FROM vehicles WHERE vehicle_id = ?');
            $vehicleStatement->execute([$vehicleId]);
            $vehicleCustomerId = (int) $vehicleStatement->fetchColumn();

            if ($vehicleCustomerId !== $selectedCustomerId) {
                throw new RuntimeException('Selected vehicle does not belong to the selected customer.');
            }

            $pdo->beginTransaction();

            if ($repairOrderId > 0) {
                $statement = $pdo->prepare(
                    'UPDATE repair_orders
                     SET vehicle_id = ?, service_date = ?, problem_description = ?
                     WHERE repair_order_id = ?'
                );
                $statement->execute([$vehicleId, $serviceDate, $problemDescription, $repairOrderId]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO repair_orders (vehicle_id, service_date, problem_description)
                     VALUES (?, ?, ?)'
                );
                $statement->execute([$vehicleId, $serviceDate, $problemDescription]);
                $repairOrderId = (int) $pdo->lastInsertId();
                $editRepairOrder['repair_order_id'] = (string) $repairOrderId;
            }

            $servicesChanged = sync_repair_order_services($pdo, $repairOrderId, $selectedServiceTypeIds);

            if ($servicesChanged) {
                $resetStatement = $pdo->prepare(
                    'UPDATE repair_orders
                     SET resolved_at = NULL
                     WHERE repair_order_id = ?'
                );
                $resetStatement->execute([$repairOrderId]);
            }

            $pdo->commit();

            if ($isUpdate) {
                write_audit_log($pdo, current_user()['user_id'], 'update', 'repair_orders', $repairOrderId, 'Updated repair order and assigned services.');
                set_flash('Repair order updated.');
            } else {
                write_audit_log($pdo, current_user()['user_id'], 'create', 'repair_orders', $repairOrderId, 'Created repair order with assigned services.');
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
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage = $exception->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $statement = $pdo->prepare(
        'SELECT repair_orders.repair_order_id, vehicles.customer_id, repair_orders.vehicle_id,
                repair_orders.service_date, repair_orders.problem_description, repair_orders.resolved_at,
                COUNT(repair_order_services.service_type_id) AS service_count
         FROM repair_orders
         INNER JOIN vehicles ON vehicles.vehicle_id = repair_orders.vehicle_id
         LEFT JOIN repair_order_services ON repair_order_services.repair_order_id = repair_orders.repair_order_id
         WHERE repair_orders.repair_order_id = ?
         GROUP BY repair_orders.repair_order_id, vehicles.customer_id, repair_orders.vehicle_id,
                  repair_orders.service_date, repair_orders.problem_description, repair_orders.resolved_at'
    );
    $statement->execute([(int) $_GET['edit']]);
    $found = $statement->fetch();

    if ($found !== false) {
        $editRepairOrder = $found;
        $selectedCustomerId = (int) $found['customer_id'];
        $selectedServiceTypeIds = fetch_repair_order_service_ids($pdo, (int) $found['repair_order_id']);
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
            repair_orders.resolved_at, vehicles.plate_number, customers.customer_name,
            COUNT(repair_order_services.service_type_id) AS service_count,
            GROUP_CONCAT(DISTINCT service_types.service_name ORDER BY service_types.service_name SEPARATOR ", ") AS service_names
     FROM repair_orders
     INNER JOIN vehicles ON vehicles.vehicle_id = repair_orders.vehicle_id
     INNER JOIN customers ON customers.customer_id = vehicles.customer_id
     LEFT JOIN repair_order_services ON repair_order_services.repair_order_id = repair_orders.repair_order_id
     LEFT JOIN service_types ON service_types.service_type_id = repair_order_services.service_type_id
     GROUP BY repair_orders.repair_order_id, repair_orders.service_date, repair_orders.problem_description,
              repair_orders.resolved_at, vehicles.plate_number, customers.customer_name
     ORDER BY repair_orders.service_date DESC, repair_orders.repair_order_id DESC'
)->fetchAll();

render_header('Repair Orders');
?>
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
        <th>Services</th>
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
            <td><?php echo e($repairOrder['service_names'] ?: 'No services assigned'); ?></td>
            <td>
                <span class="app-clamp-text" title="<?php echo e($repairOrder['problem_description'] ?? ''); ?>">
                    <?php echo e($repairOrder['problem_description']); ?>
                </span>
            </td>
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
<?php if ($canManage): ?>
    <h2><?php echo $editRepairOrder['repair_order_id'] === '' ? 'Add Repair Order' : 'Address Repair Order'; ?></h2>

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
            <label for="service_date">Service Date</label><br>
            <input type="date" name="service_date" id="service_date" value="<?php echo e($editRepairOrder['service_date']); ?>">
        </p>
        <p>
            <label for="problem_description">Problem Description</label><br>
            <textarea name="problem_description" id="problem_description" rows="4" cols="50"><?php echo e($editRepairOrder['problem_description']); ?></textarea>
        </p>
        <div class="app-service-picker">
            <div class="app-service-picker-header">
                <label>Services</label>
            </div>
            <div class="app-service-grid">
                <?php foreach ($serviceTypes as $serviceType): ?>
                    <?php $serviceTypeId = (int) $serviceType['service_type_id']; ?>
                    <label class="app-service-option">
                        <input type="checkbox" name="service_type_ids[]" value="<?php echo e((string) $serviceTypeId); ?>" <?php echo in_array($serviceTypeId, $selectedServiceTypeIds, true) ? 'checked' : ''; ?>>
                        <span class="app-service-check"></span>
                        <span class="app-service-copy">
                            <strong><?php echo e($serviceType['service_name']); ?></strong>
                            <small><?php echo e(number_format((float) $serviceType['standard_hours'], 2) . ' hrs @ ' . number_format((float) $serviceType['hourly_rate'], 2)); ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
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
                <span>Changing the assigned services reopens the repair order until it is resolved again.</span>
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
                <p class="app-field-help">Legacy unassigned repair orders must be given at least one service before they can be resolved.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
render_footer();
