<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Remove all session variables
 */
$_SESSION = [];

/*
 * Destroy the current session
 */
session_destroy();

/*
 * Return to login page
 */
header('Location: login.php?logout=1');
exit;