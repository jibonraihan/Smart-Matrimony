<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/language.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$isHomePage = $currentPage === 'index.php';
$isChatRequestsPage = $currentPage === 'message_requests.php';

$navItems = [
    [
        'label' => sm_text('Home', 'হোম'),
        'icon' => 'fa-house',
        'href' => BASE_URL . ($isHomePage ? '#' : 'index.php'),
        'match' => $currentPage === 'index.php',
    ],
    [
        'label' => sm_text('About Us', 'আমাদের সম্পর্কে'),
        'icon' => 'fa-circle-info',
        'href' => BASE_URL . 'about.php',
        'match' => $currentPage === 'about.php',
    ],
    [
        'label' => sm_text('Services', 'সেবাসমূহ'),
        'icon' => 'fa-layer-group',
        'href' => '#',
        'match' => false,
        'dropdown' => true,
    ],
    [
        'label' => sm_text('Contact', 'যোগাযোগ'),
        'icon' => 'fa-envelope',
        'href' => BASE_URL . 'index.php#final-cta',
        'match' => false,
    ],
];
?>

<nav class="navbar navbar-expand-lg navbar-dark smart-navbar shadow-sm<?= $isChatRequestsPage ? ' chat-requests-navbar' : ''; ?>">

    <div class="container smart-navbar-container">

        <a class="navbar-brand smart-navbar-brand" href="<?= BASE_URL; ?>" aria-label="স্মার্ট ম্যাট্রিমনি হোম">
            <span class="smart-navbar-logo">
                <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="স্মার্ট ম্যাট্রিমনি লোগো">
            </span>
            <span class="smart-navbar-title" aria-hidden="true">
                <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="স্মার্ট ম্যাট্রিমনি">
            </span>
        </a>

        <?php if ($isChatRequestsPage): ?>
            <div class="smart-navbar-actions chat-requests-navbar-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= BASE_URL; ?>dashboard.php" class="smart-user-greeting">
                        <span class="smart-user-greeting-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                        <span><?= sm_text('Welcome, ', 'স্বাগতম, '); ?><?= htmlspecialchars($_SESSION['first_name'] ?? sm_text('User', 'ব্যবহারকারী')); ?></span>
                    </a>
                    <a href="<?= BASE_URL; ?>logout.php" class="btn smart-nav-btn smart-nav-logout">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        <span><?= sm_text('Logout', 'লগআউট'); ?></span>
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <button
            class="navbar-toggler smart-navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbar"
            aria-controls="navbar"
            aria-expanded="false"
            aria-label="নেভিগেশন খুলুন বা বন্ধ করুন">
            <span class="smart-menu-icon" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </button>

        <div class="collapse navbar-collapse smart-navbar-collapse" id="navbar">

            <ul class="navbar-nav smart-navbar-nav">
                <?php foreach ($navItems as $item): ?>
                    <?php if (!empty($item['dropdown'])): ?>
                        <li class="nav-item smart-nav-services">
                            <a
                                class="nav-link smart-nav-link smart-nav-services-toggle"
                                href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                                aria-haspopup="true"
                                aria-expanded="false">
                                <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($item['label']); ?></span>
                                <i class="fa-solid fa-chevron-down smart-services-chevron" aria-hidden="true"></i>
                            </a>
                            <div class="smart-services-dropdown" aria-label="Wedding service categories">
                                <div class="smart-services-dropdown-head">
                                    <strong><?= sm_text('Explore Wedding Services', 'বিয়ের সেবাসমূহ দেখুন'); ?></strong>
                                    <small><?= sm_text('Find packages and service providers', 'প্যাকেজ ও সেবাদাতা খুঁজুন'); ?></small>
                                </div>
                                <a href="<?= BASE_URL; ?>index.php?service=1#home-services" data-home-service="1"><i class="fa-solid fa-camera-retro"></i><span><?= sm_text('Photography', 'ফটোগ্রাফি'); ?></span></a>
                                <a href="<?= BASE_URL; ?>index.php?service=7#home-services" data-home-service="7"><i class="fa-solid fa-building-columns"></i><span><?= sm_text('Convention Center', 'কনভেনশন সেন্টার'); ?></span></a>
                                <a href="<?= BASE_URL; ?>index.php?service=3#home-services" data-home-service="3"><i class="fa-solid fa-wand-magic-sparkles"></i><span><?= sm_text('Decoration', 'সাজসজ্জা'); ?></span></a>
                                <a href="<?= BASE_URL; ?>index.php?service=15#home-services" data-home-service="15"><i class="fa-solid fa-lightbulb"></i><span><?= sm_text('Sound &amp; Lighting', 'সাউন্ড ও লাইটিং'); ?></span></a>
                                <a href="<?= BASE_URL; ?>index.php?service=5#home-services" data-home-service="5"><i class="fa-solid fa-brush"></i><span><?= sm_text('Makeup Artist', 'মেকআপ আর্টিস্ট'); ?></span></a>
                                <a href="<?= BASE_URL; ?>index.php?service=12#home-services" data-home-service="12"><i class="fa-solid fa-shirt"></i><span><?= sm_text('Wedding Dress', 'বিয়ের পোশাক'); ?></span></a>
                                <a href="<?= BASE_URL; ?>index.php?service=4#home-services" data-home-service="4"><i class="fa-solid fa-car"></i><span><?= sm_text('Car Rental', 'গাড়ি ভাড়া'); ?></span></a>
                                <a class="smart-services-all" href="<?= BASE_URL; ?>index.php#home-services"><i class="fa-solid fa-table-cells-large"></i><span><?= sm_text('All Services', 'সব সেবা'); ?></span><i class="fa-solid fa-arrow-right"></i></a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a
                                class="nav-link smart-nav-link<?= $item['match'] ? ' active' : ''; ?>"
                                href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                                <?= $item['match'] ? 'aria-current="page"' : ''; ?>>
                                <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($item['label']); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <div class="smart-navbar-actions">

                <?php if ($isHomePage): ?>
                    <button type="button" class="btn smart-nav-btn smart-nav-login" data-open-language-picker aria-label="Change language" title="Change language">
                        <i class="fa-solid fa-language" aria-hidden="true"></i>
                        <span>বাংলা / EN</span>
                    </button>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): ?>

                    <a href="<?= BASE_URL; ?>dashboard.php" class="smart-user-greeting">
                        <span class="smart-user-greeting-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                        <span><?= sm_text('Welcome, ', 'স্বাগতম, '); ?><?= htmlspecialchars($_SESSION['first_name'] ?? sm_text('User', 'ব্যবহারকারী')); ?></span>
                    </a>

                    <a href="<?= BASE_URL; ?>logout.php" class="btn smart-nav-btn smart-nav-logout">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        <span><?= sm_text('Logout', 'লগআউট'); ?></span>
                    </a>

                <?php else: ?>

                    <?php if ($currentPage === 'register.php'): ?>
                        <!-- Register page: Login only -->
                        <a href="<?= BASE_URL; ?>login.php" class="btn smart-nav-btn smart-nav-login">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                            <span><?= sm_text('Login', 'লগইন'); ?></span>
                        </a>

                    <?php elseif ($currentPage === 'login.php'): ?>
                        <!-- Login page: Register only -->
                        <a href="<?= BASE_URL; ?>register.php" class="btn smart-nav-btn smart-nav-register">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span><?= sm_text('Register', 'নিবন্ধন'); ?></span>
                        </a>

                    <?php else: ?>
                        <!-- Other public pages: Login + Register -->
                        <a href="<?= BASE_URL; ?>login.php" class="btn smart-nav-btn smart-nav-login">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                            <span><?= sm_text('Login', 'লগইন'); ?></span>
                        </a>

                        <a href="<?= BASE_URL; ?>register.php" class="btn smart-nav-btn smart-nav-register">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span><?= sm_text('Register', 'নিবন্ধন'); ?></span>
                        </a>
                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>
        <?php endif; ?>

    </div>

</nav>
