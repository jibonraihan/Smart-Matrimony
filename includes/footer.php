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

                <h3>
                    Quick Links
                </h3>

                <a href="<?= BASE_URL; ?>">
                    Home
                </a>

                <a href="<?= BASE_URL; ?>login.php">
                    Login
                </a>

                <a href="<?= BASE_URL; ?>register.php">
                    Create Account
                </a>

            </div>


            <!-- =========================
                 GET STARTED
            ========================== -->

            <div class="footer-links">

                <h3>
                    Get Started
                </h3>

                <a href="<?= BASE_URL; ?>register.php">
                    Join Smart Matrimony
                </a>

                <a href="<?= BASE_URL; ?>login.php">
                    Access Your Account
                </a>

                <span class="footer-static-link">

                    <i class="fa-solid fa-lock"></i>

                    Privacy-focused experience

                </span>

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


<!-- Bootstrap JS -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>


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