<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

$pdo = get_pdo();
$user = current_user();

write_audit_log($pdo, $user['user_id'], 'logout', 'users', $user['user_id'], 'User logged out.');
logout_user();
set_flash('You have been logged out.');

redirect('login.php');
