<?php

require_once __DIR__ . '/includes/bootstrap.php';

if (has_role(['super_admin'])) {
    redirect('users.php');
}

redirect('dashboard.php');
