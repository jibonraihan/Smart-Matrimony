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
                    <span class="terms-eyebrow">SMART MATRIMONY • DOCUMENTATION</span>
                    <h1>Terms &amp; Conditions</h1>
                    <p>
                        Please read these terms carefully before creating an account or using
                        Smart Matrimony. They explain the basic rules, responsibilities and
                        expectations for using our matrimonial platform.
                    </p>
                    <div class="terms-meta">
                        <span><i class="fa-regular fa-calendar"></i> Last updated: September 18, 2026</span>
                        <span><i class="fa-solid fa-circle-check"></i> Clear &amp; transparent guidelines</span>
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
                            On this page
                        </div>
                        <a href="#acceptance">1. Acceptance of Terms</a>
                        <a href="#eligibility">2. Eligibility</a>
                        <a href="#account">3. Account &amp; Registration</a>
                        <a href="#verification">4. Verification</a>
                        <a href="#profile">5. Profiles &amp; Content</a>
                        <a href="#conduct">6. User Conduct</a>
                        <a href="#matching">7. Matching &amp; Services</a>
                        <a href="#privacy">8. Privacy &amp; Data</a>
                        <a href="#safety">9. Safety &amp; Reporting</a>
                        <a href="#communications">10. Communications</a>
                        <a href="#intellectual-property">11. Intellectual Property</a>
                        <a href="#availability">12. Availability &amp; Changes</a>
                        <a href="#termination">13. Account Suspension</a>
                        <a href="#contact">14. Contact</a>
                    </div>
                </aside>

                <div class="col-lg-9">
                    <article class="terms-document">

                        <div class="terms-notice">
                            <i class="fa-solid fa-shield-heart"></i>
                            <div>
                                <strong>Our goal</strong>
                                <p>
                                    Smart Matrimony is designed to support respectful, privacy-conscious
                                    and family-friendly matrimonial connections. Please use the platform
                                    honestly and treat other members with dignity.
                                </p>
                            </div>
                        </div>

                        <section id="acceptance" class="terms-section">
                            <div class="section-number">01</div>
                            <div>
                                <h2>Acceptance of Terms</h2>
                                <p>
                                    By creating an account, accessing or using Smart Matrimony, you agree
                                    to follow these Terms &amp; Conditions and any applicable rules or notices
                                    presented within the platform. If you do not agree with these terms,
                                    please do not create or use an account.
                                </p>
                            </div>
                        </section>

                        <section id="eligibility" class="terms-section">
                            <div class="section-number">02</div>
                            <div>
                                <h2>Eligibility</h2>
                                <p>
                                    You should use Smart Matrimony only if you are legally permitted to use
                                    an online matrimonial service in your place of residence. You are
                                    responsible for providing information that is truthful and appropriate
                                    for your circumstances.
                                </p>
                            </div>
                        </section>

                        <section id="account" class="terms-section">
                            <div class="section-number">03</div>
                            <div>
                                <h2>Account &amp; Registration</h2>
                                <ul>
                                    <li>Provide accurate, current and complete registration information.</li>
                                    <li>Keep your login credentials confidential and do not share your account.</li>
                                    <li>Use only your own email address and mobile number when registering.</li>
                                    <li>Do not create accounts for another person without their knowledge and permission.</li>
                                    <li>Notify Smart Matrimony if you believe your account has been accessed without authorization.</li>
                                </ul>
                            </div>
                        </section>

                        <section id="verification" class="terms-section">
                            <div class="section-number">04</div>
                            <div>
                                <h2>Verification</h2>
                                <p>
                                    Smart Matrimony may use email verification, OTPs and other reasonable
                                    verification steps to help confirm account ownership and protect the
                                    platform. Verification does not guarantee that every detail supplied by
                                    a member is accurate, nor does it guarantee a particular matrimonial outcome.
                                </p>
                                <ul>
                                    <li>For verification or account-safety purposes, Smart Matrimony may contact the member through their own mobile number or email address, or through a guardian's mobile number or email address where such contact information has been provided.</li>
                                </ul>
                            </div>
                        </section>

                        <section id="profile" class="terms-section">
                            <div class="section-number">05</div>
                            <div>
                                <h2>Profiles &amp; Content</h2>
                                <p>
                                    You are responsible for the information, photographs, preferences and
                                    other content you submit to your profile. Content should be truthful,
                                    respectful and relevant to matrimonial purposes.
                                </p>
                                <div class="terms-points">
                                    <div><i class="fa-solid fa-check"></i><span>Do not impersonate another person.</span></div>
                                    <div><i class="fa-solid fa-check"></i><span>Do not publish misleading or intentionally false information.</span></div>
                                    <div><i class="fa-solid fa-check"></i><span>Do not upload content that violates another person's rights.</span></div>
                                </div>
                            </div>
                        </section>

                        <section id="conduct" class="terms-section">
                            <div class="section-number">06</div>
                            <div>
                                <h2>User Conduct</h2>
                                <p>
                                    Members are expected to communicate respectfully and use Smart Matrimony
                                    only for legitimate matrimonial purposes. The following activities are
                                    not permitted:
                                </p>
                                <ul class="danger-list">
                                    <li>Harassment, threats, hate, abusive or deliberately offensive behavior.</li>
                                    <li>Fraud, scams, financial solicitation or attempts to obtain money through deception.</li>
                                    <li>Spam, bulk messaging, scraping, automated misuse or attempts to disrupt the service.</li>
                                    <li>Sharing another person's private information without appropriate permission.</li>
                                    <li>Using the platform for unlawful, deceptive or unrelated commercial activity.</li>
                                </ul>
                            </div>
                        </section>

                        <section id="matching" class="terms-section">
                            <div class="section-number">07</div>
                            <div>
                                <h2>Matching &amp; Services</h2>
                                <p>
                                    Smart Matrimony may provide profile discovery, matching suggestions and
                                    wedding-related services or packages. Matching results are generated from
                                    information and preferences available in the platform and should be treated
                                    as suggestions, not guarantees of compatibility or marriage.
                                </p>
                                <p>
                                    Where third-party wedding services are offered, users should review the
                                    relevant package information, availability, pricing and provider details
                                    before making a booking or payment.
                                </p>
                            </div>
                        </section>

                        <section id="privacy" class="terms-section">
                            <div class="section-number">08</div>
                            <div>
                                <h2>Privacy &amp; Data</h2>
                                <p>
                                    Information submitted to Smart Matrimony may be processed to create and
                                    manage accounts, provide matching and platform features, support
                                    verification, improve the service and communicate with users. Users should
                                    avoid placing sensitive personal information in public profile fields or
                                    messages unless they are comfortable sharing it with the intended recipient.
                                </p>
                            </div>
                        </section>

                        <section id="safety" class="terms-section">
                            <div class="section-number">09</div>
                            <div>
                                <h2>Safety &amp; Reporting</h2>
                                <p>
                                    Online interactions require personal judgment and care. Do not send money,
                                    passwords, OTPs or sensitive financial information to another member. If a
                                    profile or interaction appears suspicious, inappropriate or unsafe, stop
                                    communicating and use the available reporting or support channels.
                                </p>
                            </div>
                        </section>

                        <section id="communications" class="terms-section">
                            <div class="section-number">10</div>
                            <div>
                                <h2>Communications</h2>
                                <p>
                                    By registering, you may receive essential account-related communications,
                                    such as verification messages, security notices and service updates.
                                    Promotional communications, where applicable, may be handled separately
                                    according to the options provided by the platform.
                                </p>
                            </div>
                        </section>

                        <section id="intellectual-property" class="terms-section">
                            <div class="section-number">11</div>
                            <div>
                                <h2>Intellectual Property</h2>
                                <p>
                                    Smart Matrimony's branding, interface, original design elements, software
                                    and documentation are intended for use within the platform and may not be
                                    copied, reproduced, redistributed or commercially exploited without
                                    appropriate permission, except where applicable law permits otherwise.
                                </p>
                            </div>
                        </section>

                        <section id="availability" class="terms-section">
                            <div class="section-number">12</div>
                            <div>
                                <h2>Availability &amp; Changes</h2>
                                <p>
                                    Features, services, matching logic, content and availability may change as
                                    Smart Matrimony develops. Temporary interruptions may also occur because of
                                    maintenance, hosting, network or other technical circumstances. We may
                                    update these Terms &amp; Conditions when the platform or its practices change.
                                </p>
                            </div>
                        </section>

                        <section id="termination" class="terms-section">
                            <div class="section-number">13</div>
                            <div>
                                <h2>Account Suspension or Termination</h2>
                                <p>
                                    An account may be restricted, suspended or terminated when necessary to
                                    protect members, the platform or the integrity of the service, including
                                    where there is a suspected violation of these terms, fraudulent activity,
                                    abuse or unauthorized use. The administrator reserves the right to suspend
                                    an account if a member engages in, causes or is reasonably suspected of
                                    causing harmful, abusive, fraudulent, unlawful or otherwise inappropriate
                                    activity on the platform.
                                </p>
                            </div>
                        </section>

                        <section id="contact" class="terms-section terms-contact-section">
                            <div class="section-number">14</div>
                            <div>
                                <h2>Contact</h2>
                                <p>
                                    If you have questions about these Terms &amp; Conditions, account use or
                                    platform rules, please contact the Smart Matrimony support team through
                                    the contact options available on the website.
                                </p>
                                <div class="terms-contact-card">
                                    <i class="fa-solid fa-envelope"></i>
                                    <div class="terms-contact-email">
                                        <span>Support</span>
                                        <strong id="terms-support-email">support.smartmatrimony@gmail.com</strong>
                                    </div>
                                    <button type="button" class="terms-copy-btn" id="copy-support-email"
                                            aria-label="Copy support email" title="Copy email">
                                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <span class="terms-copy-status" id="terms-copy-status" aria-live="polite"></span>
                            </div>
                        </section>

                        <div class="terms-footer-note">
                            <i class="fa-solid fa-circle-info"></i>
                            <p>
                                These Terms &amp; Conditions are intended to describe the rules and expectations
                                for this Smart Matrimony project. They should be reviewed and adapted for the
                                platform's final production and legal requirements before public launch.
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
            statusElement.textContent = 'Email copied';
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
