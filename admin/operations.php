<?php
require_once __DIR__ . '/admin_guard.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function scalar_count(mysqli $conn, string $sql): int {
    $result = mysqli_query($conn, $sql);
    if (!$result) return 0;
    $row = mysqli_fetch_row($result);
    return (int)($row[0] ?? 0);
}


    /* Support & Feedback Operations */
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['operation_support_csrf'])) {
        $_SESSION['operation_support_csrf'] = bin2hex(random_bytes(32));
    }
    $support_csrf = $_SESSION['operation_support_csrf'];

    $support_action = (string)($_POST['support_action'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $support_action !== '') {
        $posted_csrf = (string)($_POST['support_csrf'] ?? '');
        if (!$posted_csrf || !hash_equals($support_csrf, $posted_csrf)) {
            http_response_code(403);
            exit('Invalid support action request.');
        }

        $support_ids = [];
        if (isset($_POST['support_id'])) $support_ids[] = (int)$_POST['support_id'];
        if (isset($_POST['support_ids']) && is_array($_POST['support_ids'])) {
            foreach ($_POST['support_ids'] as $id) {
                $id = (int)$id;
                if ($id > 0) $support_ids[] = $id;
            }
        }
        $support_ids = array_values(array_unique(array_filter($support_ids, static fn($id) => $id > 0)));

        $support_post_type = (string)($_POST['support_type'] ?? '');
        $support_post_search = trim((string)($_POST['support_search'] ?? ''));
        $support_post_status = (string)($_POST['support_status'] ?? 'All');
        $support_post_page = max(1, (int)($_POST['support_page'] ?? 1));

        if ($support_action === 'delete' && $support_ids) {
            $placeholders = implode(',', array_fill(0, count($support_ids), '?'));
            $stmt = mysqli_prepare($conn, "DELETE FROM site_messages WHERE message_id IN ($placeholders)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, str_repeat('i', count($support_ids)), ...$support_ids);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } elseif (in_array($support_action, ['mark_read', 'mark_resolved'], true) && $support_ids) {
            $new_status = $support_action === 'mark_read' ? 'Read' : 'Resolved';
            $placeholders = implode(',', array_fill(0, count($support_ids), '?'));
            $stmt = mysqli_prepare($conn, "UPDATE site_messages SET status=? WHERE message_id IN ($placeholders)");
            if ($stmt) {
                $types = 's' . str_repeat('i', count($support_ids));
                $params = array_merge([$new_status], $support_ids);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        $redirect_query = http_build_query(array_filter([
            'support_type' => in_array($support_post_type, ['Contact','Feedback'], true) ? $support_post_type : '',
            'support_search' => $support_post_search,
            'support_status' => in_array($support_post_status, ['New','Read','Resolved'], true) ? $support_post_status : '',
            'support_page' => $support_post_page > 1 ? $support_post_page : '',
        ], static fn($v) => $v !== '' && $v !== null));
        header('Location: operations.php' . ($redirect_query ? '?' . $redirect_query : '') . '#support-feedback-operations-heading');
        exit;
    }

    $support_type = (string)($_GET['support_type'] ?? '');
    if (!in_array($support_type, ['Contact','Feedback'], true)) $support_type = '';
    $support_search = trim((string)($_GET['support_search'] ?? ''));
    $support_status = (string)($_GET['support_status'] ?? 'All');
    if (!in_array($support_status, ['All','New','Read','Resolved'], true)) $support_status = 'All';
    $support_page = max(1, (int)($_GET['support_page'] ?? 1));
    $support_per_page = 20;

    $support_where = [];
    $support_types = '';
    $support_params = [];
    if ($support_type !== '') {
        $support_where[] = 'sm.message_type = ?';
        $support_types .= 's';
        $support_params[] = $support_type;
    }
    if ($support_search !== '') {
        $support_where[] = '(CAST(sm.message_id AS CHAR) LIKE ? OR COALESCE(CAST(sm.user_id AS CHAR), \'\') LIKE ? OR sm.name LIKE ? OR sm.email LIKE ? OR sm.message LIKE ?)';
        $term = '%' . $support_search . '%';
        $support_types .= 'sssss';
        array_push($support_params, $term, $term, $term, $term, $term);
    }
    if ($support_status !== 'All') {
        $support_where[] = 'sm.status = ?';
        $support_types .= 's';
        $support_params[] = $support_status;
    }
    $support_sql_where = $support_where ? 'WHERE ' . implode(' AND ', $support_where) : '';

    $support_count_sql = "SELECT COUNT(*) FROM site_messages sm $support_sql_where";
    $support_count_stmt = mysqli_prepare($conn, $support_count_sql);
    if ($support_types !== '') mysqli_stmt_bind_param($support_count_stmt, $support_types, ...$support_params);
    mysqli_stmt_execute($support_count_stmt);
    $support_count_result = mysqli_stmt_get_result($support_count_stmt);
    $support_total_rows = (int)(mysqli_fetch_row($support_count_result)[0] ?? 0);
    mysqli_stmt_close($support_count_stmt);

    $support_pages = max(1, (int)ceil($support_total_rows / $support_per_page));
    if ($support_page > $support_pages) $support_page = $support_pages;
    $support_offset = ($support_page - 1) * $support_per_page;

    $support_sql = "SELECT sm.message_id, sm.message_type, sm.name, sm.email, sm.message, sm.user_id, sm.status, sm.created_at
                    FROM site_messages sm
                    $support_sql_where
                    ORDER BY CASE sm.status WHEN 'New' THEN 1 WHEN 'Read' THEN 2 WHEN 'Resolved' THEN 3 ELSE 4 END,
                             sm.created_at DESC, sm.message_id DESC
                    LIMIT ? OFFSET ?";
    $support_stmt = mysqli_prepare($conn, $support_sql);
    $support_query_types = $support_types . 'ii';
    $support_query_params = $support_params;
    $support_query_params[] = $support_per_page;
    $support_query_params[] = $support_offset;
    mysqli_stmt_bind_param($support_stmt, $support_query_types, ...$support_query_params);
    mysqli_stmt_execute($support_stmt);
    $support_result = mysqli_stmt_get_result($support_stmt);
    $support_rows = [];
    while ($row = mysqli_fetch_assoc($support_result)) $support_rows[] = $row;
    mysqli_stmt_close($support_stmt);

    $support_summary = [
        'total' => scalar_count($conn, 'SELECT COUNT(*) FROM site_messages'),
        'new' => scalar_count($conn, "SELECT COUNT(*) FROM site_messages WHERE status='New'"),
        'read' => scalar_count($conn, "SELECT COUNT(*) FROM site_messages WHERE status='Read'"),
        'resolved' => scalar_count($conn, "SELECT COUNT(*) FROM site_messages WHERE status='Resolved'"),
        'contact' => scalar_count($conn, "SELECT COUNT(*) FROM site_messages WHERE message_type='Contact'"),
        'feedback' => scalar_count($conn, "SELECT COUNT(*) FROM site_messages WHERE message_type='Feedback'"),
    ];

    $support_query = function(array $overrides = []) use ($support_type, $support_search, $support_status, $support_page) {
        $q = [
            'support_type' => $support_type,
            'support_search' => $support_search,
            'support_status' => $support_status,
            'support_page' => $support_page,
        ];
        foreach ($overrides as $k => $v) $q[$k] = $v;
        return http_build_query(array_filter($q, static fn($v) => $v !== '' && $v !== 'All'));
    };

$service_search = trim((string)($_GET['service_search'] ?? ''));
$service_filter = (string)($_GET['service_filter'] ?? '');
$service_status = (string)($_GET['service_status'] ?? 'All');
$service_page = max(1, (int)($_GET['service_page'] ?? 1));
$service_sort = (string)($_GET['service_sort'] ?? 'newest');
$service_sort_map = [
    'newest' => 'sp.created_at DESC, sp.provider_id DESC',
    'oldest' => 'sp.created_at ASC, sp.provider_id ASC',
    'price_high' => 'sp.price DESC, sp.provider_id DESC',
    'price_low' => 'sp.price ASC, sp.provider_id ASC',
    'rating_high' => 'sp.rating DESC, sp.review_count DESC, sp.provider_id DESC',
    'rating_low' => 'sp.rating ASC, sp.review_count ASC, sp.provider_id ASC',
];
if (!isset($service_sort_map[$service_sort])) $service_sort = 'newest';
$service_per_page = 12;

$service_where = [];
$service_types = '';
$service_params = [];

if ($service_search !== '') {
    $service_where[] = '(s.service_name LIKE ? OR sp.provider_name LIKE ? OR sp.package_name LIKE ? OR sp.location LIKE ?)';
    $term = '%' . $service_search . '%';
    $service_types .= 'ssss';
    array_push($service_params, $term, $term, $term, $term);
}
if ($service_filter !== '') {
    $service_where[] = 's.service_id = ?';
    $service_types .= 'i';
    $service_params[] = (int)$service_filter;
}
if (in_array($service_status, ['Active', 'Inactive'], true)) {
    $service_where[] = 'sp.status = ?';
    $service_types .= 's';
    $service_params[] = $service_status;
}

$service_sql_where = $service_where ? 'WHERE ' . implode(' AND ', $service_where) : '';
$service_count_sql = "SELECT COUNT(*) FROM service_providers sp INNER JOIN services s ON s.service_id=sp.service_id $service_sql_where";
$service_count_stmt = mysqli_prepare($conn, $service_count_sql);
if ($service_types !== '') mysqli_stmt_bind_param($service_count_stmt, $service_types, ...$service_params);
mysqli_stmt_execute($service_count_stmt);
$service_count_result = mysqli_stmt_get_result($service_count_stmt);
$service_total_rows = (int)(mysqli_fetch_row($service_count_result)[0] ?? 0);
mysqli_stmt_close($service_count_stmt);

$service_pages = max(1, (int)ceil($service_total_rows / $service_per_page));
if ($service_page > $service_pages) $service_page = $service_pages;
$service_offset = ($service_page - 1) * $service_per_page;

$service_sql = "SELECT sp.provider_id, sp.provider_name, sp.package_name, sp.package_details, sp.price, sp.contact_number,
                       sp.location, sp.rating, sp.review_count, sp.status, sp.created_at,
                       s.service_name, u.first_name AS manager_first_name, u.last_name AS manager_last_name
                FROM service_providers sp
                INNER JOIN services s ON s.service_id=sp.service_id
                LEFT JOIN users u ON u.user_id=sp.manager_id AND u.role='Manager'
                $service_sql_where
                ORDER BY {$service_sort_map[$service_sort]}
                LIMIT ? OFFSET ?";
$service_stmt = mysqli_prepare($conn, $service_sql);
$service_query_types = $service_types . 'ii';
$service_query_params = $service_params;
$service_query_params[] = $service_per_page;
$service_query_params[] = $service_offset;
mysqli_stmt_bind_param($service_stmt, $service_query_types, ...$service_query_params);
mysqli_stmt_execute($service_stmt);
$service_result = mysqli_stmt_get_result($service_stmt);
$service_rows = [];
while ($row = mysqli_fetch_assoc($service_result)) $service_rows[] = $row;
mysqli_stmt_close($service_stmt);

$service_list = [];
$service_list_result = mysqli_query($conn, 'SELECT service_id, service_name FROM services ORDER BY service_name ASC');
if ($service_list_result) {
    while ($row = mysqli_fetch_assoc($service_list_result)) $service_list[] = $row;
}

$service_summary = [
    'total' => scalar_count($conn, 'SELECT COUNT(*) FROM service_providers'),
    'active' => scalar_count($conn, "SELECT COUNT(*) FROM service_providers WHERE status='Active'"),
    'inactive' => scalar_count($conn, "SELECT COUNT(*) FROM service_providers WHERE status='Inactive'"),
    'services' => scalar_count($conn, 'SELECT COUNT(*) FROM services'),
];


$audit_search = trim((string)($_GET['audit_search'] ?? ''));
$audit_type = (string)($_GET['audit_type'] ?? 'All');
$audit_page = max(1, (int)($_GET['audit_page'] ?? 1));
$audit_sort = (string)($_GET['audit_sort'] ?? 'newest');
$audit_sort_map = ['newest' => 'audit_events.event_at DESC', 'oldest' => 'audit_events.event_at ASC'];
if (!isset($audit_sort_map[$audit_sort])) $audit_sort = 'newest';
$audit_per_page = 20;
$audit_types_allowed = ['Service','Booking','Matching','Communication','Verification'];
if (!in_array($audit_type, array_merge(['All'], $audit_types_allowed), true)) $audit_type = 'All';

$audit_union = "
    SELECT 'Service' AS event_type, CONCAT('Service #', s.service_id) AS entity_label,
           s.service_name AS subject_label, 'Service created' AS event_label,
           s.created_at AS event_at, 'Service catalog' AS source_label,
           CONCAT('Service: ', s.service_name) AS event_details
    FROM services s

    UNION ALL

    SELECT 'Service', CONCAT('Provider #', sp.provider_id), sp.provider_name,
           'Package/provider created', sp.created_at, 'Service & Package Operations',
           CONCAT('Package: ', COALESCE(sp.package_name, 'Unnamed package'), ' · Status: ', sp.status)
    FROM service_providers sp

    UNION ALL

    SELECT 'Booking', CONCAT('Booking #', b.booking_id),
           CONCAT('User #', b.user_id), 'Booking created', b.booking_date,
           'Booking Operations', CONCAT('Status: ', b.booking_status, ' · Total: ৳ ', FORMAT(b.total_price, 2))
    FROM bookings b
    WHERE b.admin_removed = 0

    UNION ALL

    SELECT 'Booking', CONCAT('Booking #', b.booking_id),
           CONCAT('User #', b.user_id), 'Booking confirmed', b.confirmed_at,
           'Booking Operations', CONCAT('Confirmed by manager #', COALESCE(CAST(b.confirmed_by_manager_id AS CHAR), '—'))
    FROM bookings b
    WHERE b.admin_removed = 0 AND b.confirmed_at IS NOT NULL

    UNION ALL

    SELECT 'Booking', CONCAT('Booking #', b.booking_id),
           CONCAT('User #', b.user_id), 'Booking completed', b.completed_at,
           'Booking Operations', CONCAT('Completed by manager #', COALESCE(CAST(b.completed_by_manager_id AS CHAR), '—'))
    FROM bookings b
    WHERE b.admin_removed = 0 AND b.completed_at IS NOT NULL

    UNION ALL

    SELECT 'Booking', CONCAT('Booking #', b.booking_id),
           CONCAT('User #', b.user_id), 'Booking cancelled', b.cancelled_at,
           'Booking Operations', CONCAT('Cancelled by ', COALESCE(NULLIF(b.cancelled_by, ''), CONCAT('manager #', COALESCE(CAST(b.cancelled_by_manager_id AS CHAR), '—'))))
    FROM bookings b
    WHERE b.admin_removed = 0 AND b.cancelled_at IS NOT NULL

    UNION ALL

    SELECT 'Matching', CONCAT('Match #', m.match_id),
           CONCAT('Users #', m.sender_user_id, ' ↔ #', m.receiver_user_id), 'Interest created', m.created_at,
           'Matching Operations', CONCAT('Status: ', m.status)
    FROM matches m

    UNION ALL

    SELECT 'Matching', CONCAT('Match #', m.match_id),
           CONCAT('Users #', m.sender_user_id, ' ↔ #', m.receiver_user_id), 'Interest responded', m.responded_at,
           'Matching Operations', CONCAT('Status: ', m.status)
    FROM matches m
    WHERE m.responded_at IS NOT NULL

    UNION ALL

    SELECT 'Matching', CONCAT('Match #', m.match_id),
           CONCAT('Users #', m.sender_user_id, ' ↔ #', m.receiver_user_id), 'Match accepted', m.matched_at,
           'Matching Operations', 'Relationship matched'
    FROM matches m
    WHERE m.matched_at IS NOT NULL

    UNION ALL

    SELECT 'Matching', CONCAT('Match #', m.match_id),
           CONCAT('Users #', m.sender_user_id, ' ↔ #', m.receiver_user_id), 'Relationship ended', m.unmatched_at,
           'Matching Operations', CONCAT('Closed reason: ', COALESCE(m.closed_reason, '—'))
    FROM matches m
    WHERE m.unmatched_at IS NOT NULL

    UNION ALL

    SELECT 'Communication', CONCAT('Chat Request #', cr.chat_request_id),
           CONCAT('Users #', cr.sender_user_id, ' ↔ #', cr.receiver_user_id), 'Chat request created', cr.created_at,
           'Communication Operations', CONCAT('Status: ', cr.status)
    FROM chat_requests cr

    UNION ALL

    SELECT 'Communication', CONCAT('Chat Request #', cr.chat_request_id),
           CONCAT('Users #', cr.sender_user_id, ' ↔ #', cr.receiver_user_id), 'Chat request responded', cr.responded_at,
           'Communication Operations', CONCAT('Status: ', cr.status)
    FROM chat_requests cr
    WHERE cr.responded_at IS NOT NULL

    UNION ALL

    SELECT 'Communication', CONCAT('Chat Request #', cr.chat_request_id),
           CONCAT('Users #', cr.sender_user_id, ' ↔ #', cr.receiver_user_id), 'Chat request closed', cr.closed_at,
           'Communication Operations', 'Chat request lifecycle closed'
    FROM chat_requests cr
    WHERE cr.closed_at IS NOT NULL

    UNION ALL

    SELECT 'Communication', CONCAT('Conversation #', c.conversation_id),
           CONCAT('Chat Request #', c.chat_request_id), 'Conversation started', c.started_at,
           'Communication Operations', CONCAT('Status: ', c.status, ' · Limit: ', c.message_limit)
    FROM conversations c

    UNION ALL

    SELECT 'Verification', CONCAT('Verification Log #', vl.log_id),
           CONCAT('Profile #', vl.profile_id), CONCAT('Profile ', vl.action), vl.action_at,
           'Authenticator Verification', CONCAT('Authenticator #', vl.authenticator_id, ' · Remarks: ', COALESCE(vl.remarks, '—'))
    FROM verification_logs vl
";

$audit_conditions = [];
$audit_types = '';
$audit_params = [];
if ($audit_search !== '') {
    $audit_conditions[] = '(audit_events.entity_label LIKE ? OR audit_events.subject_label LIKE ? OR audit_events.event_label LIKE ? OR audit_events.event_details LIKE ?)';
    $audit_term = '%' . $audit_search . '%';
    $audit_types .= 'ssss';
    array_push($audit_params, $audit_term, $audit_term, $audit_term, $audit_term);
}
if ($audit_type !== 'All') {
    $audit_conditions[] = 'audit_events.event_type = ?';
    $audit_types .= 's';
    $audit_params[] = $audit_type;
}
$audit_where = $audit_conditions ? 'WHERE ' . implode(' AND ', $audit_conditions) : '';

$audit_count_sql = "SELECT COUNT(*) FROM ($audit_union) AS audit_events $audit_where";
$audit_count_stmt = mysqli_prepare($conn, $audit_count_sql);
if ($audit_types !== '') mysqli_stmt_bind_param($audit_count_stmt, $audit_types, ...$audit_params);
mysqli_stmt_execute($audit_count_stmt);
$audit_count_result = mysqli_stmt_get_result($audit_count_stmt);
$audit_total_rows = (int)(mysqli_fetch_row($audit_count_result)[0] ?? 0);
mysqli_stmt_close($audit_count_stmt);

$audit_pages = max(1, (int)ceil($audit_total_rows / $audit_per_page));
if ($audit_page > $audit_pages) $audit_page = $audit_pages;
$audit_offset = ($audit_page - 1) * $audit_per_page;

$audit_sql = "SELECT event_type, entity_label, subject_label, event_label, event_at, source_label, event_details
              FROM ($audit_union) AS audit_events $audit_where
              ORDER BY {$audit_sort_map[$audit_sort]}, event_label ASC
              LIMIT ? OFFSET ?";
$audit_stmt = mysqli_prepare($conn, $audit_sql);
$audit_query_types = $audit_types . 'ii';
$audit_query_params = $audit_params;
$audit_query_params[] = $audit_per_page;
$audit_query_params[] = $audit_offset;
mysqli_stmt_bind_param($audit_stmt, $audit_query_types, ...$audit_query_params);
mysqli_stmt_execute($audit_stmt);
$audit_result = mysqli_stmt_get_result($audit_stmt);
$audit_rows = [];
while ($row = mysqli_fetch_assoc($audit_result)) $audit_rows[] = $row;
mysqli_stmt_close($audit_stmt);

$audit_summary = [
    'total' => scalar_count($conn, "SELECT COUNT(*) FROM (
        SELECT service_id AS id FROM services
        UNION ALL SELECT provider_id FROM service_providers
        UNION ALL SELECT booking_id FROM bookings WHERE admin_removed=0
        UNION ALL SELECT booking_id FROM bookings WHERE admin_removed=0 AND confirmed_at IS NOT NULL
        UNION ALL SELECT booking_id FROM bookings WHERE admin_removed=0 AND completed_at IS NOT NULL
        UNION ALL SELECT booking_id FROM bookings WHERE admin_removed=0 AND cancelled_at IS NOT NULL
        UNION ALL SELECT match_id FROM matches
        UNION ALL SELECT match_id FROM matches WHERE responded_at IS NOT NULL
        UNION ALL SELECT match_id FROM matches WHERE matched_at IS NOT NULL
        UNION ALL SELECT match_id FROM matches WHERE unmatched_at IS NOT NULL
        UNION ALL SELECT chat_request_id FROM chat_requests
        UNION ALL SELECT chat_request_id FROM chat_requests WHERE responded_at IS NOT NULL
        UNION ALL SELECT chat_request_id FROM chat_requests WHERE closed_at IS NOT NULL
        UNION ALL SELECT conversation_id FROM conversations
        UNION ALL SELECT log_id FROM verification_logs
    ) audit_count"),
    'verification' => scalar_count($conn, 'SELECT COUNT(*) FROM verification_logs'),
    'booking' => scalar_count($conn, "SELECT COUNT(*) FROM bookings WHERE admin_removed=0") + scalar_count($conn, "SELECT COUNT(*) FROM bookings WHERE admin_removed=0 AND confirmed_at IS NOT NULL") + scalar_count($conn, "SELECT COUNT(*) FROM bookings WHERE admin_removed=0 AND completed_at IS NOT NULL") + scalar_count($conn, "SELECT COUNT(*) FROM bookings WHERE admin_removed=0 AND cancelled_at IS NOT NULL"),
    'matching' => scalar_count($conn, 'SELECT COUNT(*) FROM matches') + scalar_count($conn, 'SELECT COUNT(*) FROM matches WHERE responded_at IS NOT NULL') + scalar_count($conn, 'SELECT COUNT(*) FROM matches WHERE matched_at IS NOT NULL') + scalar_count($conn, 'SELECT COUNT(*) FROM matches WHERE unmatched_at IS NOT NULL'),
    'communication' => scalar_count($conn, 'SELECT COUNT(*) FROM chat_requests') + scalar_count($conn, 'SELECT COUNT(*) FROM chat_requests WHERE responded_at IS NOT NULL') + scalar_count($conn, 'SELECT COUNT(*) FROM chat_requests WHERE closed_at IS NOT NULL') + scalar_count($conn, 'SELECT COUNT(*) FROM conversations'),
    'service' => scalar_count($conn, 'SELECT COUNT(*) FROM services') + scalar_count($conn, 'SELECT COUNT(*) FROM service_providers'),
];

function audit_query(array $extra = []): string {
    $params = [
        'audit_search' => $GLOBALS['audit_search'],
        'audit_type' => $GLOBALS['audit_type'],
        'audit_sort' => $GLOBALS['audit_sort'],
        'audit_page' => $GLOBALS['audit_page'],
    ];
    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]); else $params[$k] = $v;
    }
    return http_build_query($params);
}


?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Operation Center | Smart Matrimony</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin-operations.css">
</head>
<body>
<?php
$admin_header_title = 'Admin Operation Center';
$admin_header_subtitle = 'System-wide operational overview for Smart Matrimony.';
require __DIR__ . '/admin_header.php';
?>

<main id="operation-center-top" class="wrap operation-center">
    <a class="operation-back" href="dashboard.php" aria-label="Back to Admin Dashboard">
        <span aria-hidden="true">←</span>
        <span>Back to Admin Dashboard</span>
    </a>

    <nav class="operation-section-nav" aria-label="Operation Center sections">
        <a href="#service-operations-heading">Service &amp; Package</a>
        <a href="#booking-operations-heading">Booking Operations</a>
        <a href="#match-operations-heading">Matching Operations</a>
        <a href="#communication-operations-heading">Communication Operations</a>
        <a href="#support-feedback-operations-heading">Support &amp; Feedback</a>
        <a href="#audit-operations-heading">Operational Audit</a>
    </nav>

    <section class="service-operations panel" aria-labelledby="service-operations-heading">
        <div class="panel-head operation-panel-head">
            <div>
                <h2 id="service-operations-heading">Service &amp; Package Operations</h2>
            </div>
        </div>

        <div class="service-summary-grid" aria-label="Service operation summary">
            <div class="service-summary-card"><span>Total Services</span><strong><?=number_format($service_summary['services'])?></strong></div>
            <div class="service-summary-card"><span>Total Packages</span><strong><?=number_format($service_summary['total'])?></strong></div>
            <div class="service-summary-card active"><span>Active Packages</span><strong><?=number_format($service_summary['active'])?></strong></div>
            <div class="service-summary-card inactive"><span>Inactive Packages</span><strong><?=number_format($service_summary['inactive'])?></strong></div>
        </div>

        <form method="get" action="operations.php#service-operations-heading" class="service-filter-bar" id="service-filter-form" data-scroll-target="service-operations-heading">
            <div class="service-search-field">
                <label for="service-search">Search</label>
                <input id="service-search" type="search" name="service_search" value="<?=h($service_search)?>" placeholder="Provider, package, service or location">
            </div>
            <div>
                <label for="service-filter">Service</label>
                <select id="service-filter" name="service_filter">
                    <option value="">All services</option>
                    <?php foreach ($service_list as $svc): ?>
                        <option value="<?=h($svc['service_id'])?>" <?=((string)$svc['service_id']===$service_filter)?'selected':''?>><?=h($svc['service_name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="service-sort">Sort</label>
                <select id="service-sort" name="service_sort">
                    <option value="newest" <?=$service_sort==='newest'?'selected':''?>>Newest</option>
                    <option value="oldest" <?=$service_sort==='oldest'?'selected':''?>>Oldest</option>
                    <option value="price_high" <?=$service_sort==='price_high'?'selected':''?>>Price: High to Low</option>
                    <option value="price_low" <?=$service_sort==='price_low'?'selected':''?>>Price: Low to High</option>
                    <option value="rating_high" <?=$service_sort==='rating_high'?'selected':''?>>Rating: High to Low</option>
                    <option value="rating_low" <?=$service_sort==='rating_low'?'selected':''?>>Rating: Low to High</option>
                </select>
            </div>
            <div>
                <label for="service-status">Status</label>
                <select id="service-status" name="service_status">
                    <option value="All" <?=$service_status==='All'?'selected':''?>>All statuses</option>
                    <option value="Active" <?=$service_status==='Active'?'selected':''?>>Active</option>
                    <option value="Inactive" <?=$service_status==='Inactive'?'selected':''?>>Inactive</option>
                </select>
            </div>
            <button class="service-filter-btn" type="submit">Apply</button>
            <a class="service-clear-btn" data-scroll-target="service-operations-heading" href="operations.php#service-operations-heading">Clear</a>
        </form>

        <div class="service-results-meta">
            <strong>Package Directory</strong>
            <span>Showing <?= $service_total_rows ? number_format($service_offset + 1) . '–' . number_format(min($service_offset + $service_per_page, $service_total_rows)) : '0' ?> of <?=number_format($service_total_rows)?></span>
        </div>

        <div class="service-table-wrap">
            <table class="service-table">
                <thead>
                    <tr>
                        <th>Provider / Package</th>
                        <th>Service</th>
                        <th>Manager</th>
                        <th>Price</th>
                        <th>Location</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$service_rows): ?>
                    <tr><td colspan="8" class="service-empty">No service packages matched the current filters.</td></tr>
                <?php else: foreach ($service_rows as $row): ?>
                    <?php $manager_name = trim(($row['manager_first_name'] ?? '') . ' ' . ($row['manager_last_name'] ?? '')); ?>
                    <tr>
                        <td><strong><?=h($row['provider_name'])?></strong><small><?=h($row['package_name'] ?: 'Unnamed package')?></small></td>
                        <td><?=h($row['service_name'])?></td>
                        <td><?=h($manager_name ?: 'Unassigned')?></td>
                        <td>৳ <?=number_format((float)$row['price'], 2)?></td>
                        <td><?=h($row['location'] ?: '—')?></td>
                        <td><span class="service-rating">★ <?=number_format((float)$row['rating'], 1)?> <small>(<?=number_format((int)$row['review_count'])?>)</small></span></td>
                        <td><span class="service-status <?=strtolower(h($row['status']))?>"><?=h($row['status'])?></span></td>
                        <td><button type="button" class="service-view-btn" data-service-open="service-<?=h($row['provider_id'])?>">View</button></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($service_pages > 1): ?>
            <nav class="service-pagination" aria-label="Service package pagination">
                <?php if ($service_page > 1): ?><a href="?service_search=<?=urlencode($service_search)?>&service_filter=<?=urlencode($service_filter)?>&service_status=<?=urlencode($service_status)?>&service_sort=<?=urlencode($service_sort)?>&service_page=<?=$service_page-1?>#service-operations-heading" data-scroll-target="service-operations-heading">←</a><?php endif; ?>
                <span>Page <?=$service_page?> of <?=$service_pages?></span>
                <?php if ($service_page < $service_pages): ?><a href="?service_search=<?=urlencode($service_search)?>&service_filter=<?=urlencode($service_filter)?>&service_status=<?=urlencode($service_status)?>&service_sort=<?=urlencode($service_sort)?>&service_page=<?=$service_page+1?>#service-operations-heading" data-scroll-target="service-operations-heading">→</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

    <?php foreach ($service_rows as $row): ?>
        <div class="service-modal" id="service-<?=h($row['provider_id'])?>" hidden>
            <div class="service-modal-backdrop" data-service-close></div>
            <div class="service-modal-card" role="dialog" aria-modal="true" aria-labelledby="service-title-<?=h($row['provider_id'])?>">
                <button type="button" class="service-modal-close" data-service-close aria-label="Close">×</button>
                <span class="eyebrow">PACKAGE DETAILS</span>
                <h3 id="service-title-<?=h($row['provider_id'])?>"><?=h($row['package_name'] ?: 'Unnamed package')?></h3>
                <p class="service-modal-provider"><?=h($row['provider_name'])?> · <?=h($row['service_name'])?></p>
                <div class="service-detail-grid">
                    <div><small>Manager</small><strong><?=h(trim(($row['manager_first_name'] ?? '') . ' ' . ($row['manager_last_name'] ?? '')) ?: 'Unassigned')?></strong></div>
                    <div><small>Price</small><strong>৳ <?=number_format((float)$row['price'], 2)?></strong></div>
                    <div><small>Location</small><strong><?=h($row['location'] ?: '—')?></strong></div>
                    <div><small>Contact</small><strong><?=h($row['contact_number'] ?: '—')?></strong></div>
                    <div><small>Rating</small><strong>★ <?=number_format((float)$row['rating'], 1)?> (<?=number_format((int)$row['review_count'])?> reviews)</strong></div>
                    <div><small>Status</small><strong><?=h($row['status'])?></strong></div>
                </div>
                <div class="service-description">
                    <small>Package Details</small>
                    <p><?=nl2br(h($row['package_details'] ?: 'No package details provided.'))?></p>
                </div>
                <div class="service-modal-note">Operational overview only. Package editing and manager controls remain in Manager Management.</div>
            </div>
        </div>
    <?php endforeach; ?>


    <?php
    $booking_search = trim((string)($_GET['booking_search'] ?? ''));
    $booking_status = (string)($_GET['booking_status'] ?? 'All');
    $booking_page = max(1, (int)($_GET['booking_page'] ?? 1));
    $booking_sort = (string)($_GET['booking_sort'] ?? 'booked_newest');
    $booking_sort_map = [
        'booked_newest' => 'b.booking_date DESC, b.booking_id DESC',
        'booked_oldest' => 'b.booking_date ASC, b.booking_id ASC',
        'event_latest' => 'MIN(bd.event_date) DESC, b.booking_id DESC',
        'event_earliest' => 'MIN(bd.event_date) ASC, b.booking_id ASC',
        'total_high' => 'b.total_price DESC, b.booking_id DESC',
        'total_low' => 'b.total_price ASC, b.booking_id ASC',
    ];
    if (!isset($booking_sort_map[$booking_sort])) $booking_sort = 'booked_newest';
    $booking_per_page = 10;

    $booking_where = ["b.admin_removed = 0"];
    $booking_types = '';
    $booking_params = [];
    if ($booking_search !== '') {
        $booking_where[] = '(CAST(b.booking_id AS CHAR) LIKE ? OR CONCAT_WS(" ", cu.first_name, cu.last_name) LIKE ? OR CONCAT_WS(" ", mu.first_name, mu.last_name) LIKE ? OR sp.provider_name LIKE ?)';
        $term = '%' . $booking_search . '%';
        $booking_types .= 'ssss';
        array_push($booking_params, $term, $term, $term, $term);
    }
    if (in_array($booking_status, ['Pending','Confirmed','Completed','Cancelled'], true)) {
        $booking_where[] = 'b.booking_status = ?';
        $booking_types .= 's';
        $booking_params[] = $booking_status;
    }
    $booking_sql_where = 'WHERE ' . implode(' AND ', $booking_where);

    $booking_count_sql = "SELECT COUNT(DISTINCT b.booking_id)
        FROM bookings b
        INNER JOIN users cu ON cu.user_id=b.user_id
        LEFT JOIN users mu ON mu.user_id=b.manager_id
        LEFT JOIN booking_details bd ON bd.booking_id=b.booking_id
        LEFT JOIN service_providers sp ON sp.provider_id=bd.provider_id
        $booking_sql_where";
    $booking_count_stmt = mysqli_prepare($conn, $booking_count_sql);
    if ($booking_types !== '') mysqli_stmt_bind_param($booking_count_stmt, $booking_types, ...$booking_params);
    mysqli_stmt_execute($booking_count_stmt);
    $booking_count_result = mysqli_stmt_get_result($booking_count_stmt);
    $booking_total_rows = (int)(mysqli_fetch_row($booking_count_result)[0] ?? 0);
    mysqli_stmt_close($booking_count_stmt);

    $booking_pages = max(1, (int)ceil($booking_total_rows / $booking_per_page));
    if ($booking_page > $booking_pages) $booking_page = $booking_pages;
    $booking_offset = ($booking_page - 1) * $booking_per_page;

    $booking_sql = "SELECT b.booking_id, b.user_id, b.manager_id, b.total_price, b.booking_status, b.cancellation_reason,
                           b.cancelled_at, b.cancelled_by, b.confirmation_email_status, b.confirmation_email_sent_at,
                           b.cancellation_email_status, b.cancellation_email_sent_at, b.confirmed_at, b.completed_at,
                           b.booking_date,
                           CONCAT_WS(' ', cu.first_name, cu.last_name) AS customer_name,
                           CONCAT_WS(' ', mu.first_name, mu.last_name) AS manager_name,
                           COUNT(DISTINCT bd.detail_id) AS item_count,
                           MIN(bd.event_date) AS first_event_date,
                           MAX(bd.event_date) AS last_event_date,
                           GROUP_CONCAT(DISTINCT sp.provider_name ORDER BY sp.provider_name SEPARATOR ', ') AS provider_names
                    FROM bookings b
                    INNER JOIN users cu ON cu.user_id=b.user_id
                    LEFT JOIN users mu ON mu.user_id=b.manager_id
                    LEFT JOIN booking_details bd ON bd.booking_id=b.booking_id
                    LEFT JOIN service_providers sp ON sp.provider_id=bd.provider_id
                    $booking_sql_where
                    GROUP BY b.booking_id
                    ORDER BY {$booking_sort_map[$booking_sort]}
                    LIMIT ? OFFSET ?";
    $booking_stmt = mysqli_prepare($conn, $booking_sql);
    $booking_query_types = $booking_types . 'ii';
    $booking_query_params = $booking_params;
    $booking_query_params[] = $booking_per_page;
    $booking_query_params[] = $booking_offset;
    mysqli_stmt_bind_param($booking_stmt, $booking_query_types, ...$booking_query_params);
    mysqli_stmt_execute($booking_stmt);
    $booking_result = mysqli_stmt_get_result($booking_stmt);
    $booking_rows = [];
    while ($row = mysqli_fetch_assoc($booking_result)) $booking_rows[] = $row;
    mysqli_stmt_close($booking_stmt);

    $booking_details_map = [];
    if ($booking_rows) {
        $booking_ids = array_map(static fn($r) => (int)$r['booking_id'], $booking_rows);
        $placeholders = implode(',', array_fill(0, count($booking_ids), '?'));
        $detail_sql = "SELECT bd.booking_id, bd.quantity, bd.unit_price, bd.event_date, bd.special_instruction,
                              sp.provider_name, sp.package_name, s.service_name,
                              CONCAT_WS(' ', mu.first_name, mu.last_name) AS detail_manager_name
                       FROM booking_details bd
                       INNER JOIN service_providers sp ON sp.provider_id=bd.provider_id
                       INNER JOIN services s ON s.service_id=sp.service_id
                       LEFT JOIN users mu ON mu.user_id=bd.manager_id
                       WHERE bd.booking_id IN ($placeholders)
                       ORDER BY bd.booking_id DESC, bd.detail_id ASC";
        $detail_stmt = mysqli_prepare($conn, $detail_sql);
        $detail_types = str_repeat('i', count($booking_ids));
        mysqli_stmt_bind_param($detail_stmt, $detail_types, ...$booking_ids);
        mysqli_stmt_execute($detail_stmt);
        $detail_result = mysqli_stmt_get_result($detail_stmt);
        while ($detail = mysqli_fetch_assoc($detail_result)) $booking_details_map[(int)$detail['booking_id']][] = $detail;
        mysqli_stmt_close($detail_stmt);
    }

    $booking_summary = [
        'total' => scalar_count($conn, 'SELECT COUNT(*) FROM bookings WHERE admin_removed=0'),
        'pending' => scalar_count($conn, "SELECT COUNT(*) FROM bookings WHERE admin_removed=0 AND booking_status='Pending'"),
        'confirmed' => scalar_count($conn, "SELECT COUNT(*) FROM bookings WHERE admin_removed=0 AND booking_status='Confirmed'"),
        'completed' => scalar_count($conn, "SELECT COUNT(*) FROM bookings WHERE admin_removed=0 AND booking_status='Completed'"),
        'cancelled' => scalar_count($conn, "SELECT COUNT(*) FROM bookings WHERE admin_removed=0 AND booking_status='Cancelled'"),
    ];
    $booking_query = function(array $overrides = []) use ($booking_search, $booking_status, $booking_sort, $booking_page) {
        $q = ['booking_search'=>$booking_search, 'booking_status'=>$booking_status, 'booking_sort'=>$booking_sort, 'booking_page'=>$booking_page];
        foreach ($overrides as $k=>$v) $q[$k] = $v;
        return http_build_query($q);
    };

    $match_search = trim((string)($_GET['match_search'] ?? ''));
    $match_status = (string)($_GET['match_status'] ?? 'All');
    $match_relationship = (string)($_GET['match_relationship'] ?? 'All');
    $match_page = max(1, (int)($_GET['match_page'] ?? 1));
    $match_sort = (string)($_GET['match_sort'] ?? 'newest');
    $match_sort_map = [
        'newest' => 'm.created_at DESC, m.match_id DESC',
        'oldest' => 'm.created_at ASC, m.match_id ASC',
        'responded_newest' => 'm.responded_at DESC, m.match_id DESC',
        'responded_oldest' => 'm.responded_at ASC, m.match_id ASC',
    ];
    if (!isset($match_sort_map[$match_sort])) $match_sort = 'newest';
    $match_per_page = 15;
    $match_where = [];
    $match_types = '';
    $match_params = [];
    if ($match_search !== '') {
        if (ctype_digit($match_search)) {
            $match_where[] = "(m.match_id = ? OR s.user_id = ? OR r.user_id = ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ? OR s.email LIKE ? OR r.email LIKE ?)";
            $match_types .= 'iiissss';
            $id = (int)$match_search; $term = '%' . $match_search . '%';
            array_push($match_params, $id, $id, $id, $term, $term, $term, $term);
        } else {
            $match_where[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ? OR s.email LIKE ? OR r.email LIKE ?)";
            $match_types .= 'ssss';
            $term = '%' . $match_search . '%';
            array_push($match_params, $term, $term, $term, $term);
        }
    }
    if (in_array($match_status, ['Pending','Accepted','Rejected','Cancelled','Unmatched'], true)) {
        $match_where[] = 'm.status = ?'; $match_types .= 's'; $match_params[] = $match_status;
    }
    if ($match_relationship === 'Active') $match_where[] = 'm.relationship_active = 1';
    elseif ($match_relationship === 'Inactive') $match_where[] = 'm.relationship_active = 0';
    $match_sql_where = $match_where ? 'WHERE ' . implode(' AND ', $match_where) : '';

    $match_count_sql = "SELECT COUNT(*) FROM matches m INNER JOIN users s ON s.user_id=m.sender_user_id INNER JOIN users r ON r.user_id=m.receiver_user_id $match_sql_where";
    $match_count_stmt = mysqli_prepare($conn, $match_count_sql);
    if ($match_types !== '') mysqli_stmt_bind_param($match_count_stmt, $match_types, ...$match_params);
    mysqli_stmt_execute($match_count_stmt); $match_count_result = mysqli_stmt_get_result($match_count_stmt);
    $match_total_rows = (int)(mysqli_fetch_row($match_count_result)[0] ?? 0); mysqli_stmt_close($match_count_stmt);
    $match_pages = max(1, (int)ceil($match_total_rows / $match_per_page));
    if ($match_page > $match_pages) $match_page = $match_pages;
    $match_offset = ($match_page - 1) * $match_per_page;

    $match_sql = "SELECT m.match_id, m.sender_user_id, m.receiver_user_id, m.status, m.relationship_active,
                         m.responded_at, m.matched_at, m.unmatched_at, m.unmatched_by, m.closed_reason, m.created_at,
                         CONCAT(s.first_name, ' ', s.last_name) AS sender_name, s.email AS sender_email,
                         CONCAT(r.first_name, ' ', r.last_name) AS receiver_name, r.email AS receiver_email,
                         CONCAT(u.first_name, ' ', u.last_name) AS unmatched_by_name
                  FROM matches m INNER JOIN users s ON s.user_id=m.sender_user_id INNER JOIN users r ON r.user_id=m.receiver_user_id
                  LEFT JOIN users u ON u.user_id=m.unmatched_by $match_sql_where
                  ORDER BY {$match_sort_map[$match_sort]} LIMIT ? OFFSET ?";
    $match_stmt = mysqli_prepare($conn, $match_sql);
    $match_query_types = $match_types . 'ii'; $match_query_params = $match_params; $match_query_params[] = $match_per_page; $match_query_params[] = $match_offset;
    mysqli_stmt_bind_param($match_stmt, $match_query_types, ...$match_query_params); mysqli_stmt_execute($match_stmt);
    $match_result = mysqli_stmt_get_result($match_stmt); $match_rows = [];
    while ($row = mysqli_fetch_assoc($match_result)) $match_rows[] = $row; mysqli_stmt_close($match_stmt);
    $match_summary = [
        'total' => scalar_count($conn, 'SELECT COUNT(*) FROM matches'),
        'pending' => scalar_count($conn, "SELECT COUNT(*) FROM matches WHERE status='Pending'"),
        'accepted' => scalar_count($conn, "SELECT COUNT(*) FROM matches WHERE status='Accepted'"),
        'active' => scalar_count($conn, "SELECT COUNT(*) FROM matches WHERE status='Accepted' AND relationship_active=1"),
        'closed' => scalar_count($conn, "SELECT COUNT(*) FROM matches WHERE status IN ('Rejected','Cancelled','Unmatched')"),
    ];
    $match_query = function(array $overrides = []) use ($match_search, $match_status, $match_relationship, $match_sort, $match_page) {
        $q = ['match_search'=>$match_search, 'match_status'=>$match_status, 'match_relationship'=>$match_relationship, 'match_sort'=>$match_sort, 'match_page'=>$match_page];
        foreach ($overrides as $k=>$v) $q[$k] = $v;
        return http_build_query(array_filter($q, static fn($v) => $v !== '' && $v !== 'All'));
    };

    $comm_search = trim((string)($_GET['comm_search'] ?? ''));
    $comm_request_status = (string)($_GET['comm_request_status'] ?? 'All');
    $comm_chat_status = (string)($_GET['comm_chat_status'] ?? 'All');
    $comm_page = max(1, (int)($_GET['comm_page'] ?? 1));
    $comm_sort = (string)($_GET['comm_sort'] ?? 'newest');
    $comm_sort_map = [
        'newest' => 'cr.created_at DESC, cr.chat_request_id DESC',
        'oldest' => 'cr.created_at ASC, cr.chat_request_id ASC',
        'messages_high' => 'COALESCE(mc.message_count, 0) DESC, cr.chat_request_id DESC',
        'messages_low' => 'COALESCE(mc.message_count, 0) ASC, cr.chat_request_id ASC',
    ];
    if (!isset($comm_sort_map[$comm_sort])) $comm_sort = 'newest';
    $comm_per_page = 15;
    $comm_where = [];
    $comm_types = '';
    $comm_params = [];
    if ($comm_search !== '') {
        if (ctype_digit($comm_search)) {
            $comm_where[] = "(cr.chat_request_id = ? OR cr.match_id = ? OR s.user_id = ? OR r.user_id = ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ? OR s.email LIKE ? OR r.email LIKE ? )";
            $comm_types .= 'iiiissss';
            $id = (int)$comm_search; $term = '%' . $comm_search . '%';
            array_push($comm_params, $id, $id, $id, $id, $term, $term, $term, $term);
        } else {
            $comm_where[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ? OR s.email LIKE ? OR r.email LIKE ?)";
            $comm_types .= 'ssss';
            $term = '%' . $comm_search . '%';
            array_push($comm_params, $term, $term, $term, $term);
        }
    }
    if (in_array($comm_request_status, ['Pending','Accepted','Rejected','Cancelled'], true)) {
        $comm_where[] = 'cr.status = ?'; $comm_types .= 's'; $comm_params[] = $comm_request_status;
    }
    if ($comm_chat_status === 'Active') $comm_where[] = 'c.status = \'Active\'';
    elseif ($comm_chat_status === 'Closed') $comm_where[] = 'c.status = \'Closed\'';
    elseif ($comm_chat_status === 'No Conversation') $comm_where[] = 'c.conversation_id IS NULL';
    $comm_sql_where = $comm_where ? 'WHERE ' . implode(' AND ', $comm_where) : '';

    $comm_count_sql = "SELECT COUNT(*) FROM chat_requests cr
        INNER JOIN users s ON s.user_id=cr.sender_user_id
        INNER JOIN users r ON r.user_id=cr.receiver_user_id
        LEFT JOIN conversations c ON c.chat_request_id=cr.chat_request_id
        $comm_sql_where";
    $comm_count_stmt = mysqli_prepare($conn, $comm_count_sql);
    if ($comm_types !== '') mysqli_stmt_bind_param($comm_count_stmt, $comm_types, ...$comm_params);
    mysqli_stmt_execute($comm_count_stmt); $comm_count_result = mysqli_stmt_get_result($comm_count_stmt);
    $comm_total_rows = (int)(mysqli_fetch_row($comm_count_result)[0] ?? 0); mysqli_stmt_close($comm_count_stmt);
    $comm_pages = max(1, (int)ceil($comm_total_rows / $comm_per_page));
    if ($comm_page > $comm_pages) $comm_page = $comm_pages;
    $comm_offset = ($comm_page - 1) * $comm_per_page;

    $comm_sql = "SELECT cr.chat_request_id, cr.match_id, cr.sender_user_id, cr.receiver_user_id, cr.status AS request_status,
                        cr.chat_active, cr.responded_at, cr.closed_at, cr.created_at,
                        CONCAT(s.first_name, ' ', s.last_name) AS sender_name, s.email AS sender_email,
                        CONCAT(r.first_name, ' ', r.last_name) AS receiver_name, r.email AS receiver_email,
                        c.conversation_id, c.status AS chat_status, c.message_limit, c.user1_message_count, c.user2_message_count, c.started_at,
                        COALESCE(mc.message_count, 0) AS message_count
                 FROM chat_requests cr
                 INNER JOIN users s ON s.user_id=cr.sender_user_id
                 INNER JOIN users r ON r.user_id=cr.receiver_user_id
                 LEFT JOIN conversations c ON c.chat_request_id=cr.chat_request_id
                 LEFT JOIN (SELECT conversation_id, COUNT(*) AS message_count FROM conversation_messages GROUP BY conversation_id) mc ON mc.conversation_id=c.conversation_id
                 $comm_sql_where
                 ORDER BY {$comm_sort_map[$comm_sort]} LIMIT ? OFFSET ?";
    $comm_stmt = mysqli_prepare($conn, $comm_sql);
    $comm_query_types = $comm_types . 'ii'; $comm_query_params = $comm_params; $comm_query_params[] = $comm_per_page; $comm_query_params[] = $comm_offset;
    mysqli_stmt_bind_param($comm_stmt, $comm_query_types, ...$comm_query_params); mysqli_stmt_execute($comm_stmt);
    $comm_result = mysqli_stmt_get_result($comm_stmt); $comm_rows = [];
    while ($row = mysqli_fetch_assoc($comm_result)) $comm_rows[] = $row; mysqli_stmt_close($comm_stmt);

    $comm_summary = [
        'requests' => scalar_count($conn, 'SELECT COUNT(*) FROM chat_requests'),
        'pending' => scalar_count($conn, "SELECT COUNT(*) FROM chat_requests WHERE status='Pending'"),
        'accepted' => scalar_count($conn, "SELECT COUNT(*) FROM chat_requests WHERE status='Accepted'"),
        'active' => scalar_count($conn, 'SELECT COUNT(*) FROM chat_requests WHERE chat_active=1'),
        'closed' => scalar_count($conn, "SELECT COUNT(*) FROM conversations WHERE status='Closed'"),
        'messages' => scalar_count($conn, 'SELECT COUNT(*) FROM conversation_messages'),
    ];
    $comm_query = function(array $overrides = []) use ($comm_search, $comm_request_status, $comm_chat_status, $comm_sort, $comm_page) {
        $q = ['comm_search'=>$comm_search, 'comm_request_status'=>$comm_request_status, 'comm_chat_status'=>$comm_chat_status, 'comm_sort'=>$comm_sort, 'comm_page'=>$comm_page];
        foreach ($overrides as $k=>$v) $q[$k] = $v;
        return http_build_query(array_filter($q, static fn($v) => $v !== '' && $v !== 'All'));
    };

    ?>

    <section class="booking-operations panel" aria-labelledby="booking-operations-heading">
        <div class="panel-head operation-panel-head">
            <div>
                <h2 id="booking-operations-heading">Booking Operations</h2>
            </div>
        </div>

        <div class="booking-summary-grid">
            <div class="booking-summary-card"><span>Total Bookings</span><strong><?=number_format($booking_summary['total'])?></strong></div>
            <div class="booking-summary-card"><span>Pending</span><strong><?=number_format($booking_summary['pending'])?></strong></div>
            <div class="booking-summary-card"><span>Confirmed</span><strong><?=number_format($booking_summary['confirmed'])?></strong></div>
            <div class="booking-summary-card"><span>Completed</span><strong><?=number_format($booking_summary['completed'])?></strong></div>
        </div>

        <form method="get" action="operations.php#booking-operations-heading" class="booking-filter-bar" id="booking-filter-form" data-scroll-target="booking-operations-heading">
            <div>
                <label for="booking_search">Search Booking</label>
                <input id="booking_search" name="booking_search" value="<?=h($booking_search)?>" placeholder="Booking ID, customer, manager or provider">
            </div>
            <div>
                <label for="booking_status">Status</label>
                <select id="booking_status" name="booking_status">
                    <?php foreach (['All','Pending','Confirmed','Completed','Cancelled'] as $status): ?>
                        <option value="<?=h($status)?>" <?= $booking_status === $status ? 'selected' : '' ?>><?=h($status)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="booking_sort">Sort</label>
                <select id="booking_sort" name="booking_sort">
                    <option value="booked_newest" <?=$booking_sort==='booked_newest'?'selected':''?>>Booked: Newest</option>
                    <option value="booked_oldest" <?=$booking_sort==='booked_oldest'?'selected':''?>>Booked: Oldest</option>
                    <option value="event_latest" <?=$booking_sort==='event_latest'?'selected':''?>>Event Date: Latest</option>
                    <option value="event_earliest" <?=$booking_sort==='event_earliest'?'selected':''?>>Event Date: Earliest</option>
                    <option value="total_high" <?=$booking_sort==='total_high'?'selected':''?>>Total: High to Low</option>
                    <option value="total_low" <?=$booking_sort==='total_low'?'selected':''?>>Total: Low to High</option>
                </select>
            </div>
            <div class="booking-filter-actions"><button class="booking-filter-btn" type="submit">Apply Filters</button><a class="booking-clear-btn" data-scroll-target="booking-operations-heading" href="operations.php#booking-operations-heading">Clear</a></div>
        </form>

        <div class="booking-results-meta">
            <strong>Showing <?= $booking_total_rows ? (($booking_page-1)*$booking_per_page+1) : 0 ?>–<?= min($booking_page*$booking_per_page,$booking_total_rows) ?> of <?=number_format($booking_total_rows)?></strong>
            <span>Operational view · newest bookings first</span>
        </div>

        <div class="booking-table-wrap">
            <table class="booking-table">
                <thead><tr><th>Booking</th><th>Customer</th><th>Manager</th><th>Services</th><th>Total</th><th>Event Date</th><th>Status</th><th>Booked</th><th>View</th></tr></thead>
                <tbody>
                <?php if (!$booking_rows): ?>
                    <tr><td colspan="9" class="service-empty">No bookings matched the current filters.</td></tr>
                <?php else: foreach ($booking_rows as $row): ?>
                    <tr>
                        <td><strong>#<?=h($row['booking_id'])?></strong><small><?=number_format((int)$row['item_count'])?> item<?=((int)$row['item_count']===1?'':'s')?></small></td>
                        <td><?=h($row['customer_name'] ?: 'Unknown user')?></td>
                        <td><?=h($row['manager_name'] ?: 'Unassigned')?></td>
                        <td><strong><?=h($row['provider_names'] ?: 'No service item')?></strong></td>
                        <td>৳ <?=number_format((float)$row['total_price'],2)?></td>
                        <td><?=h($row['first_event_date'] ?: '—')?><?=($row['last_event_date'] && $row['last_event_date'] !== $row['first_event_date']) ? ' → '.h($row['last_event_date']) : ''?></td>
                        <td><span class="booking-status <?=strtolower(h($row['booking_status']))?>"><?=h($row['booking_status'])?></span></td>
                        <td><?=h(date('d M Y', strtotime($row['booking_date'])))?></td>
                        <td><button type="button" class="booking-view-btn" data-booking-open="booking-<?=h($row['booking_id'])?>">View</button></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($booking_pages > 1): ?>
            <nav class="booking-pagination" aria-label="Booking pagination">
                <?php if ($booking_page > 1): ?><a href="?<?=h($booking_query(['booking_page'=>$booking_page-1]))?>#booking-operations-heading" data-scroll-target="booking-operations-heading">←</a><?php endif; ?>
                <span>Page <?=$booking_page?> of <?=$booking_pages?></span>
                <?php if ($booking_page < $booking_pages): ?><a href="?<?=h($booking_query(['booking_page'=>$booking_page+1]))?>#booking-operations-heading" data-scroll-target="booking-operations-heading">→</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

    <?php foreach ($booking_rows as $row): ?>
        <div class="booking-modal" id="booking-<?=h($row['booking_id'])?>" hidden>
            <div class="booking-modal-backdrop" data-booking-close></div>
            <div class="booking-modal-card" role="dialog" aria-modal="true" aria-labelledby="booking-title-<?=h($row['booking_id'])?>">
                <button type="button" class="booking-modal-close" data-booking-close aria-label="Close">×</button>
                <span class="eyebrow">BOOKING DETAILS</span>
                <h3 id="booking-title-<?=h($row['booking_id'])?>">Booking #<?=h($row['booking_id'])?></h3>
                <p class="booking-modal-sub"><?=h($row['customer_name'] ?: 'Unknown user')?> · <?=h($row['booking_status'])?></p>
                <div class="booking-detail-grid">
                    <div><small>Customer</small><strong><?=h($row['customer_name'] ?: 'Unknown user')?></strong></div>
                    <div><small>Manager</small><strong><?=h($row['manager_name'] ?: 'Unassigned')?></strong></div>
                    <div><small>Total Price</small><strong>৳ <?=number_format((float)$row['total_price'],2)?></strong></div>
                    <div><small>Booking Date</small><strong><?=h(date('d M Y, h:i A', strtotime($row['booking_date'])))?></strong></div>
                    <div><small>Event Date</small><strong><?=h($row['first_event_date'] ?: '—')?><?=($row['last_event_date'] && $row['last_event_date'] !== $row['first_event_date']) ? ' → '.h($row['last_event_date']) : ''?></strong></div>
                    <div><small>Status</small><strong><?=h($row['booking_status'])?></strong></div>
                    <div><small>Confirmation Email</small><strong><?=h($row['confirmation_email_status'])?></strong></div>
                    <div><small>Cancellation Email</small><strong><?=h($row['cancellation_email_status'])?></strong></div>
                    <div><small>Cancelled By</small><strong><?=h($row['cancelled_by'] ?: '—')?></strong></div>
                </div>
                <div class="booking-items">
                    <h4>Booked Service Items</h4>
                    <table><thead><tr><th>Service / Package</th><th>Manager</th><th>Qty</th><th>Unit Price</th><th>Event Date</th></tr></thead><tbody>
                    <?php foreach (($booking_details_map[(int)$row['booking_id']] ?? []) as $detail): ?>
                        <tr><td><strong><?=h($detail['service_name'])?></strong><small><?=h($detail['provider_name'])?> · <?=h($detail['package_name'] ?: 'Unnamed package')?></small></td><td><?=h($detail['detail_manager_name'] ?: 'Unassigned')?></td><td><?=h($detail['quantity'])?></td><td>৳ <?=number_format((float)$detail['unit_price'],2)?></td><td><?=h($detail['event_date'])?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
                <?php $instructions = array_values(array_filter(array_map(static fn($d) => trim((string)($d['special_instruction'] ?? '')), $booking_details_map[(int)$row['booking_id']] ?? []))); ?>
                <?php if ($instructions): ?><div class="booking-instructions"><small>Special Instructions</small><p><?=h(implode("\n", array_unique($instructions)))?></p></div><?php endif; ?>
                <?php if ($row['booking_status'] === 'Cancelled' && $row['cancellation_reason']): ?><div class="booking-instructions"><small>Cancellation Reason</small><p><?=h($row['cancellation_reason'])?></p></div><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <section class="match-operations panel" aria-labelledby="match-operations-heading">
        <div class="panel-head operation-panel-head"><div><h2 id="match-operations-heading">Matching Operations</h2></div></div>
        <div class="match-summary-grid">
            <div class="match-summary-card"><span>Total Interests</span><strong><?=number_format($match_summary['total'])?></strong></div><div class="match-summary-card"><span>Pending</span><strong><?=number_format($match_summary['pending'])?></strong></div><div class="match-summary-card"><span>Accepted</span><strong><?=number_format($match_summary['accepted'])?></strong></div><div class="match-summary-card"><span>Active Relationships</span><strong><?=number_format($match_summary['active'])?></strong></div><div class="match-summary-card"><span>Closed / Ended</span><strong><?=number_format($match_summary['closed'])?></strong></div>
        </div>
        <form class="match-filter-bar" id="match-filter-form" data-scroll-target="match-operations-heading" method="get" action="operations.php#match-operations-heading">
            <div class="match-search-field"><label for="match_search">Search Matching Activity</label><input id="match_search" name="match_search" value="<?=h($match_search)?>" placeholder="Match ID, user ID, name or email"></div>
            <div><label for="match_status">Status</label><select id="match_status" name="match_status"><option value="All" <?=$match_status==='All'?'selected':''?>>All statuses</option><?php foreach(['Pending','Accepted','Rejected','Cancelled','Unmatched'] as $status): ?><option value="<?=h($status)?>" <?=$match_status===$status?'selected':''?>><?=h($status)?></option><?php endforeach; ?></select></div>
            <div><label for="match_relationship">Relationship</label><select id="match_relationship" name="match_relationship"><option value="All" <?=$match_relationship==='All'?'selected':''?>>All</option><option value="Active" <?=$match_relationship==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=$match_relationship==='Inactive'?'selected':''?>>Inactive / Ended</option></select></div>
            <div><label for="match_sort">Sort</label><select id="match_sort" name="match_sort"><option value="newest" <?=$match_sort==='newest'?'selected':''?>>Created: Newest</option><option value="oldest" <?=$match_sort==='oldest'?'selected':''?>>Created: Oldest</option><option value="responded_newest" <?=$match_sort==='responded_newest'?'selected':''?>>Responded: Newest</option><option value="responded_oldest" <?=$match_sort==='responded_oldest'?'selected':''?>>Responded: Oldest</option></select></div>
            <input type="hidden" name="match_page" value="1"><div><button class="match-filter-btn" type="submit">Apply</button></div><div><a class="match-clear-btn" data-scroll-target="match-operations-heading" href="operations.php#match-operations-heading">Clear</a></div>
        </form>
        <div class="match-results-meta"><strong>Showing <?=$match_total_rows ? (($match_page-1)*$match_per_page+1) : 0?>–<?=min($match_page*$match_per_page,$match_total_rows)?> of <?=number_format($match_total_rows)?></strong><span>Operational view · newest matching activity first</span></div>
        <div class="match-table-wrap"><table class="match-table"><thead><tr><th>Match</th><th>Sender</th><th>Receiver</th><th>Status</th><th>Relationship</th><th>Matched</th><th>Created</th><th>View</th></tr></thead><tbody>
        <?php if(!$match_rows): ?><tr><td colspan="8" class="service-empty">No matching activity matched the current filters.</td></tr><?php else: foreach($match_rows as $row): ?>
            <tr><td><strong>#<?=h($row['match_id'])?></strong><small>#<?=h($row['sender_user_id'])?> → #<?=h($row['receiver_user_id'])?></small></td><td><strong><?=h($row['sender_name'])?></strong><small><?=h($row['sender_email'])?></small></td><td><strong><?=h($row['receiver_name'])?></strong><small><?=h($row['receiver_email'])?></small></td><td><span class="match-status <?=strtolower(h($row['status']))?>"><?=h($row['status'])?></span></td><td><span class="match-relationship <?=((int)$row['relationship_active']===1?'active':'inactive')?>"><?=((int)$row['relationship_active']===1?'Active':'Inactive')?></span></td><td><?=h($row['matched_at'] ?: '—')?></td><td><?=h(date('d M Y', strtotime($row['created_at'])))?></td><td><button type="button" class="match-view-btn" data-match-open="match-<?=h($row['match_id'])?>">View</button></td></tr>
        <?php endforeach; endif; ?></tbody></table></div>
        <?php if($match_pages>1): ?><nav class="match-pagination" aria-label="Matching activity pagination"><?php if($match_page>1): ?><a href="?<?=h($match_query(['match_page'=>$match_page-1]))?>#match-operations-heading" data-scroll-target="match-operations-heading">←</a><?php endif; ?><span>Page <?=$match_page?> of <?=$match_pages?></span><?php if($match_page<$match_pages): ?><a href="?<?=h($match_query(['match_page'=>$match_page+1]))?>#match-operations-heading" data-scroll-target="match-operations-heading">→</a><?php endif; ?></nav><?php endif; ?>
    </section>
    <?php foreach($match_rows as $row): ?><div class="match-modal" id="match-<?=h($row['match_id'])?>" hidden><div class="match-modal-backdrop" data-match-close></div><div class="match-modal-card" role="dialog" aria-modal="true" aria-labelledby="match-title-<?=h($row['match_id'])?>"><button type="button" class="match-modal-close" data-match-close aria-label="Close">×</button><span class="eyebrow">MATCHING DETAILS</span><h3 id="match-title-<?=h($row['match_id'])?>">Match #<?=h($row['match_id'])?></h3><p class="match-modal-sub"><?=h($row['status'])?> · <?=((int)$row['relationship_active']===1?'Active relationship':'Inactive / ended')?></p><div class="match-detail-grid"><div><small>Sender</small><strong><?=h($row['sender_name'])?></strong><span>#<?=h($row['sender_user_id'])?></span></div><div><small>Receiver</small><strong><?=h($row['receiver_name'])?></strong><span>#<?=h($row['receiver_user_id'])?></span></div><div><small>Status</small><strong><?=h($row['status'])?></strong></div><div><small>Relationship</small><strong><?=((int)$row['relationship_active']===1?'Active':'Inactive')?></strong></div><div><small>Created</small><strong><?=h($row['created_at'])?></strong></div><div><small>Responded</small><strong><?=h($row['responded_at'] ?: '—')?></strong></div><div><small>Matched At</small><strong><?=h($row['matched_at'] ?: '—')?></strong></div><div><small>Unmatched At</small><strong><?=h($row['unmatched_at'] ?: '—')?></strong></div><div><small>Closed Reason</small><strong><?=h($row['closed_reason'] ?: '—')?></strong></div><div><small>Unmatched By</small><strong><?=h($row['unmatched_by_name'] ?: ($row['unmatched_by'] ? '#'.$row['unmatched_by'] : '—'))?></strong></div></div><div class="match-modal-note">This section is operational oversight only. Match acceptance, rejection, cancellation and relationship controls remain outside the Operation Center.</div></div></div><?php endforeach; ?>

    <section class="communication-operations panel" aria-labelledby="communication-operations-heading">
        <div class="panel-head operation-panel-head">
            <div>
                <h2 id="communication-operations-heading">Communication Operations</h2>
            </div>
        </div>

        <div class="communication-summary-grid">
            <div class="communication-summary-card"><span>Total Chat Requests</span><strong><?=number_format($comm_summary['requests'])?></strong></div>
            <div class="communication-summary-card pending"><span>Pending Requests</span><strong><?=number_format($comm_summary['pending'])?></strong></div>
            <div class="communication-summary-card accepted"><span>Accepted Requests</span><strong><?=number_format($comm_summary['accepted'])?></strong></div>
            <div class="communication-summary-card active"><span>Active Chats</span><strong><?=number_format($comm_summary['active'])?></strong></div>
            <div class="communication-summary-card"><span>Closed Conversations</span><strong><?=number_format($comm_summary['closed'])?></strong></div>
            <div class="communication-summary-card"><span>Total Messages</span><strong><?=number_format($comm_summary['messages'])?></strong></div>
        </div>

        <form method="get" action="operations.php#communication-operations-heading" class="communication-filter-bar" id="communication-filter-form" data-scroll-target="communication-operations-heading">
            <div class="communication-search-field">
                <label for="comm_search">Search Communication Activity</label>
                <input id="comm_search" name="comm_search" value="<?=h($comm_search)?>" placeholder="Request ID, match ID, user ID, name or email">
            </div>
            <div>
                <label for="comm_request_status">Request Status</label>
                <select id="comm_request_status" name="comm_request_status">
                    <?php foreach (['All','Pending','Accepted','Rejected','Cancelled'] as $status): ?><option value="<?=h($status)?>" <?=$comm_request_status===$status?'selected':''?>><?=h($status)?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="comm_chat_status">Conversation</label>
                <select id="comm_chat_status" name="comm_chat_status">
                    <?php foreach (['All','Active','Closed','No Conversation'] as $status): ?><option value="<?=h($status)?>" <?=$comm_chat_status===$status?'selected':''?>><?=h($status)?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="comm_sort">Sort</label>
                <select id="comm_sort" name="comm_sort">
                    <option value="newest" <?=$comm_sort==='newest'?'selected':''?>>Request: Newest</option>
                    <option value="oldest" <?=$comm_sort==='oldest'?'selected':''?>>Request: Oldest</option>
                    <option value="messages_high" <?=$comm_sort==='messages_high'?'selected':''?>>Messages: High to Low</option>
                    <option value="messages_low" <?=$comm_sort==='messages_low'?'selected':''?>>Messages: Low to High</option>
                </select>
            </div>
            <div><button class="communication-filter-btn" type="submit">Apply Filters</button></div>
            <div><a class="communication-clear-btn" data-scroll-target="communication-operations-heading" href="operations.php#communication-operations-heading">Clear</a></div>
        </form>

        <div class="communication-results-meta">
            <strong>Showing <?=$comm_total_rows ? (($comm_page-1)*$comm_per_page+1) : 0?>–<?=min($comm_page*$comm_per_page,$comm_total_rows)?> of <?=number_format($comm_total_rows)?></strong>
            <span>Operational view · newest chat requests first</span>
        </div>

        <div class="communication-table-wrap">
            <table class="communication-table">
                <thead><tr><th>Request</th><th>Participants</th><th>Request</th><th>Conversation</th><th>Messages</th><th>Started</th><th>View</th></tr></thead>
                <tbody>
                <?php if(!$comm_rows): ?><tr><td colspan="7" class="service-empty">No communication activity matched the current filters.</td></tr>
                <?php else: foreach($comm_rows as $row): ?>
                    <tr>
                        <td><strong>#<?=h($row['chat_request_id'])?></strong><small>Match #<?=h($row['match_id'])?></small></td>
                        <td><strong><?=h($row['sender_name'])?></strong><small>↔ <?=h($row['receiver_name'])?></small></td>
                        <td><span class="communication-status <?=strtolower(h($row['request_status']))?>"><?=h($row['request_status'])?></span></td>
                        <td><?php if($row['conversation_id']): ?><span class="communication-chat <?=strtolower(h($row['chat_status']))?>"><?=h($row['chat_status'])?></span><small>#<?=h($row['conversation_id'])?></small><?php else: ?><span class="communication-chat none">Not started</span><?php endif; ?></td>
                        <td><strong><?=number_format((int)$row['message_count'])?></strong><small>of <?=number_format((int)$row['message_limit'])?> limit</small></td>
                        <td><?=h($row['started_at'] ?: '—')?></td>
                        <td><button type="button" class="communication-view-btn" data-communication-open="communication-<?=h($row['chat_request_id'])?>">View</button></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($comm_pages>1): ?><nav class="communication-pagination" aria-label="Communication pagination">
            <?php if($comm_page>1): ?><a href="?<?=h($comm_query(['comm_page'=>$comm_page-1]))?>#communication-operations-heading" data-scroll-target="communication-operations-heading">←</a><?php endif; ?>
            <span>Page <?=$comm_page?> of <?=$comm_pages?></span>
            <?php if($comm_page<$comm_pages): ?><a href="?<?=h($comm_query(['comm_page'=>$comm_page+1]))?>#communication-operations-heading" data-scroll-target="communication-operations-heading">→</a><?php endif; ?>
        </nav><?php endif; ?>
    </section>

    <?php foreach($comm_rows as $row): ?>
        <div class="communication-modal" id="communication-<?=h($row['chat_request_id'])?>" hidden>
            <div class="communication-modal-backdrop" data-communication-close></div>
            <div class="communication-modal-card" role="dialog" aria-modal="true" aria-labelledby="communication-title-<?=h($row['chat_request_id'])?>">
                <button type="button" class="communication-modal-close" data-communication-close aria-label="Close">×</button>
                <span class="eyebrow">COMMUNICATION DETAILS</span>
                <h3 id="communication-title-<?=h($row['chat_request_id'])?>">Chat Request #<?=h($row['chat_request_id'])?></h3>
                <p class="communication-modal-sub">Operational metadata only · message content is not displayed</p>
                <div class="communication-detail-grid">
                    <div><small>Match</small><strong>#<?=h($row['match_id'])?></strong></div>
                    <div><small>Request Status</small><strong><?=h($row['request_status'])?></strong></div>
                    <div><small>Sender</small><strong><?=h($row['sender_name'])?></strong><span>#<?=h($row['sender_user_id'])?> · <?=h($row['sender_email'])?></span></div>
                    <div><small>Receiver</small><strong><?=h($row['receiver_name'])?></strong><span>#<?=h($row['receiver_user_id'])?> · <?=h($row['receiver_email'])?></span></div>
                    <div><small>Chat Status</small><strong><?=h($row['chat_status'] ?: 'Not started')?></strong></div>
                    <div><small>Chat Active</small><strong><?=((int)$row['chat_active']===1?'Yes':'No')?></strong></div>
                    <div><small>Conversation</small><strong><?=($row['conversation_id'] ? '#'.h($row['conversation_id']) : '—')?></strong></div>
                    <div><small>Message Count</small><strong><?=number_format((int)$row['message_count'])?> / <?=number_format((int)$row['message_limit'])?></strong></div>
                    <div><small>Created</small><strong><?=h($row['created_at'])?></strong></div>
                    <div><small>Responded</small><strong><?=h($row['responded_at'] ?: '—')?></strong></div>
                    <div><small>Conversation Started</small><strong><?=h($row['started_at'] ?: '—')?></strong></div>
                    <div><small>Closed</small><strong><?=h($row['closed_at'] ?: '—')?></strong></div>
                </div>
                <div class="communication-modal-note">This Operation Center section provides communication metadata and activity counts only. It does not expose private message text or provide chat/request controls.</div>
            </div>
        </div>
    <?php endforeach; ?>



    <section class="support-feedback-operations panel" aria-labelledby="support-feedback-operations-heading">
        <div class="panel-head operation-panel-head">
            <div>
                <h2 id="support-feedback-operations-heading">Support &amp; Feedback Operations</h2>
            </div>
        </div>

        <div class="support-summary-grid" aria-label="Support and feedback summary">
            <div class="support-summary-card"><span>Total Messages</span><strong><?=number_format($support_summary['total'])?></strong></div>
            <div class="support-summary-card new"><span>New</span><strong><?=number_format($support_summary['new'])?></strong></div>
            <div class="support-summary-card read"><span>Read</span><strong><?=number_format($support_summary['read'])?></strong></div>
            <div class="support-summary-card resolved"><span>Resolved</span><strong><?=number_format($support_summary['resolved'])?></strong></div>
        </div>

        <div class="support-type-switch" aria-label="Support message type">
            <a class="<?= $support_type === 'Contact' ? 'active' : '' ?>" href="?<?=h($support_query(['support_type' => $support_type === 'Contact' ? '' : 'Contact', 'support_page' => 1]))?>#support-feedback-operations-heading" data-scroll-target="support-feedback-operations-heading">
                <span>Contact</span><strong><?=number_format($support_summary['contact'])?></strong>
            </a>
            <a class="<?= $support_type === 'Feedback' ? 'active' : '' ?>" href="?<?=h($support_query(['support_type' => $support_type === 'Feedback' ? '' : 'Feedback', 'support_page' => 1]))?>#support-feedback-operations-heading" data-scroll-target="support-feedback-operations-heading">
                <span>Feedback</span><strong><?=number_format($support_summary['feedback'])?></strong>
            </a>
        </div>

        <form method="get" action="operations.php#support-feedback-operations-heading" class="support-filter-bar" id="support-filter-form" data-scroll-target="support-feedback-operations-heading">
            <?php if ($support_type !== ''): ?><input type="hidden" name="support_type" value="<?=h($support_type)?>"><?php endif; ?>
            <div class="support-search-field">
                <label for="support-search">Search Messages</label>
                <input id="support-search" type="search" name="support_search" value="<?=h($support_search)?>" placeholder="Message ID, user ID, name, email or message">
            </div>
            <div>
                <label for="support-status">Status</label>
                <select id="support-status" name="support_status">
                    <?php foreach (['All','New','Read','Resolved'] as $status): ?>
                        <option value="<?=h($status)?>" <?=$support_status === $status ? 'selected' : ''?>><?=h($status)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="support_page" value="1">
            <div><button class="support-filter-btn" type="submit">Apply Filters</button></div>
            <div><a class="support-clear-btn" data-scroll-target="support-feedback-operations-heading" href="operations.php#support-feedback-operations-heading">Clear</a></div>
        </form>

        <div class="support-results-meta">
            <strong><?= $support_type !== '' ? h($support_type) : 'All Support Messages' ?></strong>
            <span>Showing <?= $support_total_rows ? number_format($support_offset + 1) . '–' . number_format(min($support_offset + $support_per_page, $support_total_rows)) : '0' ?> of <?=number_format($support_total_rows)?></span>
        </div>

        <form method="post" action="operations.php#support-feedback-operations-heading" class="support-bulk-form" id="support-bulk-form" data-scroll-target="support-feedback-operations-heading">
            <input type="hidden" name="support_csrf" value="<?=h($support_csrf)?>">
            <input type="hidden" name="support_type" value="<?=h($support_type)?>">
            <input type="hidden" name="support_search" value="<?=h($support_search)?>">
            <input type="hidden" name="support_status" value="<?=h($support_status)?>">
            <input type="hidden" name="support_page" value="<?=h($support_page)?>">
            <div class="support-bulk-bar">
                <span><strong id="support-selected-count">0</strong> selected</span>
                <div class="support-bulk-actions">
                    <button type="submit" name="support_action" value="mark_read" class="support-action-btn" data-support-bulk>Mark Read</button>
                    <button type="submit" name="support_action" value="mark_resolved" class="support-action-btn" data-support-bulk>Mark Resolved</button>
                    <button type="submit" name="support_action" value="delete" class="support-delete-btn" data-support-bulk data-support-delete>Delete Selected</button>
                </div>
            </div>

            <div class="support-table-wrap">
                <table class="support-table">
                    <thead>
                        <tr>
                            <th class="support-check-col"><input type="checkbox" id="support-select-all" aria-label="Select all visible messages"></th>
                            <th>Message ID</th>
                            <th>Sender Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>View Message</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$support_rows): ?>
                        <tr><td colspan="7" class="service-empty">No support or feedback messages matched the current filters.</td></tr>
                    <?php else: foreach ($support_rows as $row): ?>
                        <tr>
                            <td class="support-check-col"><input type="checkbox" name="support_ids[]" value="<?=h($row['message_id'])?>" class="support-row-check" aria-label="Select message #<?=h($row['message_id'])?>"></td>
                            <td><strong>#<?=h($row['message_id'])?></strong></td>
                            <td><strong><?=h($row['name'])?></strong></td>
                            <td>
                                <div class="support-email-cell">
                                    <span><?=h($row['email'])?></span>
                                    <button type="button" class="support-copy-email" data-copy-email="<?=h($row['email'])?>" aria-label="Copy email" title="Copy email">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                </div>
                            </td>
                            <td><span class="support-status <?=strtolower(h($row['status']))?>"><?=h($row['status'])?></span></td>
                            <td><?=h(date('d M Y', strtotime($row['created_at'])))?><small><?=h(date('H:i', strtotime($row['created_at'])))?></small></td>
                            <td><button type="button" class="support-view-btn" data-support-open="support-<?=h($row['message_id'])?>">View Message</button></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php if ($support_pages > 1): ?>
            <nav class="support-pagination" aria-label="Support and feedback pagination">
                <?php if ($support_page > 1): ?><a href="?<?=h($support_query(['support_page' => $support_page - 1]))?>#support-feedback-operations-heading" data-scroll-target="support-feedback-operations-heading">←</a><?php endif; ?>
                <span>Page <?=$support_page?> of <?=$support_pages?></span>
                <?php if ($support_page < $support_pages): ?><a href="?<?=h($support_query(['support_page' => $support_page + 1]))?>#support-feedback-operations-heading" data-scroll-target="support-feedback-operations-heading">→</a><?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php foreach ($support_rows as $row): ?>
            <div class="support-modal" id="support-<?=h($row['message_id'])?>" hidden>
                <div class="support-modal-backdrop" data-support-close></div>
                <div class="support-modal-card" role="dialog" aria-modal="true" aria-labelledby="support-title-<?=h($row['message_id'])?>">
                    <button type="button" class="support-modal-close" data-support-close aria-label="Close">×</button>
                    <span class="eyebrow"><?=h(strtoupper($row['message_type']))?> MESSAGE</span>
                    <h3 id="support-title-<?=h($row['message_id'])?>">Message #<?=h($row['message_id'])?></h3>
                    <div class="support-detail-grid">
                        <div><small>Type</small><strong><?=h($row['message_type'])?></strong></div>
                        <div><small>Status</small><strong><?=h($row['status'])?></strong></div>
                        <div><small>Name</small><strong><?=h($row['name'])?></strong></div>
                        <div><small>Email</small><strong><?=h($row['email'])?></strong></div>
                        <div><small>User ID</small><strong><?=h($row['user_id'] ? '#'.$row['user_id'] : 'Guest')?></strong></div>
                        <div><small>Submitted</small><strong><?=h(date('d M Y, h:i A', strtotime($row['created_at'])))?></strong></div>
                    </div>
                    <div class="support-message-detail">
                        <small>Message</small>
                        <p><?=nl2br(h($row['message']))?></p>
                    </div>
                    <div class="support-modal-actions">
                        <?php if ($row['status'] === 'New'): ?>
                            <form method="post" action="operations.php#support-feedback-operations-heading">
                                <input type="hidden" name="support_csrf" value="<?=h($support_csrf)?>">
                                <input type="hidden" name="support_action" value="mark_read">
                                <input type="hidden" name="support_id" value="<?=h($row['message_id'])?>">
                                <input type="hidden" name="support_type" value="<?=h($support_type)?>">
                                <input type="hidden" name="support_search" value="<?=h($support_search)?>">
                                <input type="hidden" name="support_status" value="<?=h($support_status)?>">
                                <input type="hidden" name="support_page" value="<?=h($support_page)?>">
                                <button type="submit" class="support-action-btn">Mark Read</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($row['status'] !== 'Resolved'): ?>
                            <form method="post" action="operations.php#support-feedback-operations-heading">
                                <input type="hidden" name="support_csrf" value="<?=h($support_csrf)?>">
                                <input type="hidden" name="support_action" value="mark_resolved">
                                <input type="hidden" name="support_id" value="<?=h($row['message_id'])?>">
                                <input type="hidden" name="support_type" value="<?=h($support_type)?>">
                                <input type="hidden" name="support_search" value="<?=h($support_search)?>">
                                <input type="hidden" name="support_status" value="<?=h($support_status)?>">
                                <input type="hidden" name="support_page" value="<?=h($support_page)?>">
                                <button type="submit" class="support-action-btn">Mark Resolved</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="operations.php#support-feedback-operations-heading" data-support-single-delete>
                            <input type="hidden" name="support_csrf" value="<?=h($support_csrf)?>">
                            <input type="hidden" name="support_action" value="delete">
                            <input type="hidden" name="support_id" value="<?=h($row['message_id'])?>">
                            <input type="hidden" name="support_type" value="<?=h($support_type)?>">
                            <input type="hidden" name="support_search" value="<?=h($support_search)?>">
                            <input type="hidden" name="support_status" value="<?=h($support_status)?>">
                            <input type="hidden" name="support_page" value="<?=h($support_page)?>">
                            <button type="submit" class="support-delete-btn">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="audit-operations panel" aria-labelledby="audit-operations-heading">
        <div class="panel-head operation-panel-head">
            <div>
                <h2 id="audit-operations-heading">Operational Audit</h2>
            </div>
        </div>

        <div class="audit-summary-grid" aria-label="Operational audit summary">
            <div class="audit-summary-card"><span>Total Audit Events</span><strong><?=number_format($audit_summary['total'])?></strong></div>
            <div class="audit-summary-card verification"><span>Verification Events</span><strong><?=number_format($audit_summary['verification'])?></strong></div>
            <div class="audit-summary-card booking"><span>Booking Events</span><strong><?=number_format($audit_summary['booking'])?></strong></div>
            <div class="audit-summary-card matching"><span>Matching Events</span><strong><?=number_format($audit_summary['matching'])?></strong></div>
            <div class="audit-summary-card communication"><span>Communication Events</span><strong><?=number_format($audit_summary['communication'])?></strong></div>
            <div class="audit-summary-card service"><span>Service Events</span><strong><?=number_format($audit_summary['service'])?></strong></div>
        </div>

        <form method="get" action="operations.php#audit-operations-heading" class="audit-filter-bar" id="audit-filter-form" data-scroll-target="audit-operations-heading">
            <div class="audit-search-field">
                <label for="audit-search">Search</label>
                <input id="audit-search" type="search" name="audit_search" value="<?=h($audit_search)?>" placeholder="Event, ID, subject or details">
            </div>
            <div>
                <label for="audit-type">Activity Type</label>
                <select id="audit-type" name="audit_type">
                    <option value="All" <?=($audit_type==='All'?'selected':'')?>>All activity</option>
                    <?php foreach ($audit_types_allowed as $type): ?><option value="<?=h($type)?>" <?=($audit_type===$type?'selected':'')?>><?=h($type)?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="audit-sort">Sort</label>
                <select id="audit-sort" name="audit_sort">
                    <option value="newest" <?=$audit_sort==='newest'?'selected':''?>>Newest</option>
                    <option value="oldest" <?=$audit_sort==='oldest'?'selected':''?>>Oldest</option>
                </select>
            </div>
            <button type="submit" class="audit-filter-btn">Filter</button>
            <?php if($audit_search!=='' || $audit_type!=='All'): ?><a class="audit-clear-btn" data-scroll-target="audit-operations-heading" href="operations.php#audit-operations-heading">Clear</a><?php endif; ?>
        </form>

        <div class="audit-results-meta">
            <strong>Activity History</strong>
            <span>Showing <?=($audit_total_rows ? ($audit_offset+1) : 0)?>–<?=min($audit_offset+$audit_per_page,$audit_total_rows)?> of <?=number_format($audit_total_rows)?> events</span>
        </div>

        <div class="audit-table-wrap">
            <table class="audit-table">
                <thead><tr><th>Time</th><th>Type</th><th>Event</th><th>Entity</th><th>Subject</th><th>Source</th><th>Details</th></tr></thead>
                <tbody>
                <?php if(!$audit_rows): ?><tr><td colspan="7" class="service-empty">No audit activity matched the current filters.</td></tr>
                <?php else: foreach($audit_rows as $row): ?>
                    <tr>
                        <td><strong><?=h(date('d M Y', strtotime($row['event_at'])))?></strong><small><?=h(date('H:i:s', strtotime($row['event_at'])))?></small></td>
                        <td><span class="audit-type <?=strtolower(h($row['event_type']))?>"><?=h($row['event_type'])?></span></td>
                        <td><strong><?=h($row['event_label'])?></strong></td>
                        <td><?=h($row['entity_label'])?></td>
                        <td><?=h($row['subject_label'])?></td>
                        <td><?=h($row['source_label'])?></td>
                        <td><?=h($row['event_details'])?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($audit_pages>1): ?><nav class="audit-pagination" aria-label="Operational audit pagination">
            <?php if($audit_page>1): ?><a href="?<?=h(audit_query(['audit_page'=>$audit_page-1]))?>#audit-operations-heading" data-scroll-target="audit-operations-heading">←</a><?php endif; ?>
            <span>Page <?=$audit_page?> of <?=$audit_pages?></span>
            <?php if($audit_page<$audit_pages): ?><a href="?<?=h(audit_query(['audit_page'=>$audit_page+1]))?>#audit-operations-heading" data-scroll-target="audit-operations-heading">→</a><?php endif; ?>
        </nav><?php endif; ?>

    </section>


    <div class="operation-move-top">
        <a href="#operation-center-top" aria-label="Move to top">↑ <span>Move to top</span></a>
    </div>
</main>
<script>
(function(){
  const key='smart_operation_scroll_target';
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  const saveTarget=(target)=>{if(target){sessionStorage.setItem(key,target);}};

  document.querySelectorAll('form[data-scroll-target]').forEach(form=>{
    form.addEventListener('submit',()=>saveTarget(form.getAttribute('data-scroll-target')));
  });
  document.querySelectorAll('a[data-scroll-target]').forEach(link=>{
    link.addEventListener('click',()=>saveTarget(link.getAttribute('data-scroll-target')));
  });

  const savedTarget=sessionStorage.getItem(key);
  if(savedTarget){
    sessionStorage.removeItem(key);
    const restore=()=>{
      const target=document.getElementById(savedTarget);
      if(target){target.scrollIntoView({block:'start',behavior:'auto'}); return true;}
      return false;
    };
    window.requestAnimationFrame(()=>{
      if(!restore()) window.setTimeout(restore,120);
      window.setTimeout(restore,320);
    });
  }



  document.querySelectorAll('[data-service-open]').forEach(btn=>btn.addEventListener('click',()=>{const modal=document.getElementById(btn.getAttribute('data-service-open'));if(modal){modal.hidden=false;document.body.classList.add('service-modal-open');}}));
  document.querySelectorAll('[data-service-close]').forEach(btn=>btn.addEventListener('click',()=>{const modal=btn.closest('.service-modal');if(modal){modal.hidden=true;document.body.classList.remove('service-modal-open');}}));
  document.querySelectorAll('[data-booking-open]').forEach(btn=>btn.addEventListener('click',()=>{const modal=document.getElementById(btn.getAttribute('data-booking-open'));if(modal){modal.hidden=false;document.body.classList.add('service-modal-open');}}));
  document.querySelectorAll('[data-booking-close]').forEach(btn=>btn.addEventListener('click',()=>{const modal=btn.closest('.booking-modal');if(modal){modal.hidden=true;document.body.classList.remove('service-modal-open');}}));
  document.querySelectorAll('[data-match-open]').forEach(btn=>btn.addEventListener('click',()=>{const modal=document.getElementById(btn.getAttribute('data-match-open'));if(modal){modal.hidden=false;document.body.classList.add('service-modal-open');}}));
  document.querySelectorAll('[data-match-close]').forEach(btn=>btn.addEventListener('click',()=>{const modal=btn.closest('.match-modal');if(modal){modal.hidden=true;document.body.classList.remove('service-modal-open');}}));
  document.querySelectorAll('[data-communication-open]').forEach(btn=>btn.addEventListener('click',()=>{const modal=document.getElementById(btn.getAttribute('data-communication-open'));if(modal){modal.hidden=false;document.body.classList.add('service-modal-open');}}));
  document.querySelectorAll('[data-communication-close]').forEach(btn=>btn.addEventListener('click',()=>{const modal=btn.closest('.communication-modal');if(modal){modal.hidden=true;document.body.classList.remove('service-modal-open');}}));

  document.addEventListener('keydown',e=>{
    if(e.key==='Escape'){
      document.querySelectorAll('.service-modal:not([hidden]),.booking-modal:not([hidden]),.match-modal:not([hidden]),.communication-modal:not([hidden])').forEach(modal=>{modal.hidden=true;});
      document.body.classList.remove('service-modal-open');
    }
  });

  const supportUpdateSelection=()=>{
    const checks=[...document.querySelectorAll('.support-row-check')];
    const selected=checks.filter(c=>c.checked).length;
    const count=document.getElementById('support-selected-count');
    if(count) count.textContent=String(selected);
    const all=document.getElementById('support-select-all');
    if(all){
      all.checked=checks.length>0 && selected===checks.length;
      all.indeterminate=selected>0 && selected<checks.length;
    }
  };
  const supportSelectAll=document.getElementById('support-select-all');
  if(supportSelectAll){
    supportSelectAll.addEventListener('change',()=>{
      document.querySelectorAll('.support-row-check').forEach(c=>{c.checked=supportSelectAll.checked;});
      supportUpdateSelection();
    });
  }
  document.querySelectorAll('.support-row-check').forEach(c=>c.addEventListener('change',supportUpdateSelection));
  document.querySelectorAll('[data-support-bulk]').forEach(btn=>{
    btn.addEventListener('click',e=>{
      const selected=document.querySelectorAll('.support-row-check:checked').length;
      if(!selected){
        e.preventDefault();
        alert('Select at least one message first.');
        return;
      }
      if(btn.hasAttribute('data-support-delete') && !confirm('Delete the selected support/feedback messages permanently?')){
        e.preventDefault();
      }
    });
  });
  document.querySelectorAll('[data-support-single-delete]').forEach(form=>{
    form.setAttribute('data-scroll-target','support-feedback-operations-heading');
    form.addEventListener('submit',e=>{
      saveTarget('support-feedback-operations-heading');
      if(!confirm('Delete this message permanently?')) e.preventDefault();
    });
  });
  document.querySelectorAll('.support-modal form').forEach(form=>{
    if(!form.hasAttribute('data-scroll-target')) form.setAttribute('data-scroll-target','support-feedback-operations-heading');
    form.addEventListener('submit',()=>saveTarget('support-feedback-operations-heading'));
  });
  document.querySelectorAll('.support-copy-email').forEach(btn=>btn.addEventListener('click',async()=>{
    const email=btn.getAttribute('data-copy-email') || '';
    if(!email) return;
    try{
      await navigator.clipboard.writeText(email);
      btn.classList.add('copied');
      btn.setAttribute('title','Copied');
      setTimeout(()=>{btn.classList.remove('copied');btn.setAttribute('title','Copy email');},1200);
    }catch(err){
      const input=document.createElement('textarea');
      input.value=email; input.style.position='fixed'; input.style.opacity='0';
      document.body.appendChild(input); input.select();
      try{document.execCommand('copy'); btn.classList.add('copied'); btn.setAttribute('title','Copied'); setTimeout(()=>{btn.classList.remove('copied');btn.setAttribute('title','Copy email');},1200);}catch(e){}
      input.remove();
    }
  }));
  document.querySelectorAll('[data-support-open]').forEach(btn=>btn.addEventListener('click',()=>{
    const modal=document.getElementById(btn.getAttribute('data-support-open'));
    if(modal){modal.hidden=false;document.body.classList.add('service-modal-open');}
  }));
  document.querySelectorAll('[data-support-close]').forEach(btn=>btn.addEventListener('click',()=>{
    const modal=btn.closest('.support-modal');
    if(modal){modal.hidden=true;document.body.classList.remove('service-modal-open');}
  }));
  supportUpdateSelection();
})();
</script>
</body>
</html>
