(function () {
    'use strict';

    function initChatMenu() {
        var trigger = document.querySelector('.menu-toggle, .navbar-toggler, [aria-label*="menu" i]');
        if (!trigger) return;

        var menu = document.getElementById('chatQuickMenu');
        var overlay = document.getElementById('chatMenuOverlay');

        if (!menu) {
            overlay = document.createElement('div');
            overlay.id = 'chatMenuOverlay';
            overlay.className = 'chat-menu-overlay';

            menu = document.createElement('aside');
            menu.id = 'chatQuickMenu';
            menu.className = 'chat-quick-menu';
            menu.setAttribute('aria-hidden', 'true');
            menu.innerHTML = `
                <div class="chat-menu-head">
                    <div><span>SMART MATRIMONY</span><strong>Quick Navigation</strong></div>
                    <button type="button" class="chat-menu-close" aria-label="Close menu">&times;</button>
                </div>
                <nav class="chat-menu-links">
                    <a href="../dashboard.php"><i class="fa-solid fa-grid-2"></i><span>Dashboard</span></a>
                    <a href="../profile/view_profile.php"><i class="fa-solid fa-user-circle"></i><span>My Profile</span></a>
                    <a href="../dashboard.php#partner-search"><i class="fa-solid fa-magnifying-glass"></i><span>Find Partner</span></a>
                    <a href="my_matches.php"><i class="fa-solid fa-heart"></i><span>My Matches</span></a>
                    <a href="bookmarks.php"><i class="fa-solid fa-bookmark"></i><span>Bookmarks</span></a>
                    <a href="chat_requests.php"><i class="fa-solid fa-comments"></i><span>Messages</span></a>
                    <a href="../cart.php"><i class="fa-solid fa-cart-shopping"></i><span>My Service Cart</span></a>
                    <a href="../my_bookings.php"><i class="fa-solid fa-calendar-check"></i><span>My Bookings</span></a>
                </nav>
                <a href="../logout.php" class="chat-menu-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
            `;
            document.body.appendChild(overlay);
            document.body.appendChild(menu);
        }

        var close = menu.querySelector('.chat-menu-close');
        function openMenu(e) {
            if (e) e.preventDefault();
            menu.classList.add('open');
            overlay.classList.add('show');
            menu.setAttribute('aria-hidden', 'false');
            document.body.classList.add('chat-menu-open');
        }
        function closeMenu() {
            menu.classList.remove('open');
            overlay.classList.remove('show');
            menu.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('chat-menu-open');
        }

        trigger.addEventListener('click', openMenu);
        if (close) close.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initChatMenu);
    else initChatMenu();
})();
