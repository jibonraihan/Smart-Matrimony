<?php

$page_css = 'assets/css/index.css?v=contract-provider-1';

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/language.php';
$show_initial_language_picker = empty($_SESSION['sm_language_picker_seen']);
if ($show_initial_language_picker) {
    $_SESSION['sm_language_picker_seen'] = true;
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
    SELECT service_id, service_name, service_name_bn, description, description_bn, icon_class, service_prefix
    FROM services
    WHERE is_active = 1
    ORDER BY service_id
");
if ($home_service_result) {
    while ($row = mysqli_fetch_assoc($home_service_result)) {
        $home_services[] = $row;
    }
}

$home_service_packages = [];
$home_package_result = mysqli_query($conn, "
    SELECT sp.provider_id, sp.package_code, sp.service_id, s.service_name, sp.provider_name, sp.package_name,
           sp.package_details, sp.image, sp.price, sp.discount_percent, sp.location, sp.rating, sp.review_count,
           sp.manager_id, m.user_id AS active_manager_id,
           CONCAT_WS(' ', NULLIF(TRIM(m.first_name), ''), NULLIF(TRIM(m.last_name), '')) AS manager_full_name,
           mp.date_of_birth AS manager_date_of_birth, mp.religion AS manager_religion, sf.profile_image AS manager_profile_image
    FROM service_providers sp
    INNER JOIN services s ON s.service_id = sp.service_id AND s.is_active = 1
    LEFT JOIN users m ON m.user_id = sp.manager_id AND m.role = 'Manager' AND m.account_status = 'Active'
    LEFT JOIN (SELECT user_id, MAX(profile_id) AS profile_id FROM user_profiles GROUP BY user_id) latest_mp ON latest_mp.user_id = m.user_id
    LEFT JOIN user_profiles mp ON mp.profile_id = latest_mp.profile_id
    LEFT JOIN staff_profiles sf ON sf.user_id = m.user_id
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
$home_manager_directory = [];
foreach ($home_service_packages as $service_package_group) {
    foreach ($service_package_group as $package) {
        $home_featured_packages[] = $package;
        $home_max_discount = max($home_max_discount, (float) ($package['discount_percent'] ?? 0));

        $manager_id = (int) ($package['active_manager_id'] ?? 0);
        if ($manager_id <= 0) {
            continue;
        }

        if (!isset($home_manager_directory[$manager_id])) {
            $manager_dob = trim((string) ($package['manager_date_of_birth'] ?? ''));
            $manager_age = null;
            if ($manager_dob !== '' && $manager_dob !== '0000-00-00') {
                try {
                    $dob = new DateTimeImmutable($manager_dob);
                    $today = new DateTimeImmutable('today');
                    if ($dob <= $today) {
                        $manager_age = $dob->diff($today)->y;
                    }
                } catch (Throwable $e) {
                    $manager_age = null;
                }
            }

            $manager_photo = trim((string) ($package['manager_profile_image'] ?? ''));
            $manager_photo_path = __DIR__ . '/uploads/staff/' . basename($manager_photo);
            $manager_photo_url = ($manager_photo !== '' && is_file($manager_photo_path))
                ? BASE_URL . 'uploads/staff/' . rawurlencode(basename($manager_photo))
                : '';

            $home_manager_directory[$manager_id] = [
                'manager_id' => $manager_id,
                'manager_code' => 'SM-' . str_pad((string) $manager_id, 6, '0', STR_PAD_LEFT),
                'name' => trim((string) ($package['manager_full_name'] ?? '')) ?: 'Event Manager',
                'age' => $manager_age,
                'religion' => trim((string) ($package['manager_religion'] ?? '')),
                'photo' => $manager_photo_url,
                'packages' => [],
            ];
        }

        $home_manager_directory[$manager_id]['packages'][] = [
            'provider_id' => (int) $package['provider_id'],
            'package_code' => trim((string) ($package['package_code'] ?? '')),
            'package_name' => trim((string) ($package['package_name'] ?? '')) ?: trim((string) ($package['provider_name'] ?? 'Service package')),
            'service_name' => trim((string) ($package['service_name'] ?? 'Wedding Service')),
            'service_id' => (int) ($package['service_id'] ?? 0),
        ];
    }
}

/* Latest published review snippets for featured service packages. */
$home_package_reviews = [];
$home_review_result = mysqli_query($conn, "
    SELECT r.provider_id, r.rating, r.review_text, r.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS reviewer_name
    FROM service_reviews r
    INNER JOIN users u ON u.user_id = r.user_id
    INNER JOIN (
        SELECT provider_id, MAX(created_at) AS latest_review
        FROM service_reviews
        WHERE status = 'Published'
        GROUP BY provider_id
    ) latest ON latest.provider_id = r.provider_id AND latest.latest_review = r.created_at
    WHERE r.status = 'Published'
    ORDER BY r.created_at DESC
");
if ($home_review_result) {
    while ($review_row = mysqli_fetch_assoc($home_review_result)) {
        $pid = (int) $review_row['provider_id'];
        if (!isset($home_package_reviews[$pid])) {
            $home_package_reviews[$pid] = $review_row;
        }
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

/* Bengali display labels for the homepage service catalog. Keep original
   database names in data attributes so existing JavaScript lookups continue working. */
$home_service_names_bn = [
    'Photography' => 'ফটোগ্রাফি',
    'Catering' => 'খাবার পরিবেশন',
    'Decoration' => 'সাজসজ্জা',
    'Car Rental' => 'গাড়ি ভাড়া',
    'Makeup Artist' => 'মেকআপ শিল্পী',
    'Wedding Planner' => 'বিয়ে পরিকল্পনাকারী',
    'Convention Center' => 'কনভেনশন সেন্টার',
    'Resort / Lawn' => 'রিসোর্ট / লন',
    'Mehendi Artist' => 'মেহেদি শিল্পী',
    'Wedding Flowers' => 'বিয়ের ফুল',
    'Basor Ghor Decoration' => 'বাসরঘর সাজসজ্জা',
    'Wedding Dress' => 'বিয়ের পোশাক',
    'Groom Dress' => 'বরের পোশাক',
    'Bridal Accessories' => 'কনের সাজসজ্জার সামগ্রী',
    'Sound & Lighting' => 'সাউন্ড ও আলোকসজ্জা',
    'Invitation & Printing' => 'নিমন্ত্রণপত্র ও মুদ্রণ'
];

/* Bengali subtitles for the homepage service cards. Keep database descriptions
   untouched; show these translations only when Bengali is selected. */
$home_service_subtitles_bn = [
    'Photography' => 'বিয়ের ছবি তোলার সেবা',
    'Catering' => 'খাবার ও আপ্যায়ন সেবা',
    'Decoration' => 'বিয়ের সাজসজ্জার সেবা',
    'Car Rental' => 'বিয়ের জন্য গাড়ি ভাড়ার সেবা',
    'Makeup Artist' => 'কনের সাজসজ্জার সেবা',
    'Wedding Planner' => 'বিয়ের আয়োজন ও পরিকল্পনা',
    'Convention Center' => 'বিয়ের হল ও অনুষ্ঠানস্থল ভাড়ার ব্যবস্থা',
    'Resort / Lawn' => 'খোলা লন ও রিসোর্টে বিয়ের আয়োজন',
    'Mehendi Artist' => 'কনে ও পরিবারের মেহেদি সাজের সেবা',
    'Wedding Flowers' => 'বিয়ের তোড়া ও ফুলের সাজসজ্জা',
    'Basor Ghor Decoration' => 'বাসরঘর ও কক্ষ সাজানোর সেবা',
    'Wedding Dress' => 'কনের বিয়ের পোশাক ও সাজের সামগ্রী',
    'Groom Dress' => 'বরের বিয়ের পোশাক ও সাজের সামগ্রী',
    'Bridal Accessories' => 'গয়না, জুতা ও কনের সাজের উপকরণ',
    'Sound & Lighting' => 'বিয়ের সাউন্ড সিস্টেম ও আলোকসজ্জা',
    'Invitation & Printing' => 'বিয়ের নিমন্ত্রণপত্র তৈরি ও মুদ্রণ'
];

if (!function_exists('sm_bn_digits')) {
    function sm_bn_digits($value): string {
        return strtr((string) $value, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
    }
}

/* =========================================================
   RECENTLY JOINED PUBLIC PROFILES
   Only public, active user profiles are shown; photos and
   sensitive contact information are intentionally excluded.
========================================================= */
$featured_profiles = [];
$featured_result = mysqli_query($conn, "
    SELECT up.user_id, up.first_name, up.last_name, up.gender, up.date_of_birth,
           up.area, up.country, up.highest_education, up.profession,
           up.district, up.upazila,
           d.name_bn AS district_bn, d.name_en AS district_en,
           z.name_bn AS upazila_bn, z.name_en AS upazila_en,
           up.verification_status, up.created_at
    FROM user_profiles up
    INNER JOIN users u ON u.user_id = up.user_id
    LEFT JOIN districts d ON d.id = up.district_id
    LEFT JOIN upazilas z ON z.id = up.upazila_id
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

<!-- Session language chooser: shown on the first homepage visit in each session. -->
<div class="sm-language-picker-backdrop" id="smLanguagePicker" role="dialog" aria-modal="true" aria-labelledby="smLanguagePickerTitle" hidden>
    <div class="sm-language-picker-card">
        <span class="sm-language-picker-icon" aria-hidden="true"><i class="fa-solid fa-language"></i></span>
        <h2 id="smLanguagePickerTitle"><?= sm_text('Choose Your Language', 'ভাষা নির্বাচন করুন'); ?></h2>
        <p><?= sm_text('আপনার পছন্দের ভাষা নির্বাচন করুন', 'Choose your preferred language'); ?></p>
        <div class="sm-language-picker-options">
            <form method="post" action="<?= BASE_URL; ?>set_language.php">
                <input type="hidden" name="language" value="bn">
                <input type="hidden" name="redirect" value="index.php">
                <button type="submit" class="sm-language-choice">বাংলা</button>
            </form>
            <form method="post" action="<?= BASE_URL; ?>set_language.php">
                <input type="hidden" name="language" value="en">
                <input type="hidden" name="redirect" value="index.php">
                <button type="submit" class="sm-language-choice sm-language-choice-secondary">English</button>
            </form>
        </div>
    </div>
</div>
<style>
.sm-language-picker-backdrop{position:fixed;inset:0;z-index:3000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(12,28,38,.62);backdrop-filter:blur(5px)}
.sm-language-picker-backdrop[hidden]{display:none}
.sm-language-picker-card{width:min(100%,420px);padding:30px 26px 26px;text-align:center;background:#fff;border-radius:22px;box-shadow:0 24px 80px rgba(0,0,0,.25)}
.sm-language-picker-icon{display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;margin-bottom:12px;border-radius:18px;background:#e6f4f1;color:#176b63;font-size:27px}
.sm-language-picker-card h2{margin:0 0 8px;color:#183c3a;font-size:24px;font-weight:750}
.sm-language-picker-card p{margin:0 0 22px;color:#667575}
.sm-language-picker-options{display:flex;gap:12px}
.sm-language-picker-options form{flex:1}
.sm-language-choice{width:100%;border:1px solid #176b63;border-radius:11px;padding:12px 14px;background:#176b63;color:#fff;font-weight:700;cursor:pointer}
.sm-language-choice-secondary{background:#fff;color:#176b63}
.sm-language-choice:focus-visible{outline:3px solid #e2b35b;outline-offset:3px}
@media(max-width:420px){.sm-language-picker-card{padding:25px 18px}.sm-language-picker-card h2{font-size:21px}}
</style>
<script>
(function(){
    var picker=document.getElementById('smLanguagePicker');
    if(!picker)return;
    document.querySelectorAll('[data-open-language-picker]').forEach(function(button){
        button.addEventListener('click',function(event){event.preventDefault();picker.hidden=false;});
    });
    <?php if ($show_initial_language_picker): ?>
    picker.hidden=false;
    <?php endif; ?>
})();
</script>

<main class="home-page">

    <!-- =========================
         HERO SECTION
    ========================== -->
    <section class="home-hero">
        <div class="container">
            <div class="row align-items-center hero-layout">
                <div class="col-lg-6">
                    <div class="hero-content" data-aos="fade-right">
                        <h1 class="hero-title">
                            <?= sm_text('Find Your Perfect', 'আপনার মনের মতো'); ?>
                            <span><?= sm_text('Life Partner.', 'জীবনসঙ্গী খুঁজুন।'); ?></span>
                        </h1>
                        <div class="hero-actions">
                            <a href="<?= BASE_URL; ?>register.php" class="btn btn-primary hero-btn-primary">
                                <i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>
                                <?= sm_text('Create Account', 'অ্যাকাউন্ট তৈরি করুন'); ?>
                                <i class="fa-solid fa-arrow-right hero-button-arrow" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL; ?>login.php" class="btn hero-btn-secondary">
                                <i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>
                                <?= sm_text('Login', 'লগইন'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-visual" data-aos="fade-left">
                        <span class="hero-art-shape hero-art-shape-one" aria-hidden="true"></span>
                        <span class="hero-art-shape hero-art-shape-two" aria-hidden="true"></span>
                        <span class="hero-art-dots hero-art-dots-left" aria-hidden="true"></span>
                        <span class="hero-art-dots hero-art-dots-right" aria-hidden="true"></span>
                        <span class="hero-arch" aria-hidden="true"></span>

                        <div class="hero-card">
                            <div class="hero-card-feature">
                                <div class="hero-feature-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></div>
                                <div class="hero-feature-copy">
                                    <strong><?= sm_text('Verified Profiles', 'যাচাইকৃত প্রোফাইল'); ?></strong>
                                    <p><?= sm_text('Real people, genuine intentions.', 'বাস্তব মানুষ, আন্তরিক উদ্দেশ্য।'); ?></p>
                                </div>
                                <i class="fa-solid fa-chevron-right hero-feature-chevron" aria-hidden="true"></i>
                            </div>
                            <div class="hero-card-feature">
                                <div class="hero-feature-icon hero-feature-icon-gold"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                                <div class="hero-feature-copy">
                                    <strong><?= sm_text('Privacy &amp; Security', 'গোপনীয়তা ও নিরাপত্তা'); ?></strong>
                                    <p><?= sm_text('Your information stays safe.', 'আপনার তথ্য সুরক্ষিত থাকে।'); ?></p>
                                </div>
                                <i class="fa-solid fa-chevron-right hero-feature-chevron" aria-hidden="true"></i>
                            </div>
                            <div class="hero-card-feature">
                                <div class="hero-feature-icon"><i class="fa-solid fa-heart" aria-hidden="true"></i></div>
                                <div class="hero-feature-copy">
                                    <strong><?= sm_text('Preference-based Matching', 'পছন্দ অনুযায়ী ম্যাচিং'); ?></strong>
                                    <p><?= sm_text('Find people who truly match you.', 'আপনার সঙ্গে মানানসই মানুষ খুঁজুন।'); ?></p>
                                </div>
                                <i class="fa-solid fa-chevron-right hero-feature-chevron" aria-hidden="true"></i>
                            </div>
                        </div>

                        <div class="hero-stats-shell">
                            <div class="hero-stats-card" aria-label="Platform statistics">
                                <div class="hero-stat">
                                    <strong><?= $current_language === 'en' ? number_format($registered_members) : sm_bn_digits(number_format($registered_members)); ?></strong>
                                    <span><?= sm_text('Active Members', 'সক্রিয় সদস্য'); ?></span>
                                </div>
                                <span class="hero-stat-divider" aria-hidden="true"></span>
                                <div class="hero-stat">
                                    <strong><?= $current_language === 'en' ? number_format($completed_profiles) : sm_bn_digits(number_format($completed_profiles)); ?></strong>
                                    <span><?= sm_text('Completed Profiles', 'সম্পূর্ণ প্রোফাইল'); ?></span>
                                </div>
                                <span class="hero-stat-divider" aria-hidden="true"></span>
                                <div class="hero-stat">
                                    <strong><?= $current_language === 'en' ? number_format($service_packages) : sm_bn_digits(number_format($service_packages)); ?></strong>
                                    <span><?= sm_text('Available Packages', 'উপলভ্য প্যাকেজ'); ?></span>
                                </div>
                            </div>
                            <div class="hero-stats-ornament" aria-hidden="true"><span></span><i class="fa-solid fa-heart"></i><span></span></div>
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

                    <?= sm_text('Islamic Guidance', 'ইসলামি নির্দেশনা'); ?>

                </span>

                <h2>

                    <?= sm_text('Marriage with', 'বিবাহে থাকুক'); ?>
                    <span><?= sm_text('Tranquility, Love &amp; Mercy.', 'প্রশান্তি, ভালোবাসা ও দয়া।'); ?></span>

                </h2>

                <p>

                    <?= sm_text(
                        'Islam gives marriage a meaningful place in life, reminding us of companionship, responsibility, affection and faith.',
                        'ইসলাম বিবাহকে জীবনের এক অর্থবহ বন্ধন হিসেবে তুলে ধরে, যেখানে রয়েছে সঙ্গ, দায়িত্ব, ভালোবাসা ও বিশ্বাস।'
                    ); ?>

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
                                    <?= sm_text('From the Quran', 'আল-কুরআন থেকে'); ?>
                                </span>

                                <h3>
                                    <?= sm_text('Guidance for Marriage', 'বিবাহ বিষয়ে নির্দেশনা'); ?>
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
                                    <?= sm_text('Tranquility, Love &amp; Mercy', 'প্রশান্তি, ভালোবাসা ও দয়া'); ?>
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
                                        <?= sm_text('অর্থ', 'Meaning'); ?>
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

                                    <?= sm_text('Surah Ar-Rum — 30:21', 'সূরা আর-রূম — ৩০:২১'); ?>

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
                                    <?= sm_text('Encouragement to Marry', 'বিবাহে উৎসাহ'); ?>
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
                                        <?= sm_text('অর্থ', 'Meaning'); ?>
                                    </span>

                                    তোমাদের মধ্যে যারা বিবাহহীন তাদের
                                    বিবাহ সম্পন্ন কর আর তোমাদের সৎ
                                    দাস-দাসীদেরও। তারা যদি নিঃস্ব হয়
                                    তাহলে আল্লাহ তাদেরকে নিজ অনুগ্রহে
                                    অভাবমুক্ত করে দেবেন।

                                </div>


                                <div class="content-reference">

                                    <i class="fa-solid fa-bookmark"></i>

                                    <?= sm_text('Surah An-Nur — 24:32', 'সূরা আন-নূর — ২৪:৩২'); ?>

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
                                    <?= sm_text('From the Sunnah', 'সুন্নাহ থেকে'); ?>
                                </span>

                                <h3>
                                    <?= sm_text('Prophetic Guidance', 'নবীজির নির্দেশনা'); ?>
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
                                    <?= sm_text('Guidance for Young People', 'যুবকদের জন্য নির্দেশনা'); ?>
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

                                    <?= sm_text('— রাসূলুল্লাহ ﷺ', '— Prophet Muhammad ﷺ'); ?>

                                </div>


                                <div class="content-reference">

                                    <i class="fa-solid fa-bookmark"></i>

                                    <?= sm_text('Sahih al-Bukhari — 5066', 'সহিহ আল-বুখারি — ৫০৬৬'); ?>

                                    <span class="reference-separator">
                                        •
                                    </span>

                                    <?= sm_text('Book 67, Hadith 4', 'কিতাব ৬৭, হাদিস ৪'); ?>

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
                                    <?= sm_text('Marriage &amp; Half of Faith', 'বিবাহ ও ঈমানের অর্ধেক'); ?>
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

                                    <?= sm_text('— রাসূলুল্লাহ ﷺ', '— Prophet Muhammad ﷺ'); ?>

                                </div>


                                <div class="content-reference">

                                    <i class="fa-solid fa-bookmark"></i>

                                    <?= sm_text('Shu\'ab al-Iman — 5486', 'শুআবুল ঈমান — ৫৪৮৬'); ?>

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

                    <?= sm_text('Why Smart Matrimony?', 'কেন স্মার্ট ম্যাট্রিমনি?'); ?>

                </span>

                <h2>

                    <?= sm_text('Thoughtful Features for a', 'সুবিবেচিত ফিচারে'); ?>
                    <span><?= sm_text('Better Matrimonial Experience.', 'আরও ভালো জীবনসঙ্গী খোঁজার অভিজ্ঞতা।'); ?></span>

                </h2>

                <p>

                    <?= sm_text(
                        'Everything is designed to make discovering, connecting and communicating with potential life partners more organized, respectful and secure.',
                        'সম্ভাব্য জীবনসঙ্গী খোঁজা, পরিচিত হওয়া ও যোগাযোগের প্রতিটি ধাপকে আরও সুশৃঙ্খল, সম্মানজনক ও নিরাপদ করতে সবকিছু সাজানো হয়েছে।'
                    ); ?>

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
                            <?= sm_text('Verified Profiles', 'যাচাইকৃত প্রোফাইল'); ?>
                        </h3>

                        <p>

                            <?= sm_text(
                                'Profile verification helps create a more trustworthy matrimonial environment.',
                                'প্রোফাইল যাচাইয়ের মাধ্যমে জীবনসঙ্গী খোঁজার পরিবেশ আরও বিশ্বস্ত হয়ে ওঠে।'
                            ); ?>

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
                            <?= sm_text('Smart Matching', 'স্মার্ট ম্যাচিং'); ?>
                        </h3>

                        <p>

                            <?= sm_text(
                                'Matching is designed around partner information and compatibility preferences.',
                                'জীবনসঙ্গীর তথ্য ও পারস্পরিক সামঞ্জস্যের পছন্দের ভিত্তিতে মিল খোঁজা হয়।'
                            ); ?>

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
                            <?= sm_text('Preference-Based Search', 'পছন্দ অনুযায়ী অনুসন্ধান'); ?>
                        </h3>

                        <p>

                            <?= sm_text(
                                'Search preferences help users define the qualities and details that matter to them.',
                                'অনুসন্ধানের পছন্দের মাধ্যমে ব্যবহারকারীরা তাঁদের কাছে গুরুত্বপূর্ণ বৈশিষ্ট্য ও তথ্য নির্ধারণ করতে পারেন।'
                            ); ?>

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
                            <?= sm_text('Privacy &amp; Security', 'গোপনীয়তা ও নিরাপত্তা'); ?>
                        </h3>

                        <p>

                            <?= sm_text(
                                'Privacy-focused profile and communication features help protect personal information.',
                                'গোপনীয়তাকে গুরুত্ব দেওয়া প্রোফাইল ও যোগাযোগের সুবিধাগুলো ব্যক্তিগত তথ্য সুরক্ষায় সহায়তা করে।'
                            ); ?>

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
                            <?= sm_text('Email Verification', 'ইমেইল যাচাইকরণ'); ?>
                        </h3>

                        <p>

                            <?= sm_text(
                                'Email verification helps confirm account ownership before an account becomes active.',
                                'অ্যাকাউন্ট সক্রিয় হওয়ার আগে ইমেইল যাচাইয়ের মাধ্যমে অ্যাকাউন্টের মালিকানা নিশ্চিত করা হয়।'
                            ); ?>

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
                            <?= sm_text('Wedding Services', 'বিয়ের সেবা'); ?>
                        </h3>

                        <p>

                            <?= sm_text(
                                'Explore photography, decoration, venues and other wedding services from available packages.',
                                'উপলভ্য প্যাকেজ থেকে ফটোগ্রাফি, সাজসজ্জা, অনুষ্ঠানস্থলসহ বিয়ের নানা সেবা বেছে নিন।'
                            ); ?>

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
                            <?= sm_text('Save Profiles', 'প্রোফাইল সংরক্ষণ'); ?>
                        </h3>

                        <p>

                            <?= sm_text(
                                'Bookmark profiles that you may want to revisit later during your search.',
                                'অনুসন্ধানের সময় পরে আবার দেখতে চাইলে পছন্দের প্রোফাইল সংরক্ষণ করে রাখুন।'
                            ); ?>

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
                            <?= sm_text('Respectful Communication', 'সম্মানজনক যোগাযোগ'); ?>
                        </h3>

                        <p>

                            <?= sm_text(
                                'Chat requests and conversations are structured around a more respectful communication flow.',
                                'চ্যাটের অনুরোধ ও কথোপকথন আরও সম্মানজনক যোগাযোগ নিশ্চিত করার জন্য সাজানো হয়েছে।'
                            ); ?>

                        </p>

                    </div>

                </div>

            </div>


            <!-- Feature Bottom Note -->

            

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
                    <span class="featured-offer-kicker"><?= sm_text('SPECIAL OFFER', 'বিশেষ অফার'); ?></span>
                    <strong><?= $home_max_discount > 0 ? 'Save up to ' . rtrim(rtrim(number_format($home_max_discount, 2), '0'), '.') . '% on selected packages' : 'Explore our latest wedding service packages'; ?></strong>
                    <small><?= sm_text('Discounts are managed by our Event Managers and applied automatically to package pricing.', 'ডিসকাউন্ট আমাদের ইভেন্ট ম্যানেজাররা নির্ধারণ করেন এবং প্যাকেজের দামে স্বয়ংক্রিয়ভাবে প্রয়োগ হয়।'); ?></small>
                </div>
            </div>

            <div class="featured-packages-heading" data-aos="fade-up">
                <div>
                    <span class="featured-packages-label"><i class="fa-solid fa-gift"></i> <?= sm_text('Featured Packages', 'বিশেষ প্যাকেজ'); ?></span>
                    <h2><?= sm_text('Plan Your Day with', 'আপনার বিশেষ দিন সাজান'); ?> <span><?= sm_text('Smart Wedding Services.', 'স্মার্ট ওয়েডিং সার্ভিসেসের সঙ্গে।'); ?></span></h2>
                    <p><?= sm_text('Explore active packages from our service providers. Swipe, use the arrows, or let the showcase move automatically.', 'আমাদের সেবা প্রদানকারীদের সক্রিয় প্যাকেজগুলো দেখুন। পাশে সরিয়ে, তীরচিহ্ন ব্যবহার করে অথবা স্বয়ংক্রিয়ভাবে প্যাকেজগুলো ঘুরে দেখুন।'); ?></p>
                </div>
                <div class="featured-packages-heading-actions">
                    <a class="featured-packages-view-all" href="#home-services">
                        <?= sm_text('View All Services', 'সব সেবা দেখুন'); ?> <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a class="featured-packages-budget-link" href="<?= BASE_URL; ?>services/budget_finder.php">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> <?= sm_text('Find by Budget', 'বাজেট অনুযায়ী খুঁজুন'); ?>
                    </a>
                </div>
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
                                <article class="featured-package-card" data-package-card data-provider-id="<?= (int) $package['provider_id']; ?>">
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
                                        <div class="featured-package-rating"><i class="fa-solid fa-star"></i> <?= number_format((float) $package['rating'], 1); ?> <?php if ((int) $package['review_count'] > 0): ?><small><?= (int) $package['review_count']; ?> <?= (int) $package['review_count'] === 1 ? 'review' : 'reviews'; ?></small><?php else: ?><small>No reviews yet</small><?php endif; ?></div>
                                        <?php if (!empty($package['package_code'])): ?><span class="package-code-badge"><?= htmlspecialchars($package['package_code']); ?></span><?php endif; ?>
                                        <h3><?= htmlspecialchars($package['package_name'] ?: $package['provider_name']); ?></h3>
                                        <p class="featured-package-provider"><i class="fa-solid fa-building-user"></i> <?= htmlspecialchars($package['provider_name']); ?></p>
                                        <p class="featured-package-details"><?= htmlspecialchars($package['package_details'] ?: 'A curated wedding service package for your special day.'); ?></p>
                                        <div class="featured-package-price">
                                            <?php if ($discount > 0): ?><del>৳<?= number_format($original_price, 0); ?></del><?php endif; ?>
                                            <strong>৳<?= number_format($final_price, 0); ?></strong>
                                            <?php if ($discount > 0): ?><span>after discount</span><?php endif; ?>
                                        </div>
                                        <?php $featured_review = $home_package_reviews[(int) $package['provider_id']] ?? null; ?>
                                        <?php if ($featured_review): ?>
                                            <a class="featured-package-review" href="<?= BASE_URL; ?>services/service_reviews.php?provider_id=<?= (int) $package['provider_id']; ?>">
                                                <span class="featured-package-review-meta"><i class="fa-solid fa-star"></i> <?= (int) $featured_review['rating']; ?>/5 · <?= htmlspecialchars($featured_review['reviewer_name'] ?: 'Verified customer'); ?></span>
                                                <?php if (trim((string) $featured_review['review_text']) !== ''): ?><span class="featured-package-review-text"><?= htmlspecialchars(mb_strimwidth(trim($featured_review['review_text']), 0, 105, '…', 'UTF-8')); ?></span><?php endif; ?>
                                                <span class="featured-package-review-more"><?= sm_text('Read reviews', 'রিভিউ পড়ুন'); ?> <i class="fa-solid fa-arrow-right"></i></span>
                                            </a>
                                        <?php else: ?>
                                            <a class="featured-package-review featured-package-review-empty" href="<?= BASE_URL; ?>services/service_reviews.php?provider_id=<?= (int) $package['provider_id']; ?>"><?= sm_text('No written reviews yet · View reviews', 'এখনো লিখিত রিভিউ নেই · রিভিউ দেখুন'); ?></a>
                                        <?php endif; ?>
                                        <div class="featured-package-card-footer">
                                            <button type="button" class="featured-package-contract-btn" data-contract-provider data-manager-id="<?= (int) ($package['active_manager_id'] ?? 0); ?>" <?= (int) ($package['active_manager_id'] ?? 0) > 0 ? '' : 'disabled'; ?>><i class="fa-solid fa-address-card" aria-hidden="true"></i><span><?= sm_text('Contact', 'যোগাযোগ'); ?></span></button>
                                            <?php if ($home_is_logged_in): ?>
                                                <form method="post" action="<?= BASE_URL; ?>cart.php" class="featured-package-cart-form">
                                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['cart_csrf']); ?>">
                                                    <input type="hidden" name="action" value="add">
                                                    <input type="hidden" name="provider_id" value="<?= (int) $package['provider_id']; ?>">
                                                    <button type="submit" class="featured-package-cart-btn"><i class="fa-solid fa-cart-plus"></i> <?= sm_text('Add to Cart', 'কার্টে যোগ'); ?></button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="featured-package-cart-btn featured-package-guest-cart" data-login-required><i class="fa-solid fa-cart-plus"></i> <?= sm_text('Add to Cart', 'কার্টে যোগ'); ?></button>
                                            <?php endif; ?>
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
                    <strong><?= sm_text('No active service packages yet.', 'এখনো কোনো সক্রিয় সেবা প্যাকেজ নেই।'); ?></strong>
                    <span><?= sm_text('New packages added by Event Managers will appear here automatically.', 'ইভেন্ট ম্যানেজারদের যোগ করা নতুন প্যাকেজ এখানে স্বয়ংক্রিয়ভাবে দেখা যাবে।'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Contract Provider modal: public manager details only; contact details are fetched server-side for logged-in users. -->
            <div class="contract-provider-overlay" id="contractProviderModal" data-contact-endpoint="<?= htmlspecialchars(BASE_URL . 'includes/manager_contact_details.php', ENT_QUOTES, 'UTF-8'); ?>" data-login-url="<?= htmlspecialchars(BASE_URL . 'login.php', ENT_QUOTES, 'UTF-8'); ?>" data-register-url="<?= htmlspecialchars(BASE_URL . 'register.php', ENT_QUOTES, 'UTF-8'); ?>" data-logged-in="<?= $home_is_logged_in ? '1' : '0'; ?>" aria-hidden="true" hidden>
                <section class="contract-provider-dialog" role="dialog" aria-modal="true" aria-labelledby="contractProviderTitle" tabindex="-1">
                    <button type="button" class="contract-provider-close-icon" data-contract-close aria-label="Close manager details"><i class="fa-solid fa-xmark"></i></button>
                    <div class="contract-provider-profile">
                        <div class="contract-provider-avatar" data-manager-avatar><i class="fa-solid fa-user-tie" aria-hidden="true"></i></div>
                        <div class="contract-provider-manager-code" data-manager-code></div>
                        <h2 id="contractProviderTitle" data-manager-name><?= sm_text('Manager details', 'ম্যানেজারের বিস্তারিত'); ?></h2>
                        <div class="contract-provider-meta"><span data-manager-package-count></span></div>
                    </div>
                    <div class="contract-provider-packages-block">
                        <label for="contractProviderPackages"><?= sm_text('ম্যানেজার প্যাকেজ সমূহ', 'Manager packages'); ?></label>
                        <div class="contract-provider-select-wrap" data-manager-package-picker>
                            <button type="button" id="contractProviderPackages" class="contract-provider-package-trigger" data-package-menu-toggle aria-expanded="false" aria-controls="contractProviderPackageMenu"><span data-package-selected><?= sm_text('প্যাকেজ নির্বাচন করুন', 'Select a package'); ?></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                            <div class="contract-provider-package-menu" id="contractProviderPackageMenu" data-manager-packages role="listbox" aria-label="ম্যানেজার প্যাকেজ সমূহ" hidden></div>
                        </div>
                        <p class="contract-provider-package-hint" data-package-hint><?= sm_text('প্যাকেজ নির্বাচন করলে সংশ্লিষ্ট কার্ডে নিয়ে যাবে।', 'Selecting a package will take you to its card.'); ?></p>
                    </div>
                    <div class="contract-provider-contact-list">
                        <div class="contract-provider-contact-row"><span class="contract-provider-contact-icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></span><div class="contract-provider-contact-content"><span class="contract-provider-contact-label"><?= sm_text('Manager\'s Mobile', 'ম্যানেজারের মোবাইল'); ?></span><span class="contract-provider-contact-value" data-manager-mobile><?= sm_text('লগইন করুন', 'Log in'); ?></span></div><button type="button" class="contract-provider-copy" data-copy-field="mobile" aria-label="Copy manager mobile" title="Copy mobile" hidden><i class="fa-regular fa-copy"></i></button></div>
                        <div class="contract-provider-contact-row"><span class="contract-provider-contact-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span><div class="contract-provider-contact-content"><span class="contract-provider-contact-label"><?= sm_text('Manager\'s Email', 'ম্যানেজারের ইমেইল'); ?></span><span class="contract-provider-contact-value" data-manager-email><?= sm_text('লগইন করুন', 'Log in'); ?></span></div><button type="button" class="contract-provider-copy" data-copy-field="email" aria-label="Copy manager email" title="Copy email" hidden><i class="fa-regular fa-copy"></i></button></div>
                        <div class="contract-provider-login-note" data-manager-login-note hidden>যোগাযোগের তথ্য দেখতে <a data-login-link href="<?= htmlspecialchars(BASE_URL . 'login.php', ENT_QUOTES, 'UTF-8'); ?>">লগইন</a> / <a data-register-link href="<?= htmlspecialchars(BASE_URL . 'register.php', ENT_QUOTES, 'UTF-8'); ?>">রেজিস্ট্রার</a> করুন।</div>
                    </div>
                    <button type="button" class="contract-provider-close-btn" data-contract-close><?= sm_text('Close', 'বন্ধ করুন'); ?></button>
                </section>
            </div>
            <script type="application/json" id="homeManagerDirectoryData"><?= json_encode($home_manager_directory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>

            <div class="featured-budget-cta" data-aos="fade-up">
                <div class="featured-budget-cta-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                <div>
                    <span><?= sm_text('SMART BUDGET FINDER', 'স্মার্ট বাজেট ফাইন্ডার'); ?></span>
                    <strong><?= sm_text('Build your wedding package around your budget.', 'আপনার বাজেট অনুযায়ী বিয়ের প্যাকেজ তৈরি করুন।'); ?></strong>
                </div>
                <a href="<?= BASE_URL; ?>services/budget_finder.php"><span><?= sm_text('Find Packages', 'প্যাকেজ খুঁজুন'); ?></span><i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <section id="statistics" class="statistics-section">
        <div class="container">
            <div class="statistics-heading text-center" data-aos="fade-up">
                <span class="statistics-label"><i class="fa-solid fa-chart-simple"></i> <?= sm_text('Smart Matrimony at a Glance', 'এক নজরে স্মার্ট ম্যাট্রিমনি'); ?></span>
                <h2><?= sm_text('Growing with', 'বাড়ছে'); ?> <span><?= sm_text('Real Members &amp; Services.', 'প্রকৃত সদস্য ও সেবার সঙ্গে।'); ?></span></h2>
                <p><?= sm_text('These live counters are connected directly to the Smart Matrimony database and grow with the platform.', 'এই লাইভ কাউন্টারগুলো সরাসরি স্মার্ট ম্যাট্রিমনির ডেটাবেসের সঙ্গে যুক্ত এবং প্ল্যাটফর্মের সঙ্গে বাড়ে।'); ?></p>
            </div>

            <div class="statistics-panel statistics-five" data-aos="fade-up">
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $registered_members; ?>">0</strong><span><?= sm_text('Registered Members', 'নিবন্ধিত সদস্য'); ?></span></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-id-card"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $completed_profiles; ?>">0</strong><span><?= sm_text('Profiles Completed', 'সম্পূর্ণ প্রোফাইল'); ?></span></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-person"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $male_profiles; ?>">0</strong><span><?= sm_text('Male Profiles', 'পুরুষের প্রোফাইল'); ?></span></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-person-dress"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $female_profiles; ?>">0</strong><span><?= sm_text('Female Profiles', 'নারীর প্রোফাইল'); ?></span></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fa-solid fa-gift"></i></div>
                    <div class="stat-content"><strong data-stat-value="<?= $service_packages; ?>">0</strong><span><?= sm_text('Service Packages', 'সেবা প্যাকেজ'); ?></span></div>
                </div>
            </div>

            <div class="statistics-note" data-aos="fade-up">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= sm_text('Counts reflect active user accounts, available profiles and active service provider packages in the current database.', 'সংখ্যাগুলো বর্তমান ডেটাবেসের সক্রিয় অ্যাকাউন্ট, উপলভ্য প্রোফাইল ও সক্রিয় সেবা প্যাকেজ নির্দেশ করে।'); ?></span>
            </div>
        </div>
    </section>

    <!-- =========================
         HOW IT WORKS SECTION
    ========================== -->
    <section id="how-it-works" class="how-it-works-section how-it-works-teaser">
        <div class="container">
            <div class="how-it-works-teaser-head" data-aos="fade-up">
                <div>
                    <span class="how-it-works-label"><i class="fa-solid fa-route"></i> <?= sm_text('How It Works', 'যেভাবে কাজ করে'); ?></span>
                    <h2><?= sm_text('Start with a profile.', 'প্রোফাইল দিয়ে শুরু করুন।'); ?> <span><?= sm_text('Connect with confidence.', 'আস্থার সঙ্গে সংযুক্ত হোন।'); ?></span></h2>
                    <p><?= sm_text('A quick look at how Smart Matrimony works. Explore the full visual journey on our How It Works page.', 'স্মার্ট ম্যাট্রিমনি কীভাবে কাজ করে তার সংক্ষিপ্ত ধারণা নিন। বিস্তারিত জানতে যেভাবে কাজ করে পেজটি দেখুন।'); ?></p>
                </div>
                <a href="<?= BASE_URL; ?>how_it_works.php" class="how-it-works-cta"><?= sm_text('Explore How It Works', 'কীভাবে কাজ করে দেখুন'); ?> <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <div class="how-it-works-teaser-flow">
                <div class="teaser-step" data-aos="fade-up" data-aos-delay="50">
                    <div class="teaser-step-icon"><i class="fa-solid fa-user-plus"></i><span>01</span></div>
                    <div><strong><?= sm_text('Create Your Account', 'অ্যাকাউন্ট তৈরি করুন'); ?></strong><small><?= sm_text('Register and get started.', 'নিবন্ধন করে শুরু করুন।'); ?></small></div>
                </div>
                <div class="teaser-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="teaser-step" data-aos="fade-up" data-aos-delay="100">
                    <div class="teaser-step-icon"><i class="fa-solid fa-user-pen"></i><span>02</span></div>
                    <div><strong><?= sm_text('Build Your Profile', 'প্রোফাইল তৈরি করুন'); ?></strong><small><?= sm_text('Add your details and preferences.', 'আপনার তথ্য ও পছন্দ যোগ করুন।'); ?></small></div>
                </div>
                <div class="teaser-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="teaser-step" data-aos="fade-up" data-aos-delay="150">
                    <div class="teaser-step-icon"><i class="fa-solid fa-heart-circle-check"></i><span>03</span></div>
                    <div><strong><?= sm_text('Discover &amp; Connect', 'খুঁজুন ও সংযুক্ত হোন'); ?></strong><small><?= sm_text('Search profiles and connect respectfully.', 'প্রোফাইল খুঁজুন ও সম্মানজনকভাবে যোগাযোগ করুন।'); ?></small></div>
                </div>
                <div class="teaser-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="teaser-step" data-aos="fade-up" data-aos-delay="200">
                    <div class="teaser-step-icon"><i class="fa-solid fa-calendar-check"></i><span>04</span></div>
                    <div><strong><?= sm_text('Plan Your Wedding', 'বিয়ের পরিকল্পনা করুন'); ?></strong><small><?= sm_text('Explore services and book packages.', 'সেবা দেখুন ও প্যাকেজ বুক করুন।'); ?></small></div>
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
                    <span class="premium-section-label"><i class="fa-solid fa-heart-circle-check"></i> <?= sm_text('Smart Matching', 'স্মার্ট ম্যাচিং'); ?></span>
                    <h2 class="premium-section-title"><?= sm_text('Meet people who', 'এমন মানুষের সঙ্গে পরিচিত হোন যারা'); ?> <span><?= sm_text('match what matters.', 'আপনার গুরুত্বপূর্ণ পছন্দের সঙ্গে মানানসই।'); ?></span></h2>
                    <p class="premium-section-text"><?= sm_text('Smart Matrimony brings profile information and partner preferences together to help users explore more relevant connections.', 'স্মার্ট ম্যাট্রিমনি প্রোফাইলের তথ্য ও সঙ্গীর পছন্দ মিলিয়ে আরও প্রাসঙ্গিক সম্পর্ক খুঁজতে সাহায্য করে।'); ?></p>
                    <div class="matching-points">
                        <div><i class="fa-solid fa-circle-check"></i><span><?= sm_text('Preference-based compatibility', 'পছন্দভিত্তিক সামঞ্জস্য'); ?></span></div>
                        <div><i class="fa-solid fa-circle-check"></i><span><?= sm_text('Multiple profile factors considered', 'প্রোফাইলের একাধিক বিষয় বিবেচনা'); ?></span></div>
                        <div><i class="fa-solid fa-circle-check"></i><span><?= sm_text('Clear match details for informed decisions', 'সঠিক সিদ্ধান্তের জন্য ম্যাচের বিস্তারিত'); ?></span></div>
                    </div>
                    <a href="<?= BASE_URL; ?>register.php" class="section-cta"><i class="fa-solid fa-user-plus"></i> Start Exploring</a>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="matching-visual">
                        <div class="matching-visual-top"><span><i class="fa-solid fa-sparkles"></i> <?= sm_text('Example Match View', 'ম্যাচের উদাহরণ'); ?></span><small><?= sm_text('Illustration', 'চিত্র'); ?></small></div>
                        <div class="matching-profile-row">
                            <div class="matching-avatar"><i class="fa-solid fa-person"></i></div>
                            <div class="matching-profile-copy"><strong><?= sm_text('Compatible Profile', 'সামঞ্জস্যপূর্ণ প্রোফাইল'); ?></strong><span><?= sm_text('Preferences aligned across multiple factors', 'একাধিক বিষয়ে পছন্দের মিল'); ?></span></div>
                            <div class="matching-score"><strong>92%</strong><small><?= sm_text('Match', 'মিল'); ?></small></div>
                        </div>
                        <div class="matching-bars">
                            <div><span><?= sm_text('Age', 'বয়স'); ?></span><b><i style="width:92%"></i></b></div>
                            <div><span><?= sm_text('Education', 'শিক্ষা'); ?></span><b><i style="width:86%"></i></b></div>
                            <div><span><?= sm_text('Location', 'অবস্থান'); ?></span><b><i style="width:90%"></i></b></div>
                            <div><span><?= sm_text('Lifestyle', 'জীবনধারা'); ?></span><b><i style="width:82%"></i></b></div>
                        </div>
                        <div class="matching-visual-footer"><i class="fa-solid fa-circle-info"></i> <?= sm_text('Example visualization — actual match scores are generated from user data and preferences.', 'এটি একটি উদাহরণ—প্রকৃত ম্যাচ স্কোর ব্যবহারকারীর তথ্য ও পছন্দ থেকে তৈরি হয়।'); ?></div>
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
                            <div class="profile-main-top"><span><?= sm_text('My Profile', 'আমার প্রোফাইল'); ?></span><i class="fa-solid fa-ellipsis"></i></div>
                            <div class="profile-avatar-large"><i class="fa-solid fa-user"></i><span class="profile-online-dot"></span></div>
                            <div class="profile-name-lines"><b></b><span></span></div>
                            <div class="profile-progress-head"><span><?= sm_text('Profile completeness', 'প্রোফাইলের পূর্ণতা'); ?></span><strong><?= sm_text('Ready to grow', 'আরও সম্পূর্ণ করুন'); ?></strong></div>
                            <div class="profile-progress"><i></i></div>
                            <div class="profile-check-grid">
                                <span><i class="fa-solid fa-circle-check"></i> <?= sm_text('Personal', 'ব্যক্তিগত'); ?></span>
                                <span><i class="fa-solid fa-circle-check"></i> <?= sm_text('Education', 'শিক্ষা'); ?></span>
                                <span><i class="fa-solid fa-circle-check"></i> <?= sm_text('Profession', 'পেশা'); ?></span>
                                <span><i class="fa-solid fa-circle-check"></i> <?= sm_text('Preferences', 'পছন্দ'); ?></span>
                            </div>
                        </div>
                        <div class="profile-floating-badge"><i class="fa-solid fa-shield-heart"></i><span><?= sm_text('Privacy focused', 'গোপনীয়তাকে গুরুত্ব দিন'); ?></span></div>
                    </div>
                </div>
                <div class="col-lg-6 order-lg-2 order-1" data-aos="fade-left">
                    <span class="premium-section-label"><i class="fa-solid fa-id-card"></i> <?= sm_text('Build a Complete Profile', 'সম্পূর্ণ প্রোফাইল তৈরি করুন'); ?></span>
                    <h2 class="premium-section-title"><?= sm_text('A thoughtful profile creates a', 'যত্ন নিয়ে তৈরি প্রোফাইল গড়ে তোলে'); ?> <span><?= sm_text('better first impression.', 'আরও ভালো প্রথম ধারণা।'); ?></span></h2>
                    <p class="premium-section-text"><?= sm_text('Add the details that help others understand you — from education and profession to lifestyle and partner preferences.', 'শিক্ষা ও পেশা থেকে জীবনধারা ও সঙ্গীর পছন্দ—আপনাকে বুঝতে সাহায্য করে এমন তথ্য যোগ করুন।'); ?></p>
                    <div class="profile-benefit-list">
                        <div><span>01</span><div><strong><?= sm_text('Tell your story', 'নিজের কথা জানান'); ?></strong><p><?= sm_text('Share meaningful information about your background and lifestyle.', 'নিজের পটভূমি ও জীবনধারা সম্পর্কে গুরুত্বপূর্ণ তথ্য দিন।'); ?></p></div></div>
                        <div><span>02</span><div><strong><?= sm_text('Set clear preferences', 'স্পষ্ট পছন্দ নির্ধারণ করুন'); ?></strong><p><?= sm_text('Describe the qualities and details you value in a partner.', 'সঙ্গীর মধ্যে যে গুণাবলি ও বিষয়গুলো মূল্য দেন তা জানান।'); ?></p></div></div>
                        <div><span>03</span><div><strong><?= sm_text('Keep it respectful', 'সম্মান বজায় রাখুন'); ?></strong><p><?= sm_text('Choose the information you are comfortable making visible.', 'যে তথ্য প্রকাশ করতে স্বাচ্ছন্দ্যবোধ করেন তা বেছে নিন।'); ?></p></div></div>
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
                <span class="premium-section-label"><i class="fa-solid fa-shield-heart"></i> <?= sm_text('Privacy &amp; Safety', 'গোপনীয়তা ও নিরাপত্তা'); ?></span>
                <h2 class="premium-section-title"><?= sm_text('Your personal journey deserves', 'আপনার ব্যক্তিগত যাত্রার প্রাপ্য'); ?> <span><?= sm_text('respect and control.', 'সম্মান ও নিয়ন্ত্রণ।'); ?></span></h2>
                <p class="premium-section-text"><?= sm_text('Smart Matrimony is designed around secure access, profile privacy and respectful communication.', 'স্মার্ট ম্যাট্রিমনি নিরাপদ প্রবেশ, প্রোফাইলের গোপনীয়তা ও সম্মানজনক যোগাযোগকে গুরুত্ব দিয়ে তৈরি।'); ?></p>
            </div>
            <div class="privacy-grid">
                <article class="privacy-card" data-aos="fade-up" data-aos-delay="50"><div><i class="fa-solid fa-lock"></i></div><h3><?= sm_text('Secure Access', 'নিরাপদ প্রবেশ'); ?></h3><p><?= sm_text('Authentication and account controls help keep your account protected.', 'যাচাইকরণ ও অ্যাকাউন্ট নিয়ন্ত্রণ আপনার অ্যাকাউন্ট সুরক্ষিত রাখতে সাহায্য করে।'); ?></p></article>
                <article class="privacy-card" data-aos="fade-up" data-aos-delay="100"><div><i class="fa-solid fa-eye-slash"></i></div><h3><?= sm_text('Profile Privacy', 'প্রোফাইলের গোপনীয়তা'); ?></h3><p><?= sm_text('Profile visibility settings give users control over who can discover their profile.', 'প্রোফাইল দৃশ্যমানতার সেটিংস দিয়ে কারা আপনার প্রোফাইল দেখতে পারবে তা নিয়ন্ত্রণ করুন।'); ?></p></article>
                <article class="privacy-card" data-aos="fade-up" data-aos-delay="150"><div><i class="fa-solid fa-user-shield"></i></div><h3><?= sm_text('Photo Controls', 'ছবির নিয়ন্ত্রণ'); ?></h3><p><?= sm_text('Photo visibility can be managed separately according to available privacy settings.', 'উপলভ্য গোপনীয়তা সেটিংস অনুযায়ী ছবির দৃশ্যমানতা আলাদাভাবে নিয়ন্ত্রণ করা যায়।'); ?></p></article>
                <article class="privacy-card" data-aos="fade-up" data-aos-delay="200"><div><i class="fa-solid fa-comments"></i></div><h3><?= sm_text('Respectful Communication', 'সম্মানজনক যোগাযোগ'); ?></h3><p><?= sm_text('Connection and chat flows are structured to encourage considerate communication.', 'সংযোগ ও চ্যাটের ধারা বিবেচনাপূর্ণ যোগাযোগে উৎসাহ দেয়।'); ?></p></article>
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
                    <span class="premium-section-label"><i class="fa-solid fa-users"></i> <?= sm_text('Recently Joined', 'সদ্য যোগ দিয়েছেন'); ?></span>
                    <h2 class="premium-section-title"><?= sm_text('Meet some of our', 'আমাদের কয়েকজন'); ?> <span><?= sm_text('new members.', 'নতুন সদস্যের সঙ্গে পরিচিত হোন।'); ?></span></h2>
                    <p class="premium-section-text"><?= sm_text('Public profiles are shown without photos or private contact details. Create an account to explore the full experience.', 'সদস্যদের প্রকাশ্য প্রোফাইলে ছবি ও ব্যক্তিগত যোগাযোগের তথ্য দেখানো হয় না। সব সুবিধা দেখতে একটি অ্যাকাউন্ট তৈরি করুন।'); ?></p>
                </div>
                <a href="<?= BASE_URL; ?>register.php" class="section-cta section-cta-outline"><i class="fa-solid fa-user-plus"></i> <?= sm_text('Join Free', 'বিনামূল্যে যোগ দিন'); ?></a>
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
                            $featured_district = $current_language === 'en'
                                ? trim((string)($featured['district_en'] ?? '') ?: (string)($featured['district'] ?? ''))
                                : trim((string)($featured['district_bn'] ?? '') ?: (string)($featured['district'] ?? ''));
                            $featured_upazila = $current_language === 'en'
                                ? trim((string)($featured['upazila_en'] ?? '') ?: (string)($featured['upazila'] ?? ''))
                                : trim((string)($featured['upazila_bn'] ?? '') ?: (string)($featured['upazila'] ?? ''));
                            $featured_country = trim((string)($featured['country'] ?? ''));
                            if ($current_language !== 'en' && in_array(strtolower($featured_country), ['bangladesh', 'বাংলাদেশ'], true)) {
                                $featured_country = 'বাংলাদেশ';
                            }
                            $featured_location_parts = array_values(array_filter([$featured_district, $featured_upazila, $featured_country], static fn($part) => $part !== ''));
                            $featured_location = implode(', ', $featured_location_parts);
                            $featured_gender = sm_text(
                                strtolower($featured['gender'] ?? '') === 'female' ? 'Female' : 'Male',
                                strtolower($featured['gender'] ?? '') === 'female' ? 'নারী' : 'পুরুষ'
                            );
                            $featured_profession_translations = [
                                'Student' => 'শিক্ষার্থী', 'Teacher' => 'শিক্ষক', 'Doctor' => 'চিকিৎসক', 'Nurse' => 'নার্স',
                                'Engineer' => 'প্রকৌশলী', 'Lawyer' => 'আইনজীবী', 'Government Job' => 'সরকারি চাকরি',
                                'Private Job' => 'বেসরকারি চাকরি', 'Business' => 'ব্যবসা', 'Banker' => 'ব্যাংকার',
                                'Freelancer' => 'ফ্রিল্যান্সার', 'Unemployed' => 'বেকার', 'Other' => 'অন্যান্য',
                                'Software Engineer' => 'সফটওয়্যার প্রকৌশলী', 'IT Professional' => 'তথ্যপ্রযুক্তি পেশাজীবী',
                                'Agriculture' => 'কৃষি', 'Government Service' => 'সরকারি সেবা', 'Private Service' => 'বেসরকারি চাকরি'
                            ];
                            $featured_education_translations = [
                                'Primary' => 'প্রাথমিক', 'SSC' => 'এসএসসি', 'HSC' => 'এইচএসসি', 'HSC (General)' => 'এইচএসসি (সাধারণ)',
                                'Bachelor' => 'স্নাতক', "Bachelor's" => 'স্নাতক', 'Fazil (Bachelor)' => 'ফাজিল (স্নাতক)',
                                'Masters' => 'স্নাতকোত্তর', "Master's" => 'স্নাতকোত্তর', 'Kamil (Masters)' => 'কামিল (স্নাতকোত্তর)',
                                'MBBS' => 'এমবিবিএস', 'Engineering' => 'প্রকৌশল', 'Diploma' => 'ডিপ্লোমা', 'PhD' => 'পিএইচডি',
                                'Other' => 'অন্যান্য', 'Hifz' => 'হিফজ', 'Qari' => 'কারী', 'Mufti' => 'মুফতি'
                            ];
                            $featured_profession = (string)($featured['profession'] ?? '');
                            $featured_education = (string)($featured['highest_education'] ?? '');
                            if ($current_language !== 'en') {
                                $featured_profession = $featured_profession_translations[$featured_profession] ?? $featured_profession;
                                $featured_education = $featured_education_translations[$featured_education] ?? $featured_education;
                            }
                        ?>
                        <article class="recent-member-card" data-aos="fade-up">
                            <div class="recent-member-top">
                                <div class="recent-member-avatar <?= strtolower($featured['gender'] ?? '') === 'female' ? 'female' : 'male'; ?>">
                                    <i class="fa-solid <?= strtolower($featured['gender'] ?? '') === 'female' ? 'fa-person-dress' : 'fa-person'; ?>"></i>
                                </div>
                                <?php if (($featured['verification_status'] ?? '') === 'Verified'): ?><span class="recent-verified"><i class="fa-solid fa-circle-check"></i> <?= sm_text('Verified', 'যাচাইকৃত'); ?></span><?php endif; ?>
                            </div>
                            <h3><?= htmlspecialchars($featured_name ?: sm_text('Smart Matrimony Member', 'স্মার্ট ম্যাট্রিমনি সদস্য')); ?></h3>
                            <p class="recent-member-meta">
                                <?= htmlspecialchars($featured_gender); ?>
                                <?php if ($featured_age !== null): ?> · <?= $current_language === 'en' ? $featured_age : sm_bn_digits($featured_age); ?> <?= sm_text('yrs', 'বছর'); ?><?php endif; ?>
                            </p>
                            <div class="recent-member-details">
                                <?php if ($featured_location): ?><span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($featured_location); ?></span><?php endif; ?>
                                <?php if ($featured_profession !== ''): ?><span><i class="fa-solid fa-briefcase"></i><?= htmlspecialchars($featured_profession); ?></span><?php endif; ?>
                                <?php if ($featured_education !== ''): ?><span><i class="fa-solid fa-graduation-cap"></i><?= htmlspecialchars($featured_education); ?></span><?php endif; ?>
                            </div>
                            <a href="<?= BASE_URL; ?>login.php" class="recent-member-action"><?= sm_text('Login to explore', 'বিস্তারিত দেখতে লগইন করুন'); ?> <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="recent-empty" data-aos="fade-up"><i class="fa-solid fa-users"></i><h3><?= sm_text('New member profiles are on the way.', 'নতুন সদস্যদের প্রোফাইল শিগগিরই আসছে।'); ?></h3><p><?= sm_text('As public profiles are completed, they will appear here.', 'পাবলিক প্রোফাইল সম্পূর্ণ হলে এখানে দেখা যাবে।'); ?></p></div>
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
                    <span class="premium-section-label"><i class="fa-solid fa-magnifying-glass"></i> <?= sm_text('Find a Match', 'ম্যাচ খুঁজুন'); ?></span>
                    <h2><?= sm_text('Start with the preferences that', 'আপনার পছন্দ দিয়ে শুরু করুন যা'); ?> <span><?= sm_text('matter to you.', 'আপনার কাছে গুরুত্বপূর্ণ।'); ?></span></h2>
                    <p><?= sm_text('Search through partner preferences, location and profile details after creating your Smart Matrimony account.', 'স্মার্ট ম্যাট্রিমনি অ্যাকাউন্ট তৈরি করার পর সঙ্গীর পছন্দ, অবস্থান ও প্রোফাইলের তথ্য দিয়ে খুঁজুন।'); ?></p>
                    <a href="<?= BASE_URL; ?>register.php" class="section-cta"><i class="fa-solid fa-user-plus"></i> Create Your Profile</a>
                </div>
                <div class="search-preview-ui" aria-hidden="true">
                    <div class="search-ui-top"><span><?= sm_text('Partner Search', 'সঙ্গী খুঁজুন'); ?></span><i class="fa-solid fa-sliders"></i></div>
                    <div class="search-ui-fields">
                        <div><small><?= sm_text('Preferred Gender', 'পছন্দের লিঙ্গ'); ?></small><strong><i class="fa-solid fa-user-group"></i> <?= sm_text('Select', 'নির্বাচন করুন'); ?></strong></div>
                        <div><small><?= sm_text('Age Range', 'বয়সের সীমা'); ?></small><strong><i class="fa-solid fa-calendar-days"></i> 24 — 30</strong></div>
                        <div><small><?= sm_text('Location', 'অবস্থান'); ?></small><strong><i class="fa-solid fa-location-dot"></i> <?= sm_text('Choose area', 'এলাকা বেছে নিন'); ?></strong></div>
                        <div><small><?= sm_text('Education', 'শিক্ষা'); ?></small><strong><i class="fa-solid fa-graduation-cap"></i> <?= sm_text('Select', 'নির্বাচন করুন'); ?></strong></div>
                    </div>
                    <div class="search-ui-button"><i class="fa-solid fa-magnifying-glass"></i> <?= sm_text('Find Compatible Profiles', 'মানানসই প্রোফাইল খুঁজুন'); ?></div>
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
                    <span class="home-services-label"><i class="fa-solid fa-layer-group"></i> <?= sm_text('Smart Wedding Services', 'স্মার্ট ওয়েডিং সার্ভিসেস'); ?></span>
                    <h2><?= sm_text('Everything you need for your', 'আপনার বিশেষ দিনের জন্য প্রয়োজনীয় সবকিছু'); ?> <span><?= sm_text('special day.', 'এক জায়গায়।'); ?></span></h2>
                    <p><?= sm_text('Explore available wedding services and discover packages from our current provider catalog.', 'উপলভ্য বিয়ের সেবাগুলো দেখুন এবং আমাদের সেবা প্রদানকারীদের প্যাকেজ খুঁজে নিন।'); ?></p>
                </div>
                <div class="home-services-heading-actions">
                    <div class="home-services-count">
                        <strong><?= $current_language === 'en' ? count($home_services) : sm_bn_digits(count($home_services)); ?></strong>
                        <span><?= sm_text('Service Categories', 'সেবার বিভাগ'); ?></span>
                    </div>
                    <div class="home-services-count home-packages-count">
                        <strong><?= $current_language === 'en' ? count($home_featured_packages) : sm_bn_digits(count($home_featured_packages)); ?></strong>
                        <span><?= sm_text('Active Packages', 'সক্রিয় প্যাকেজ'); ?></span>
                    </div>
                    <a href="<?= BASE_URL; ?>services/budget_finder.php" class="home-services-budget-btn"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= sm_text('Find Packages by Budget', 'বাজেট অনুযায়ী প্যাকেজ খুঁজুন'); ?></a>
                </div>
            </div>

            <div class="home-service-grid">
                <?php foreach ($home_services as $service): ?>
                    <?php $service_id = (int) $service['service_id']; ?>
                    <?php $package_count = count($home_service_packages[$service_id] ?? []); ?>
                    <button type="button" class="home-service-card" data-service-id="<?= $service_id; ?>" data-service-name="<?= htmlspecialchars($service['service_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="home-service-icon"><i class="fa-solid <?= htmlspecialchars($service['icon_class'] ?? ($home_service_icons[$service['service_name']] ?? 'fa-heart')); ?>"></i></span>
                        <span class="home-service-copy">
                            <strong><?= htmlspecialchars($current_language === 'en' ? $service['service_name'] : (($service['service_name_bn'] ?? '') !== '' ? $service['service_name_bn'] : ($home_service_names_bn[$service['service_name']] ?? $service['service_name'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?= htmlspecialchars($current_language === 'en' ? ($service['description'] ?? 'Wedding-related service') : (($service['description_bn'] ?? '') !== '' ? $service['description_bn'] : ($home_service_subtitles_bn[$service['service_name']] ?? ($service['description'] ?? 'বিয়েসংক্রান্ত সেবা'))), ENT_QUOTES, 'UTF-8'); ?></small>
                        </span>
                        <span class="home-service-meta">
                            <?= $current_language === 'en' ? $package_count : sm_bn_digits($package_count); ?> <?= $current_language === 'en' ? ($package_count === 1 ? 'package' : 'packages') : 'টি প্যাকেজ'; ?>
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="home-services-note">
                <span><i class="fa-solid fa-shield-heart"></i></span>
                <div><strong><?= sm_text('Browse freely, book securely.', 'স্বাচ্ছন্দ্যে দেখুন, নিরাপদে বুক করুন।'); ?></strong><p><?= sm_text('Anyone can explore service packages. Login or registration is required when adding a package to your service cart.', 'যে কেউ সেবা প্যাকেজ দেখতে পারবেন। কার্টে প্যাকেজ যোগ করতে লগইন বা নিবন্ধন প্রয়োজন।'); ?></p></div>
            </div>
        </div>
    </section>


    <!-- =========================
         FAQ
    ========================== -->
    <section id="faq" class="faq-section">
        <div class="container">
            <div class="faq-heading text-center" data-aos="fade-up">
                <span class="premium-section-label"><i class="fa-solid fa-circle-question"></i> <?= sm_text('Frequently Asked Questions', 'সাধারণ জিজ্ঞাসা'); ?></span>
                <h2 class="premium-section-title"><?= sm_text('A few things you may', 'কিছু বিষয় যা আপনি'); ?> <span><?= sm_text('want to know.', 'জানতে চাইতে পারেন।'); ?></span></h2>
            </div>
            <div class="faq-list" data-aos="fade-up">
                <details><summary><?= sm_text('How does Smart Matrimony matching work?', 'স্মার্ট ম্যাট্রিমনির ম্যাচিং কীভাবে কাজ করে?'); ?><i class="fa-solid fa-chevron-down"></i></summary><p><?= sm_text('Matching uses profile information and partner preferences to calculate compatibility across supported factors. Users can review match information before deciding how to proceed.', 'ম্যাচিংয়ে প্রোফাইলের তথ্য ও সঙ্গীর পছন্দ ব্যবহার করে বিভিন্ন বিষয়ের সামঞ্জস্য নির্ণয় করা হয়। পরবর্তী সিদ্ধান্ত নেওয়ার আগে ব্যবহারকারীরা ম্যাচের তথ্য দেখতে পারেন।'); ?></p></details>
                <details><summary><?= sm_text('Can I browse wedding service packages before registering?', 'নিবন্ধনের আগে কি বিয়ের প্যাকেজ দেখা যাবে?'); ?><i class="fa-solid fa-chevron-down"></i></summary><p><?= sm_text('Yes. Visitors can explore available service categories and packages. Login or registration is required when adding a package to the service cart.', 'হ্যাঁ। দর্শনার্থীরা সেবার বিভাগ ও প্যাকেজ দেখতে পারবেন। কার্টে প্যাকেজ যোগ করতে লগইন বা নিবন্ধন প্রয়োজন।'); ?></p></details>
                <details><summary><?= sm_text('Can I control my profile visibility?', 'আমি কি প্রোফাইলের দৃশ্যমানতা নিয়ন্ত্রণ করতে পারি?'); ?><i class="fa-solid fa-chevron-down"></i></summary><p><?= sm_text('Yes. Available privacy controls include profile visibility and separate photo visibility settings.', 'হ্যাঁ। গোপনীয়তা নিয়ন্ত্রণে প্রোফাইল ও ছবির দৃশ্যমানতা আলাদাভাবে নির্ধারণ করা যায়।'); ?></p></details>
                <details><summary><?= sm_text('Can I bookmark profiles?', 'আমি কি প্রোফাইল বুকমার্ক করতে পারি?'); ?><i class="fa-solid fa-chevron-down"></i></summary><p><?= sm_text('Logged-in users can use the bookmark feature to save profiles they may want to revisit.', 'লগইন করা ব্যবহারকারীরা পরে দেখার জন্য প্রোফাইল বুকমার্ক করতে পারেন।'); ?></p></details>
                <details><summary><?= sm_text('How do I communicate with another member?', 'অন্য সদস্যের সঙ্গে কীভাবে যোগাযোগ করব?'); ?><i class="fa-solid fa-chevron-down"></i></summary><p><?= sm_text('Users can use the interest and chat request flow provided by the platform, with conversations available after the required connection step.', 'প্ল্যাটফর্মের আগ্রহ প্রকাশ ও চ্যাট অনুরোধ ব্যবহার করা যায়; প্রয়োজনীয় সংযোগ ধাপ সম্পন্ন হলে কথোপকথন শুরু করা যায়।'); ?></p></details>
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

                        <?= sm_text('Begin with Intention', 'সঠিক উদ্দেশ্যে শুরু করুন'); ?>

                    </span>


                    <h2>

                        <?= sm_text('Ready to Begin Your', 'আপনার জীবনসঙ্গী খোঁজার'); ?>
                        <span><?= sm_text('Matrimonial Journey?', 'যাত্রা শুরু করতে প্রস্তুত?'); ?></span>

                    </h2>


                    <p>

                        <?= sm_text(
                            'Discover meaningful connections in a respectful, secure and family-friendly environment.',
                            'সম্মানজনক, নিরাপদ ও পরিবারবান্ধব পরিবেশে খুঁজে নিন অর্থবহ সম্পর্কের সম্ভাবনা।'
                        ); ?>

                    </p>


                    <div class="final-cta-buttons">

                        <!-- CREATE ACCOUNT -->

                        <a
                            href="register.php"
                            class="final-cta-primary"
                        >

                            <i class="fa-solid fa-user-plus"></i>

                            <span>
                                <?= sm_text('Create Account', 'অ্যাকাউন্ট তৈরি করুন'); ?>
                            </span>

                        </a>


                        <!-- LOGIN -->

                        <a
                            href="login.php"
                            class="final-cta-secondary"
                        >

                            <i class="fa-solid fa-right-to-bracket"></i>

                            <span>
                                <?= sm_text('Login', 'লগইন'); ?>
                            </span>

                        </a>

                    </div>


                    <div class="final-cta-trust">

                        <span>

                            <i class="fa-solid fa-shield-heart"></i>

                            <?= sm_text('Secure', 'নিরাপদ'); ?>

                        </span>

                        <span>

                            <i class="fa-solid fa-heart"></i>

                            <?= sm_text('Respectful', 'সম্মানজনক'); ?>

                        </span>

                        <span>

                            <i class="fa-solid fa-users"></i>

                            <?= sm_text('Family-Friendly', 'পরিবারবান্ধব'); ?>

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
            el.textContent = Math.round(target * eased).toLocaleString(<?= json_encode($current_language === 'bn' ? 'bn-BD' : 'en-US'); ?>);
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
            <span class="home-services-label"><i class="fa-solid fa-layer-group"></i> <span data-service-available-label><?= sm_text('Available Packages', 'উপলভ্য প্যাকেজ'); ?></span></span>
            <h2 id="servicePackageTitle"><?= sm_text('Wedding Service', 'বিয়ের সেবা'); ?></h2>
            <p id="servicePackageDescription"><?= sm_text('Explore available packages and provider details.', 'উপলভ্য প্যাকেজ ও সেবা প্রদানকারীর বিস্তারিত দেখুন।'); ?></p>
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

<!-- Contract Provider window for Smart Wedding Services package cards -->
<div class="home-service-provider-overlay" id="homeServiceProviderModal" data-contact-endpoint="<?= htmlspecialchars(BASE_URL . 'includes/manager_contact_details.php', ENT_QUOTES, 'UTF-8'); ?>" data-login-url="<?= htmlspecialchars(BASE_URL . 'login.php', ENT_QUOTES, 'UTF-8'); ?>" data-register-url="<?= htmlspecialchars(BASE_URL . 'register.php', ENT_QUOTES, 'UTF-8'); ?>" data-logged-in="<?= $home_is_logged_in ? '1' : '0'; ?>" aria-hidden="true" hidden>
    <section class="home-service-provider-dialog" role="dialog" aria-modal="true" aria-labelledby="homeServiceProviderTitle" tabindex="-1">
        <button type="button" class="home-service-provider-close" data-home-provider-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        <div class="home-service-provider-heading">
            <span class="home-services-label"><i class="fa-solid fa-handshake"></i> <span data-hsp-text="eyebrow"><?= sm_text('Provider\'s Contract', 'সেবা প্রদানকারীর চুক্তি'); ?></span></span>
            <h2 id="homeServiceProviderTitle" data-hsp-text="title"><?= sm_text('Provider\'s Contract', 'সেবা প্রদানকারীর চুক্তি'); ?></h2>
            <p data-hsp-text="subtitle"><?= sm_text('Explore the manager\'s details and packages.', 'ম্যানেজারের তথ্য ও প্যাকেজ দেখুন।'); ?></p>
        </div>
        <div class="home-service-provider-form">
            <label for="homeServiceManagerSelect" data-hsp-text="managerLabel"><?= sm_text('Select Manager', 'ম্যানেজার নির্বাচন করুন'); ?></label>
            <select id="homeServiceManagerSelect" data-home-manager-select></select>
            <label for="homeServicePackageSelect" data-hsp-text="packagesLabel"><?= sm_text('Manager\'s Packages', 'ম্যানেজারের প্যাকেজ'); ?></label>
            <select id="homeServicePackageSelect" data-home-manager-package-select></select>
        </div>
        <div class="home-service-provider-profile" data-home-provider-profile>
            <div class="home-service-provider-avatar" data-home-provider-avatar><i class="fa-solid fa-user-tie" aria-hidden="true"></i></div>
            <div class="home-service-provider-info">
                <span class="home-service-provider-code" data-home-provider-code></span>
                <h3 data-home-provider-name></h3>
                <span class="home-service-provider-count" data-home-provider-count></span>
                <div class="home-service-provider-contact-row"><i class="fa-solid fa-phone" aria-hidden="true"></i><span class="home-service-provider-contact-label" data-hsp-text="mobileLabel"><?= sm_text('Manager\'s Mobile', 'ম্যানেজারের মোবাইল'); ?></span><span class="home-service-provider-contact-value" data-home-provider-mobile></span><button type="button" data-home-provider-copy="mobile" aria-label="Copy mobile" title="Copy mobile"><i class="fa-regular fa-copy"></i></button></div>
                <div class="home-service-provider-contact-row"><i class="fa-solid fa-envelope" aria-hidden="true"></i><span class="home-service-provider-contact-label" data-hsp-text="emailLabel"><?= sm_text('Manager\'s Email', 'ম্যানেজারের ইমেইল'); ?></span><span class="home-service-provider-contact-value" data-home-provider-email></span><button type="button" data-home-provider-copy="email" aria-label="Copy email" title="Copy email"><i class="fa-regular fa-copy"></i></button></div>
                <p class="home-service-provider-login-note" data-home-provider-login-note hidden></p>
            </div>
        </div>
        <p class="home-service-provider-hint" data-hsp-text="packageHint"><?= sm_text('Choose a package to view its card.', 'কার্ড দেখতে একটি প্যাকেজ বেছে নিন।'); ?></p>
        <button type="button" class="home-service-provider-close-bottom" data-home-provider-close data-hsp-text="close"><?= sm_text('Close', 'বন্ধ করুন'); ?></button>
    </section>
</div>

<script>
    window.SMART_SERVICE_DATA = <?= json_encode($home_services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.SMART_SERVICE_PACKAGES = <?= json_encode($home_service_packages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.SMART_SERVICE_ICONS = <?= json_encode($home_service_icons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.SMART_SERVICE_LOGGED_IN = <?= $home_is_logged_in ? 'true' : 'false'; ?>;
    window.SMART_HOME_LANGUAGE = <?= json_encode($current_language ?? 'bn'); ?>;
    window.SMART_SERVICE_BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.SMART_SERVICE_CART_CSRF = <?= json_encode($home_is_logged_in ? ($_SESSION['cart_csrf'] ?? '') : ''); ?>;
</script>
<script>
document.querySelectorAll('[data-login-required]').forEach(function (button) {
    button.addEventListener('click', function () {
        var goToLogin = function () {
            if (window.confirm(<?= json_encode($current_language === 'en' ? 'Please log in or register to add packages to your cart. Continue to Login / Register?' : 'কার্টে প্যাকেজ যোগ করতে লগইন বা নিবন্ধন করুন। লগইন / নিবন্ধন পেজে যাবেন?'); ?>)) {
                window.location.href = <?= json_encode(BASE_URL . 'login.php'); ?>;
            }
        };
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: 'info',
                title: <?= json_encode($current_language === 'en' ? 'Login Required' : 'লগইন প্রয়োজন'); ?>,
                text: <?= json_encode($current_language === 'en' ? 'Please log in or register to add this package to your cart.' : 'এই প্যাকেজটি কার্টে যোগ করতে লগইন বা নিবন্ধন করুন।'); ?>,
                confirmButtonText: <?= json_encode($current_language === 'en' ? 'Login / Register' : 'লগইন / নিবন্ধন'); ?>,
                showCancelButton: true,
                cancelButtonText: <?= json_encode($current_language === 'en' ? 'Continue Browsing' : 'ব্রাউজ চালিয়ে যান'); ?>,
                confirmButtonColor: '#0f766e'
            }).then(function (result) {
                if (result.isConfirmed) window.location.href = <?= json_encode(BASE_URL . 'login.php'); ?>;
            });
        } else {
            goToLogin();
        }
    });
});
</script>
<script src="<?= BASE_URL; ?>assets/js/contract-provider.js?v=1"></script>
<script src="<?= BASE_URL; ?>assets/js/home-services.js?v=contract-provider-1"></script>
<script src="<?= BASE_URL; ?>assets/js/home-service-provider.js?v=1"></script>
<script src="<?= BASE_URL; ?>assets/js/home-packages.js?v=contract-provider-1"></script>

<!-- Homepage floating Back to Top control -->
<button type="button" class="home-back-to-top" id="homeBackToTop" aria-label="Back to top" title="Back to top" hidden>
    <span><?= sm_text('Back to Top', 'উপরে ফিরে যান'); ?></span><span class="home-back-to-top-arrow" aria-hidden="true">↑</span>
</button>
<script>
(function () {
    const button = document.getElementById('homeBackToTop');
    if (!button) return;

    const updateVisibility = () => {
        button.hidden = window.scrollY < 360;
    };

    button.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
        });
    });

    window.addEventListener('scroll', updateVisibility, { passive: true });
    updateVisibility();
})();
</script>

<?php include 'includes/footer.php'; ?>
