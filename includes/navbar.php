<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">

    <div class="container">

        <a class="navbar-brand fw-bold" href="<?= BASE_URL; ?>">
            💍 Smart Matrimony
        </a>

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbar">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="navbar">

            <ul class="navbar-nav mx-auto">

                <li class="nav-item">

                    <a class="nav-link" href="<?= BASE_URL; ?>">

                        Home

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link" href="#">

                        About

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link" href="#">

                        Services

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link" href="#">

                        Contact

                    </a>

                </li>

            </ul>

            <div>

                <a href="<?= BASE_URL; ?>login.php"
                   class="btn btn-outline-light me-2">

                    Login

                </a>

                <a href="<?= BASE_URL; ?>register.php"
                   class="btn btn-warning">

                    Register

                </a>

            </div>

        </div>

    </div>

</nav>