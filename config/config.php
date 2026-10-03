<?php

// Project Information
define('SITE_NAME', 'Smart Matrimony');
define('BASE_URL', 'http://localhost/smart_matrimony/');

// Default Time Zone
date_default_timezone_set('Asia/Dhaka');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Central maintenance gate. Staff sessions may continue working while
// regular users see the branded maintenance page.
require_once __DIR__ . '/maintenance_gate.php';
smart_enforce_maintenance_mode();