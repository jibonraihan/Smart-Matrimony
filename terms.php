<?php
$page_css = "assets/css/terms.css";

require_once 'config/db.php';
require_once 'includes/functions.php';

include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="terms-page">

    <section class="terms-hero">
        <div class="container">
            <div class="terms-hero-inner">
                <div class="terms-icon" aria-hidden="true">
                    <i class="fa-solid fa-file-contract"></i>
                </div>
                <div>
                    <span class="terms-eyebrow"><?= sm_text('SMART MATRIMONY • DOCUMENTATION', 'স্মার্ট ম্যাট্রিমনি • নীতিমালা'); ?></span>
                    <h1><?= sm_text('Terms &amp; Conditions', 'শর্তাবলী'); ?></h1>
                    <p>
                        <?= sm_text('Please read these terms carefully before creating an account or using
                        Smart Matrimony. They explain the basic rules, responsibilities and
                        expectations for using our matrimonial platform.', 'স্মার্ট ম্যাট্রিমনিতে অ্যাকাউন্ট তৈরি বা প্ল্যাটফর্ম ব্যবহার করার আগে অনুগ্রহ করে এই শর্তাবলী মনোযোগ দিয়ে পড়ুন। আমাদের বিবাহ-সংক্রান্ত প্ল্যাটফর্ম ব্যবহারের মৌলিক নিয়ম, দায়িত্ব ও প্রত্যাশা এখানে ব্যাখ্যা করা হয়েছে।'); ?>
                    </p>
                    <div class="terms-meta">
                        <span><i class="fa-regular fa-calendar"></i> <?= sm_text('Last updated: September 18, 2026', 'সর্বশেষ হালনাগাদ: ১৮ সেপ্টেম্বর, ২০২৬'); ?></span>
                        <span><i class="fa-solid fa-circle-check"></i> <?= sm_text('Clear &amp; transparent guidelines', 'স্পষ্ট ও স্বচ্ছ নির্দেশনা'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="terms-content-section">
        <div class="container">
            <div class="row g-4 g-xl-5 align-items-start">

                <aside class="col-lg-3">
                    <div class="terms-toc">
                        <div class="toc-title">
                            <i class="fa-solid fa-list"></i>
                            <?= sm_text('On this page', 'এই পৃষ্ঠায়'); ?>
                        </div>
                        <a href="#acceptance"><?= sm_text('1. Acceptance of Terms', '১. শর্তাবলীতে সম্মতি'); ?></a>
                        <a href="#eligibility"><?= sm_text('2. Eligibility', '২. যোগ্যতা'); ?></a>
                        <a href="#account"><?= sm_text('3. Account &amp; Registration', '৩. অ্যাকাউন্ট ও নিবন্ধন'); ?></a>
                        <a href="#verification"><?= sm_text('4. Verification', '৪. যাচাইকরণ'); ?></a>
                        <a href="#profile"><?= sm_text('5. Profiles &amp; Content', '৫. প্রোফাইল ও কনটেন্ট'); ?></a>
                        <a href="#conduct"><?= sm_text('6. User Conduct', '৬. ব্যবহারকারীর আচরণ'); ?></a>
                        <a href="#matching"><?= sm_text('7. Matching &amp; Services', '৭. ম্যাচিং ও সেবা'); ?></a>
                        <a href="#privacy"><?= sm_text('8. Privacy &amp; Data', '৮. গোপনীয়তা ও তথ্য'); ?></a>
                        <a href="#safety"><?= sm_text('9. Safety &amp; Reporting', '৯. নিরাপত্তা ও রিপোর্ট'); ?></a>
                        <a href="#communications"><?= sm_text('10. Communications', '১০. যোগাযোগ'); ?></a>
                        <a href="#intellectual-property"><?= sm_text('11. Intellectual Property', '১১. মেধাস্বত্ব'); ?></a>
                        <a href="#availability"><?= sm_text('12. Availability &amp; Changes', '১২. প্রাপ্যতা ও পরিবর্তন'); ?></a>
                        <a href="#termination"><?= sm_text('13. Account Suspension', '১৩. অ্যাকাউন্ট স্থগিতকরণ'); ?></a>
                        <a href="#contact"><?= sm_text('14. Contact', '১৪. যোগাযোগ'); ?></a>
                    </div>
                </aside>

                <div class="col-lg-9">
                    <article class="terms-document">

                        <div class="terms-notice">
                            <i class="fa-solid fa-shield-heart"></i>
                            <div>
                                <strong><?= sm_text('Our goal', 'আমাদের লক্ষ্য'); ?></strong>
                                <p>
                                    <?= sm_text('Smart Matrimony is designed to support respectful, privacy-conscious
                                    and family-friendly matrimonial connections. Please use the platform
                                    honestly and treat other members with dignity.', 'স্মার্ট ম্যাট্রিমনি সম্মানজনক, গোপনীয়তাসচেতন ও পরিবারবান্ধব বৈবাহিক যোগাযোগে সহায়তার জন্য তৈরি। অনুগ্রহ করে প্ল্যাটফর্মটি সততার সঙ্গে ব্যবহার করুন এবং অন্য সদস্যদের মর্যাদা দিন।'); ?>
                                </p>
                            </div>
                        </div>

                        <section id="acceptance" class="terms-section">
                            <div class="section-number">01</div>
                            <div>
                                <h2><?= sm_text('Acceptance of Terms', 'শর্তাবলীতে সম্মতি'); ?></h2>
                                <p>
                                    <?= sm_text('By creating an account, accessing or using Smart Matrimony, you agree
                                    to follow these Terms &amp; Conditions and any applicable rules or notices
                                    presented within the platform. If you do not agree with these terms,
                                    please do not create or use an account.', 'অ্যাকাউন্ট তৈরি, প্রবেশ বা স্মার্ট ম্যাট্রিমনি ব্যবহার করার মাধ্যমে আপনি এই শর্তাবলী এবং প্ল্যাটফর্মে প্রদর্শিত প্রযোজ্য নিয়ম বা বিজ্ঞপ্তি মেনে চলতে সম্মত হচ্ছেন। আপনি এসব শর্তে সম্মত না হলে অনুগ্রহ করে অ্যাকাউন্ট তৈরি বা ব্যবহার করবেন না।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="eligibility" class="terms-section">
                            <div class="section-number">02</div>
                            <div>
                                <h2><?= sm_text('Eligibility', 'যোগ্যতা'); ?></h2>
                                <p>
                                    <?= sm_text('You should use Smart Matrimony only if you are legally permitted to use
                                    an online matrimonial service in your place of residence. You are
                                    responsible for providing information that is truthful and appropriate
                                    for your circumstances.', 'আপনার বসবাসের স্থানে অনলাইন বিবাহ-সংক্রান্ত সেবা ব্যবহারের আইনগত অনুমতি থাকলেই কেবল স্মার্ট ম্যাট্রিমনি ব্যবহার করা উচিত। আপনার পরিস্থিতির সঙ্গে সামঞ্জস্যপূর্ণ ও সত্য তথ্য দেওয়ার দায়িত্ব আপনার।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="account" class="terms-section">
                            <div class="section-number">03</div>
                            <div>
                                <h2><?= sm_text('Account &amp; Registration', 'অ্যাকাউন্ট ও নিবন্ধন'); ?></h2>
                                <ul>
                                    <li><?= sm_text('Provide accurate, current and complete registration information.', 'নিবন্ধনের জন্য সঠিক, হালনাগাদ ও সম্পূর্ণ তথ্য দিন.'); ?></li>
                                    <li><?= sm_text('Keep your login credentials confidential and do not share your account.', 'আপনার লগইন তথ্য গোপন রাখুন এবং অ্যাকাউন্ট অন্য কারও সঙ্গে ভাগ করবেন না।'); ?></li>
                                    <li><?= sm_text('Use only your own email address and mobile number when registering.', 'নিবন্ধনের সময় শুধু নিজের ইমেইল ঠিকানা ও মোবাইল নম্বর ব্যবহার করুন।'); ?></li>
                                    <li><?= sm_text('Do not create accounts for another person without their knowledge and permission.', 'অন্য ব্যক্তির জ্ঞান ও অনুমতি ছাড়া তাঁর জন্য অ্যাকাউন্ট তৈরি করবেন না।'); ?></li>
                                    <li><?= sm_text('Notify Smart Matrimony if you believe your account has been accessed without authorization.', 'আপনার অনুমতি ছাড়া কেউ অ্যাকাউন্টে প্রবেশ করেছে বলে মনে হলে স্মার্ট ম্যাট্রিমনিকে জানান।'); ?></li>
                                </ul>
                            </div>
                        </section>

                        <section id="verification" class="terms-section">
                            <div class="section-number">04</div>
                            <div>
                                <h2><?= sm_text('Verification', 'যাচাইকরণ'); ?></h2>
                                <p>
                                    <?= sm_text('Smart Matrimony may use email verification, OTPs and other reasonable
                                    verification steps to help confirm account ownership and protect the
                                    platform. Verification does not guarantee that every detail supplied by
                                    a member is accurate, nor does it guarantee a particular matrimonial outcome.', 'অ্যাকাউন্টের মালিকানা নিশ্চিত ও প্ল্যাটফর্ম সুরক্ষিত রাখতে স্মার্ট ম্যাট্রিমনি ইমেইল যাচাইকরণ, OTP এবং অন্যান্য যুক্তিসংগত যাচাইকরণ পদ্ধতি ব্যবহার করতে পারে। যাচাইকরণে সদস্যের দেওয়া প্রতিটি তথ্য সঠিক—এমন নিশ্চয়তা পাওয়া যায় না; একইভাবে নির্দিষ্ট বৈবাহিক ফলাফলেরও নিশ্চয়তা দেওয়া হয় না।'); ?>
                                </p>
                                <ul>
                                    <li><?= sm_text('For verification or account-safety purposes, Smart Matrimony may contact the member through their own mobile number or email address, or through a guardian\'s mobile number or email address where such contact information has been provided.', 'যাচাইকরণ বা অ্যাকাউন্টের নিরাপত্তার প্রয়োজনে স্মার্ট ম্যাট্রিমনি সদস্যের নিজের মোবাইল নম্বর বা ইমেইলে যোগাযোগ করতে পারে। অভিভাবকের যোগাযোগের তথ্য দেওয়া থাকলে সেই নম্বর বা ইমেইলেও যোগাযোগ করা হতে পারে।'); ?></li>
                                </ul>
                            </div>
                        </section>

                        <section id="profile" class="terms-section">
                            <div class="section-number">05</div>
                            <div>
                                <h2><?= sm_text('Profiles &amp; Content', 'প্রোফাইল ও কনটেন্ট'); ?></h2>
                                <p>
                                    <?= sm_text('You are responsible for the information, photographs, preferences and
                                    other content you submit to your profile. Content should be truthful,
                                    respectful and relevant to matrimonial purposes.', 'আপনার প্রোফাইলে দেওয়া তথ্য, ছবি, পছন্দ এবং অন্যান্য কনটেন্টের জন্য আপনি দায়ী। এসব কনটেন্ট সত্য, সম্মানজনক এবং বিবাহ-সংক্রান্ত উদ্দেশ্যের সঙ্গে প্রাসঙ্গিক হওয়া উচিত।'); ?>
                                </p>
                                <div class="terms-points">
                                    <div><i class="fa-solid fa-check"></i><span><?= sm_text('Do not impersonate another person.', 'অন্য কারও পরিচয় ধারণ করবেন না।'); ?></span></div>
                                    <div><i class="fa-solid fa-check"></i><span><?= sm_text('Do not publish misleading or intentionally false information.', 'বিভ্রান্তিকর বা ইচ্ছাকৃতভাবে মিথ্যা তথ্য প্রকাশ করবেন না।'); ?></span></div>
                                    <div><i class="fa-solid fa-check"></i><span><?= sm_text('Do not upload content that violates another person\'s rights.', 'অন্য ব্যক্তির অধিকার লঙ্ঘন করে এমন কনটেন্ট আপলোড করবেন না।'); ?></span></div>
                                </div>
                            </div>
                        </section>

                        <section id="conduct" class="terms-section">
                            <div class="section-number">06</div>
                            <div>
                                <h2><?= sm_text('User Conduct', 'ব্যবহারকারীর আচরণ'); ?></h2>
                                <p>
                                    <?= sm_text('Members are expected to communicate respectfully and use Smart Matrimony
                                    only for legitimate matrimonial purposes. The following activities are
                                    not permitted:', 'সদস্যদের সম্মানজনকভাবে যোগাযোগ করা এবং স্মার্ট ম্যাট্রিমনি কেবল বৈধ বিবাহ-সংক্রান্ত উদ্দেশ্যে ব্যবহার করা প্রত্যাশিত। নিচের কাজগুলো অনুমোদিত নয়:'); ?>
                                </p>
                                <ul class="danger-list">
                                    <li><?= sm_text('Harassment, threats, hate, abusive or deliberately offensive behavior.', 'হয়রানি, হুমকি, বিদ্বেষ, গালিগালাজ বা ইচ্ছাকৃতভাবে অপমানজনক আচরণ।'); ?></li>
                                    <li><?= sm_text('Fraud, scams, financial solicitation or attempts to obtain money through deception.', 'প্রতারণা, জালিয়াতি, আর্থিক সহায়তা চাওয়া বা ছলনার মাধ্যমে অর্থ নেওয়ার চেষ্টা।'); ?></li>
                                    <li><?= sm_text('Spam, bulk messaging, scraping, automated misuse or attempts to disrupt the service.', 'স্প্যাম, একসঙ্গে অনেক বার্তা পাঠানো, তথ্য স্বয়ংক্রিয়ভাবে সংগ্রহ, স্বয়ংক্রিয় অপব্যবহার বা সেবা ব্যাহত করার চেষ্টা।'); ?></li>
                                    <li><?= sm_text('Sharing another person\'s private information without appropriate permission.', 'যথাযথ অনুমতি ছাড়া অন্য ব্যক্তির ব্যক্তিগত তথ্য শেয়ার করা।'); ?></li>
                                    <li><?= sm_text('Using the platform for unlawful, deceptive or unrelated commercial activity.', 'বেআইনি, প্রতারণামূলক বা সম্পর্কহীন বাণিজ্যিক কাজে প্ল্যাটফর্ম ব্যবহার করা।'); ?></li>
                                </ul>
                            </div>
                        </section>

                        <section id="matching" class="terms-section">
                            <div class="section-number">07</div>
                            <div>
                                <h2><?= sm_text('Matching &amp; Services', 'ম্যাচিং ও সেবা'); ?></h2>
                                <p>
                                    <?= sm_text('Smart Matrimony may provide profile discovery, matching suggestions and
                                    wedding-related services or packages. Matching results are generated from
                                    information and preferences available in the platform and should be treated
                                    as suggestions, not guarantees of compatibility or marriage.', 'স্মার্ট ম্যাট্রিমনি প্রোফাইল খোঁজা, ম্যাচিংয়ের পরামর্শ এবং বিয়েসংশ্লিষ্ট সেবা বা প্যাকেজ দিতে পারে। ম্যাচিংয়ের ফলাফল প্ল্যাটফর্মে থাকা তথ্য ও পছন্দের ভিত্তিতে তৈরি হয়; এগুলোকে উপযুক্ততা বা বিয়ের নিশ্চয়তা নয়, বরং পরামর্শ হিসেবে বিবেচনা করতে হবে।'); ?>
                                </p>
                                <p>
                                    <?= sm_text('Where third-party wedding services are offered, users should review the
                                    relevant package information, availability, pricing and provider details
                                    before making a booking or payment.', 'তৃতীয় পক্ষের বিয়েসংশ্লিষ্ট সেবা দেওয়া হলে, বুকিং বা অর্থ পরিশোধের আগে ব্যবহারকারীদের সংশ্লিষ্ট প্যাকেজের তথ্য, প্রাপ্যতা, মূল্য এবং সেবা প্রদানকারীর বিবরণ দেখে নেওয়া উচিত।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="privacy" class="terms-section">
                            <div class="section-number">08</div>
                            <div>
                                <h2><?= sm_text('Privacy &amp; Data', 'গোপনীয়তা ও তথ্য'); ?></h2>
                                <p>
                                    <?= sm_text('Information submitted to Smart Matrimony may be processed to create and
                                    manage accounts, provide matching and platform features, support
                                    verification, improve the service and communicate with users. Users should
                                    avoid placing sensitive personal information in public profile fields or
                                    messages unless they are comfortable sharing it with the intended recipient.', 'স্মার্ট ম্যাট্রিমনিতে দেওয়া তথ্য অ্যাকাউন্ট তৈরি ও পরিচালনা, ম্যাচিং ও প্ল্যাটফর্মের সুবিধা প্রদান, যাচাইকরণে সহায়তা, সেবা উন্নত করা এবং ব্যবহারকারীদের সঙ্গে যোগাযোগের জন্য প্রক্রিয়াকরণ করা হতে পারে। নির্দিষ্ট প্রাপককে জানাতে স্বচ্ছন্দ না হলে ব্যবহারকারীদের প্রকাশ্য প্রোফাইলের ঘর বা বার্তায় সংবেদনশীল ব্যক্তিগত তথ্য দেওয়া এড়িয়ে চলা উচিত।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="safety" class="terms-section">
                            <div class="section-number">09</div>
                            <div>
                                <h2><?= sm_text('Safety &amp; Reporting', 'নিরাপত্তা ও রিপোর্ট করা'); ?></h2>
                                <p>
                                    <?= sm_text('Online interactions require personal judgment and care. Do not send money,
                                    passwords, OTPs or sensitive financial information to another member. If a
                                    profile or interaction appears suspicious, inappropriate or unsafe, stop
                                    communicating and use the available reporting or support channels.', 'অনলাইনে যোগাযোগের ক্ষেত্রে ব্যক্তিগত বিবেচনা ও সতর্কতা প্রয়োজন। অন্য সদস্যকে টাকা, পাসওয়ার্ড, OTP বা সংবেদনশীল আর্থিক তথ্য পাঠাবেন না। কোনো প্রোফাইল বা যোগাযোগ সন্দেহজনক, অনুপযুক্ত বা অনিরাপদ মনে হলে যোগাযোগ বন্ধ করুন এবং উপলভ্য রিপোর্ট বা সহায়তা চ্যানেল ব্যবহার করুন।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="communications" class="terms-section">
                            <div class="section-number">10</div>
                            <div>
                                <h2><?= sm_text('Communications', 'যোগাযোগ'); ?></h2>
                                <p>
                                    <?= sm_text('By registering, you may receive essential account-related communications,
                                    such as verification messages, security notices and service updates.
                                    Promotional communications, where applicable, may be handled separately
                                    according to the options provided by the platform.', 'নিবন্ধন করলে আপনি অ্যাকাউন্ট-সম্পর্কিত প্রয়োজনীয় বার্তা পেতে পারেন, যেমন যাচাইকরণ বার্তা, নিরাপত্তা বিজ্ঞপ্তি ও সেবার হালনাগাদ। প্রযোজ্য ক্ষেত্রে প্রচারমূলক যোগাযোগ প্ল্যাটফর্মে দেওয়া বিকল্প অনুযায়ী আলাদাভাবে পরিচালিত হতে পারে।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="intellectual-property" class="terms-section">
                            <div class="section-number">11</div>
                            <div>
                                <h2><?= sm_text('Intellectual Property', 'মেধাস্বত্ব'); ?></h2>
                                <p>
                                    <?= sm_text('Smart Matrimony\'s branding, interface, original design elements, software
                                    and documentation are intended for use within the platform and may not be
                                    copied, reproduced, redistributed or commercially exploited without
                                    appropriate permission, except where applicable law permits otherwise.', 'স্মার্ট ম্যাট্রিমনির ব্র্যান্ডিং, ইন্টারফেস, মৌলিক নকশার উপাদান, সফটওয়্যার এবং ডকুমেন্টেশন প্ল্যাটফর্মে ব্যবহারের জন্য তৈরি। প্রযোজ্য আইন অন্যথা অনুমতি না দিলে যথাযথ অনুমতি ছাড়া এগুলো কপি, পুনরুৎপাদন, পুনর্বিতরণ বা বাণিজ্যিকভাবে ব্যবহার করা যাবে না।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="availability" class="terms-section">
                            <div class="section-number">12</div>
                            <div>
                                <h2><?= sm_text('Availability &amp; Changes', 'প্রাপ্যতা ও পরিবর্তন'); ?></h2>
                                <p>
                                    <?= sm_text('Features, services, matching logic, content and availability may change as
                                    Smart Matrimony develops. Temporary interruptions may also occur because of
                                    maintenance, hosting, network or other technical circumstances. We may
                                    update these Terms &amp; Conditions when the platform or its practices change.', 'স্মার্ট ম্যাট্রিমনি উন্নত হওয়ার সঙ্গে সঙ্গে এর সুবিধা, সেবা, ম্যাচিং পদ্ধতি, কনটেন্ট ও প্রাপ্যতা পরিবর্তিত হতে পারে। রক্ষণাবেক্ষণ, হোস্টিং, নেটওয়ার্ক বা অন্যান্য প্রযুক্তিগত কারণে সাময়িক বিঘ্নও ঘটতে পারে। প্ল্যাটফর্ম বা এর কার্যপদ্ধতিতে পরিবর্তন হলে আমরা এই শর্তাবলী হালনাগাদ করতে পারি।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="termination" class="terms-section">
                            <div class="section-number">13</div>
                            <div>
                                <h2><?= sm_text('Account Suspension or Termination', 'অ্যাকাউন্ট স্থগিত বা বাতিল'); ?></h2>
                                <p>
                                    <?= sm_text('An account may be restricted, suspended or terminated when necessary to
                                    protect members, the platform or the integrity of the service, including
                                    where there is a suspected violation of these terms, fraudulent activity,
                                    abuse or unauthorized use. The administrator reserves the right to suspend
                                    an account if a member engages in, causes or is reasonably suspected of
                                    causing harmful, abusive, fraudulent, unlawful or otherwise inappropriate
                                    activity on the platform.', 'সদস্য, প্ল্যাটফর্ম বা সেবার অখণ্ডতা রক্ষার প্রয়োজন হলে কোনো অ্যাকাউন্টে সীমাবদ্ধতা আরোপ, স্থগিত বা বাতিল করা হতে পারে। এর মধ্যে এই শর্ত লঙ্ঘনের সন্দেহ, প্রতারণামূলক কার্যকলাপ, অপব্যবহার বা অননুমোদিত ব্যবহার অন্তর্ভুক্ত। কোনো সদস্য প্ল্যাটফর্মে ক্ষতিকর, অপমানজনক, প্রতারণামূলক, বেআইনি বা অন্যভাবে অনুপযুক্ত কাজে জড়িত হলে, এমন কাজ ঘটালে বা যুক্তিসংগতভাবে সন্দেহ হলে প্রশাসক অ্যাকাউন্ট স্থগিত করার অধিকার সংরক্ষণ করেন।'); ?>
                                </p>
                            </div>
                        </section>

                        <section id="contact" class="terms-section terms-contact-section">
                            <div class="section-number">14</div>
                            <div>
                                <h2><?= sm_text('Contact', 'যোগাযোগ'); ?></h2>
                                <p>
                                    <?= sm_text('If you have questions about these Terms &amp; Conditions, account use or
                                    platform rules, please contact the Smart Matrimony support team through
                                    the contact options available on the website.', 'এই শর্তাবলী, অ্যাকাউন্ট ব্যবহার বা প্ল্যাটফর্মের নিয়ম সম্পর্কে প্রশ্ন থাকলে ওয়েবসাইটে দেওয়া যোগাযোগের মাধ্যম ব্যবহার করে স্মার্ট ম্যাট্রিমনি সহায়তা দলের সঙ্গে যোগাযোগ করুন।'); ?>
                                </p>
                                <div class="terms-contact-card">
                                    <i class="fa-solid fa-envelope"></i>
                                    <div class="terms-contact-email">
                                        <span><?= sm_text('Support', 'সহায়তা'); ?></span>
                                        <strong id="terms-support-email">support.smartmatrimony@gmail.com</strong>
                                    </div>
                                    <button type="button" class="terms-copy-btn" id="copy-support-email"
                                            aria-label="<?= sm_text('Copy support email', 'সহায়তা ইমেইল কপি করুন'); ?>" title="<?= sm_text('Copy email', 'ইমেইল কপি করুন'); ?>">
                                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <span class="terms-copy-status" id="terms-copy-status" aria-live="polite"></span>
                            </div>
                        </section>

                        <div class="terms-footer-note">
                            <i class="fa-solid fa-circle-info"></i>
                            <p>
                                <?= sm_text('These Terms &amp; Conditions are intended to describe the rules and expectations
                                for this Smart Matrimony project. They should be reviewed and adapted for the
                                platform\'s final production and legal requirements before public launch.', 'এই শর্তাবলীর উদ্দেশ্য হলো স্মার্ট ম্যাট্রিমনি প্রকল্পের নিয়ম ও প্রত্যাশা বর্ণনা করা। জনসাধারণের জন্য চালুর আগে প্ল্যাটফর্মের চূড়ান্ত প্রকাশনা ও আইনগত প্রয়োজন অনুযায়ী এগুলো পর্যালোচনা ও অভিযোজিত করা উচিত।'); ?>
                            </p>
                        </div>

                    </article>
                </div>

            </div>
        </div>
    </section>

</main>

<script>
(function () {
    const copyButton = document.getElementById('copy-support-email');
    const emailElement = document.getElementById('terms-support-email');
    const statusElement = document.getElementById('terms-copy-status');

    if (!copyButton || !emailElement) return;

    function showCopiedState() {
        if (statusElement) {
            statusElement.textContent = '<?= sm_text('Email copied', 'ইমেইল কপি করা হয়েছে'); ?>';
            window.setTimeout(function () { statusElement.textContent = ''; }, 1800);
        }
        copyButton.classList.add('is-copied');
        copyButton.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
        window.setTimeout(function () {
            copyButton.classList.remove('is-copied');
            copyButton.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i>';
        }, 1800);
    }

    function fallbackCopy(text) {
        const input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        input.setSelectionRange(0, input.value.length);
        let copied = false;
        try { copied = document.execCommand('copy'); } catch (error) {}
        document.body.removeChild(input);
        return copied;
    }

    copyButton.addEventListener('click', function () {
        const email = emailElement.textContent.trim();

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(email).then(showCopiedState).catch(function () {
                if (fallbackCopy(email)) showCopiedState();
            });
        } else if (fallbackCopy(email)) {
            showCopiedState();
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
