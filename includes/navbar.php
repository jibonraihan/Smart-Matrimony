<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isHomePage = $currentPage === 'index.php';

$navItems = [
    [
        'label' => 'Public Home',
        'icon' => 'fa-house',
        'href' => BASE_URL . ($isHomePage ? '#' : 'index.php'),
        'match' => $currentPage === 'index.php',
    ],
    [
        'label' => 'About',
        'icon' => 'fa-circle-info',
        'href' => BASE_URL . 'about.php',
        'match' => $currentPage === 'about.php',
    ],
    [
        'label' => 'Services',
        'icon' => 'fa-layer-group',
        'href' => BASE_URL . 'index.php#features',
        'match' => false,
    ],
    [
        'label' => 'Contact',
        'icon' => 'fa-envelope',
        'href' => BASE_URL . 'index.php#final-cta',
        'match' => false,
    ],
];
?>

<nav class="navbar navbar-expand-lg navbar-dark smart-navbar shadow-sm">

    <div class="container smart-navbar-container">

        <a class="navbar-brand smart-navbar-brand" href="<?= BASE_URL; ?>" aria-label="Smart Matrimony Home">
            <span class="smart-navbar-logo">
                <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
            </span>
            <span class="smart-navbar-title">Smart Matrimony</span>
        </a>

        <button
            class="navbar-toggler smart-navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbar"
            aria-controls="navbar"
            aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="smart-menu-icon" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </button>

        <div class="collapse navbar-collapse smart-navbar-collapse" id="navbar">

            <ul class="navbar-nav smart-navbar-nav">
                <?php foreach ($navItems as $item): ?>
                    <li class="nav-item">
                        <a
                            class="nav-link smart-nav-link<?= $item['match'] ? ' active' : ''; ?>"
                            href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?= $item['match'] ? 'aria-current="page"' : ''; ?>>
                            <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($item['label']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="smart-navbar-actions">

                <?php if (isset($_SESSION['user_id'])): ?>

                    <a href="<?= BASE_URL; ?>dashboard.php" class="smart-user-greeting">
                        <span class="smart-user-greeting-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                        <span>Hi, <?= htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?></span>
                    </a>

                    <a href="<?= BASE_URL; ?>logout.php" class="btn smart-nav-btn smart-nav-logout">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        <span>Logout</span>
                    </a>

                <?php else: ?>

                    <?php if ($currentPage === 'register.php'): ?>
                        <!-- Register page: Login only -->
                        <a href="<?= BASE_URL; ?>login.php" class="btn smart-nav-btn smart-nav-login">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                            <span>Login</span>
                        </a>

                    <?php elseif ($currentPage === 'login.php'): ?>
                        <!-- Login page: Register only -->
                        <a href="<?= BASE_URL; ?>register.php" class="btn smart-nav-btn smart-nav-register">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span>Register</span>
                        </a>

                    <?php else: ?>
                        <!-- Other public pages: Login + Register -->
                        <a href="<?= BASE_URL; ?>login.php" class="btn smart-nav-btn smart-nav-login">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                            <span>Login</span>
                        </a>

                        <a href="<?= BASE_URL; ?>register.php" class="btn smart-nav-btn smart-nav-register">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span>Register</span>
                        </a>
                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

</nav>
