<?php

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

set_flash('Services now live inside each repair order.');
redirect('repair_orders.php');

