<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (has_role(['super_admin'])) {
    redirect('users.php');
}

redirect('dashboard.php');
