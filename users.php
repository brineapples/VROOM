<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_role(['super_admin']);

$pdo = get_pdo();
$roles = $pdo->query('SELECT role_id, role_name FROM roles ORDER BY role_name')->fetchAll();
$editUser = [
    'user_id' => '',
    'role_id' => '',
    'full_name' => '',
    'username' => '',
    'is_active' => '1',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $isActive = (int) ($_POST['is_active'] ?? 1);

            if ($roleId <= 0) {
                throw new RuntimeException('Role is required.');
            }

            if ($fullName === '' || $username === '') {
                throw new RuntimeException('Full name and username are required.');
            }

            if ($userId > 0) {
                if ($password !== '') {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $statement = $pdo->prepare(
                        'UPDATE users
                         SET role_id = ?, full_name = ?, username = ?, password_hash = ?, is_active = ?
                         WHERE user_id = ?'
                    );
                    $statement->execute([$roleId, $fullName, $username, $passwordHash, $isActive, $userId]);
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE users
                         SET role_id = ?, full_name = ?, username = ?, is_active = ?
                         WHERE user_id = ?'
                    );
                    $statement->execute([$roleId, $fullName, $username, $isActive, $userId]);
                }

                write_audit_log($pdo, current_user()['user_id'], 'update', 'users', $userId, 'Updated user: ' . $username);
                set_flash('Account updated.');
            } else {
                if ($password === '') {
                    throw new RuntimeException('Password is required for new users.');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $statement = $pdo->prepare(
                    'INSERT INTO users (role_id, full_name, username, password_hash, is_active)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $statement->execute([$roleId, $fullName, $username, $passwordHash, $isActive]);
                $newId = (int) $pdo->lastInsertId();
                write_audit_log($pdo, current_user()['user_id'], 'create', 'users', $newId, 'Created user: ' . $username);
                set_flash('Account added.');
            }

            redirect('users.php');
        }

        if ($action === 'delete') {
            $userId = (int) ($_POST['user_id'] ?? 0);

            if ($userId === current_user()['user_id']) {
                throw new RuntimeException('You cannot delete the account you are currently using.');
            }

            $statement = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
            $statement->execute([$userId]);
            write_audit_log($pdo, current_user()['user_id'], 'delete', 'users', $userId, 'Deleted user.');
            set_flash('Account deleted.');
            redirect('users.php');
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $statement = $pdo->prepare(
        'SELECT user_id, role_id, full_name, username, is_active
         FROM users
         WHERE user_id = ?'
    );
    $statement->execute([(int) $_GET['edit']]);
    $found = $statement->fetch();

    if ($found !== false) {
        $editUser = $found;
    }
}

$users = $pdo->query(
    'SELECT users.user_id, users.full_name, users.username, users.is_active, roles.role_name
     FROM users
     INNER JOIN roles ON roles.role_id = users.role_id
     ORDER BY users.user_id'
)->fetchAll();

render_header('Accounts');
?>
<h2><?php echo $editUser['user_id'] === '' ? 'Add Account' : 'Edit Account'; ?></h2>
<form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="user_id" value="<?php echo e((string) $editUser['user_id']); ?>">
    <p>
        <label for="role_id">Role</label><br>
        <select name="role_id" id="role_id">
            <option value="">Select a role</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?php echo e((string) $role['role_id']); ?>" <?php echo (string) $role['role_id'] === (string) $editUser['role_id'] ? 'selected' : ''; ?>>
                    <?php echo e($role['role_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="full_name">Full Name</label><br>
        <input type="text" name="full_name" id="full_name" value="<?php echo e($editUser['full_name']); ?>">
    </p>
    <p>
        <label for="username">Username</label><br>
        <input type="text" name="username" id="username" value="<?php echo e($editUser['username']); ?>">
    </p>
    <p>
        <label for="password">Password</label><br>
        <input type="password" name="password" id="password">
    </p>
    <p>
        <label for="is_active">Active</label><br>
        <select name="is_active" id="is_active">
            <option value="1" <?php echo (string) $editUser['is_active'] === '1' ? 'selected' : ''; ?>>Yes</option>
            <option value="0" <?php echo (string) $editUser['is_active'] === '0' ? 'selected' : ''; ?>>No</option>
        </select>
    </p>
    <p>
        <button type="submit">Save Account</button>
        <a href="users.php">Clear Form</a>
    </p>
</form>

<?php if ($errorMessage !== ''): ?>
    <p><?php echo e($errorMessage); ?></p>
<?php endif; ?>

<h2>Account List</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Username</th>
        <th>Role</th>
        <th>Active</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo e((string) $user['user_id']); ?></td>
            <td><?php echo e($user['full_name']); ?></td>
            <td><?php echo e($user['username']); ?></td>
            <td><?php echo e($user['role_name']); ?></td>
            <td><?php echo (int) $user['is_active'] === 1 ? 'Yes' : 'No'; ?></td>
            <td>
                <a href="users.php?edit=<?php echo e((string) $user['user_id']); ?>">Edit</a>
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?php echo e((string) $user['user_id']); ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php
render_footer();
