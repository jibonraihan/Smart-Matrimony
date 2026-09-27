<?php
// Shared visual header for protected Admin modules. Authentication remains in admin_guard.php.
if (!function_exists('admin_header_h')) {
    function admin_header_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
$admin_header_title = $admin_header_title ?? 'Admin Control Center';
$admin_header_subtitle = $admin_header_subtitle ?? 'Smart Matrimony administration';
?>
<header class="admin-topbar">
  <div class="admin-brand" aria-label="Smart Matrimony">
    <img src="../assets/images/logo/logo.png" alt="Smart Matrimony logo" class="admin-logo">
    <img src="../assets/images/logo/matrimony_title.png" alt="Smart Matrimony" class="admin-title-logo">
  </div>

  <div class="admin-header-actions">
    <?php if (!empty($admin_show_staff_messages)): ?>
      <a href="staff_messages.php" class="admin-staff-messages-btn" aria-label="Staff Messages">
        <span class="admin-staff-messages-icon" aria-hidden="true">✉</span>
        <span>Staff Messages</span>
        <?php if (!empty($admin_staff_unread_count)): ?>
          <span class="admin-staff-messages-badge"><?=admin_header_h($admin_staff_unread_count > 99 ? '99+' : $admin_staff_unread_count)?></span>
        <?php endif; ?>
      </a>
    <?php endif; ?>

    <a href="logout.php" class="admin-logout-btn" aria-label="Logout">
      <svg class="admin-logout-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M10 5H6.8A1.8 1.8 0 0 0 5 6.8v10.4A1.8 1.8 0 0 0 6.8 19H10" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
        <path d="M13 8l4 4-4 4M9 12h8" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>Logout</span>
    </a>
  </div>
</header>
