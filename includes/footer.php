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
        $footer_form_error = 'Please enter a valid name.';
    } elseif (!filter_var($footer_form_email, FILTER_VALIDATE_EMAIL) || mb_strlen($footer_form_email) > 150) {
        $footer_form_error = 'Please enter a valid email address.';
    } elseif ($footer_form_message === '' || mb_strlen($footer_form_message) > 2000) {
        $footer_form_error = 'Please enter your message (maximum 2000 characters).';
    } else {
        require_once __DIR__ . '/../config/db.php';
        $footer_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $stmt = mysqli_prepare($conn, 'INSERT INTO site_messages (message_type, name, email, message, user_id) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssi', $footer_form_type, $footer_form_name, $footer_form_email, $footer_form_message, $footer_user_id);
            if (mysqli_stmt_execute($stmt)) {
                $footer_form_success = true;
            } else {
                $footer_form_error = 'We could not submit your message right now. Please try again.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $footer_form_error = 'We could not submit your message right now. Please try again.';
        }
    }
}
?>

<!-- =========================
     SMART MATRIMONY FOOTER
========================== -->

<footer class="site-footer">

    <div class="container">

        <div class="footer-main">


            <!-- =========================
                 BRAND
            ========================== -->

            <div class="footer-brand">

                <a
                    href="<?= BASE_URL; ?>"
                    class="footer-brand-link"
                >

                    <img
                        src="<?= BASE_URL; ?>assets/images/logo/logo.png"
                        alt="Smart Matrimony Logo"
                        class="footer-logo"
                    >

                    <span>
                        Smart Matrimony
                    </span>

                </a>


                <p class="footer-description">

                    A modern, privacy-focused matrimony platform
                    designed to help individuals and families
                    discover meaningful connections with trust,
                    dignity and respect.

                </p>


                <div class="footer-values">

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


            <!-- =========================
                 QUICK LINKS
            ========================== -->

            <div class="footer-links">

                <h3>Quick Links</h3>

                <a href="<?= BASE_URL; ?>">
                    <i class="fa-solid fa-house"></i> Home
                </a>

                <a href="<?= BASE_URL; ?>login.php">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </a>

                <a href="<?= BASE_URL; ?>register.php">
                    <i class="fa-solid fa-user-plus"></i> Create Account
                </a>

            </div>


            <!-- =========================
                 SUPPORT & CONTACT
            ========================== -->

            <div class="footer-links footer-support-links">

                <h3>Support</h3>

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

            </div>
        </div>


        <!-- =========================
             TRUST BAR
        ========================== -->

        <div class="footer-trust">

            <div class="footer-trust-item">

                <i class="fa-solid fa-shield-halved"></i>

                <span>
                    Designed with trust in mind
                </span>

            </div>


            <div class="footer-trust-divider"></div>


            <div class="footer-trust-item">

                <i class="fa-solid fa-heart"></i>

                <span>
                    Built around dignity and respect
                </span>

            </div>

        </div>


        <!-- =========================
             BOTTOM BAR
        ========================== -->

        <div class="footer-bottom">

            <span>

                © <?= date('Y'); ?> Smart Matrimony.
                All rights reserved.

            </span>


            <span>

                Built for meaningful connections.

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
        <button type="button" class="footer-form-close" data-footer-close aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="footer-form-icon" id="footerFormIcon">
            <i class="fa-solid fa-headset"></i>
        </div>
        <span class="footer-form-kicker">SMART MATRIMONY</span>
        <h2 id="footerFormTitle">Contact Us</h2>
        <p id="footerFormSubtitle">Have a question or need assistance? Send us a message.</p>

        <form method="post" class="footer-message-form" id="footerMessageForm">
            <input type="hidden" name="footer_form_type" id="footerFormType" value="Contact">

            <div class="footer-form-grid">
                <div class="footer-field">
                    <label for="footerName">Name</label>
                    <div class="footer-input-wrap">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="footerName" name="footer_name" maxlength="100" required value="<?= htmlspecialchars($_POST['footer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="footer-field">
                    <label for="footerEmail">Email</label>
                    <div class="footer-input-wrap">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="footerEmail" name="footer_email" maxlength="150" required value="<?= htmlspecialchars($_POST['footer_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </div>

            <div class="footer-field">
                <label for="footerMessage">Message</label>
                <textarea id="footerMessage" name="footer_message" maxlength="2000" rows="5" required placeholder="Write your message here..."><?= htmlspecialchars($_POST['footer_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="footer-character-count"><span id="footerMessageCount">0</span>/2000</div>
            </div>

            <button type="submit" class="footer-submit-btn">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Submit Message</span>
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
        title.textContent = isFeedback ? 'Share Your Feedback' : 'Contact Us';
        subtitle.textContent = isFeedback
            ? 'We value your thoughts. Tell us how we can improve Smart Matrimony.'
            : 'Have a question or need assistance? Send us a message.';
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