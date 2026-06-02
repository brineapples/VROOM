<?php

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $message): void
{
    $_SESSION['flash_message'] = $message;
}

function get_flash(): ?string
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return $message;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(array $roles): bool
{
    $user = current_user();

    return $user !== null && in_array($user['role_name'], $roles, true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('Please log in first.');
        redirect('login.php');
    }
}

function require_role(array $roles): void
{
    require_login();

    if (!has_role($roles)) {
        set_flash('You do not have permission to do that.');
        redirect('dashboard.php');
    }
}

function login_user(array $user): void
{
    $_SESSION['user'] = [
        'user_id' => (int) $user['user_id'],
        'role_id' => (int) $user['role_id'],
        'role_name' => $user['role_name'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
    ];
}

function logout_user(): void
{
    unset($_SESSION['user']);
}

function find_user_by_username(PDO $pdo, string $username): ?array
{
    $statement = $pdo->prepare(
        'SELECT users.user_id, users.role_id, users.full_name, users.username, users.password_hash, users.is_active, roles.role_name
         FROM users
         INNER JOIN roles ON roles.role_id = users.role_id
         WHERE users.username = ?'
    );
    $statement->execute([$username]);

    $user = $statement->fetch();

    return $user ?: null;
}
