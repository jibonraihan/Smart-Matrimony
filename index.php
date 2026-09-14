<?php

$page_css = 'assets/css/index.css';

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   DYNAMIC HOMEPAGE STATISTICS
========================================================= */

$active_members = 0;
$verified_profiles = 0;
$active_matches = 0;
$preference_profiles = 0;


/* Active registered members */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'User'
     AND account_status = 'Active'"
);

if ($stmt) {

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $active_members = (int)($row['total'] ?? 0);

    }

    mysqli_stmt_close($stmt);

}


/* Verified profiles */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM user_profiles
     WHERE verification_status = 'Verified'"
);

if ($stmt) {

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $verified_profiles = (int)($row['total'] ?? 0);

    }

    mysqli_stmt_close($stmt);

}


/* Active accepted matches */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM matches
     WHERE status = 'Accepted'
     AND relationship_active = 1"
);

if ($stmt) {

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $active_matches = (int)($row['total'] ?? 0);

    }

    mysqli_stmt_close($stmt);

}


/* Users with saved search preferences */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM search_preferences"
);

if ($stmt) {

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $preference_profiles = (int)($row['total'] ?? 0);

    }

    mysqli_stmt_close($stmt);

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
    SELECT sp.provider_id, sp.service_id, sp.provider_name, sp.package_name,
           sp.package_details, sp.image, sp.price, sp.location, sp.rating, sp.review_count
    FROM service_providers sp
    WHERE sp.status = 'Active'
    ORDER BY sp.service_id, sp.rating DESC, sp.review_count DESC, sp.provider_id DESC
");
if ($home_package_result) {
    while ($row = mysqli_fetch_assoc($home_package_result)) {
        $home_service_packages[(int) $row['service_id']][] = $row;
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

                            <i class="fa-solid fa-right-to-bracket"></i>

                        </div>

                        <div class="feature-card-number">
                            06
                        </div>

                        <h3>
                            Google &amp; Facebook Login
                        </h3>

                        <p>

                            Convenient authentication through
                            supported Google and Facebook
                            accounts.

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
         DYNAMIC STATISTICS
    ========================== -->
    <section
        id="statistics"
        class="statistics-section"
    >

        <div class="container">

            <div
                class="statistics-heading text-center"
                data-aos="fade-up"
            >

                <span class="statistics-label">

                    <i class="fa-solid fa-chart-simple"></i>

                    Smart Matrimony at a Glance

                </span>

                <h2>

                    Growing with
                    <span>Real Connections.</span>

                </h2>

                <p>

                    These numbers are connected directly to the
                    Smart Matrimony database and update as the
                    platform grows.

                </p>

            </div>


            <div
                class="statistics-panel"
                data-aos="fade-up"
            >

                <!-- Active Members -->

                <div class="stat-item">

                    <div class="stat-icon">

                        <i class="fa-solid fa-users"></i>

                    </div>

                    <div class="stat-content">

                        <strong>
                            <?= number_format($active_members) ?>
                        </strong>

                        <span>
                            Active Members
                        </span>

                    </div>

                </div>


                <!-- Verified Profiles -->

                <div class="stat-item">

                    <div class="stat-icon">

                        <i class="fa-solid fa-user-check"></i>

                    </div>

                    <div class="stat-content">

                        <strong>
                            <?= number_format($verified_profiles) ?>
                        </strong>

                        <span>
                            Verified Profiles
                        </span>

                    </div>

                </div>


                <!-- Active Matches -->

                <div class="stat-item">

                    <div class="stat-icon">

                        <i class="fa-solid fa-heart-circle-check"></i>

                    </div>

                    <div class="stat-content">

                        <strong>
                            <?= number_format($active_matches) ?>
                        </strong>

                        <span>
                            Active Matches
                        </span>

                    </div>

                </div>


                <!-- Preference Profiles -->

                <div class="stat-item">

                    <div class="stat-icon">

                        <i class="fa-solid fa-sliders"></i>

                    </div>

                    <div class="stat-content">

                        <strong>
                            <?= number_format($preference_profiles) ?>
                        </strong>

                        <span>
                            Preference Profiles
                        </span>

                    </div>

                </div>

            </div>


            <div
                class="statistics-note"
                data-aos="fade-up"
            >

                <i class="fa-solid fa-circle-info"></i>

                <span>
                    Statistics are generated from the current
                    Smart Matrimony database.
                </span>

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

            <div class="how-it-works-flow">


                <!-- STEP 01 -->

                <div
                    class="process-step"
                    data-aos="fade-up"
                    data-aos-delay="50"
                >

                    <div class="process-icon-wrap">

                        <div class="process-icon">

                            <i class="fa-solid fa-user-plus"></i>

                        </div>

                        <span class="process-number">
                            01
                        </span>

                    </div>

                    <h3>
                        Create Your Account
                    </h3>

                    <p>

                        Register with your basic information
                        and start your Smart Matrimony journey.

                    </p>

                </div>


                <!-- CONNECTOR -->

                <div
                    class="process-connector"
                    aria-hidden="true"
                >

                    <i class="fa-solid fa-arrow-right"></i>

                </div>


                <!-- STEP 02 -->

                <div
                    class="process-step"
                    data-aos="fade-up"
                    data-aos-delay="100"
                >

                    <div class="process-icon-wrap">

                        <div class="process-icon">

                            <i class="fa-solid fa-sliders"></i>

                        </div>

                        <span class="process-number">
                            02
                        </span>

                    </div>

                    <h3>
                        Set Your Preferences
                    </h3>

                    <p>

                        Define the qualities, age, education,
                        location and other preferences that
                        matter to you.

                    </p>

                </div>


                <!-- CONNECTOR -->

                <div
                    class="process-connector"
                    aria-hidden="true"
                >

                    <i class="fa-solid fa-arrow-right"></i>

                </div>


                <!-- STEP 03 -->

                <div
                    class="process-step"
                    data-aos="fade-up"
                    data-aos-delay="150"
                >

                    <div class="process-icon-wrap">

                        <div class="process-icon">

                            <i class="fa-solid fa-heart-circle-check"></i>

                        </div>

                        <span class="process-number">
                            03
                        </span>

                    </div>

                    <h3>
                        Discover Connections
                    </h3>

                    <p>

                        Explore potential connections using
                        profile information, preferences and
                        matching relationships.

                    </p>

                </div>


                <!-- CONNECTOR -->

                <div
                    class="process-connector"
                    aria-hidden="true"
                >

                    <i class="fa-solid fa-arrow-right"></i>

                </div>


                <!-- STEP 04 -->

                <div
                    class="process-step"
                    data-aos="fade-up"
                    data-aos-delay="200"
                >

                    <div class="process-icon-wrap">

                        <div class="process-icon">

                            <i class="fa-solid fa-comments"></i>

                        </div>

                        <span class="process-number">
                            04
                        </span>

                    </div>

                    <h3>
                        Connect Respectfully
                    </h3>

                    <p>

                        Send a chat request and communicate
                        respectfully when a connection is
                        accepted.

                    </p>

                </div>

            </div>


            <!-- Bottom Message -->

            

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

<?php include 'includes/footer.php'; ?>
