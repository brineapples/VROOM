<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pdo = get_pdo();
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errorMessage = 'Username and password are required.';
    } else {
        $user = find_user_by_username($pdo, $username);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            write_audit_log($pdo, null, 'login_failed', 'users', null, 'Failed login for username: ' . $username);
            $errorMessage = 'Invalid username or password.';
        } elseif (!(bool) $user['is_active']) {
            write_audit_log($pdo, (int) $user['user_id'], 'login_blocked', 'users', (int) $user['user_id'], 'Inactive account login attempt.');
            $errorMessage = 'This user account is inactive.';
        } else {
            login_user($user);
            write_audit_log($pdo, (int) $user['user_id'], 'login', 'users', (int) $user['user_id'], 'User logged in.');
            set_flash('Welcome, ' . $user['full_name'] . '.');
            redirect('dashboard.php');
        }
    }
}

render_auth_header('Login');
?>
<div class="app-auth-card">
    <div class="app-auth-brand">
        <img class="app-auth-logo-image" src="assets/vroom-logo.png" alt="VROOM">
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="app-auth-error">
            <strong><?php echo e($errorMessage); ?></strong>
        </div>
    <?php endif; ?>

    <form method="post" class="app-auth-form">
        <p>
            <label for="username">Username</label>
            <input type="text" name="username" id="username" value="<?php echo e($_POST['username'] ?? ''); ?>" autocomplete="username">
        </p>
        <p>
            <label for="password">Password</label>
            <input type="password" name="password" id="password" autocomplete="current-password">
        </p>
        <button type="submit">Log In</button>
    </form>

    <p class="app-auth-note">Don't have an account? Contact your admin.</p>
</div>
<?php
render_auth_footer();
