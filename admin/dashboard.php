<?php
// Dashboard is the Admin overview and central platform control surface.
// Detailed account and operational actions live in their dedicated modules.
require_once __DIR__ . '/admin_guard.php';

function scalar($conn, $sql){
  $r = mysqli_query($conn, $sql);
  $x = mysqli_fetch_row($r);
  return (int) ($x[0] ?? 0);
}

$message = '';
$error = '';

$maintenance_state = smart_get_maintenance_state();
$maintenance_mode = $maintenance_state['enabled'];
$maintenance_expires_at = $maintenance_state['expires_at'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string) ($_POST['action'] ?? '');

  if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
    $error = 'Security check failed. Please refresh the page and try again.';
  } elseif ($action === 'set_maintenance_mode') {
    $requested_state = ($_POST['maintenance_state'] ?? '') === 'on';

    if ($requested_state) {
      $duration_value = filter_input(INPUT_POST, 'maintenance_duration', FILTER_VALIDATE_INT);
      $duration_unit = (string) ($_POST['maintenance_duration_unit'] ?? 'minutes');
      $duration_value = is_int($duration_value) ? $duration_value : 0;
      $multiplier = $duration_unit === 'hours' ? 3600 : 60;
      $duration_seconds = $duration_value * $multiplier;

      if ($duration_value < 1 || $duration_seconds > 7 * 24 * 3600) {
        $error = 'Please set a maintenance duration between 1 minute and 7 days.';
      } else {
        $maintenance_expires_at = time() + $duration_seconds;
        if (smart_set_maintenance_mode(true, $maintenance_expires_at)) {
          $maintenance_mode = true;
          $maintenance_state = ['enabled' => true, 'expires_at' => $maintenance_expires_at];
          $message = 'Maintenance mode is now ON. The site will return automatically when the timer ends.';
        } else {
          $error = 'The maintenance mode setting could not be saved. Please check the server folder permissions.';
        }
      }
    } else {
      if (smart_set_maintenance_mode(false, null)) {
        $maintenance_mode = false;
        $maintenance_expires_at = null;
        $maintenance_state = ['enabled' => false, 'expires_at' => null];
        $message = 'Maintenance mode is now OFF. The site is live again for users.';
      } else {
        $error = 'The maintenance mode setting could not be saved. Please check the server folder permissions.';
      }
    }
  }
}

