<?php
// Footer contact / feedback form handler.
$footer_form_type = trim($_POST['footer_form_type'] ?? '');
$footer_form_name = trim($_POST['footer_name'] ?? '');
$footer_form_email = trim($_POST['footer_email'] ?? '');
$footer_form_message = trim($_POST['footer_message'] ?? '');
$footer_form_success = false;
$footer_form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($footer_form_type, ['Contact', 'Feedback'], true)) {
    if ($footer_form_name === '' || mb_strlen($footer_form_name) > 100) {
        $footer_form_error = 'সঠিক নাম লিখুন।';
    } elseif (!filter_var($footer_form_email, FILTER_VALIDATE_EMAIL) || mb_strlen($footer_form_email) > 150) {
        $footer_form_error = 'সঠিক ইমেইল ঠিকানা লিখুন।';
    } elseif ($footer_form_message === '' || mb_strlen($footer_form_message) > 2000) {
        $footer_form_error = 'আপনার বার্তা লিখুন (সর্বোচ্চ ২০০০ অক্ষর)।';
    } else {
        require_once __DIR__ . '/../config/db.php';
        $footer_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $stmt = mysqli_prepare($conn, 'INSERT INTO site_messages (message_type, name, email, message, user_id) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssi', $footer_form_type, $footer_form_name, $footer_form_email, $footer_form_message, $footer_user_id);
            if (mysqli_stmt_execute($stmt)) {
                $footer_form_success = true;
            } else {
                $footer_form_error = 'এই মুহূর্তে বার্তা পাঠানো যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।';
            }
            mysqli_stmt_close($stmt);
        } else {
            $footer_form_error = 'এই মুহূর্তে বার্তা পাঠানো যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।';
        }
    }
}
?>

<?php if ($footer_form_success): ?>
    <div class="container" role="status"><div class="alert alert-success" lang="bn">আপনার বার্তা সফলভাবে পাঠানো হয়েছে।</div></div>
<?php elseif ($footer_form_error !== ''): ?>
    <div class="container" role="alert"><div class="alert alert-danger" lang="bn"><?= htmlspecialchars($footer_form_error, ENT_QUOTES, 'UTF-8'); ?></div></div>
<?php endif; ?>

<?php
require_once __DIR__ . '/language.php';
if ($current_language === 'en') { include __DIR__ . '/footer_en.php'; return; }
?>

<!-- =========================
     SMART MATRIMONY FOOTER
========================== -->

