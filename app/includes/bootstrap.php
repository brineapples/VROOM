<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require dirname(__DIR__, 2) . '/config/app.php';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/layout.php';
