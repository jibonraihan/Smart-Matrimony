<?php
$page_css = "assets/css/about.css";

require_once 'config/db.php';
require_once 'includes/functions.php';

include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="about-page">

    <section class="about-hero">
        <div class="about-hero-pattern" aria-hidden="true"></div>
        <div class="container">
            <div class="about-hero-content">
                <div class="about-bismillah" lang="ar" dir="rtl">
                    بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيمِ
                </div>
                <span class="about-eyebrow">ABOUT SMART MATRIMONY</span>
                <h1>A Meaningful Journey Toward <span>Marriage</span></h1>
                <p>
                    Smart Matrimony is a Bangladesh-based matrimonial platform created to make
                    the search for a suitable life partner more organized, respectful,
                    privacy-conscious and family-friendly.
                </p>
                <div class="about-hero-tags">
                    <span><i class="fa-solid fa-heart"></i> Faith &amp; Values</span>
                    <span><i class="fa-solid fa-people-roof"></i> Family Focused</span>
                    <span><i class="fa-solid fa-shield-heart"></i> Privacy Conscious</span>
                </div>
            </div>
        </div>
    </section>

    <section class="about-intro">
        <div class="container">
            <div class="about-paper">
                <div class="about-paper-accent"></div>

                <p class="about-opening">
                    <strong>নিঃসন্দেহে সকল প্রশংসা আল্লাহর।</strong> আমরা তাঁর কাছেই আমাদের
                    জীবনের অনুগ্রহ ও কল্যাণ কামনা করি। বিবাহ মহান আল্লাহপ্রদত্ত একটি বিশেষ
                    নিয়ামত এবং রাসুলুল্লাহ ﷺ-এর গুরুত্বপূর্ণ সুন্নাহ। কুরআন ও হাদিসে বিবাহকে
                    পবিত্রতা, দ্বীনের সৌন্দর্য এবং পারিবারিক শান্তির একটি গুরুত্বপূর্ণ মাধ্যম
                    হিসেবে গুরুত্ব দেওয়া হয়েছে।
                </p>

                <p>
                    বর্তমান সময়ে সঠিক জীবনসঙ্গী নির্বাচন অনেকের জন্য একটি কঠিন ও সংবেদনশীল
                    বিষয় হয়ে দাঁড়িয়েছে। পড়াশোনা, চাকরি, ব্যস্ততা, দূরত্ব এবং সামাজিক নানা
                    বাস্তবতার কারণে উপযুক্ত পাত্র-পাত্রীর সন্ধান ও পরিবারের মধ্যে যোগাযোগ
                    স্থাপন সবসময় সহজ হয় না। এই প্রয়োজন থেকেই একটি দায়িত্বশীল, আধুনিক ও
                    মূল্যবোধসম্পন্ন matrimonial platform-এর ধারণা তৈরি হয়েছে—<strong>Smart Matrimony</strong>।
                </p>

                <p>
                    আমাদের লক্ষ্য হলো প্রযুক্তির সুবিধাকে কাজে লাগিয়ে বিয়ের জন্য উপযুক্ত
                    পাত্র-পাত্রীর সন্ধানকে সহজ, গোছানো ও নিরাপদ করা; একই সঙ্গে পরিবারকে
                    সম্পৃক্ত রেখে সম্মানজনক যোগাযোগের সুযোগ তৈরি করা। আমরা বিশ্বাস করি,
                    প্রযুক্তি মানুষের সম্পর্কের বিকল্প নয়—বরং সঠিকভাবে ব্যবহার করলে এটি
                    ভালো সম্পর্কের পথে একটি সহায়ক মাধ্যম হতে পারে।
                </p>
            </div>
        </div>
    </section>

    <section class="about-section">
        <div class="container">
            <div class="about-section-heading">
                <span>OUR PURPOSE</span>
                <h2>Why Smart Matrimony?</h2>
                <p>
                    একটি সুন্দর বিবাহের শুরু হওয়া উচিত পরিষ্কার উদ্দেশ্য, পারস্পরিক সম্মান
                    এবং দায়িত্বশীলতার মাধ্যমে।
                </p>
            </div>

            <div class="about-feature-grid">
                <article class="about-feature-card">
                    <div class="about-feature-icon"><i class="fa-solid fa-mosque"></i></div>
                    <h3>Faith &amp; Values</h3>
                    <p>
                        ইসলামী মূল্যবোধ, পারিবারিক সম্মান ও দায়িত্বশীলতার বিষয়গুলোকে
                        বিবাহের যাত্রার গুরুত্বপূর্ণ অংশ হিসেবে বিবেচনা করা।
                    </p>
                </article>

                <article class="about-feature-card">
                    <div class="about-feature-icon"><i class="fa-solid fa-user-group"></i></div>
                    <h3>Family Friendly</h3>
                    <p>
                        পাত্র-পাত্রীর পাশাপাশি পরিবার ও অভিভাবকদের ভূমিকার প্রতি সম্মান
                        রেখে পরিচয় ও যোগাযোগের একটি সুন্দর পরিবেশ তৈরি করা।
                    </p>
                </article>

                <article class="about-feature-card">
                    <div class="about-feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <h3>Privacy &amp; Safety</h3>
                    <p>
                        ব্যবহারকারীর তথ্যের প্রতি সচেতন থেকে verification, responsible
                        communication এবং নিরাপদ ব্যবহারকে উৎসাহিত করা।
                    </p>
                </article>

                <article class="about-feature-card">
                    <div class="about-feature-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                    <h3>Smart Discovery</h3>
                    <p>
                        পছন্দ, তথ্য ও প্রয়োজনের ভিত্তিতে সম্ভাব্য উপযুক্ত profile খুঁজে
                        পাওয়ার প্রক্রিয়াকে আরও সংগঠিত করা।
                    </p>
                </article>
            </div>
        </div>
    </section>

    <section class="about-section about-soft-section">
        <div class="container">
            <div class="about-two-column">
                <div>
                    <span class="about-kicker">A BETTER WAY TO SEARCH</span>
                    <h2>Technology with a Human Purpose</h2>
                    <p>
                        পাত্র-পাত্রীর সন্ধানে আগে যেখানে পরিচিতজন, আত্মীয়স্বজন বা সীমিত
                        সামাজিক পরিসরের ওপর বেশি নির্ভর করতে হতো, সেখানে একটি digital
                        platform আরও বিস্তৃতভাবে profile discovery-এর সুযোগ তৈরি করতে পারে।
                    </p>
                    <p>
                        Smart Matrimony profile information, preferences এবং matching-এর
                        মতো সুবিধার মাধ্যমে এই প্রক্রিয়াকে সহজ করতে চায়—তবে চূড়ান্ত সিদ্ধান্ত
                        সবসময় ব্যবহারকারী ও পরিবারের নিজস্ব বিবেচনার বিষয়।
                    </p>
                </div>

                <div class="about-process-card">
                    <div class="about-process-item">
                        <span>01</span>
                        <div>
                            <strong>Create a profile</strong>
                            <p>নিজের প্রয়োজনীয় তথ্য ও পছন্দগুলো সুন্দরভাবে উপস্থাপন করুন।</p>
                        </div>
                    </div>
                    <div class="about-process-item">
                        <span>02</span>
                        <div>
                            <strong>Discover matches</strong>
                            <p>প্রাসঙ্গিক profile ও matching suggestions দেখুন।</p>
                        </div>
                    </div>
                    <div class="about-process-item">
                        <span>03</span>
                        <div>
                            <strong>Connect responsibly</strong>
                            <p>সম্মানজনক ও নিরাপদ উপায়ে যোগাযোগের পরবর্তী ধাপে এগিয়ে যান।</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section">
        <div class="container">
            <div class="about-section-heading">
                <span>OUR PLATFORM</span>
                <h2>What Smart Matrimony Brings Together</h2>
            </div>

            <div class="about-services-grid">
                <div><i class="fa-solid fa-id-card"></i><span>Detailed Profiles</span></div>
                <div><i class="fa-solid fa-sliders"></i><span>Partner Preferences</span></div>
                <div><i class="fa-solid fa-handshake"></i><span>Smart Matching</span></div>
                <div><i class="fa-solid fa-calendar-check"></i><span>Wedding Services</span></div>
                <div><i class="fa-solid fa-book-quran"></i><span>Islamic Guidance</span></div>
                <div><i class="fa-solid fa-lock"></i><span>Privacy &amp; Security</span></div>
            </div>
        </div>
    </section>

    <section class="about-section about-islamic-section">
        <div class="container">
            <div class="about-islamic-card">
                <div class="about-quote-mark">“</div>
                <div>
                    <span class="about-kicker">OUR BELIEF</span>
                    <h2>Marriage is more than finding a profile</h2>
                    <p>
                        এটি দুটি মানুষের পাশাপাশি দুটি পরিবারেরও একটি নতুন বন্ধন। তাই
                        Smart Matrimony-তে আমরা শুধু profile matching নয়, বরং সম্মান,
                        সততা, পারিবারিক মূল্যবোধ এবং দায়িত্বশীলতার গুরুত্বকে সামনে রাখতে চাই।
                    </p>
                    <p class="about-note">
                        আল্লাহ আমাদের নিয়তকে কবুল করুন এবং সকলকে উত্তম, শান্তিপূর্ণ ও
                        কল্যাণময় দাম্পত্য জীবনের তাওফিক দান করুন। আমিন।
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section about-contact-section">
        <div class="container">
            <div class="about-contact-card">
                <div class="about-contact-heading">
                    <span class="about-kicker">CONTACT INFORMATION</span>
                    <h2>Get in Touch with Smart Matrimony</h2>
                    <p>
                        Platform সম্পর্কে কোনো প্রশ্ন, মতামত বা সহায়তার প্রয়োজন হলে
                        আমাদের support team-এর সঙ্গে যোগাযোগ করতে পারেন।
                    </p>
                </div>

                <div class="about-contact-details">
                    <div class="about-contact-detail">
                        <span class="about-detail-icon"><i class="fa-solid fa-building"></i></span>
                        <div>
                            <small>Platform</small>
                            <strong>Smart Matrimony Plattform</strong>
                        </div>
                    </div>

                    <div class="about-contact-detail">
                        <span class="about-detail-icon"><i class="fa-solid fa-location-dot"></i></span>
                        <div>
                            <small>Address</small>
                            <strong>Room- 502, KiU, Kishoreganj Sadar, Kishoreganj, Bangladesh</strong>
                        </div>
                    </div>

                    <div class="about-contact-detail">
                        <span class="about-detail-icon"><i class="fa-solid fa-envelope"></i></span>
                        <div>
                            <small>Email</small>
                            <strong><a href="mailto:support.smartmatrimony@gmail.com">support.smartmatrimony@gmail.com</a></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include 'includes/footer.php'; ?>
