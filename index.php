<?php

$page_css = 'assets/css/index.css';

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   DYNAMIC HOMEPAGE STATISTICS
   All counters are read from the current database.
========================================================= */
$registered_members = 0;
$completed_profiles = 0;
$male_profiles = 0;
$female_profiles = 0;
$service_packages = 0;

$stats_result = mysqli_query($conn, "
    SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'User' AND account_status = 'Active') AS registered_members,
        (SELECT COUNT(*)
         FROM user_profiles up
         INNER JOIN users u ON u.user_id = up.user_id
         WHERE u.role = 'User' AND u.account_status = 'Active') AS completed_profiles,
        (SELECT COUNT(*)
         FROM user_profiles up
         INNER JOIN users u ON u.user_id = up.user_id
         WHERE u.role = 'User' AND u.account_status = 'Active' AND up.gender = 'Male') AS male_profiles,
        (SELECT COUNT(*)
         FROM user_profiles up
         INNER JOIN users u ON u.user_id = up.user_id
         WHERE u.role = 'User' AND u.account_status = 'Active' AND up.gender = 'Female') AS female_profiles,
        (SELECT COUNT(*) FROM service_providers WHERE status = 'Active') AS service_packages
");

if ($stats_result) {
    $stats = mysqli_fetch_assoc($stats_result) ?: [];
    $registered_members = (int)($stats['registered_members'] ?? 0);
    $completed_profiles = (int)($stats['completed_profiles'] ?? 0);
    $male_profiles = (int)($stats['male_profiles'] ?? 0);
    $female_profiles = (int)($stats['female_profiles'] ?? 0);
    $service_packages = (int)($stats['service_packages'] ?? 0);
}

