<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/header.php';
include 'includes/navbar.php';

?>

<div class="container py-5">

    <div class="card shadow border-0 rounded-4 p-5">

        <h2 class="fw-bold">

            Welcome,
            <?= htmlspecialchars($_SESSION['first_name']); ?> 👋

        </h2>

        <hr>

        <p class="lead">
            Login Successful.
        </p>

        <p class="text-muted">
            Welcome to your Smart Matrimony Dashboard.
        </p>
        <div class="mt-4">

    <a href="profile/create_profile.php" class="btn btn-success btn-lg">

        <i class="bi bi-person-vcard-fill"></i>

        Complete Your Profile

    </a>

</div>

    </div>

</div>

<?php

include 'includes/footer.php';

?>