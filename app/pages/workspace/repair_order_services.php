<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

set_flash('Services now live inside each repair order.');
redirect('repair_orders.php');

