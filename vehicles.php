<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

function find_or_create_vehicle_model(PDO $pdo, string $makeName, string $modelName): int
{
    $makeName = trim($makeName);
    $modelName = trim($modelName);

    if ($makeName === '') {
        throw new RuntimeException('Make is required.');
    }

    if ($modelName === '') {
        throw new RuntimeException('Model is required.');
    }

    $makeStatement = $pdo->prepare('SELECT make_id FROM vehicle_makes WHERE make_name = ?');
    $makeStatement->execute([$makeName]);
    $makeId = $makeStatement->fetchColumn();

    if ($makeId === false) {
        $insertMake = $pdo->prepare('INSERT INTO vehicle_makes (make_name) VALUES (?)');
        $insertMake->execute([$makeName]);
        $makeId = $pdo->lastInsertId();
    }

    $modelStatement = $pdo->prepare(
        'SELECT model_id
         FROM vehicle_models
         WHERE make_id = ? AND model_name = ?'
    );
    $modelStatement->execute([(int) $makeId, $modelName]);
    $modelId = $modelStatement->fetchColumn();

    if ($modelId === false) {
        $insertModel = $pdo->prepare('INSERT INTO vehicle_models (make_id, model_name) VALUES (?, ?)');
        $insertModel->execute([(int) $makeId, $modelName]);
        $modelId = $pdo->lastInsertId();
    }

    return (int) $modelId;
}

