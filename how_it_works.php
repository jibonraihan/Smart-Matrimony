<?php
$page_css = 'assets/css/how-it-works.css';
require_once 'config/db.php';
require_once 'includes/functions.php';

include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="how-it-works-page">
    <section class="hiw-hero">
        <div class="hiw-hero-glow hiw-glow-one" aria-hidden="true"></div>
        <div class="hiw-hero-glow hiw-glow-two" aria-hidden="true"></div>
        <div class="container">
            <div class="hiw-hero-content" data-aos="fade-up" data-aos-duration="800">
                <span class="hiw-kicker"><i class="fa-solid fa-route"></i> <?= sm_text('HOW IT WORKS', 'যেভাবে কাজ করে'); ?></span>
                <h1><?= sm_text('From creating your account to ', 'অ্যাকাউন্ট তৈরি থেকে '); ?><span><?= sm_text('finding your way forward.', 'সঠিক পথে এগিয়ে যাওয়া পর্যন্ত।'); ?></span></h1>
                <p><?= sm_text(
                    'Follow the journey step by step—from building your profile and discovering suitable profiles to expressing interest and arranging wedding services.',
                    'আপনার প্রোফাইল তৈরি ও উপযুক্ত প্রোফাইল খুঁজে দেখা থেকে শুরু করে আগ্রহ প্রকাশ এবং বিয়ের সেবা আয়োজন পর্যন্ত—ধাপে ধাপে পুরো প্রক্রিয়াটি অনুসরণ করুন।'
                ); ?></p>
                <div class="hiw-hero-actions">
                    <a href="<?= BASE_URL; ?>register.php" class="hiw-primary-btn">
                        <i class="fa-solid fa-user-plus"></i> <?= sm_text('Create Account', 'অ্যাকাউন্ট তৈরি করুন'); ?>
                    </a>
                    <a href="#hiw-journey" class="hiw-secondary-btn">
                        <?= sm_text('See the Journey', 'পুরো প্রক্রিয়াটি দেখুন'); ?> <i class="fa-solid fa-arrow-down"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="hiw-journey" id="hiw-journey">
        <div class="container">
            <div class="hiw-section-heading" data-aos="fade-up">
                <span><?= sm_text('YOUR JOURNEY', 'আপনার যাত্রা'); ?></span>
                <h2><?= sm_text('Everything in one clear flow', 'একটি সহজ ধারাবাহিক প্রক্রিয়া'); ?></h2>
                <p><?= sm_text('Each step builds on the one before it, so you always know what comes next.', 'প্রতিটি ধাপ আগেরটির ওপর ভিত্তি করে তৈরি, তাই পরবর্তী করণীয় সহজেই বুঝতে পারবেন।'); ?></p>
            </div>

            <div class="hiw-timeline" id="hiwTimeline">
                <div class="hiw-progress-track" aria-hidden="true"><span id="hiwProgress"></span></div>

                <article class="hiw-step hiw-step-left" data-step="1" data-aos="fade-up">
                    <div class="hiw-marker"><span>01</span><i class="fa-solid fa-user-plus"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker"><?= sm_text('START HERE', 'এখান থেকে শুরু'); ?></span>
                            <h3><?= sm_text('Create your account', 'আপনার অ্যাকাউন্ট তৈরি করুন'); ?></h3>
                            <p><?= sm_text('Register with your basic account information and enter the platform securely.', 'প্রাথমিক অ্যাকাউন্টের তথ্য দিয়ে নিবন্ধন করুন এবং নিরাপদে প্ল্যাটফর্মে প্রবেশ করুন।'); ?></p>
                            <div class="hiw-tags"><span><?= sm_text('Email verification', 'ইমেইল যাচাই'); ?></span><span><?= sm_text('Secure account', 'নিরাপদ অ্যাকাউন্ট'); ?></span></div>
                        </div>
                        <div class="hiw-visual hiw-account-visual" aria-label="Account creation illustration">
                            <div class="mock-window">
                                <div class="mock-window-bar"><span></span><span></span><span></span></div>
                                <div class="mock-form-title"><?= sm_text('Create Account', 'অ্যাকাউন্ট তৈরি করুন'); ?></div>
                                <div class="mock-input"><i class="fa-regular fa-user"></i><span><?= sm_text('Your name', 'আপনার নাম'); ?></span></div>
                                <div class="mock-input"><i class="fa-regular fa-envelope"></i><span><?= sm_text('Email address', 'ইমেইল ঠিকানা'); ?></span></div>
                                <div class="mock-button"><?= sm_text('Create Account', 'অ্যাকাউন্ট তৈরি করুন'); ?> <i class="fa-solid fa-arrow-right"></i></div>
                                <div class="mock-check"><i class="fa-solid fa-circle-check"></i> <?= sm_text('Ready to verify', 'যাচাইয়ের জন্য প্রস্তুত'); ?></div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-right" data-step="2" data-aos="fade-up">
                    <div class="hiw-marker"><span>02</span><i class="fa-solid fa-id-card"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker"><?= sm_text('BUILD YOUR PROFILE', 'প্রোফাইল তৈরি করুন'); ?></span>
                            <h3><?= sm_text('Complete your profile & preferences', 'প্রোফাইল ও পছন্দের তথ্য সম্পূর্ণ করুন'); ?></h3>
                            <p><?= sm_text('Add your personal information, profile details and partner preferences so the platform can present your requirements clearly.', 'আপনার ব্যক্তিগত তথ্য, প্রোফাইলের বিবরণ ও জীবনসঙ্গী-সংক্রান্ত পছন্দ যোগ করুন, যাতে আপনার চাহিদাগুলো স্পষ্টভাবে উপস্থাপিত হয়।'); ?></p>
                            <div class="hiw-tags"><span><?= sm_text('Profile information', 'প্রোফাইলের তথ্য'); ?></span><span><?= sm_text('Partner preferences', 'জীবনসঙ্গী-সংক্রান্ত পছন্দ'); ?></span></div>
                        </div>
                        <div class="hiw-visual hiw-profile-visual" aria-label="Profile completion illustration">
                            <div class="profile-mock">
                                <div class="profile-ring"><strong>86%</strong><small><?= sm_text('Profile', 'প্রোফাইল'); ?></small></div>
                                <div class="profile-lines"><span></span><span></span><span></span><span></span></div>
                                <div class="profile-pill"><i class="fa-solid fa-heart"></i> <?= sm_text('Partner preferences', 'জীবনসঙ্গী-সংক্রান্ত পছন্দ'); ?></div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-left" data-step="3" data-aos="fade-up">
                    <div class="hiw-marker"><span>03</span><i class="fa-solid fa-magnifying-glass"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker"><?= sm_text('DISCOVER', 'খুঁজে দেখুন'); ?></span>
                            <h3><?= sm_text('Search for suitable profiles', 'উপযুক্ত প্রোফাইল খুঁজুন'); ?></h3>
                            <p><?= sm_text('Use the partner search to explore profiles based on the information and preferences available to you.', 'আপনার কাছে থাকা তথ্য ও পছন্দের ভিত্তিতে জীবনসঙ্গী অনুসন্ধান ব্যবহার করে প্রোফাইল দেখুন।'); ?></p>
                            <div class="hiw-tags"><span><?= sm_text('Search profiles', 'প্রোফাইল খুঁজুন'); ?></span><span><?= sm_text('Filter & explore', 'ফিল্টার করে দেখুন'); ?></span></div>
                        </div>
                        <div class="hiw-visual hiw-search-visual" aria-label="Profile search illustration">
                            <div class="search-mock">
                                <div class="search-top"><span><i class="fa-solid fa-sliders"></i> <?= sm_text('Preferences', 'পছন্দসমূহ'); ?></span><strong><?= sm_text('Search', 'অনুসন্ধান'); ?></strong></div>
                                <div class="search-profile"><div class="avatar-dot"></div><div><b><?= sm_text('Suitable profile', 'উপযুক্ত প্রোফাইল'); ?></b><small><?= sm_text('Age • Education • Location', 'বয়স • শিক্ষা • অবস্থান'); ?></small></div><span class="match-chip"><?= sm_text('82% Match', '৮২% ম্যাচ'); ?></span></div>
                                <div class="search-profile second"><div class="avatar-dot"></div><div><b><?= sm_text('Another profile', 'আরেকটি প্রোফাইল'); ?></b><small><?= sm_text('Preferences aligned', 'পছন্দের সঙ্গে সামঞ্জস্যপূর্ণ'); ?></small></div><span class="match-chip"><?= sm_text('76% Match', '৭৬% ম্যাচ'); ?></span></div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-right" data-step="4" data-aos="fade-up">
                    <div class="hiw-marker"><span>04</span><i class="fa-solid fa-heart-circle-check"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker"><?= sm_text('UNDERSTAND THE MATCH', 'ম্যাচ বুঝে নিন'); ?></span>
                            <h3><?= sm_text('Review the profile & match details', 'প্রোফাইল ও ম্যাচের বিবরণ দেখুন'); ?></h3>
                            <p><?= sm_text('Open a profile, review the available information and see how your selected preferences compare with that profile.', 'একটি প্রোফাইল খুলে উপলভ্য তথ্য দেখুন এবং আপনার নির্বাচিত পছন্দগুলোর সঙ্গে সেই প্রোফাইলের মিল যাচাই করুন।'); ?></p>
                            <div class="hiw-tags"><span><?= sm_text('Profile details', 'প্রোফাইলের বিবরণ'); ?></span><span><?= sm_text('Match breakdown', 'ম্যাচের বিশ্লেষণ'); ?></span></div>
                        </div>
                        <div class="hiw-visual hiw-match-visual" aria-label="Match details illustration">
                            <div class="match-mock">
                                <div class="match-score"><strong>82%</strong><span><?= sm_text('Match', 'ম্যাচ'); ?></span></div>
                                <div class="match-row"><span><?= sm_text('Location', 'অবস্থান'); ?></span><b>80%</b><i><em style="width:80%"></em></i></div>
                                <div class="match-row"><span><?= sm_text('Education', 'শিক্ষা'); ?></span><b>100%</b><i><em style="width:100%"></em></i></div>
                                <div class="match-row"><span><?= sm_text('Religion', 'ধর্ম'); ?></span><b>100%</b><i><em style="width:100%"></em></i></div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-left" data-step="5" data-aos="fade-up">
                    <div class="hiw-marker"><span>05</span><i class="fa-solid fa-paper-plane"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker"><?= sm_text('MAKE A CONNECTION', 'যোগাযোগের সূচনা'); ?></span>
                            <h3><?= sm_text('Send interest', 'আগ্রহ জানান'); ?></h3>
                            <p><?= sm_text('When you find someone you are interested in, open the interest flow and send a respectful message with your request.', 'কাউকে পছন্দ হলে আগ্রহ জানানোর প্রক্রিয়ায় গিয়ে আপনার অনুরোধের সঙ্গে সম্মানজনক বার্তা পাঠান।'); ?></p>
                            <div class="hiw-tags"><span><?= sm_text('Express interest', 'আগ্রহ প্রকাশ'); ?></span><span><?= sm_text('Personal message', 'ব্যক্তিগত বার্তা'); ?></span></div>
                        </div>
                        <div class="hiw-visual hiw-interest-visual" aria-label="Send interest illustration">
                            <div class="interest-mock">
                                <div class="interest-avatar"><i class="fa-solid fa-heart"></i></div>
                                <div><strong><?= sm_text('Express your interest', 'আপনার আগ্রহ প্রকাশ করুন'); ?></strong><small><?= sm_text('Keep it sincere and appropriate.', 'বার্তাটি আন্তরিক ও যথাযথ রাখুন।'); ?></small></div>
                                <div class="interest-message"><?= sm_text('I would like to express my interest in your profile...', 'আমি আপনার প্রোফাইলে আগ্রহ প্রকাশ করতে চাই...'); ?></div>
                                <div class="interest-button"><i class="fa-solid fa-heart"></i> <?= sm_text('Send Interest', 'আগ্রহ জানান'); ?></div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-right" data-step="6" data-aos="fade-up">
                    <div class="hiw-marker"><span>06</span><i class="fa-solid fa-ring"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker"><?= sm_text('PLAN THE CELEBRATION', 'আয়োজনের পরিকল্পনা করুন'); ?></span>
                            <h3><?= sm_text('Explore wedding services', 'বিয়ের সেবাগুলো দেখুন'); ?></h3>
                            <p><?= sm_text('Browse the available wedding service categories and explore packages from service providers.', 'উপলভ্য বিয়ের সেবার বিভাগগুলো দেখুন এবং সেবা প্রদানকারীদের প্যাকেজ ঘেঁটে দেখুন।'); ?></p>
                            <div class="hiw-tags"><span><?= sm_text('Photography', 'ফটোগ্রাফি'); ?></span><span><?= sm_text('Decoration', 'সাজসজ্জা'); ?></span><span><?= sm_text('Many more', 'আরও অনেক কিছু'); ?></span></div>
                        </div>
                        <div class="hiw-visual hiw-services-visual" aria-label="Wedding services illustration">
                            <div class="service-mock-grid">
                                <span><i class="fa-solid fa-camera-retro"></i> <?= sm_text('Photography', 'ফটোগ্রাফি'); ?></span>
                                <span><i class="fa-solid fa-wand-magic-sparkles"></i> <?= sm_text('Decoration', 'সাজসজ্জা'); ?></span>
                                <span><i class="fa-solid fa-shirt"></i> <?= sm_text('Wedding Dress', 'বিয়ের পোশাক'); ?></span>
                                <span><i class="fa-solid fa-car"></i> <?= sm_text('Car Rental', 'গাড়ি ভাড়া'); ?></span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-left" data-step="7" data-aos="fade-up">
                    <div class="hiw-marker"><span>07</span><i class="fa-solid fa-cart-plus"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker"><?= sm_text('CHOOSE YOUR PACKAGE', 'আপনার প্যাকেজ বেছে নিন'); ?></span>
                            <h3><?= sm_text('Add services to your cart', 'কার্টে সেবা যোগ করুন'); ?></h3>
                            <p><?= sm_text('Open a service package, review the details and add the package you want to your cart.', 'সেবার প্যাকেজ খুলে বিস্তারিত দেখুন এবং পছন্দের প্যাকেজ কার্টে যোগ করুন।'); ?></p>
                            <div class="hiw-tags"><span><?= sm_text('Review package', 'প্যাকেজ পর্যালোচনা'); ?></span><span><?= sm_text('Add to cart', 'কার্টে যোগ করুন'); ?></span></div>
                        </div>
                        <div class="hiw-visual hiw-cart-visual" aria-label="Service cart illustration">
                            <div class="cart-mock">
                                <div class="cart-item"><div class="service-thumb"><i class="fa-solid fa-camera"></i></div><div><b><?= sm_text('Premium Package', 'প্রিমিয়াম প্যাকেজ'); ?></b><small><?= sm_text('Wedding service', 'বিয়ের সেবা'); ?></small></div><strong>৳15,000</strong></div>
                                <div class="cart-total"><span><?= sm_text('Cart Total', 'কার্টের মোট'); ?></span><b>৳15,000</b></div>
                                <div class="cart-button"><i class="fa-solid fa-calendar-check"></i> <?= sm_text('Continue to Booking', 'বুকিংয়ের জন্য এগিয়ে যান'); ?></div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-right" data-step="8" data-aos="fade-up">
                    <div class="hiw-marker"><span>08</span><i class="fa-solid fa-calendar-check"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker"><?= sm_text('SUBMIT & TRACK', 'জমা দিন ও অগ্রগতি দেখুন'); ?></span>
                            <h3><?= sm_text('Request a booking & track it', 'বুকিংয়ের অনুরোধ দিন ও অগ্রগতি দেখুন'); ?></h3>
                            <p><?= sm_text('Select your event date, add any special instructions and submit the booking request. You can then follow its status from My Bookings.', 'অনুষ্ঠানের তারিখ নির্বাচন করুন, প্রয়োজনীয় নির্দেশনা যোগ করুন এবং বুকিংয়ের অনুরোধ জমা দিন। এরপর “আমার বুকিং” থেকে এর অবস্থা দেখতে পারবেন।'); ?></p>
                            <div class="hiw-tags"><span><?= sm_text('Event date', 'অনুষ্ঠানের তারিখ'); ?></span><span><?= sm_text('Booking request', 'বুকিংয়ের অনুরোধ'); ?></span><span><?= sm_text('Track status', 'অবস্থা দেখুন'); ?></span></div>
                        </div>
                        <div class="hiw-visual hiw-booking-visual" aria-label="Booking tracking illustration">
                            <div class="booking-mock">
                                <div class="booking-status"><span class="status-dot"></span><b><?= sm_text('Pending', 'অপেক্ষমাণ'); ?></b><small><?= sm_text('Manager confirmation', 'ম্যানেজারের নিশ্চিতকরণ'); ?></small></div>
                                <div class="booking-line"><span class="done"></span><i></i><span class="active"></span><i></i><span></span></div>
                                <div class="booking-labels"><span><?= sm_text('Requested', 'অনুরোধ করা হয়েছে'); ?></span><span><?= sm_text('Review', 'পর্যালোচনা'); ?></span><span><?= sm_text('Confirmed', 'নিশ্চিত'); ?></span></div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="hiw-finale" data-aos="fade-up">
        <div class="container">
            <div class="hiw-finale-card">
                <div class="hiw-finale-icon"><i class="fa-solid fa-heart"></i></div>
                <div>
                    <span class="hiw-step-kicker"><?= sm_text('READY WHEN YOU ARE', 'আপনি প্রস্তুত হলেই'); ?></span>
                    <h2><?= sm_text('Start with one simple step.', 'একটি সহজ ধাপ দিয়ে শুরু করুন।'); ?></h2>
                    <p><?= sm_text('Create your account and take the journey at your own pace.', 'আপনার অ্যাকাউন্ট তৈরি করুন এবং নিজের সুবিধামতো এগিয়ে যান।'); ?></p>
                </div>
                <a href="<?= BASE_URL; ?>register.php" class="hiw-primary-btn"><?= sm_text('Create Account', 'অ্যাকাউন্ট তৈরি করুন'); ?> <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
