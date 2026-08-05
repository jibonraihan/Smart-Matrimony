<?php

require_once 'config.php';

$host = "localhost";
$username = "root";
$password = "";
$database = "smart_matrimony";

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

if (!$conn) {
    die("Database Connection Failed : " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");