$pdo = get_pdo();
$canManage = has_role(['super_admin', 'admin']);
$customers = $pdo->query('SELECT customer_id, customer_name FROM customers ORDER BY customer_name')->fetchAll();
$editVehicle = [
    'vehicle_id' => '',
    'customer_id' => '',
    'plate_number' => '',
    'make' => '',
    'model' => '',
    'vehicle_year' => '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['super_admin', 'admin']);

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
            $customerId = (int) ($_POST['customer_id'] ?? 0);
            $plateNumber = trim($_POST['plate_number'] ?? '');
            $make = trim($_POST['make'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $vehicleYear = trim($_POST['vehicle_year'] ?? '');

            if ($customerId <= 0) {
                throw new RuntimeException('Customer is required.');
            }

            if ($plateNumber === '') {
                throw new RuntimeException('Plate number is required.');
            }

            $modelId = find_or_create_vehicle_model($pdo, $make, $model);
            $vehicleYear = $vehicleYear === '' ? null : (int) $vehicleYear;

            if ($vehicleId > 0) {
                $statement = $pdo->prepare(
                    'UPDATE vehicles
                     SET customer_id = ?, plate_number = ?, model_id = ?, vehicle_year = ?
                     WHERE vehicle_id = ?'
                );
                $statement->execute([$customerId, $plateNumber, $modelId, $vehicleYear, $vehicleId]);
                write_audit_log($pdo, current_user()['user_id'], 'update', 'vehicles', $vehicleId, 'Updated vehicle: ' . $plateNumber);
                set_flash('Vehicle updated.');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO vehicles (customer_id, plate_number, model_id, vehicle_year)
                     VALUES (?, ?, ?, ?)'
                );
                $statement->execute([$customerId, $plateNumber, $modelId, $vehicleYear]);
                $newId = (int) $pdo->lastInsertId();
                write_audit_log($pdo, current_user()['user_id'], 'create', 'vehicles', $newId, 'Created vehicle: ' . $plateNumber);
                set_flash('Vehicle added.');
            }

            redirect('vehicles.php');
        }

        if ($action === 'delete') {
            $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
            $statement = $pdo->prepare('DELETE FROM vehicles WHERE vehicle_id = ?');
            $statement->execute([$vehicleId]);
            write_audit_log($pdo, current_user()['user_id'], 'delete', 'vehicles', $vehicleId, 'Deleted vehicle.');
            set_flash('Vehicle deleted.');
            redirect('vehicles.php');
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $statement = $pdo->prepare(
        'SELECT vehicles.vehicle_id, vehicles.customer_id, vehicles.plate_number,
                vehicle_makes.make_name AS make, vehicle_models.model_name AS model,
                vehicles.vehicle_year
         FROM vehicles
         INNER JOIN vehicle_models ON vehicle_models.model_id = vehicles.model_id
         INNER JOIN vehicle_makes ON vehicle_makes.make_id = vehicle_models.make_id
         WHERE vehicles.vehicle_id = ?'
    );
    $statement->execute([(int) $_GET['edit']]);
    $found = $statement->fetch();

    if ($found !== false) {
        $editVehicle = $found;
    }
}

$vehicles = $pdo->query(
    'SELECT vehicles.vehicle_id, vehicles.plate_number, vehicle_makes.make_name AS make,
            vehicle_models.model_name AS model, vehicles.vehicle_year, customers.customer_name
     FROM vehicles
     INNER JOIN customers ON customers.customer_id = vehicles.customer_id
     INNER JOIN vehicle_models ON vehicle_models.model_id = vehicles.model_id
     INNER JOIN vehicle_makes ON vehicle_makes.make_id = vehicle_models.make_id
     ORDER BY vehicles.plate_number'
)->fetchAll();

render_header('Vehicles');
?>
<?php if ($errorMessage !== ''): ?>
    <p><?php echo e($errorMessage); ?></p>
<?php endif; ?>

<h2>Vehicle List</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Customer</th>
        <th>Plate Number</th>
        <th>Make</th>
        <th>Model</th>
        <th>Year</th>
        <?php if ($canManage): ?>
            <th>Actions</th>
        <?php endif; ?>
    </tr>
    <?php foreach ($vehicles as $vehicle): ?>
        <tr>
            <td><?php echo e((string) $vehicle['vehicle_id']); ?></td>
            <td><?php echo e($vehicle['customer_name']); ?></td>
            <td><?php echo e($vehicle['plate_number']); ?></td>
            <td><?php echo e($vehicle['make']); ?></td>
            <td><?php echo e($vehicle['model']); ?></td>
            <td><?php echo e((string) $vehicle['vehicle_year']); ?></td>
            <?php if ($canManage): ?>
                <td>
                    <a href="vehicles.php?edit=<?php echo e((string) $vehicle['vehicle_id']); ?>">Edit</a>
                    <form method="post">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="vehicle_id" value="<?php echo e((string) $vehicle['vehicle_id']); ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>
<?php if ($canManage): ?>
    <h2><?php echo $editVehicle['vehicle_id'] === '' ? 'Add Vehicle' : 'Edit Vehicle'; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="vehicle_id" value="<?php echo e((string) $editVehicle['vehicle_id']); ?>">
        <p>
            <label for="customer_id">Customer</label><br>
            <select name="customer_id" id="customer_id">
                <option value="">Select a customer</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo e((string) $customer['customer_id']); ?>" <?php echo (string) $customer['customer_id'] === (string) $editVehicle['customer_id'] ? 'selected' : ''; ?>>
                        <?php echo e($customer['customer_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="plate_number">Plate Number</label><br>
            <input type="text" name="plate_number" id="plate_number" value="<?php echo e($editVehicle['plate_number']); ?>">
        </p>
        <p>
            <label for="make">Make</label><br>
            <input type="text" name="make" id="make" value="<?php echo e($editVehicle['make']); ?>">
        </p>
        <p>
            <label for="model">Model</label><br>
            <input type="text" name="model" id="model" value="<?php echo e($editVehicle['model']); ?>">
        </p>
        <p>
            <label for="vehicle_year">Year</label><br>
            <input type="number" name="vehicle_year" id="vehicle_year" value="<?php echo e((string) $editVehicle['vehicle_year']); ?>">
        </p>
        <p>
            <button type="submit">Save Vehicle</button>
            <a href="vehicles.php">Clear Form</a>
        </p>
    </form>
<?php endif; ?>
<?php
render_footer();

