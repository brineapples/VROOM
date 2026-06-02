<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

redirect('login.php');
