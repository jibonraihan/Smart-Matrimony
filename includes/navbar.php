<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">

    <div class="container">

        <a class="navbar-brand fw-bold" href="<?= BASE_URL; ?>">

            💍 Smart Matrimony

        </a>


        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbar">

            <span class="navbar-toggler-icon"></span>

        </button>


        <div
            class="collapse navbar-collapse"
            id="navbar">


            <ul class="navbar-nav mx-auto">


                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= BASE_URL; ?>">

                        Public-Home

                    </a>

                </li>


                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="#">

                        About

                    </a>

                </li>


                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="#">

                        Services

                    </a>

                </li>


                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="#">

                        Contact

                    </a>

                </li>


            </ul>


            <div class="d-flex align-items-center">


                <?php if (isset($_SESSION['user_id'])): ?>


                    <span
                        class="text-white me-3 d-none d-lg-inline">

                        Hi,
                        <?= htmlspecialchars(
                            $_SESSION['first_name'] ?? 'User'
                        ); ?>

                    </span>


                    <a
                        href="<?= BASE_URL; ?>logout.php"
                        class="btn btn-outline-light">

                        <i class="fa-solid fa-right-from-bracket me-1"></i>

                        Logout

                    </a>


                <?php else: ?>

    <?php if ($currentPage === 'register.php'): ?>

        <!-- Register page: only Login -->
        <a
            href="<?= BASE_URL; ?>login.php"
            class="btn btn-outline-light">

            Login

        </a>

    <?php elseif ($currentPage === 'login.php'): ?>

        <!-- Login page: only Register -->
        <a
            href="<?= BASE_URL; ?>register.php"
            class="btn btn-warning">

            Register

        </a>

    <?php else: ?>

        <!-- Other public pages: Login + Register -->
        <a
            href="<?= BASE_URL; ?>login.php"
            class="btn btn-outline-light me-2">

            Login

        </a>

        <a
            href="<?= BASE_URL; ?>register.php"
            class="btn btn-warning">

            Register

        </a>

    <?php endif; ?>

<?php endif; ?>


            </div>


        </div>

    </div>

</nav>