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