/*
 * Staff presentation data is intentionally kept separate from user_profiles.
 * Admins, Managers and Authenticators share the same staff profile structure.
 * Common identity data (name, email, gender) remains in users; this table
 * stores presentation-only data such as the staff profile image.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string) ($_POST['action'] ?? '');

  if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
    $error = 'Security check failed. Please refresh the page and try again.';
  } elseif ($action === 'update_admin_photo') {
    if (empty($_FILES['profile_image']) || ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      $error = 'Please choose an image first.';
    } else {
      $file = $_FILES['profile_image'];
      $upload_error = (int) ($file['error'] ?? UPLOAD_ERR_OK);

      if ($upload_error !== UPLOAD_ERR_OK) {
        $error = 'The image upload failed. Please try again.';
      } elseif ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'Profile image must be 5 MB or less.';
      } else {
        $info = @getimagesize($file['tmp_name']);
        $mime = (string) ($info['mime'] ?? '');
        $allowed = [
          'image/jpeg' => 'jpg',
          'image/png'  => 'png',
          'image/webp' => 'webp'
        ];

        if (!$info || !isset($allowed[$mime])) {
          $error = 'Only JPG, PNG or WebP images are allowed.';
        } else {
          $upload_dir = __DIR__ . '/../uploads/staff/';
          if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true)) {
            $error = 'The Admin image folder could not be created.';
          } else {
            $new_name = 'STAFF_' . $admin_id . '_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
            $destination = $upload_dir . $new_name;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
              $error = 'The image could not be saved. Please try again.';
            } else {
              $old_name = '';
              $find = mysqli_prepare($conn, 'SELECT profile_image FROM staff_profiles WHERE user_id=? LIMIT 1');
              mysqli_stmt_bind_param($find, 'i', $admin_id);
              mysqli_stmt_execute($find);
              $old_row = mysqli_fetch_assoc(mysqli_stmt_get_result($find));
              mysqli_stmt_close($find);
              $old_name = trim((string) ($old_row['profile_image'] ?? ''));

              $save = mysqli_prepare($conn, '
                INSERT INTO staff_profiles (user_id, profile_image)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE profile_image=VALUES(profile_image), updated_at=CURRENT_TIMESTAMP
              ');
              mysqli_stmt_bind_param($save, 'is', $admin_id, $new_name);

              if (mysqli_stmt_execute($save)) {
                mysqli_stmt_close($save);
                if ($old_name !== '') {
                  $old_path = $upload_dir . basename($old_name);
                  if (is_file($old_path)) @unlink($old_path);
                }
                $message = 'Profile image updated successfully.';
              } else {
                mysqli_stmt_close($save);
                @unlink($destination);
                $error = 'Unable to save the Admin profile image.';
              }
            }
          }
        }
      }
    }
  }
}

$admin_profile_image = '';
$profile_stmt = mysqli_prepare($conn, 'SELECT profile_image FROM staff_profiles WHERE user_id=? LIMIT 1');
mysqli_stmt_bind_param($profile_stmt, 'i', $admin_id);
mysqli_stmt_execute($profile_stmt);
$admin_profile = mysqli_fetch_assoc(mysqli_stmt_get_result($profile_stmt));
mysqli_stmt_close($profile_stmt);
$admin_profile_image = trim((string) ($admin_profile['profile_image'] ?? ''));
$admin_profile_image_exists = $admin_profile_image !== '' && is_file(__DIR__ . '/../uploads/staff/' . basename($admin_profile_image));

$admin_stmt = mysqli_prepare($conn, 'SELECT first_name,last_name,email,gender FROM users WHERE user_id=? AND role="Admin" LIMIT 1');
mysqli_stmt_bind_param($admin_stmt, 'i', $admin_id);
mysqli_stmt_execute($admin_stmt);
$admin_record = mysqli_fetch_assoc(mysqli_stmt_get_result($admin_stmt));
mysqli_stmt_close($admin_stmt);

$admin_first_name = trim((string) ($admin_record['first_name'] ?? 'Administrator'));
$admin_last_name = trim((string) ($admin_record['last_name'] ?? ''));
$admin_full_name = trim($admin_first_name . ' ' . $admin_last_name);
$admin_email = (string) ($admin_record['email'] ?? '');
$admin_gender = (string) ($admin_record['gender'] ?? '');
$admin_public_id = 'SM-' . str_pad((string) $admin_id, 6, '0', STR_PAD_LEFT);

$support_total = scalar($conn, "SELECT COUNT(*) FROM site_messages");
$support_new = scalar($conn, "SELECT COUNT(*) FROM site_messages WHERE status='New'");


$stats = [
  'accounts' => [
    ['label'=>'Total Accounts','value'=>scalar($conn,"SELECT COUNT(*) FROM users"),'icon'=>'users'],
    ['label'=>'Users','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='User'"),'icon'=>'user'],
    ['label'=>'Managers','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='Manager'"),'icon'=>'briefcase'],
    ['label'=>'Authenticators','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='Authenticator'"),'icon'=>'shield'],
    ['label'=>'Admins','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='Admin'"),'icon'=>'admin'],
    ['label'=>'Active Accounts','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE account_status='Active'"),'icon'=>'active'],
    ['label'=>'Inactive Accounts','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE account_status='Inactive'"),'icon'=>'inactive'],
    ['label'=>'Suspended Accounts','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE account_status='Suspended'"),'icon'=>'suspended'],
    ['label'=>'Male Users','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='User' AND gender='Male'"),'icon'=>'male'],
    ['label'=>'Female Users','value'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='User' AND gender='Female'"),'icon'=>'female'],
    ['label'=>'Users With Profiles','value'=>scalar($conn,"SELECT COUNT(DISTINCT u.user_id) FROM users u INNER JOIN user_profiles p ON p.user_id=u.user_id WHERE u.role='User'"),'icon'=>'profile-user'],
    ['label'=>'Users Without Profiles','value'=>scalar($conn,"SELECT COUNT(*) FROM users u WHERE u.role='User' AND NOT EXISTS (SELECT 1 FROM user_profiles p WHERE p.user_id=u.user_id)"),'icon'=>'profile-missing'],
  ],
  'profiles' => [
    ['label'=>'Total Profiles','value'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles"),'icon'=>'profile'],
    ['label'=>'Pending Verification','value'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles WHERE verification_status='Pending'"),'icon'=>'pending'],
    ['label'=>'Verified Profiles','value'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles WHERE verification_status='Verified'"),'icon'=>'verified'],
    ['label'=>'Rejected Profiles','value'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles WHERE verification_status='Rejected'"),'icon'=>'rejected'],
    ['label'=>'Public Profiles','value'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles WHERE profile_visibility='Public'"),'icon'=>'public'],
    ['label'=>'Hidden Profiles','value'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles WHERE profile_visibility='Hidden'"),'icon'=>'hidden'],
    ['label'=>'Profiles With Photo','value'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles WHERE photo IS NOT NULL AND photo<>''"),'icon'=>'photo'],
    ['label'=>'Photo Visible','value'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles WHERE photo_visibility='Everyone'"),'icon'=>'camera'],
    ['label'=>'Voice Introductions','value'=>scalar($conn,"SELECT COUNT(*) FROM profile_media WHERE media_type='Voice Introduction' AND status='Active'"),'icon'=>'voice'],
    ['label'=>'Video Introductions','value'=>scalar($conn,"SELECT COUNT(*) FROM profile_media WHERE media_type='Video Introduction' AND status='Active'"),'icon'=>'video'],
    ['label'=>'Search Preferences','value'=>scalar($conn,"SELECT COUNT(*) FROM search_preferences"),'icon'=>'preference'],
    ['label'=>'Health Profiles','value'=>scalar($conn,"SELECT COUNT(*) FROM health_profiles"),'icon'=>'health'],
    ['label'=>'Trait Answers','value'=>scalar($conn,"SELECT COUNT(*) FROM user_trait_answers"),'icon'=>'trait'],
    ['label'=>'Trait Questions','value'=>scalar($conn,"SELECT COUNT(*) FROM trait_questions"),'icon'=>'question'],
  ],
  'matching' => [
    ['label'=>'Pending Interests','value'=>scalar($conn,"SELECT COUNT(*) FROM matches WHERE status='Pending' AND relationship_active=1"),'icon'=>'heart'],
    ['label'=>'Accepted Matches','value'=>scalar($conn,"SELECT COUNT(*) FROM matches WHERE status='Accepted' AND relationship_active=1"),'icon'=>'match'],
    ['label'=>'Rejected Interests','value'=>scalar($conn,"SELECT COUNT(*) FROM matches WHERE status='Rejected'"),'icon'=>'reject'],
    ['label'=>'Cancelled Interests','value'=>scalar($conn,"SELECT COUNT(*) FROM matches WHERE status='Cancelled'"),'icon'=>'cancel'],
    ['label'=>'Active Matched Profiles','value'=>scalar($conn,"SELECT COUNT(*) FROM (SELECT sender_user_id AS user_id FROM matches WHERE status='Accepted' AND relationship_active=1 UNION SELECT receiver_user_id AS user_id FROM matches WHERE status='Accepted' AND relationship_active=1) AS matched_users"),'icon'=>'couple'],
    ['label'=>'Bookmarks','value'=>scalar($conn,"SELECT COUNT(*) FROM bookmarks"),'icon'=>'bookmark'],
    ['label'=>'Chat Requests','value'=>scalar($conn,"SELECT COUNT(*) FROM chat_requests"),'icon'=>'chat'],
    ['label'=>'Pending Chat Requests','value'=>scalar($conn,"SELECT COUNT(*) FROM chat_requests WHERE status='Pending'"),'icon'=>'chat-pending'],
    ['label'=>'Accepted Chat Requests','value'=>scalar($conn,"SELECT COUNT(*) FROM chat_requests WHERE status='Accepted'"),'icon'=>'chat-accepted'],
    ['label'=>'Active Conversations','value'=>scalar($conn,"SELECT COUNT(*) FROM conversations WHERE status='Active'"),'icon'=>'conversation'],
    ['label'=>'Closed Conversations','value'=>scalar($conn,"SELECT COUNT(*) FROM conversations WHERE status='Closed'"),'icon'=>'conversation-closed'],
    ['label'=>'Conversation Messages','value'=>scalar($conn,"SELECT COUNT(*) FROM conversation_messages"),'icon'=>'message'],
  ],
  'services' => [
    ['label'=>'Services','value'=>scalar($conn,"SELECT COUNT(*) FROM services"),'icon'=>'service-list'],
    ['label'=>'Service Providers','value'=>scalar($conn,"SELECT COUNT(*) FROM service_providers"),'icon'=>'service'],
    ['label'=>'Active Providers','value'=>scalar($conn,"SELECT COUNT(*) FROM service_providers WHERE status='Active'"),'icon'=>'service-active'],
    ['label'=>'Inactive Providers','value'=>scalar($conn,"SELECT COUNT(*) FROM service_providers WHERE status='Inactive'"),'icon'=>'service-off'],
    ['label'=>'Total Bookings','value'=>scalar($conn,"SELECT COUNT(*) FROM bookings"),'icon'=>'booking'],
    ['label'=>'Pending Bookings','value'=>scalar($conn,"SELECT COUNT(*) FROM bookings WHERE booking_status='Pending'"),'icon'=>'booking-pending'],
    ['label'=>'Confirmed Bookings','value'=>scalar($conn,"SELECT COUNT(*) FROM bookings WHERE booking_status='Confirmed'"),'icon'=>'booking-confirmed'],
    ['label'=>'Completed Bookings','value'=>scalar($conn,"SELECT COUNT(*) FROM bookings WHERE booking_status='Completed'"),'icon'=>'booking-complete'],
    ['label'=>'Cancelled Bookings','value'=>scalar($conn,"SELECT COUNT(*) FROM bookings WHERE booking_status='Cancelled'"),'icon'=>'booking-cancelled'],
    ['label'=>'Service Cart Items','value'=>scalar($conn,"SELECT COUNT(*) FROM service_cart_items"),'icon'=>'cart'],
  ],
  'communication' => [
    ['label'=>'Admin Messages','value'=>scalar($conn,"SELECT COUNT(*) FROM admin_messages"),'icon'=>'admin-message'],
    ['label'=>'Unread Admin Messages','value'=>scalar($conn,"SELECT COUNT(*) FROM admin_messages WHERE status='Unread'"),'icon'=>'unread'],
    ['label'=>'Authenticator Messages','value'=>scalar($conn,"SELECT COUNT(*) FROM authenticator_messages"),'icon'=>'auth-message'],
    ['label'=>'Unread Authenticator Messages','value'=>scalar($conn,"SELECT COUNT(*) FROM authenticator_messages WHERE status='Unread'"),'icon'=>'auth-unread'],
    ['label'=>'Site Messages','value'=>scalar($conn,"SELECT COUNT(*) FROM site_messages"),'icon'=>'site-message'],
    ['label'=>'New Site Messages','value'=>scalar($conn,"SELECT COUNT(*) FROM site_messages WHERE status='New'"),'icon'=>'site-new'],
    ['label'=>'Verification Logs','value'=>scalar($conn,"SELECT COUNT(*) FROM verification_logs"),'icon'=>'log'],
    ['label'=>'Authenticator Claims','value'=>scalar($conn,"SELECT COUNT(*) FROM authenticator_profile_claims"),'icon'=>'claim'],
    ['label'=>'Manager Activity Logs','value'=>scalar($conn,"SELECT COUNT(*) FROM manager_activity_log"),'icon'=>'activity'],
    ['label'=>'Email Verifications','value'=>scalar($conn,"SELECT COUNT(*) FROM email_verifications"),'icon'=>'email-verify'],
    ['label'=>'Verified Emails','value'=>scalar($conn,"SELECT COUNT(DISTINCT user_id) FROM email_verifications WHERE verified_at IS NOT NULL"),'icon'=>'email-ok'],
    ['label'=>'Pending Email Verification','value'=>scalar($conn,"SELECT COUNT(DISTINCT user_id) FROM email_verifications WHERE verified_at IS NULL"),'icon'=>'email-pending'],
  ]
];

$staff_unread_result = mysqli_query($conn, "SELECT COUNT(*) FROM staff_admin_messages WHERE recipient_role='Admin' AND status='Unread'");
$staff_unread_count = $staff_unread_result ? (int) (mysqli_fetch_row($staff_unread_result)[0] ?? 0) : 0;
$admin_show_staff_messages = true;
$admin_staff_unread_count = $staff_unread_count;

$admin_header_title = 'Admin Control Center';
$admin_header_subtitle = 'System overview and administrative modules.';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Control Center | Smart Matrimony</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20261003.1">
</head>
<body>
<?php require __DIR__ . '/admin_header.php';
?>
<main class="wrap admin-dashboard">
  <?php if ($message): ?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif; ?>

  <section class="admin-hero panel">
    <button type="button" class="admin-hero-photo" data-admin-photo-open aria-label="Change Admin profile image">
      <?php if ($admin_profile_image_exists): ?>
        <img src="../uploads/staff/<?=htmlspecialchars(basename($admin_profile_image))?>" alt="Profile image of <?=htmlspecialchars($admin_full_name)?>">
      <?php else: ?>
        <span class="admin-avatar-fallback"><?=htmlspecialchars(strtoupper(substr($admin_first_name, 0, 1)))?></span>
      <?php endif; ?>
      <span class="admin-photo-edit-badge" aria-hidden="true">✎</span>
    </button>
    <div class="admin-hero-copy">
      <span class="eyebrow">ADMIN PROFILE</span>
      <h1>Welcome, <?=htmlspecialchars($admin_first_name)?>.</h1>
      <p class="admin-hero-lead">Your administrative workspace is ready. Here is your current account identity and the platform snapshot at a glance.</p>
      <div class="admin-hero-meta">
        <span><strong><?=htmlspecialchars($admin_full_name)?></strong></span>
        <span><i class="admin-inline-icon" aria-hidden="true">ID</i><?=htmlspecialchars($admin_public_id)?></span>
        <span><i class="admin-inline-icon" aria-hidden="true">@</i><?=htmlspecialchars($admin_email)?></span>
        <span><i class="admin-inline-icon" aria-hidden="true">G</i><?=htmlspecialchars($admin_gender)?></span>
      </div>
      <button type="button" class="admin-profile-edit" data-admin-photo-open>
        <span class="admin-camera-icon" aria-hidden="true">+</span>
        <?= $admin_profile_image_exists ? 'Change profile image' : 'Add profile image' ?>
      </button>
    </div>
  </section>

  <section class="admin-support-quick" aria-label="Support and Feedback quick access">
    <a class="admin-support-quick-card" href="operations.php" aria-label="Open Support and Feedback Operations">
      <span class="admin-support-quick-icon" aria-hidden="true">✉</span>
      <span class="admin-support-quick-copy">
        <strong>Support &amp; Feedback</strong>
        <small><?=number_format($support_total)?> messages · <?=number_format($support_new)?> new</small>
      </span>
      <span class="admin-support-quick-arrow" aria-hidden="true">→</span>
    </a>
  </section>

  <section class="maintenance-control panel" aria-labelledby="maintenance-control-title">
    <div class="maintenance-control-copy">
      <span class="eyebrow">SITE AVAILABILITY</span>
      <h2 id="maintenance-control-title">Maintenance Mode</h2>
      <p>Temporarily lock regular user access while you update the site. Staff sessions remain available during the maintenance window.</p>
      <div class="maintenance-status <?= $maintenance_mode ? 'is-on' : 'is-off' ?>">
        <span class="maintenance-status-dot" aria-hidden="true"></span>
        <?= $maintenance_mode ? 'Maintenance mode is ON' : 'Site is live' ?>
        <?php if ($maintenance_mode && $maintenance_expires_at !== null): ?>
          <span class="maintenance-status-time" data-maintenance-admin-countdown="<?= (int) $maintenance_expires_at ?>">· <?= htmlspecialchars(gmdate('H:i:s', max(0, $maintenance_expires_at - time()))) ?> remaining</span>
        <?php endif; ?>
      </div>
    </div>
    <form method="post" class="maintenance-control-action">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
      <input type="hidden" name="action" value="set_maintenance_mode">
      <?php if ($maintenance_mode): ?>
        <input type="hidden" name="maintenance_state" value="off">
        <button type="submit" class="maintenance-toggle maintenance-toggle-off">
          <span>↗</span> Turn Site Back On
        </button>
        <small>Timer ends automatically when the countdown reaches zero.</small>
      <?php else: ?>
        <div class="maintenance-duration">
          <label for="maintenance_duration">Maintenance for</label>
          <div class="maintenance-duration-fields">
            <input id="maintenance_duration" name="maintenance_duration" type="number" min="1" max="10080" value="30" inputmode="numeric" required>
            <select name="maintenance_duration_unit" aria-label="Maintenance duration unit">
              <option value="minutes">Minutes</option>
              <option value="hours">Hours</option>
            </select>
          </div>
        </div>
        <input type="hidden" name="maintenance_state" value="on">
        <button type="submit" class="maintenance-toggle maintenance-toggle-on">
          <span>⚙</span> Enable Maintenance
        </button>
        <small>Set 1 minute to 7 days. The site turns back on automatically.</small>
      <?php endif; ?>
    </form>
  </section>

  <section class="admin-modules" aria-labelledby="admin-modules-title">
    <div class="section-heading">
      <div>
        <span class="eyebrow">ADMIN MODULES</span>
        <h2 id="admin-modules-title">Management Center</h2>
        <p>Use a dedicated control page for account management, staff administration and platform operations.</p>
      </div>
    </div>
    <div class="admin-module-grid">
      <a class="admin-module-card" href="users.php">
        <span class="admin-module-icon">●</span>
        <span class="admin-module-copy"><strong>User Management</strong><small>User accounts, profiles, moderation and user-level controls.</small></span>
        <span class="admin-module-arrow" aria-hidden="true">→</span>
      </a>
      <a class="admin-module-card" href="managers.php">
        <span class="admin-module-icon">▣</span>
        <span class="admin-module-copy"><strong>Manager Management</strong><small>Manager accounts and manager-specific controls.</small></span>
        <span class="admin-module-arrow" aria-hidden="true">→</span>
      </a>
      <a class="admin-module-card" href="authenticators.php">
        <span class="admin-module-icon">◆</span>
        <span class="admin-module-copy"><strong>Authenticator Management</strong><small>Authenticator accounts, verification assignments and controls.</small></span>
        <span class="admin-module-arrow" aria-hidden="true">→</span>
      </a>
      <a class="admin-module-card" href="operations.php">
        <span class="admin-module-icon">≋</span>
        <span class="admin-module-copy"><strong>Operations Center</strong><small>Services, bookings, matching, communication and operational audit.</small></span>
        <span class="admin-module-arrow" aria-hidden="true">→</span>
      </a>
    </div>
  </section>
  <section class="system-overview">
    <div class="section-heading">
      <div>
        <span class="eyebrow">PLATFORM SNAPSHOT</span>
        <h2>System Overview</h2>
        <p>Everything important, grouped by area so the current state is easy to scan.</p>
      </div>
    </div>

    <?php foreach ($stats as $category_key => $category):
      $category_titles = [
        'accounts'=>'Accounts & Access',
        'profiles'=>'Profiles & Verification',
        'matching'=>'Matching & Communication',
        'services'=>'Services & Bookings',
        'communication'=>'Messages & Activity'
      ];
    ?>
      <section class="stats-category" aria-labelledby="stats-<?=htmlspecialchars($category_key)?>">
        <div class="stats-category-head">
          <h3 id="stats-<?=htmlspecialchars($category_key)?>"><?=htmlspecialchars($category_titles[$category_key])?></h3>
          <span><?=count($category)?> metrics</span>
        </div>
        <div class="stats-grid">
          <?php foreach ($category as $stat): ?>
            <article class="stat-card stat-<?=htmlspecialchars($stat['icon'])?>">
              <div class="stat-card-top">
                <span class="stat-icon" aria-hidden="true">
                  <?php
                    $icons = [
                      'users'=>'♟','user'=>'●','briefcase'=>'▣','shield'=>'◆','admin'=>'★','active'=>'✓','inactive'=>'◌','suspended'=>'!','male'=>'♂','female'=>'♀','profile-user'=>'◎','profile-missing'=>'◌','profile'=>'◎','pending'=>'◷','verified'=>'✓','rejected'=>'×','public'=>'◉','hidden'=>'◌','photo'=>'▧','camera'=>'◍','voice'=>'♫','video'=>'▶','preference'=>'⚙','health'=>'✚','trait'=>'☷','question'=>'?','heart'=>'♥','match'=>'♡','reject'=>'×','cancel'=>'×','couple'=>'♧','bookmark'=>'◆','chat'=>'◫','chat-pending'=>'◷','chat-accepted'=>'✓','conversation'=>'◌','conversation-closed'=>'◌','message'=>'✉','service-list'=>'▦','service'=>'◆','service-active'=>'✓','service-off'=>'◌','booking'=>'▤','booking-pending'=>'◷','booking-confirmed'=>'✓','booking-complete'=>'★','booking-cancelled'=>'×','cart'=>'▣','admin-message'=>'✉','unread'=>'●','auth-message'=>'✉','auth-unread'=>'●','site-message'=>'✉','site-new'=>'●','log'=>'≡','claim'=>'◇','activity'=>'≋','email-verify'=>'@','email-ok'=>'✓','email-pending'=>'◷'
                    ];
                    echo htmlspecialchars($icons[$stat['icon']] ?? '•');
                  ?>
                </span>
                <span class="stat-label"><?=htmlspecialchars($stat['label'])?></span>
              </div>
              <strong><?=number_format((int)$stat['value'])?></strong>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </section>

</main>

<div class="admin-modal" data-admin-photo-modal hidden>
  <div class="admin-modal-backdrop" data-admin-photo-close></div>
  <section class="admin-modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-photo-title">
    <button type="button" class="admin-modal-close" data-admin-photo-close aria-label="Close">×</button>
    <span class="eyebrow">ADMIN PROFILE</span>
    <h2 id="admin-photo-title">Profile Image</h2>
    <p>Choose a clear profile image for your Admin dashboard hero.</p>
    <div class="admin-photo-preview" data-admin-photo-preview>
      <?php if ($admin_profile_image_exists): ?>
        <img src="../uploads/staff/<?=htmlspecialchars(basename($admin_profile_image))?>" alt="Current profile image">
      <?php else: ?>
        <span><?=htmlspecialchars(strtoupper(substr($admin_first_name, 0, 1)))?></span>
      <?php endif; ?>
    </div>
    <form method="post" enctype="multipart/form-data" class="admin-photo-form">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
      <input type="hidden" name="action" value="update_admin_photo">
      <label class="admin-file-picker">
        <span>Choose image</span>
        <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" required data-admin-photo-input>
      </label>
      <small>JPG, PNG or WebP · maximum 5 MB</small>
      <button type="submit">Save Profile Image</button>
    </form>
  </section>
</div>

<script>
(function(){
  const modal = document.querySelector('[data-admin-photo-modal]');
  const openers = document.querySelectorAll('[data-admin-photo-open]');
  const closers = document.querySelectorAll('[data-admin-photo-close]');
  const input = document.querySelector('[data-admin-photo-input]');
  const preview = document.querySelector('[data-admin-photo-preview]');
  if (!modal) return;
  const open = () => { modal.hidden = false; document.body.classList.add('admin-modal-open'); };
  const close = () => { modal.hidden = true; document.body.classList.remove('admin-modal-open'); };
  openers.forEach(btn => btn.addEventListener('click', open));
  closers.forEach(btn => btn.addEventListener('click', close));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) close(); });
  if (input && preview) {
    input.addEventListener('change', function(){
      const file = this.files && this.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      const url = URL.createObjectURL(file);
      preview.innerHTML = '<img src="' + url + '" alt="Selected profile image preview">';
    });
  }
})();
</script>

<script>
document.querySelectorAll('[data-maintenance-admin-countdown]').forEach(function (el) {
  var end = Number(el.getAttribute('data-maintenance-admin-countdown')) * 1000;
  var tick = function () {
    var seconds = Math.max(0, Math.floor((end - Date.now()) / 1000));
    var h = String(Math.floor(seconds / 3600)).padStart(2, '0');
    var m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    var s = String(seconds % 60).padStart(2, '0');
    el.textContent = '· ' + h + ':' + m + ':' + s + ' remaining';
    if (seconds <= 0) window.location.reload();
  };
  tick();
  setInterval(tick, 1000);
});
</script>
</body>
</html>
