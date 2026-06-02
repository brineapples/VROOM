<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$pdo = get_pdo();
$counts = [];
$tables = [
    'customers' => 'Customers',
    'vehicles' => 'Vehicles',
    'service_types' => 'Service Types',
    'repair_orders' => 'Repair Orders',
];

if (has_role(['super_admin'])) {
    $tables['users'] = 'Accounts';
}

foreach ($tables as $tableName => $label) {
    $counts[$label] = (int) $pdo->query("SELECT COUNT(*) FROM {$tableName}")->fetchColumn();
}

$statStyles = [
    'Customers' => ['icon' => 'fas fa-users', 'tone' => 'cyan'],
    'Vehicles' => ['icon' => 'fas fa-car', 'tone' => 'magenta'],
    'Service Types' => ['icon' => 'fas fa-tools', 'tone' => 'purple'],
    'Repair Orders' => ['icon' => 'fas fa-clipboard-list', 'tone' => 'cyan'],
    'Accounts' => ['icon' => 'fas fa-user-cog', 'tone' => 'purple'],
];

render_header('Dashboard');
?>
<div class="stats-grid app-stat-grid">
    <?php foreach ($counts as $label => $count): ?>
        <?php $statStyle = $statStyles[$label] ?? ['icon' => 'fas fa-table', 'tone' => 'cyan']; ?>
        <div class="glass-card glass-card-3d stat-card app-dashboard-stat">
            <div class="stat-card-inner">
                <div class="stat-info">
                    <h3><?php echo e($label); ?></h3>
                    <div class="stat-value"><?php echo e((string) $count); ?></div>
                    <span class="stat-change positive">Live table count</span>
                </div>
                <div class="stat-icon <?php echo e($statStyle['tone']); ?>">
                    <span class="<?php echo e($statStyle['icon']); ?>" aria-hidden="true"></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php
render_footer();