<footer class="site-footer">

    <div class="container">

        <div class="footer-main">

            <!-- BRAND -->
            <div class="footer-brand">
                <a href="<?= BASE_URL; ?>" class="footer-brand-link">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="স্মার্ট ম্যাট্রিমনি লোগো" class="footer-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="স্মার্ট ম্যাট্রিমনি" class="footer-title-logo">
                </a>

                <p class="footer-description">
                    বিশ্বাস, মর্যাদা ও সম্মানের সঙ্গে উপযুক্ত জীবনসঙ্গী খুঁজে পেতে ব্যক্তি ও পরিবারগুলোর জন্য তৈরি একটি আধুনিক ও গোপনীয়তাকেন্দ্রিক ম্যাট্রিমনি প্ল্যাটফর্ম।
                </p>

                <div class="footer-values">
                    <span><i class="fa-solid fa-shield-heart"></i> নিরাপদ</span>
                    <span><i class="fa-solid fa-lock"></i> গোপনীয়তাকে গুরুত্ব দেয়</span>
                    <span><i class="fa-solid fa-heart"></i> সম্মানজনক</span>
                    <span><i class="fa-solid fa-users"></i> পরিবারবান্ধব</span>
                </div>
            </div>

            <!-- QUICK LINKS -->
            <div class="footer-links">
                <h3>দ্রুত লিংক</h3>
                <a href="<?= BASE_URL; ?>"><i class="fa-solid fa-house"></i> হোম</a>
                <a href="<?= BASE_URL; ?>login.php"><i class="fa-solid fa-right-to-bracket"></i> লগইন</a>
                <a href="<?= BASE_URL; ?>register.php"><i class="fa-solid fa-user-plus"></i> অ্যাকাউন্ট তৈরি করুন</a>
            </div>

            <!-- IMPORTANT LINKS -->
            <div class="footer-links">
                <h3>গুরুত্বপূর্ণ লিংক</h3>
                <a href="<?= BASE_URL; ?>terms.php"><i class="fa-regular fa-file-lines"></i> শর্তাবলি</a>
                <a href="<?= BASE_URL; ?>how_it_works.php">
                    <i class="fa-regular fa-circle-question"></i> যেভাবে কাজ করে
                </a>
            </div>

            <!-- SUPPORT -->
            <div class="footer-links footer-support-links">
                <h3>সহায়তা</h3>
                <a href="mailto:support.smartmatrimony@gmail.com" class="footer-email-link">
                    <i class="fa-solid fa-envelope"></i>
                    <span>support.smartmatrimony@gmail.com</span>
                </a>
                <a href="#" class="footer-action-link" data-footer-modal="contact">
                    <i class="fa-solid fa-headset"></i> Contact Us
                </a>
                <a href="#" class="footer-action-link" data-footer-modal="feedback">
                    <i class="fa-regular fa-message"></i> Send Feedback
                </a>
                <span class="footer-static-link footer-support-time">
                    <i class="fa-regular fa-clock"></i> সহায়তার সময়: সকাল ১০টা - রাত ১০টা (বাংলাদেশ সময়)
                </span>
            </div>

            <!-- FOLLOW / NEWSLETTER -->
            <div class="footer-follow-column">
                <div class="footer-follow">
                    <h4>আমাদের অনুসরণ করুন</h4>
                    <div class="footer-socials" aria-label="সামাজিক যোগাযোগমাধ্যম">
                        <span class="footer-social footer-social-facebook" title="Facebook"><i class="fa-brands fa-facebook-f"></i></span>
                        <span class="footer-social footer-social-instagram" title="Instagram"><i class="fa-brands fa-instagram"></i></span>
                        <span class="footer-social footer-social-youtube" title="YouTube"><i class="fa-brands fa-youtube"></i></span>
                        <span class="footer-social footer-social-linkedin" title="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></span>
                    </div>
                </div>

                <div class="footer-newsletter">
                    <h4>আপডেট পেতে থাকুন</h4>
                    <p>গুরুত্বপূর্ণ আপডেট ও পরামর্শ পান।</p>
                    <div class="footer-newsletter-form">
                        <input type="email" aria-label="আপনার ইমেইল ঠিকানা" placeholder="আপনার ইমেইল ঠিকানা">
                        <button type="button" aria-label="সাবস্ক্রাইব করুন">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================
             FOOTER BOTTOM BAR
        ========================== -->

        <div class="footer-bottom-combined">

            <span class="footer-bottom-copy">
                © <?= date('Y'); ?> Smart Matrimony. সর্বস্বত্ব সংরক্ষিত।
            </span>

            <div class="footer-bottom-trust">
                <span class="footer-trust-item">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>বিশ্বাসকে গুরুত্ব দিয়ে তৈরি</span>
                </span>

                <span class="footer-trust-divider"></span>

                <span class="footer-trust-item">
                    <i class="fa-solid fa-heart"></i>
                    <span>মর্যাদা ও সম্মানকে ভিত্তি করে তৈরি</span>
                </span>
            </div>

            <span class="footer-bottom-tagline">
                অর্থবহ সম্পর্ক গড়ার জন্য তৈরি।
            </span>

        </div>

    </div>

</footer>


<!-- =========================
     CONTACT / FEEDBACK MODAL
========================== -->

