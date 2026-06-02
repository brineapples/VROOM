<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$pdo = get_pdo();
$canManage = has_role(['super_admin', 'admin']);
$editCustomer = [
    'customer_id' => '',
    'customer_name' => '',
    'phone' => '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['super_admin', 'admin']);

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $customerId = (int) ($_POST['customer_id'] ?? 0);
            $customerName = trim($_POST['customer_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $phone = $phone === '' ? null : $phone;

            if ($customerName === '') {
                throw new RuntimeException('Customer name is required.');
            }

            if ($customerId > 0) {
                $statement = $pdo->prepare('UPDATE customers SET customer_name = ?, phone = ? WHERE customer_id = ?');
                $statement->execute([$customerName, $phone, $customerId]);
                write_audit_log($pdo, current_user()['user_id'], 'update', 'customers', $customerId, 'Updated customer: ' . $customerName);
                set_flash('Customer updated.');
            } else {
                $statement = $pdo->prepare('INSERT INTO customers (customer_name, phone) VALUES (?, ?)');
                $statement->execute([$customerName, $phone]);
                $newId = (int) $pdo->lastInsertId();
                write_audit_log($pdo, current_user()['user_id'], 'create', 'customers', $newId, 'Created customer: ' . $customerName);
                set_flash('Customer added.');
            }

            redirect('customers.php');
        }

        if ($action === 'delete') {
            $customerId = (int) ($_POST['customer_id'] ?? 0);

            if ($customerId <= 0) {
                throw new RuntimeException('Customer is required.');
            }

            $pdo->beginTransaction();

            $vehicleIdsStatement = $pdo->prepare(
                'SELECT vehicle_id
                 FROM vehicles
                 WHERE customer_id = ?'
            );
            $vehicleIdsStatement->execute([$customerId]);
            $vehicleIds = array_map('intval', $vehicleIdsStatement->fetchAll(PDO::FETCH_COLUMN));

            if ($vehicleIds !== []) {
                $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));

                $deleteRepairOrdersStatement = $pdo->prepare(
                    "DELETE FROM repair_orders
                     WHERE vehicle_id IN ($placeholders)"
                );
                $deleteRepairOrdersStatement->execute($vehicleIds);

                $deleteVehiclesStatement = $pdo->prepare(
                    "DELETE FROM vehicles
                     WHERE vehicle_id IN ($placeholders)"
                );
                $deleteVehiclesStatement->execute($vehicleIds);
            }

            $statement = $pdo->prepare('DELETE FROM customers WHERE customer_id = ?');
            $statement->execute([$customerId]);

            $pdo->commit();

            write_audit_log($pdo, current_user()['user_id'], 'delete', 'customers', $customerId, 'Deleted customer and related vehicle records.');
            set_flash('Customer deleted.');
            redirect('customers.php');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage = $exception->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT customer_id, customer_name, phone FROM customers WHERE customer_id = ?');
    $statement->execute([(int) $_GET['edit']]);
    $found = $statement->fetch();

    if ($found !== false) {
        $editCustomer = $found;
    }
}

$customers = $pdo->query('SELECT customer_id, customer_name, phone FROM customers ORDER BY customer_name')->fetchAll();

render_header('Customers');
?>
<?php if ($errorMessage !== ''): ?>
    <p><?php echo e($errorMessage); ?></p>
<?php endif; ?>

<h2>Customer List</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Phone</th>
        <?php if ($canManage): ?>
            <th>Actions</th>
        <?php endif; ?>
    </tr>
    <?php foreach ($customers as $customer): ?>
        <tr>
            <td><?php echo e((string) $customer['customer_id']); ?></td>
            <td><?php echo e($customer['customer_name']); ?></td>
            <td><?php echo e($customer['phone']); ?></td>
            <?php if ($canManage): ?>
                <td>
                    <a href="customers.php?edit=<?php echo e((string) $customer['customer_id']); ?>">Edit</a>
                    <form method="post">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="customer_id" value="<?php echo e((string) $customer['customer_id']); ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>
<?php if ($canManage): ?>
    <h2><?php echo $editCustomer['customer_id'] === '' ? 'Add Customer' : 'Edit Customer'; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="customer_id" value="<?php echo e((string) $editCustomer['customer_id']); ?>">
        <p>
            <label for="customer_name">Customer Name</label><br>
            <input type="text" name="customer_name" id="customer_name" value="<?php echo e($editCustomer['customer_name']); ?>">
        </p>
        <p>
            <label for="phone">Phone</label><br>
            <input type="text" name="phone" id="phone" value="<?php echo e($editCustomer['phone']); ?>">
        </p>
        <p>
            <button type="submit">Save Customer</button>
            <a href="customers.php">Clear Form</a>
        </p>
    </form>
<?php endif; ?>
<?php
render_footer();

