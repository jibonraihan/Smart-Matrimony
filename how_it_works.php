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
                <span class="hiw-kicker"><i class="fa-solid fa-route"></i> HOW IT WORKS</span>
                <h1>From creating your account to <span>finding your way forward.</span></h1>
                <p>
                    Follow the journey step by step—from building your profile and discovering
                    suitable profiles to expressing interest and arranging wedding services.
                </p>
                <div class="hiw-hero-actions">
                    <a href="<?= BASE_URL; ?>register.php" class="hiw-primary-btn">
                        <i class="fa-solid fa-user-plus"></i> Create Account
                    </a>
                    <a href="#hiw-journey" class="hiw-secondary-btn">
                        See the Journey <i class="fa-solid fa-arrow-down"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="hiw-journey" id="hiw-journey">
        <div class="container">
            <div class="hiw-section-heading" data-aos="fade-up">
                <span>YOUR JOURNEY</span>
                <h2>Everything in one clear flow</h2>
                <p>Each step builds on the one before it, so you always know what comes next.</p>
            </div>

            <div class="hiw-timeline" id="hiwTimeline">
                <div class="hiw-progress-track" aria-hidden="true"><span id="hiwProgress"></span></div>

                <article class="hiw-step hiw-step-left" data-step="1" data-aos="fade-up">
                    <div class="hiw-marker"><span>01</span><i class="fa-solid fa-user-plus"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker">START HERE</span>
                            <h3>Create your account</h3>
                            <p>Register with your basic account information and enter the platform securely.</p>
                            <div class="hiw-tags"><span>Email verification</span><span>Secure account</span></div>
                        </div>
                        <div class="hiw-visual hiw-account-visual" aria-label="Account creation illustration">
                            <div class="mock-window">
                                <div class="mock-window-bar"><span></span><span></span><span></span></div>
                                <div class="mock-form-title">Create Account</div>
                                <div class="mock-input"><i class="fa-regular fa-user"></i><span>Your name</span></div>
                                <div class="mock-input"><i class="fa-regular fa-envelope"></i><span>Email address</span></div>
                                <div class="mock-button">Create Account <i class="fa-solid fa-arrow-right"></i></div>
                                <div class="mock-check"><i class="fa-solid fa-circle-check"></i> Ready to verify</div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-right" data-step="2" data-aos="fade-up">
                    <div class="hiw-marker"><span>02</span><i class="fa-solid fa-id-card"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker">BUILD YOUR PROFILE</span>
                            <h3>Complete your profile & preferences</h3>
                            <p>Add your personal information, profile details and partner preferences so the platform can present your requirements clearly.</p>
                            <div class="hiw-tags"><span>Profile information</span><span>Partner preferences</span></div>
                        </div>
                        <div class="hiw-visual hiw-profile-visual" aria-label="Profile completion illustration">
                            <div class="profile-mock">
                                <div class="profile-ring"><strong>86%</strong><small>Profile</small></div>
                                <div class="profile-lines"><span></span><span></span><span></span><span></span></div>
                                <div class="profile-pill"><i class="fa-solid fa-heart"></i> Partner preferences</div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-left" data-step="3" data-aos="fade-up">
                    <div class="hiw-marker"><span>03</span><i class="fa-solid fa-magnifying-glass"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker">DISCOVER</span>
                            <h3>Search for suitable profiles</h3>
                            <p>Use the partner search to explore profiles based on the information and preferences available to you.</p>
                            <div class="hiw-tags"><span>Search profiles</span><span>Filter & explore</span></div>
                        </div>
                        <div class="hiw-visual hiw-search-visual" aria-label="Profile search illustration">
                            <div class="search-mock">
                                <div class="search-top"><span><i class="fa-solid fa-sliders"></i> Preferences</span><strong>Search</strong></div>
                                <div class="search-profile"><div class="avatar-dot"></div><div><b>Suitable profile</b><small>Age • Education • Location</small></div><span class="match-chip">82% Match</span></div>
                                <div class="search-profile second"><div class="avatar-dot"></div><div><b>Another profile</b><small>Preferences aligned</small></div><span class="match-chip">76% Match</span></div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-right" data-step="4" data-aos="fade-up">
                    <div class="hiw-marker"><span>04</span><i class="fa-solid fa-heart-circle-check"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker">UNDERSTAND THE MATCH</span>
                            <h3>Review the profile & match details</h3>
                            <p>Open a profile, review the available information and see how your selected preferences compare with that profile.</p>
                            <div class="hiw-tags"><span>Profile details</span><span>Match breakdown</span></div>
                        </div>
                        <div class="hiw-visual hiw-match-visual" aria-label="Match details illustration">
                            <div class="match-mock">
                                <div class="match-score"><strong>82%</strong><span>Match</span></div>
                                <div class="match-row"><span>Location</span><b>80%</b><i><em style="width:80%"></em></i></div>
                                <div class="match-row"><span>Education</span><b>100%</b><i><em style="width:100%"></em></i></div>
                                <div class="match-row"><span>Religion</span><b>100%</b><i><em style="width:100%"></em></i></div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-left" data-step="5" data-aos="fade-up">
                    <div class="hiw-marker"><span>05</span><i class="fa-solid fa-paper-plane"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker">MAKE A CONNECTION</span>
                            <h3>Send interest</h3>
                            <p>When you find someone you are interested in, open the interest flow and send a respectful message with your request.</p>
                            <div class="hiw-tags"><span>Express interest</span><span>Personal message</span></div>
                        </div>
                        <div class="hiw-visual hiw-interest-visual" aria-label="Send interest illustration">
                            <div class="interest-mock">
                                <div class="interest-avatar"><i class="fa-solid fa-heart"></i></div>
                                <div><strong>Express your interest</strong><small>Keep it sincere and appropriate.</small></div>
                                <div class="interest-message">I would like to express my interest in your profile...</div>
                                <div class="interest-button"><i class="fa-solid fa-heart"></i> Send Interest</div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-right" data-step="6" data-aos="fade-up">
                    <div class="hiw-marker"><span>06</span><i class="fa-solid fa-ring"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker">PLAN THE CELEBRATION</span>
                            <h3>Explore wedding services</h3>
                            <p>Browse the available wedding service categories and explore packages from service providers.</p>
                            <div class="hiw-tags"><span>Photography</span><span>Decoration</span><span>Many more</span></div>
                        </div>
                        <div class="hiw-visual hiw-services-visual" aria-label="Wedding services illustration">
                            <div class="service-mock-grid">
                                <span><i class="fa-solid fa-camera-retro"></i> Photography</span>
                                <span><i class="fa-solid fa-wand-magic-sparkles"></i> Decoration</span>
                                <span><i class="fa-solid fa-shirt"></i> Wedding Dress</span>
                                <span><i class="fa-solid fa-car"></i> Car Rental</span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-left" data-step="7" data-aos="fade-up">
                    <div class="hiw-marker"><span>07</span><i class="fa-solid fa-cart-plus"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker">CHOOSE YOUR PACKAGE</span>
                            <h3>Add services to your cart</h3>
                            <p>Open a service package, review the details and add the package you want to your cart.</p>
                            <div class="hiw-tags"><span>Review package</span><span>Add to cart</span></div>
                        </div>
                        <div class="hiw-visual hiw-cart-visual" aria-label="Service cart illustration">
                            <div class="cart-mock">
                                <div class="cart-item"><div class="service-thumb"><i class="fa-solid fa-camera"></i></div><div><b>Premium Package</b><small>Wedding service</small></div><strong>৳15,000</strong></div>
                                <div class="cart-total"><span>Cart Total</span><b>৳15,000</b></div>
                                <div class="cart-button"><i class="fa-solid fa-calendar-check"></i> Continue to Booking</div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="hiw-step hiw-step-right" data-step="8" data-aos="fade-up">
                    <div class="hiw-marker"><span>08</span><i class="fa-solid fa-calendar-check"></i></div>
                    <div class="hiw-card">
                        <div class="hiw-card-copy">
                            <span class="hiw-step-kicker">SUBMIT & TRACK</span>
                            <h3>Request a booking & track it</h3>
                            <p>Select your event date, add any special instructions and submit the booking request. You can then follow its status from My Bookings.</p>
                            <div class="hiw-tags"><span>Event date</span><span>Booking request</span><span>Track status</span></div>
                        </div>
                        <div class="hiw-visual hiw-booking-visual" aria-label="Booking tracking illustration">
                            <div class="booking-mock">
                                <div class="booking-status"><span class="status-dot"></span><b>Pending</b><small>Manager confirmation</small></div>
                                <div class="booking-line"><span class="done"></span><i></i><span class="active"></span><i></i><span></span></div>
                                <div class="booking-labels"><span>Requested</span><span>Review</span><span>Confirmed</span></div>
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
                    <span class="hiw-step-kicker">READY WHEN YOU ARE</span>
                    <h2>Start with one simple step.</h2>
                    <p>Create your account and take the journey at your own pace.</p>
                </div>
                <a href="<?= BASE_URL; ?>register.php" class="hiw-primary-btn">Create Account <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