/* =========================================================
   PUBLIC WEDDING SERVICES
   Loaded here so visitors can explore packages without logging in.
========================================================= */
$home_services = [];
$home_service_result = mysqli_query($conn, "
    SELECT service_id, service_name, description
    FROM services
    ORDER BY service_id
");
if ($home_service_result) {
    while ($row = mysqli_fetch_assoc($home_service_result)) {
        $home_services[] = $row;
    }
}

$home_service_packages = [];
$home_package_result = mysqli_query($conn, "
    SELECT sp.provider_id, sp.service_id, s.service_name, sp.provider_name, sp.package_name,
           sp.package_details, sp.image, sp.price, sp.discount_percent, sp.location, sp.rating, sp.review_count
    FROM service_providers sp
    INNER JOIN services s ON s.service_id = sp.service_id
    WHERE sp.status = 'Active'
    ORDER BY sp.service_id, sp.rating DESC, sp.review_count DESC, sp.provider_id DESC
");
if ($home_package_result) {
    while ($row = mysqli_fetch_assoc($home_package_result)) {
        $row['discount_percent'] = max(0, min(100, (float) $row['discount_percent']));
        $row['final_price'] = round((float) $row['price'] * (1 - ($row['discount_percent'] / 100)), 2);
        $home_service_packages[(int) $row['service_id']][] = $row;
    }
}

$home_featured_packages = [];
$home_max_discount = 0;
foreach ($home_service_packages as $service_packages) {
    foreach ($service_packages as $package) {
        $home_featured_packages[] = $package;
        $home_max_discount = max($home_max_discount, (float) ($package['discount_percent'] ?? 0));
    }
}

$home_service_icons = [
    'Photography' => 'fa-camera-retro',
    'Catering' => 'fa-utensils',
    'Decoration' => 'fa-wand-magic-sparkles',
    'Car Rental' => 'fa-car',
    'Makeup Artist' => 'fa-brush',
    'Wedding Planner' => 'fa-clipboard-list',
    'Convention Center' => 'fa-building-columns',
    'Resort / Lawn' => 'fa-tree',
    'Mehendi Artist' => 'fa-hand-sparkles',
    'Wedding Flowers' => 'fa-seedling',
    'Basor Ghor Decoration' => 'fa-bed',
    'Wedding Dress' => 'fa-shirt',
    'Groom Dress' => 'fa-user-tie',
    'Bridal Accessories' => 'fa-gem',
    'Sound & Lighting' => 'fa-lightbulb',
    'Invitation & Printing' => 'fa-envelope-open-text'
];

/* =========================================================
   RECENTLY JOINED PUBLIC PROFILES
   Only public, active user profiles are shown; photos and
   sensitive contact information are intentionally excluded.
========================================================= */
$featured_profiles = [];
$featured_result = mysqli_query($conn, "
    SELECT up.user_id, up.first_name, up.last_name, up.gender, up.date_of_birth,
           up.area, up.country, up.highest_education, up.profession,
           up.verification_status, up.created_at
    FROM user_profiles up
    INNER JOIN users u ON u.user_id = up.user_id
    WHERE u.role = 'User'
      AND u.account_status = 'Active'
      AND up.profile_visibility = 'Public'
    ORDER BY up.created_at DESC
    LIMIT 6
");
if ($featured_result) {
    while ($row = mysqli_fetch_assoc($featured_result)) {
        $featured_profiles[] = $row;
    }
}

$home_is_logged_in = isset($_SESSION['user_id']);
if ($home_is_logged_in && empty($_SESSION['cart_csrf'])) {
    $_SESSION['cart_csrf'] = bin2hex(random_bytes(24));
}

include 'includes/header.php';
include 'includes/navbar.php';

?>

<main class="home-page">

    <!-- =========================
         HERO SECTION
    ========================== -->
    <section class="home-hero">

        <div class="container">

            <div class="row align-items-center gy-5">

                <!-- HERO CONTENT -->
                <div class="col-lg-7">

                    <div class="hero-content" data-aos="fade-right">

                        <div class="hero-badge">
                            <i class="fa-solid fa-shield-heart"></i>
                            Secure • Respectful • Family-Friendly
                        </div>

                        <div class="hero-brand-mark">
                            <img
                                src="<?= BASE_URL; ?>assets/images/logo/logo.png"
                                alt="Smart Matrimony Logo"
                                class="hero-logo"
                            >
                        </div>

                        <p class="hero-eyebrow">
                            Smart Matrimony
                        </p>

                        <h1 class="hero-title">
                            Find a Life Partner
                            <span>with Trust &amp; Dignity.</span>
                        </h1>

                        <p class="hero-description">
                            A modern, privacy-focused matrimony platform designed
                            to help individuals and families discover compatible
                            life partners in a respectful and secure environment.
                        </p>

                        <div class="hero-actions">

                            <a
                                href="<?= BASE_URL; ?>register.php"
                                class="btn btn-primary hero-btn-primary"
                            >
                                <i class="fa-solid fa-user-plus me-2"></i>
                                Create Account
                            </a>

                            <a
                                href="<?= BASE_URL; ?>login.php"
                                class="btn hero-btn-secondary"
                            >
                                <i class="fa-solid fa-right-to-bracket me-2"></i>
                                Login
                            </a>

                        </div>

                        <div class="hero-trust-row">

                            <div class="hero-trust-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Secure Authentication</span>
                            </div>

                            <div class="hero-trust-item">
                                <i class="fa-solid fa-user-shield"></i>
                                <span>Privacy Focused</span>
                            </div>

                            <div class="hero-trust-item">
                                <i class="fa-solid fa-heart"></i>
                                <span>Respectful Matching</span>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- HERO VISUAL -->
                <div class="col-lg-5">

                    <div class="hero-visual" data-aos="fade-left">

                        <div class="hero-glow hero-glow-one"></div>
                        <div class="hero-glow hero-glow-two"></div>

                        <div class="hero-card">

                            <div class="hero-card-top">

                                <div class="hero-card-icon">
                                    <i class="fa-solid fa-heart"></i>
                                </div>

                                <div>
                                    <span class="hero-card-label">
                                        Built for meaningful connections
                                    </span>
                                    <h2>
                                        Your journey starts here.
                                    </h2>
                                </div>

                            </div>

                            <div class="hero-card-divider"></div>

                            <div class="hero-card-feature">
                                <div class="hero-feature-icon">
                                    <i class="fa-solid fa-user-check"></i>
                                </div>
                                <div>
                                    <strong>Verified profiles</strong>
                                    <p>Encouraging authentic and trustworthy connections.</p>
                                </div>
                            </div>

                            <div class="hero-card-feature">
                                <div class="hero-feature-icon">
                                    <i class="fa-solid fa-sliders"></i>
                                </div>
                                <div>
                                    <strong>Preference-based matching</strong>
                                    <p>Find people based on the preferences that matter to you.</p>
                                </div>
                            </div>

                            <div class="hero-card-feature">
                                <div class="hero-feature-icon">
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                                <div>
                                    <strong>Privacy &amp; security</strong>
                                    <p>Designed with respectful communication and privacy in mind.</p>
                                </div>
                            </div>

                            <div class="hero-card-footer">
                                <span>
                                    <i class="fa-solid fa-shield-halved"></i>
                                    Smart Matrimony
                                </span>
                                <span class="hero-card-status">
                                    <i class="fa-solid fa-circle"></i>
                                    Ready to begin
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>

        <!-- =========================
         ISLAMIC GUIDANCE SECTION
    ========================== -->
    <section
        id="islamic-guidance"
        class="islamic-section"
    >

        <div class="container">

            <!-- Section Heading -->
            <div
                class="islamic-section-heading text-center"
                data-aos="fade-up"
            >

                <span class="islamic-section-label">

                    <i class="fa-solid fa-moon"></i>

                    Islamic Guidance

                </span>

                <h2>

                    Marriage with
                    <span>Tranquility, Love &amp; Mercy.</span>

                </h2>

                <p>

                    Islam gives marriage a meaningful place in life,
                    reminding us of companionship, responsibility,
                    affection and faith.

                </p>

            </div>


            <!-- =========================
                 QURAN & SUNNAH CARDS
            ========================== -->

            <div class="row g-4">


                <!-- =========================
                     QURAN CARD
                ========================== -->

                <div class="col-lg-6">

                    <article
                        class="islamic-card combined-islamic-card"
                        data-aos="fade-right"
                    >

                        <!-- Card Header -->

                        <div class="islamic-card-top">

                            <div class="islamic-icon">

                                <i class="fa-solid fa-book-quran"></i>

                            </div>

                            <div>

                                <span class="content-type">
                                    From the Quran
                                </span>

                                <h3>
                                    Guidance for Marriage
                                </h3>

                            </div>

                        </div>


                        <!-- =========================
                             VERSE 01
                        ========================== -->

                        <div class="islamic-content-block">

                            <div class="content-number">
                                01
                            </div>

                            <div class="content-block-body">

                                <h4>
                                    Tranquility, Love &amp; Mercy
                                </h4>

                                <div class="quran-arabic">

                                    وَمِنْ آيَاتِهِ أَنْ خَلَقَ لَكُمْ
                                    مِنْ أَنْفُسِكُمْ أَزْوَاجًا
                                    لِّتَسْكُنُوا إِلَيْهَا
                                    وَجَعَلَ بَيْنَكُم مَّوَدَّةً
                                    وَرَحْمَةً

                                </div>


                                <div class="quran-translation">

                                    <span>
                                        অর্থ
                                    </span>

                                    তাঁর নিদর্শনের মধ্যে হল এই যে,
                                    তিনি তোমাদের জন্য তোমাদের মধ্য হতেই
                                    তোমাদের সঙ্গিণী সৃষ্টি করেছেন যাতে
                                    তোমরা তার কাছে শান্তি লাভ করতে পার
                                    এবং তিনি তোমাদের মধ্যে পারস্পরিক
                                    ভালবাসা ও দয়া সৃষ্টি করেছেন।

                                </div>


                                <div class="content-reference">

                                    <i class="fa-solid fa-bookmark"></i>

                                    Surah Ar-Rum — 30:21

                                </div>

                            </div>

                        </div>


                        <!-- Divider -->

                        <div class="islamic-content-divider"></div>


                        <!-- =========================
                             VERSE 02
                        ========================== -->

                        <div class="islamic-content-block">

                            <div class="content-number">
                                02
                            </div>

                            <div class="content-block-body">

                                <h4>
                                    Encouragement to Marry
                                </h4>

                                <div class="quran-arabic quran-arabic-small">

                                    وَأَنكِحُوا۟ ٱلْأَيَـٰمَىٰ مِنكُمْ
                                    وَٱلصَّـٰلِحِينَ مِنْ عِبَادِكُمْ
                                    وَإِمَآئِكُمْ ۚ
                                    إِن يَكُونُوا۟ فُقَرَآءَ
                                    يُغْنِهِمُ ٱللَّهُ مِن فَضْلِهِۦ

                                </div>


                                <div class="quran-translation">

                                    <span>
                                        অর্থ
                                    </span>

                                    তোমাদের মধ্যে যারা বিবাহহীন তাদের
                                    বিবাহ সম্পন্ন কর আর তোমাদের সৎ
                                    দাস-দাসীদেরও। তারা যদি নিঃস্ব হয়
                                    তাহলে আল্লাহ তাদেরকে নিজ অনুগ্রহে
                                    অভাবমুক্ত করে দেবেন।

                                </div>


                                <div class="content-reference">

                                    <i class="fa-solid fa-bookmark"></i>

                                    Surah An-Nur — 24:32

                                </div>

                            </div>

                        </div>

                    </article>

                </div>


                <!-- =========================
                     SUNNAH / HADITH CARD
                ========================== -->

                <div class="col-lg-6">

                    <article
                        class="islamic-card combined-islamic-card"
                        data-aos="fade-left"
                    >

                        <!-- Card Header -->

                        <div class="islamic-card-top">

                            <div class="islamic-icon">

                                <i class="fa-solid fa-mosque"></i>

                            </div>

                            <div>

                                <span class="content-type">
                                    From the Sunnah
                                </span>

                                <h3>
                                    Prophetic Guidance
                                </h3>

                            </div>

                        </div>


                        <!-- =========================
                             HADITH 01
                        ========================== -->

                        <div class="islamic-content-block hadith-content-block">

                            <div class="content-number">
                                01
                            </div>

                            <div class="content-block-body">

                                <h4>
                                    Guidance for Young People
                                </h4>

                                <div class="hadith-mark">
                                    “
                                </div>

                                <p class="hadith-text">

                                    হে যুবকগণ! তোমাদের মধ্যে যে ব্যক্তি
                                    বিবাহের সামর্থ্য রাখে, সে যেন বিবাহ
                                    করে। কারণ, তা দৃষ্টিকে অবনত রাখতে
                                    এবং লজ্জাস্থান হেফাজত করতে অধিক
                                    সহায়ক। আর যে ব্যক্তি বিবাহের সামর্থ্য
                                    রাখে না, সে যেন রোজা রাখে; কারণ
                                    রোজা তার জন্য ঢালস্বরূপ।

                                </p>


                                <div class="hadith-source">

                                    — রাসূলুল্লাহ ﷺ

                                </div>


                                <div class="content-reference">

                                    <i class="fa-solid fa-bookmark"></i>

                                    Sahih al-Bukhari — 5066

                                    <span class="reference-separator">
                                        •
                                    </span>

                                    Book 67, Hadith 4

                                </div>

                            </div>

                        </div>


                        <!-- Divider -->

                        <div class="islamic-content-divider"></div>


                        <!-- =========================
                             HADITH 02
                        ========================== -->

                        <div class="islamic-content-block hadith-content-block">

                            <div class="content-number">
                                02
                            </div>

                            <div class="content-block-body">

                                <h4>
                                    Marriage &amp; Half of Faith
                                </h4>

                                <div class="hadith-mark">
                                    “
                                </div>

                                <p class="hadith-text">

                                    কোনো বান্দা যখন বিবাহ করে,
                                    তখন সে তার দ্বীনের অর্ধেক পূর্ণ করে।
                                    অতএব, অবশিষ্ট অর্ধেকের ব্যাপারে
                                    সে যেন আল্লাহকে ভয় করে।

                                </p>


                                <div class="hadith-source">

                                    — রাসূলুল্লাহ ﷺ

                                </div>


                                <div class="content-reference">

                                    <i class="fa-solid fa-bookmark"></i>

                                    Shu'ab al-Iman — 5486

                                </div>

                            </div>

                        </div>

                    </article>

                </div>

            </div>


            <!-- =========================
                 ISLAMIC REMINDER
            ========================== -->

            <div
                class="islamic-reminder"
                data-aos="fade-up"
            >

                <div class="reminder-icon">

                    <i class="fa-solid fa-heart"></i>

                </div>


                <div class="reminder-content">

                    <span>
                        A Gentle Reminder
                    </span>

                    <h4>
                        Seek a marriage built on
                        character, responsibility and mercy.
                    </h4>

                    <p>

                        A meaningful matrimonial journey begins
                        with sincere intention, good character,
                        responsibility and respect.

                    </p>

                </div>

            </div>

        </div>

    </section>

        <!-- =========================
         PROJECT FEATURES SECTION
    ========================== -->
    <section
        id="features"
        class="features-section"
    >

        <div class="container">

            <!-- Section Heading -->

            <div
                class="features-section-heading text-center"
                data-aos="fade-up"
            >

                <span class="features-section-label">

                    <i class="fa-solid fa-sparkles"></i>

                    Why Smart Matrimony?

                </span>

                <h2>

                    Thoughtful Features for a
                    <span>Better Matrimonial Experience.</span>

                </h2>

                <p>

                    Everything is designed to make discovering,
                    connecting and communicating with potential
                    life partners more organized, respectful and secure.

                </p>

            </div>


            <!-- =========================
                 FEATURES GRID
            ========================== -->

            <div class="row g-3">


                <!-- FEATURE 01 -->

                <div class="col-12 col-md-6 col-lg-3">

                    <div
                        class="feature-card"
                        data-aos="fade-up"
                        data-aos-delay="50"
                    >

                        <div class="feature-card-icon">

                            <i class="fa-solid fa-user-check"></i>

                        </div>

                        <div class="feature-card-number">
                            01
                        </div>

                        <h3>
                            Verified Profiles
                        </h3>

                        <p>

                            Profile verification helps create
                            a more trustworthy matrimonial
                            environment.

                        </p>

                    </div>

                </div>


                <!-- FEATURE 02 -->

                <div class="col-12 col-md-6 col-lg-3">

                    <div
                        class="feature-card"
                        data-aos="fade-up"
                        data-aos-delay="100"
                    >

                        <div class="feature-card-icon">

                            <i class="fa-solid fa-heart-circle-check"></i>

                        </div>

                        <div class="feature-card-number">
                            02
                        </div>

                        <h3>
                            Smart Matching
                        </h3>

                        <p>

                            Matching is designed around
                            partner information and
                            compatibility preferences.

                        </p>

                    </div>

                </div>


                <!-- FEATURE 03 -->

                <div class="col-12 col-md-6 col-lg-3">

                    <div
                        class="feature-card"
                        data-aos="fade-up"
                        data-aos-delay="150"
                    >

                        <div class="feature-card-icon">

                            <i class="fa-solid fa-sliders"></i>

                        </div>

                        <div class="feature-card-number">
                            03
                        </div>

                        <h3>
                            Preference-Based Search
                        </h3>

                        <p>

                            Search preferences help users
                            define the qualities and details
                            that matter to them.

                        </p>

                    </div>

                </div>


                <!-- FEATURE 04 -->

                <div class="col-12 col-md-6 col-lg-3">

                    <div
                        class="feature-card"
                        data-aos="fade-up"
                        data-aos-delay="200"
                    >

                        <div class="feature-card-icon">

                            <i class="fa-solid fa-shield-halved"></i>

                        </div>

                        <div class="feature-card-number">
                            04
                        </div>

                        <h3>
                            Privacy &amp; Security
                        </h3>

                        <p>

                            Privacy-focused profile and
                            communication features help
                            protect personal information.

                        </p>

                    </div>

                </div>


                <!-- FEATURE 05 -->

                <div class="col-12 col-md-6 col-lg-3">

                    <div
                        class="feature-card"
                        data-aos="fade-up"
                        data-aos-delay="250"
                    >

                        <div class="feature-card-icon">

                            <i class="fa-solid fa-envelope-circle-check"></i>

                        </div>

                        <div class="feature-card-number">
                            05
                        </div>

                        <h3>
                            Email Verification
                        </h3>

                        <p>

                            Email verification helps confirm
                            account ownership before an account
                            becomes active.

                        </p>

                    </div>

                </div>


                <!-- FEATURE 06 -->

                <div class="col-12 col-md-6 col-lg-3">

                    <div
                        class="feature-card"
                        data-aos="fade-up"
                        data-aos-delay="300"
                    >

                        <div class="feature-card-icon">

                            <i class="fa-solid fa-champagne-glasses"></i>

                        </div>

                        <div class="feature-card-number">
                            06
                        </div>

                        <h3>
                            Wedding Services
                        </h3>

                        <p>

                            Explore photography, decoration,
                            venues and other wedding services
                            from available packages.

                        </p>

                    </div>

                </div>


                <!-- FEATURE 07 -->

                <div class="col-12 col-md-6 col-lg-3">

                    <div
                        class="feature-card"
                        data-aos="fade-up"
                        data-aos-delay="350"
                    >

                        <div class="feature-card-icon">

                            <i class="fa-solid fa-bookmark"></i>

                        </div>

                        <div class="feature-card-number">
                            07
                        </div>

                        <h3>
                            Save Profiles
                        </h3>

                        <p>

                            Bookmark profiles that you may
                            want to revisit later during
                            your search.

                        </p>

                    </div>

                </div>


                <!-- FEATURE 08 -->

                <div class="col-12 col-md-6 col-lg-3">

                    <div
                        class="feature-card"
                        data-aos="fade-up"
                        data-aos-delay="400"
                    >

                        <div class="feature-card-icon">

                            <i class="fa-solid fa-comments"></i>

                        </div>

                        <div class="feature-card-number">
                            08
                        </div>

                        <h3>
                            Respectful Communication
                        </h3>

                        <p>

                            Chat requests and conversations
                            are structured around a more
                            respectful communication flow.

                        </p>

                    </div>

                </div>

            </div>


            <!-- Feature Bottom Note -->

            <div
                class="features-bottom-note"
                data-aos="fade-up"
            >

                <div class="features-bottom-icon">

                    <i class="fa-solid fa-shield-heart"></i>

                </div>

                <div>

                    <strong>
                        Designed with trust in mind.
                    </strong>

                    <span>
                        From account verification to
                        respectful communication.
                    </span>

                </div>

            </div>

        </div>

    </section>

    <!-- =========================
         DYNAMIC STATISTICS
    ========================== -->
    <!-- =========================
         FEATURED SERVICE PACKAGES
         This showcase is independent from the existing
         Smart Wedding Services section below.
    ========================= -->
    <section id="featured-packages" class="featured-packages-section">
        <div class="container">
            <div class="featured-packages-offer" data-aos="fade-up">
                <div class="featured-offer-icon"><i class="fa-solid fa-tags"></i></div>
                <div>
                    <span class="featured-offer-kicker">SPECIAL OFFER</span>
                    <strong><?= $home_max_discount > 0 ? 'Save up to ' . rtrim(rtrim(number_format($home_max_discount, 2), '0'), '.') . '% on selected packages' : 'Explore our latest wedding service packages'; ?></strong>
                    <small>Discounts are managed by our Event Managers and applied automatically to package pricing.</small>
                </div>
            </div>

            <div class="featured-packages-heading" data-aos="fade-up">
                <div>
                    <span class="featured-packages-label"><i class="fa-solid fa-gift"></i> Featured Packages</span>
                    <h2>Plan Your Day with <span>Smart Wedding Services.</span></h2>
                    <p>Explore active packages from our service providers. Swipe, use the arrows, or let the showcase move automatically.</p>
                </div>
                <a class="featured-packages-view-all" href="#home-services">
                    View All Services <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <?php if ($home_featured_packages): ?>
                <div class="featured-packages-carousel" data-package-carousel>
                    <button type="button" class="featured-package-nav featured-package-prev" data-package-prev aria-label="Previous package"><i class="fa-solid fa-chevron-left"></i></button>
                    <div class="featured-package-viewport">
                        <div class="featured-package-track" data-package-track>
                            <?php foreach ($home_featured_packages as $package): ?>
                                <?php
                                    $discount = (float) ($package['discount_percent'] ?? 0);
                                    $original_price = (float) $package['price'];
                                    $final_price = (float) ($package['final_price'] ?? $original_price);
                                ?>
                                <article class="featured-package-card" data-package-card>
                                    <div class="featured-package-image">
                                        <?php if (!empty($package['image'])): ?>
                                            <img src="<?= BASE_URL; ?>uploads/services/<?= htmlspecialchars($package['image']); ?>" alt="<?= htmlspecialchars($package['package_name'] ?: $package['provider_name']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="featured-package-image-fallback"><i class="fa-solid fa-ring"></i></div>
                                        <?php endif; ?>
                                        <span class="featured-package-service"><?= htmlspecialchars($package['service_name'] ?? 'Wedding Service'); ?></span>
                                        <?php if ($discount > 0): ?><span class="featured-package-discount"><?= rtrim(rtrim(number_format($discount, 2), '0'), '.'); ?>% OFF</span><?php endif; ?>
                                    </div>
                                    <div class="featured-package-body">
                                        <div class="featured-package-rating"><i class="fa-solid fa-star"></i> <?= number_format((float) $package['rating'], 1); ?> <small>(<?= (int) $package['review_count']; ?>)</small></div>
                                        <h3><?= htmlspecialchars($package['package_name'] ?: $package['provider_name']); ?></h3>
                                        <p class="featured-package-provider"><i class="fa-solid fa-building-user"></i> <?= htmlspecialchars($package['provider_name']); ?></p>
                                        <p class="featured-package-details"><?= htmlspecialchars($package['package_details'] ?: 'A curated wedding service package for your special day.'); ?></p>
                                        <div class="featured-package-price">
                                            <?php if ($discount > 0): ?><del>৳<?= number_format($original_price, 0); ?></del><?php endif; ?>
                                            <strong>৳<?= number_format($final_price, 0); ?></strong>
                                            <?php if ($discount > 0): ?><span>after discount</span><?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="button" class="featured-package-nav featured-package-next" data-package-next aria-label="Next package"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
                <div class="featured-package-dots" data-package-dots aria-label="Package pagination"></div>
            <?php else: ?>
                <div class="featured-packages-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    <strong>No active service packages yet.</strong>
                    <span>New packages added by Event Managers will appear here automatically.</span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="statistics" class="statistics-section">
        <div class="container">
            <div class="statistics-heading text-center" data-aos="fade-up">
                <span class="statistics-label"><i class="fa-solid fa-chart-simple"></i> Smart Matrimony at a Glance</span>
                <h2>Growing with <span>Real Members &amp; Services.</span></h2>
                <p>These live counters are connected directly to the Smart Matrimony database and grow with the platform.</p>
            </div>

            <div class="statistics-panel statistics-five" data-aos="fade-up">
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $registered_members; ?>">0</strong><span>Registered Members</span></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-id-card"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $completed_profiles; ?>">0</strong><span>Profiles Completed</span></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-person"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $male_profiles; ?>">0</strong><span>Male Profiles</span></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-person-dress"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $female_profiles; ?>">0</strong><span>Female Profiles</span></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-gift"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $service_packages; ?>">0</strong><span>Service Packages</span></div>
                </div>
            </div>

            <div class="statistics-note" data-aos="fade-up">
                <i class="fa-solid fa-circle-check"></i>
                <span>Counts reflect active user accounts, available profiles and active service provider packages in the current database.</span>
            </div>
        </div>
    </section>

    <!-- =========================
         HOW IT WORKS SECTION
    ========================== -->
    <section
        id="how-it-works"
        class="how-it-works-section"
    >

        <div class="container">

            <!-- Section Heading -->

            <div
                class="how-it-works-heading text-center"
                data-aos="fade-up"
            >

                <span class="how-it-works-label">

                    <i class="fa-solid fa-route"></i>

                    How It Works

                </span>

                <h2>

                    A Simple Path to a
                    <span>Meaningful Connection.</span>

                </h2>

                <p>

                    Smart Matrimony brings the important parts of
                    your matrimonial journey together in a simple,
                    organized and respectful way.

                </p>

            </div>


            <!-- =========================
                 PROCESS FLOW
            ========================== -->

            <div class="how-it-works-flow how-it-works-five">
                <div class="process-step" data-aos="fade-up" data-aos-delay="50">
                    <div class="process-icon-wrap"><div class="process-icon"><i class="fa-solid fa-user-plus"></i></div><span class="process-number">01</span></div>
                    <h3>Create Your Account</h3>
                    <p>Register with your basic information and begin your Smart Matrimony journey.</p>
                </div>
                <div class="process-connector" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="process-step" data-aos="fade-up" data-aos-delay="100">
                    <div class="process-icon-wrap"><div class="process-icon"><i class="fa-solid fa-sliders"></i></div><span class="process-number">02</span></div>
                    <h3>Set Your Preferences</h3>
                    <p>Define the qualities, age, education, location and other preferences that matter to you.</p>
                </div>
                <div class="process-connector" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="process-step" data-aos="fade-up" data-aos-delay="150">
                    <div class="process-icon-wrap"><div class="process-icon"><i class="fa-solid fa-heart-circle-check"></i></div><span class="process-number">03</span></div>
                    <h3>Discover Connections</h3>
                    <p>Explore potential connections using profiles, preferences and compatibility information.</p>
                </div>
                <div class="process-connector" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="process-step" data-aos="fade-up" data-aos-delay="200">
                    <div class="process-icon-wrap"><div class="process-icon"><i class="fa-solid fa-comments"></i></div><span class="process-number">04</span></div>
                    <h3>Connect Respectfully</h3>
                    <p>Send a chat request and communicate respectfully when a connection is accepted.</p>
                </div>
                <div class="process-connector" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="process-step" data-aos="fade-up" data-aos-delay="250">
                    <div class="process-icon-wrap"><div class="process-icon"><i class="fa-solid fa-calendar-check"></i></div><span class="process-number">05</span></div>
                    <h3>Book Wedding Packages</h3>
                    <p>Explore wedding services and book the packages that fit your celebration after signing in.</p>
                </div>
            </div>

            <!-- Bottom Message -->

            <div
                class="how-it-works-note"
                data-aos="fade-up"
            >

                <div class="how-note-icon">

                    <i class="fa-solid fa-heart"></i>

                </div>

                <div class="how-note-content">

                    <strong>
                        Take your time. Choose with care.
                    </strong>

                    <span>
                        A matrimonial journey is about finding
                        compatibility, trust and mutual respect.
                    </span>

                </div>

            </div>

        </div>

    </section>

        <!-- =========================
         SMART MATCHING
    ========================== -->
    <section id="smart-matching" class="home-feature-section matching-showcase-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <span class="premium-section-label"><i class="fa-solid fa-heart-circle-check"></i> Smart Matching</span>
                    <h2 class="premium-section-title">Meet people who <span>match what matters.</span></h2>
                    <p class="premium-section-text">Smart Matrimony brings profile information and partner preferences together to help users explore more relevant connections.</p>
                    <div class="matching-points">
                        <div><i class="fa-solid fa-circle-check"></i><span>Preference-based compatibility</span></div>
                        <div><i class="fa-solid fa-circle-check"></i><span>Multiple profile factors considered</span></div>
                        <div><i class="fa-solid fa-circle-check"></i><span>Clear match details for informed decisions</span></div>
                    </div>
                    <a href="<?= BASE_URL; ?>register.php" class="section-cta"><i class="fa-solid fa-user-plus"></i> Start Exploring</a>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="matching-visual">
                        <div class="matching-visual-top"><span><i class="fa-solid fa-sparkles"></i> Example Match View</span><small>Illustration</small></div>
                        <div class="matching-profile-row">
                            <div class="matching-avatar"><i class="fa-solid fa-person"></i></div>
                            <div class="matching-profile-copy"><strong>Compatible Profile</strong><span>Preferences aligned across multiple factors</span></div>
                            <div class="matching-score"><strong>92%</strong><small>Match</small></div>
                        </div>
                        <div class="matching-bars">
                            <div><span>Age</span><b><i style="width:92%"></i></b></div>
                            <div><span>Education</span><b><i style="width:86%"></i></b></div>
                            <div><span>Location</span><b><i style="width:90%"></i></b></div>
                            <div><span>Lifestyle</span><b><i style="width:82%"></i></b></div>
                        </div>
                        <div class="matching-visual-footer"><i class="fa-solid fa-circle-info"></i> Example visualization — actual match scores are generated from user data and preferences.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================
         PROFILE COMPLETION
    ========================== -->
    <section id="profile-completion" class="profile-showcase-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 order-lg-1 order-2" data-aos="fade-right">
                    <div class="profile-illustration">
                        <div class="profile-illustration-glow"></div>
                        <div class="profile-mini-card profile-mini-card-back"><span></span><span></span><span></span></div>
                        <div class="profile-main-card">
                            <div class="profile-main-top"><span>My Profile</span><i class="fa-solid fa-ellipsis"></i></div>
                            <div class="profile-avatar-large"><i class="fa-solid fa-user"></i><span class="profile-online-dot"></span></div>
                            <div class="profile-name-lines"><b></b><span></span></div>
                            <div class="profile-progress-head"><span>Profile completeness</span><strong>Ready to grow</strong></div>
                            <div class="profile-progress"><i></i></div>
                            <div class="profile-check-grid">
                                <span><i class="fa-solid fa-circle-check"></i> Personal</span>
                                <span><i class="fa-solid fa-circle-check"></i> Education</span>
                                <span><i class="fa-solid fa-circle-check"></i> Profession</span>
                                <span><i class="fa-solid fa-circle-check"></i> Preferences</span>
                            </div>
                        </div>
                        <div class="profile-floating-badge"><i class="fa-solid fa-shield-heart"></i><span>Privacy focused</span></div>
                    </div>
                </div>
                <div class="col-lg-6 order-lg-2 order-1" data-aos="fade-left">
                    <span class="premium-section-label"><i class="fa-solid fa-id-card"></i> Build a Complete Profile</span>
                    <h2 class="premium-section-title">A thoughtful profile creates a <span>better first impression.</span></h2>
                    <p class="premium-section-text">Add the details that help others understand you — from education and profession to lifestyle and partner preferences.</p>
                    <div class="profile-benefit-list">
                        <div><span>01</span><div><strong>Tell your story</strong><p>Share meaningful information about your background and lifestyle.</p></div></div>
                        <div><span>02</span><div><strong>Set clear preferences</strong><p>Describe the qualities and details you value in a partner.</p></div></div>
                        <div><span>03</span><div><strong>Keep it respectful</strong><p>Choose the information you are comfortable making visible.</p></div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================
         PRIVACY & SAFETY
    ========================== -->
    <section id="privacy-safety" class="privacy-safety-section">
        <div class="container">
            <div class="privacy-heading text-center" data-aos="fade-up">
                <span class="premium-section-label"><i class="fa-solid fa-shield-heart"></i> Privacy &amp; Safety</span>
                <h2 class="premium-section-title">Your personal journey deserves <span>respect and control.</span></h2>
                <p class="premium-section-text">Smart Matrimony is designed around secure access, profile privacy and respectful communication.</p>
            </div>
            <div class="privacy-grid">
                <article class="privacy-card" data-aos="fade-up" data-aos-delay="50"><div><i class="fa-solid fa-lock"></i></div><h3>Secure Access</h3><p>Authentication and account controls help keep your account protected.</p></article>
                <article class="privacy-card" data-aos="fade-up" data-aos-delay="100"><div><i class="fa-solid fa-eye-slash"></i></div><h3>Profile Privacy</h3><p>Profile visibility settings give users control over who can discover their profile.</p></article>
                <article class="privacy-card" data-aos="fade-up" data-aos-delay="150"><div><i class="fa-solid fa-user-shield"></i></div><h3>Photo Controls</h3><p>Photo visibility can be managed separately according to available privacy settings.</p></article>
                <article class="privacy-card" data-aos="fade-up" data-aos-delay="200"><div><i class="fa-solid fa-comments"></i></div><h3>Respectful Communication</h3><p>Connection and chat flows are structured to encourage considerate communication.</p></article>
            </div>
        </div>
    </section>

    <!-- =========================
         FEATURED / RECENTLY JOINED
    ========================== -->
    <section id="recent-members" class="recent-members-section">
        <div class="container">
            <div class="recent-heading" data-aos="fade-up">
                <div>
                    <span class="premium-section-label"><i class="fa-solid fa-users"></i> Recently Joined</span>
                    <h2 class="premium-section-title">Meet some of our <span>new members.</span></h2>
                    <p class="premium-section-text">Public profiles are shown without photos or private contact details. Create an account to explore the full experience.</p>
                </div>
                <a href="<?= BASE_URL; ?>register.php" class="section-cta section-cta-outline"><i class="fa-solid fa-user-plus"></i> Join Free</a>
            </div>

            <?php if ($featured_profiles): ?>
                <div class="recent-members-grid">
                    <?php foreach ($featured_profiles as $featured): ?>
                        <?php
                            $featured_age = null;
                            if (!empty($featured['date_of_birth'])) {
                                try { $featured_age = (new DateTime($featured['date_of_birth']))->diff(new DateTime('today'))->y; } catch (Throwable $e) { $featured_age = null; }
                            }
                            $featured_name = trim(($featured['first_name'] ?? '') . ' ' . ($featured['last_name'] ?? ''));
                            $featured_location = trim(($featured['area'] ?? '') . (($featured['area'] ?? '') && ($featured['country'] ?? '') ? ', ' : '') . ($featured['country'] ?? ''));
                        ?>
                        <article class="recent-member-card" data-aos="fade-up">
                            <div class="recent-member-top">
                                <div class="recent-member-avatar <?= strtolower($featured['gender'] ?? '') === 'female' ? 'female' : 'male'; ?>">
                                    <i class="fa-solid <?= strtolower($featured['gender'] ?? '') === 'female' ? 'fa-person-dress' : 'fa-person'; ?>"></i>
                                </div>
                                <?php if (($featured['verification_status'] ?? '') === 'Verified'): ?><span class="recent-verified"><i class="fa-solid fa-circle-check"></i> Verified</span><?php endif; ?>
                            </div>
                            <h3><?= htmlspecialchars($featured_name ?: 'Smart Matrimony Member'); ?></h3>
                            <p class="recent-member-meta">
                                <?= htmlspecialchars($featured['gender'] ?? 'Member'); ?>
                                <?php if ($featured_age !== null): ?> · <?= $featured_age; ?> yrs<?php endif; ?>
                            </p>
                            <div class="recent-member-details">
                                <?php if ($featured_location): ?><span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($featured_location); ?></span><?php endif; ?>
                                <?php if (!empty($featured['profession'])): ?><span><i class="fa-solid fa-briefcase"></i><?= htmlspecialchars($featured['profession']); ?></span><?php endif; ?>
                                <?php if (!empty($featured['highest_education'])): ?><span><i class="fa-solid fa-graduation-cap"></i><?= htmlspecialchars($featured['highest_education']); ?></span><?php endif; ?>
                            </div>
                            <a href="<?= BASE_URL; ?>login.php" class="recent-member-action">Login to explore <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="recent-empty" data-aos="fade-up"><i class="fa-solid fa-users"></i><h3>New member profiles are on the way.</h3><p>As public profiles are completed, they will appear here.</p></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- =========================
         SEARCH PREVIEW
    ========================== -->
    <section id="search-preview" class="search-preview-section">
        <div class="container">
            <div class="search-preview-card" data-aos="fade-up">
                <div class="search-preview-copy">
                    <span class="premium-section-label"><i class="fa-solid fa-magnifying-glass"></i> Find a Match</span>
                    <h2>Start with the preferences that <span>matter to you.</span></h2>
                    <p>Search through partner preferences, location and profile details after creating your Smart Matrimony account.</p>
                    <a href="<?= BASE_URL; ?>register.php" class="section-cta"><i class="fa-solid fa-user-plus"></i> Create Your Profile</a>
                </div>
                <div class="search-preview-ui" aria-hidden="true">
                    <div class="search-ui-top"><span>Partner Search</span><i class="fa-solid fa-sliders"></i></div>
                    <div class="search-ui-fields">
                        <div><small>Preferred Gender</small><strong><i class="fa-solid fa-user-group"></i> Select</strong></div>
                        <div><small>Age Range</small><strong><i class="fa-solid fa-calendar-days"></i> 24 — 30</strong></div>
                        <div><small>Location</small><strong><i class="fa-solid fa-location-dot"></i> Choose area</strong></div>
                        <div><small>Education</small><strong><i class="fa-solid fa-graduation-cap"></i> Select</strong></div>
                    </div>
                    <div class="search-ui-button"><i class="fa-solid fa-magnifying-glass"></i> Find Compatible Profiles</div>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================
         WEDDING SERVICES
    ========================== -->
    <section id="home-services" class="home-services-section">
        <div class="container">
            <div class="home-services-heading" data-aos="fade-up">
                <div>
                    <span class="home-services-label"><i class="fa-solid fa-layer-group"></i> Smart Wedding Services</span>
                    <h2>Everything you need for your <span>special day.</span></h2>
                    <p>Explore available wedding services and discover packages from our current provider catalog.</p>
                </div>
                <div class="home-services-count">
                    <strong><?= count($home_services); ?></strong>
                    <span>Service Categories</span>
                </div>
            </div>

            <div class="home-service-grid">
                <?php foreach ($home_services as $service): ?>
                    <?php $service_id = (int) $service['service_id']; ?>
                    <?php $package_count = count($home_service_packages[$service_id] ?? []); ?>
                    <button type="button" class="home-service-card" data-service-id="<?= $service_id; ?>" data-service-name="<?= htmlspecialchars($service['service_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="home-service-icon"><i class="fa-solid <?= htmlspecialchars($home_service_icons[$service['service_name']] ?? 'fa-heart'); ?>"></i></span>
                        <span class="home-service-copy">
                            <strong><?= htmlspecialchars($service['service_name']); ?></strong>
                            <small><?= htmlspecialchars($service['description'] ?? 'Wedding-related service'); ?></small>
                        </span>
                        <span class="home-service-meta">
                            <?= $package_count; ?> <?= $package_count === 1 ? 'package' : 'packages'; ?>
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="home-services-note">
                <span><i class="fa-solid fa-shield-heart"></i></span>
                <div><strong>Browse freely, book securely.</strong><p>Anyone can explore service packages. Login or registration is required when adding a package to your service cart.</p></div>
            </div>
        </div>
    </section>


    <!-- =========================
         FAQ
    ========================== -->
    <section id="faq" class="faq-section">
        <div class="container">
            <div class="faq-heading text-center" data-aos="fade-up">
                <span class="premium-section-label"><i class="fa-solid fa-circle-question"></i> Frequently Asked Questions</span>
                <h2 class="premium-section-title">A few things you may <span>want to know.</span></h2>
            </div>
            <div class="faq-list" data-aos="fade-up">
                <details><summary>How does Smart Matrimony matching work?<i class="fa-solid fa-chevron-down"></i></summary><p>Matching uses profile information and partner preferences to calculate compatibility across supported factors. Users can review match information before deciding how to proceed.</p></details>
                <details><summary>Can I browse wedding service packages before registering?<i class="fa-solid fa-chevron-down"></i></summary><p>Yes. Visitors can explore available service categories and packages. Login or registration is required when adding a package to the service cart.</p></details>
                <details><summary>Can I control my profile visibility?<i class="fa-solid fa-chevron-down"></i></summary><p>Yes. Available privacy controls include profile visibility and separate photo visibility settings.</p></details>
                <details><summary>Can I bookmark profiles?<i class="fa-solid fa-chevron-down"></i></summary><p>Logged-in users can use the bookmark feature to save profiles they may want to revisit.</p></details>
                <details><summary>How do I communicate with another member?<i class="fa-solid fa-chevron-down"></i></summary><p>Users can use the interest and chat request flow provided by the platform, with conversations available after the required connection step.</p></details>
            </div>
        </div>
    </section>

    <!-- =========================
         FINAL CTA SECTION
    ========================== -->
    <section
        id="final-cta"
        class="final-cta-section"
    >

        <div class="container">

            <div
                class="final-cta-card"
                data-aos="fade-up"
            >

                <!-- Decorative Elements -->

                <div class="final-cta-glow final-cta-glow-left"></div>

                <div class="final-cta-glow final-cta-glow-right"></div>


                <div class="final-cta-content">

                    <span class="final-cta-label">

                        <i class="fa-solid fa-heart"></i>

                        Begin with Intention

                    </span>


                    <h2>

                        Ready to Begin Your
                        <span>Matrimonial Journey?</span>

                    </h2>


                    <p>

                        Discover meaningful connections in a
                        respectful, secure and family-friendly
                        environment.

                    </p>


                    <div class="final-cta-buttons">

                        <!-- CREATE ACCOUNT -->

                        <a
                            href="register.php"
                            class="final-cta-primary"
                        >

                            <i class="fa-solid fa-user-plus"></i>

                            <span>
                                Create Account
                            </span>

                        </a>


                        <!-- LOGIN -->

                        <a
                            href="login.php"
                            class="final-cta-secondary"
                        >

                            <i class="fa-solid fa-right-to-bracket"></i>

                            <span>
                                Login
                            </span>

                        </a>

                    </div>


                    <div class="final-cta-trust">

                        <span>

                            <i class="fa-solid fa-shield-heart"></i>

                            Secure

                        </span>

                        <span>

                            <i class="fa-solid fa-heart"></i>

                            Respectful

                        </span>

                        <span>

                            <i class="fa-solid fa-users"></i>

                            Family-Friendly

                        </span>

                    </div>

                </div>

            </div>

        </div>

    </section>

</main>

<script>
(function () {
    const counters = document.querySelectorAll('[data-stat-value]');
    if (!counters.length) return;
    const run = (el) => {
        const target = Number(el.dataset.statValue || 0);
        const duration = 900;
        const start = performance.now();
        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased).toLocaleString();
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => { if (entry.isIntersecting) { run(entry.target); obs.unobserve(entry.target); } });
    }, { threshold: 0.35 });
    counters.forEach(counter => observer.observe(counter));
})();
</script>

