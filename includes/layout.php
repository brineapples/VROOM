<?php

function current_page_name(): string
{
    return basename($_SERVER['PHP_SELF'] ?? '');
}

function nav_link(string $href, string $iconClass, string $label): string
{
    $isActive = current_page_name() === $href;
    $activeClass = $isActive ? ' active' : '';

    return sprintf(
        '<li class="nav-item"><a href="%s" class="nav-link%s"><span class="nav-icon %s" aria-hidden="true"></span><span>%s</span></a></li>',
        e($href),
        $activeClass,
        e($iconClass),
        e($label)
    );
}

function app_user_initials(?array $user): string
{
    if ($user === null) {
        return 'G';
    }

    $parts = preg_split('/\s+/', trim((string) $user['full_name']));
    $initials = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'U';
}

function render_document_head(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo e($title); ?> | VROOM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="template/assets/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="template/glass-admin/templatemo-glass-admin-style.css">
    <link rel="stylesheet" href="assets/app.css">
</head>
<?php
}

function render_header(string $title): void
{
    $user = current_user();
    $flashMessage = get_flash();

    render_document_head($title);
    ?>
<body class="app-shell">
    <div class="background"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="dashboard">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a class="app-brand-logo" href="dashboard.php" aria-label="VROOM dashboard">
                    <img src="assets/vroom-logo.png" alt="VROOM">
                </a>
            </div>

            <ul class="nav-menu">
                <li class="nav-section">
                    <span class="nav-section-title">Workspace</span>
                    <ul>
                        <?php if ($user === null): ?>
                            <?php echo nav_link('login.php', 'fas fa-sign-in-alt', 'Login'); ?>
                        <?php else: ?>
                            <?php echo nav_link('dashboard.php', 'fas fa-th-large', 'Dashboard'); ?>
                            <?php echo nav_link('customers.php', 'fas fa-users', 'Customers'); ?>
                            <?php echo nav_link('vehicles.php', 'fas fa-car', 'Vehicles'); ?>
                            <?php echo nav_link('service_types.php', 'fas fa-tools', 'Service Types'); ?>
                            <?php echo nav_link('repair_orders.php', 'fas fa-clipboard-list', 'Repair Orders'); ?>
                            <?php echo nav_link('repair_order_services.php', 'fas fa-link', 'Order Services'); ?>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php if ($user !== null && has_role(['super_admin', 'admin'])): ?>
                    <li class="nav-section">
                        <span class="nav-section-title">Admin</span>
                        <ul>
                            <?php if (has_role(['super_admin'])): ?>
                                <?php echo nav_link('users.php', 'fas fa-user-cog', 'Accounts'); ?>
                            <?php endif; ?>
                            <?php echo nav_link('audit_logs.php', 'fas fa-history', 'Audit Logs'); ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <li class="nav-section">
                    <span class="nav-section-title">Session</span>
                    <ul>
                        <?php if ($user === null): ?>
                            <?php echo nav_link('login.php', 'fas fa-lock', 'Sign In'); ?>
                        <?php else: ?>
                            <?php echo nav_link('logout.php', 'fas fa-sign-out-alt', 'Logout'); ?>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo e(app_user_initials($user)); ?></div>
                    <div class="user-info">
                        <div class="user-name"><?php echo e($user['full_name'] ?? 'Guest'); ?></div>
                        <div class="user-role"><?php echo e($user['role_name'] ?? 'Not signed in'); ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <nav class="navbar">
                <div>
                    <h1 class="page-title"><?php echo e($title); ?></h1>
                    <div class="page-breadcrumb">
                        <a href="<?php echo $user === null ? 'login.php' : 'dashboard.php'; ?>">VROOM</a>
                        <span>/</span>
                        <span><?php echo e($title); ?></span>
                    </div>
                </div>
                <div class="navbar-right">
                    <?php if ($user !== null): ?>
                        <div class="app-account-pill">
                            <div class="app-account-avatar"><?php echo e(app_user_initials($user)); ?></div>
                            <div>
                                <span class="app-account-name"><?php echo e($user['full_name']); ?></span>
                                <span class="app-account-role"><?php echo e($user['role_name']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>

            <?php if ($flashMessage !== null): ?>
                <div class="app-notice">
                    <strong><?php echo e($flashMessage); ?></strong>
                </div>
            <?php endif; ?>

            <section class="app-content">
<?php
}

function render_footer(): void
{
    ?>
            </section>
        </main>
    </div>

    <button class="mobile-menu-toggle" type="button" aria-label="Open navigation">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>

    <footer class="site-footer">
        <p>&copy; VROOM, Vehicle Repair Order &amp; Operations Manager. Theme base: <a href="https://templatemo.com/tm-607-glass-admin" target="_blank" rel="nofollow">TemplateMo 607 Glass Admin</a>.</p>
    </footer>

    <script src="template/glass-admin/templatemo-glass-admin-script.js"></script>
</body>
</html>
<?php
}

function render_auth_header(string $title): void
{
    render_document_head($title);
    ?>
<body class="app-auth-body">
    <div class="background"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <main class="app-auth-page">
<?php
}

function render_auth_footer(): void
{
    ?>
    </main>
    <script src="template/glass-admin/templatemo-glass-admin-script.js"></script>
</body>
</html>
<?php
}