<div class="footer-form-modal" id="footerFormModal" aria-hidden="true">
    <div class="footer-form-backdrop" data-footer-close></div>
    <div class="footer-form-dialog" role="dialog" aria-modal="true" aria-labelledby="footerFormTitle">
        <button type="button" class="footer-form-close" data-footer-close aria-label="বন্ধ করুন">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="footer-form-icon" id="footerFormIcon">
            <i class="fa-solid fa-headset"></i>
        </div>
        <span class="footer-form-kicker">SMART MATRIMONY</span>
        <h2 id="footerFormTitle">যোগাযোগ করুন</h2>
        <p id="footerFormSubtitle">কোনো প্রশ্ন আছে বা সহায়তা প্রয়োজন? আমাদের বার্তা পাঠান।</p>

        <form method="post" class="footer-message-form" id="footerMessageForm">
            <input type="hidden" name="footer_form_type" id="footerFormType" value="Contact">

            <div class="footer-form-grid">
                <div class="footer-field">
                    <label for="footerName">নাম</label>
                    <div class="footer-input-wrap">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="footerName" name="footer_name" maxlength="100" required value="<?= htmlspecialchars($_POST['footer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="footer-field">
                    <label for="footerEmail">ইমেইল</label>
                    <div class="footer-input-wrap">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="footerEmail" name="footer_email" maxlength="150" required value="<?= htmlspecialchars($_POST['footer_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </div>

            <div class="footer-field">
                <label for="footerMessage">বার্তা</label>
                <textarea id="footerMessage" name="footer_message" maxlength="2000" rows="5" required placeholder="এখানে আপনার বার্তা লিখুন..."><?= htmlspecialchars($_POST['footer_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="footer-character-count"><span id="footerMessageCount">0</span>/2000</div>
            </div>

            <button type="submit" class="footer-submit-btn">
                <i class="fa-solid fa-paper-plane"></i>
                <span>বার্তা পাঠান</span>
            </button>
        </form>
    </div>
</div>


<!-- Bootstrap JS -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>


<script>
(function () {
    const modal = document.getElementById('footerFormModal');
    const form = document.getElementById('footerMessageForm');
    const typeInput = document.getElementById('footerFormType');
    const title = document.getElementById('footerFormTitle');
    const subtitle = document.getElementById('footerFormSubtitle');
    const icon = document.getElementById('footerFormIcon');
    const count = document.getElementById('footerMessageCount');
    const textarea = document.getElementById('footerMessage');

    function openModal(type) {
        if (!modal) return;
        const isFeedback = type === 'feedback';
        typeInput.value = isFeedback ? 'Feedback' : 'Contact';
        title.textContent = isFeedback ? 'আপনার মতামত জানান' : 'যোগাযোগ করুন';
        subtitle.textContent = isFeedback
            ? 'আপনার মতামত আমাদের কাছে গুরুত্বপূর্ণ। স্মার্ট ম্যাট্রিমনিকে কীভাবে আরও উন্নত করা যায়, জানান।'
            : 'কোনো প্রশ্ন আছে বা সহায়তা প্রয়োজন? আমাদের বার্তা পাঠান।';
        icon.innerHTML = isFeedback
            ? '<i class="fa-regular fa-message"></i>'
            : '<i class="fa-solid fa-headset"></i>';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('footer-modal-open');
        setTimeout(() => document.getElementById('footerName')?.focus(), 120);
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('footer-modal-open');
    }

    document.querySelectorAll('[data-footer-modal]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openModal(trigger.dataset.footerModal);
        });
    });

    document.querySelectorAll('[data-footer-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });

    function updateCount() {
        if (textarea && count) count.textContent = textarea.value.length;
    }
    textarea?.addEventListener('input', updateCount);
    updateCount();

    <?php if ($footer_form_success): ?>
    if (window.Swal) {
        Swal.fire({
            icon: 'success',
            title: 'Message Submitted',
            text: <?= json_encode($footer_form_type === 'Feedback' ? 'Thank you for your feedback. We appreciate you taking the time to share it with us.' : 'We will contract you as soon as possible through your email. Thank You'); ?>,
            confirmButtonColor: '#1d756e'
        });
    }
    <?php elseif ($footer_form_error): ?>
    if (window.Swal) {
        Swal.fire({
            icon: 'error',
            title: 'Unable to Submit',
            text: <?= json_encode($footer_form_error); ?>,
            confirmButtonColor: '#1d756e'
        });
    }
    <?php endif; ?>
})();
</script>

</body>

</html>


<!-- AOS -->

<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

<script>
AOS.init({
    duration: 800,
    once: true
});
</script>