<!-- =========================
     SERVICE PACKAGE MODAL
========================== -->
<div class="service-package-modal" id="servicePackageModal" aria-hidden="true">
    <div class="service-package-backdrop" data-service-modal-close></div>
    <div class="service-package-dialog" role="dialog" aria-modal="true" aria-labelledby="servicePackageTitle">
        <button type="button" class="service-package-close" data-service-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        <div class="service-package-header">
            <span class="home-services-label"><i class="fa-solid fa-layer-group"></i> Available Packages</span>
            <h2 id="servicePackageTitle">Wedding Service</h2>
            <p id="servicePackageDescription">Explore available packages and provider details.</p>
        </div>
        <div class="service-package-body">
            <button type="button" class="service-package-nav service-package-prev" id="servicePackagePrev" aria-label="Previous package"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="service-package-card-wrap" id="servicePackageContent"></div>
            <button type="button" class="service-package-nav service-package-next" id="servicePackageNext" aria-label="Next package"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
        <div class="service-package-footer">
            <span id="servicePackageCounter"></span>
            <a href="<?= BASE_URL; ?>login.php" class="service-login-link" id="serviceLoginLink"><i class="fa-solid fa-right-to-bracket"></i> Login to continue</a>
        </div>
    </div>
</div>

<script>
    window.SMART_SERVICE_DATA = <?= json_encode($home_services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.SMART_SERVICE_PACKAGES = <?= json_encode($home_service_packages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.SMART_SERVICE_ICONS = <?= json_encode($home_service_icons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.SMART_SERVICE_LOGGED_IN = <?= $home_is_logged_in ? 'true' : 'false'; ?>;
    window.SMART_SERVICE_BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.SMART_SERVICE_CART_CSRF = <?= json_encode($home_is_logged_in ? ($_SESSION['cart_csrf'] ?? '') : ''); ?>;
</script>
<script src="<?= BASE_URL; ?>assets/js/home-services.js?v=1"></script>
<script src="<?= BASE_URL; ?>assets/js/home-packages.js?v=1"></script>

<?php include 'includes/footer.php'; ?>
