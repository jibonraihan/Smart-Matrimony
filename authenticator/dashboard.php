<?php
$page_css = 'assets/css/authenticator-verification.css';

// Authenticator has its own session cookie. Select it before config.php so
// the normal user session is never mixed with the Authenticator session.
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_AUTH_SESSION');
    session_start();
}

require_once '../config/db.php';

if (empty($_SESSION['authenticator_user_id'])) {
    header('Location: login.php');
    exit;
}

$authenticator_id = (int) $_SESSION['authenticator_user_id'];
$auth_stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, role, account_status FROM users WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($auth_stmt, 'i', $authenticator_id);
mysqli_stmt_execute($auth_stmt);
$auth = mysqli_fetch_assoc(mysqli_stmt_get_result($auth_stmt));
mysqli_stmt_close($auth_stmt);

if (!$auth || $auth['role'] !== 'Authenticator' || $auth['account_status'] !== 'Active') {
    header('Location: ../login.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'Pending';
$allowed_statuses = ['All', 'Pending', 'Verified', 'Rejected'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'Pending';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

function count_profiles(mysqli $conn, string $condition = ''): int {
    $sql = "SELECT COUNT(*) AS total FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id WHERE u.role='User'" . ($condition ? " AND {$condition}" : '');
    $result = mysqli_query($conn, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return (int)($row['total'] ?? 0);
}

$pending_count = count_profiles($conn, "up.verification_status='Pending'");
$verified_count = count_profiles($conn, "up.verification_status='Verified'");
$rejected_count = count_profiles($conn, "up.verification_status='Rejected'");
$total_profiles = count_profiles($conn);

$where = ["u.role='User'"];
$params = [];
$types = '';

if ($status !== 'All') {
    $where[] = 'up.verification_status=?';
    $params[] = $status;
    $types .= 's';
}

if ($search !== '') {
    $where[] = '(CONCAT(up.first_name, " ", COALESCE(up.last_name, "")) LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR CAST(up.profile_id AS CHAR) LIKE ? OR CAST(u.user_id AS CHAR) LIKE ?)';
    $like = '%' . $search . '%';
    for ($i=0; $i<6; $i++) $params[] = $like;
    $types .= 'ssssss';
}

$where_sql = implode(' AND ', $where);
$count_sql = "SELECT COUNT(*) AS total FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id WHERE {$where_sql}";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($params) mysqli_stmt_bind_param($count_stmt, $types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
mysqli_stmt_close($count_stmt);
$total_rows = (int)($count_row['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_rows / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

$list_sql = "SELECT up.profile_id, up.user_id, up.first_name, up.last_name, up.gender, up.date_of_birth, up.religion, up.photo, up.verification_status, up.updated_at, u.email, u.mobile, u.account_status
             FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id
             WHERE {$where_sql} ORDER BY FIELD(up.verification_status,'Pending','Rejected','Verified'), up.updated_at DESC LIMIT ? OFFSET ?";
$list_stmt = mysqli_prepare($conn, $list_sql);
$bind_types = $types . 'ii';
$bind_values = $params;
$bind_values[] = $per_page;
$bind_values[] = $offset;
mysqli_stmt_bind_param($list_stmt, $bind_types, ...$bind_values);
mysqli_stmt_execute($list_stmt);
$profiles = mysqli_stmt_get_result($list_stmt);

function qs(array $extra = []): string {
    $base = [
        'q' => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? 'Pending'
    ];
    return http_build_query(array_merge($base, $extra));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authenticator Verification Center | Smart Matrimony</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars('../assets/css/authenticator-verification.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="auth-page">
<header class="auth-header">
    <div>
        <span class="eyebrow">SMART MATRIMONY</span>
        <h1>Verification Center</h1>
        <p>Review member profiles carefully before approving them.</p>
    </div>
    <div class="auth-header-actions">
        <span class="identity"><span>AUTHENTICATOR</span> <?= htmlspecialchars($auth['first_name'].' '.$auth['last_name']) ?></span>
        <a href="../dashboard.php" class="header-btn"><i class="fa-solid fa-arrow-left"></i> User Dashboard</a>
        <a href="logout.php" class="header-btn danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</header>

<main class="auth-container">
    <section class="intro-card">
        <div>
            <span class="section-label">PROFILE VERIFICATION</span>
            <h2>Authenticator Workspace</h2>
            <p>Profiles are completed through the project's six-step profile process. This verification module reviews the submitted information; it does not modify those six profile steps.</p>
        </div>
        <div class="intro-icon"><i class="fa-solid fa-user-shield"></i></div>
    </section>

    <section class="stats-grid">
        <div class="stat-card"><span>Pending Profiles</span><strong><?= number_format($pending_count) ?></strong><small>Need review</small></div>
        <div class="stat-card"><span>Verified Profiles</span><strong><?= number_format($verified_count) ?></strong><small>Approved</small></div>
        <div class="stat-card"><span>Rejected Profiles</span><strong><?= number_format($rejected_count) ?></strong><small>Needs attention</small></div>
        <div class="stat-card"><span>Total Profiles</span><strong><?= number_format($total_profiles) ?></strong><small>Member profiles</small></div>
    </section>

    <section class="queue-card">
        <div class="section-heading">
            <div><span class="section-label">VERIFICATION QUEUE</span><h2>Member Profiles</h2></div>
            <span class="result-count"><?= number_format($total_rows) ?> result<?= $total_rows === 1 ? '' : 's' ?></span>
        </div>

        <form class="filter-bar" method="GET">
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search ID, name, email or mobile">
            <select name="status">
                <?php foreach ($allowed_statuses as $item): ?>
                    <option value="<?= $item ?>" <?= $status === $item ? 'selected' : '' ?>><?= $item ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $status !== 'Pending'): ?><a class="clear-btn" href="dashboard.php">Clear</a><?php endif; ?>
        </form>

        <?php if (mysqli_num_rows($profiles) === 0): ?>
            <div class="empty-state"><i class="fa-solid fa-circle-check"></i><h3>No profiles found</h3><p>Try another search or status filter.</p></div>
        <?php else: ?>
            <div class="profile-table-wrap">
                <table class="profile-table">
                    <thead><tr><th>Profile</th><th>Contact</th><th>Profile Status</th><th>Account</th><th>Updated</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($profiles)): ?>
                        <tr>
                            <td>
                                <div class="member-cell">
                                    <?php if (!empty($row['photo'])): ?><img src="../uploads/profile/<?= htmlspecialchars($row['photo']) ?>" alt="Profile photo"><?php else: ?><div class="avatar-placeholder"><i class="fa-solid fa-user"></i></div><?php endif; ?>
                                    <div><strong><?= htmlspecialchars(trim($row['first_name'].' '.$row['last_name'])) ?></strong><span>SM-<?= str_pad((string)$row['user_id'], 6, '0', STR_PAD_LEFT) ?></span></div>
                                </div>
                            </td>
                            <td><span><?= htmlspecialchars($row['email']) ?></span><small><?= htmlspecialchars($row['mobile']) ?></small></td>
                            <td><span class="status-pill <?= strtolower($row['verification_status']) ?>"><?= htmlspecialchars($row['verification_status']) ?></span></td>
                            <td><?= htmlspecialchars($row['account_status']) ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($row['updated_at']))) ?></td>
                            <td><a class="review-btn" href="verify_profile.php?id=<?= (int)$row['profile_id'] ?>"><i class="fa-solid fa-file-shield"></i> Review</a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?<?= qs(['page'=>$page-1]) ?>">&laquo;</a><?php endif; ?>
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <?php if ($page < $total_pages): ?><a href="?<?= qs(['page'=>$page+1]) ?>">&raquo;</a><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
