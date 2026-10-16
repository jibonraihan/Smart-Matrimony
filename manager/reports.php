<?php
/* Manager-only booking, confirmation and service reports. Read-only; no schema changes. */
/* Use the same isolated Manager session as manager/dashboard.php. */
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_MANAGER_SESSION');
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.use_trans_sid', '0');

    $manager_sid = $_GET['manager_sid'] ?? $_POST['manager_sid'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $manager_sid)) {
        header('Location: login.php');
        exit;
    }
    session_id($manager_sid);
    session_start();
}
require_once __DIR__ . '/../config/db.php';

$managerId = (int)($_SESSION['user_id'] ?? 0);
if ($managerId < 1) {
    header('Location: login.php');
    exit;
}

/* Revalidate the Manager account, matching the dashboard's access checks. */
$managerAuthStmt = mysqli_prepare($conn, 'SELECT role, account_status FROM users WHERE user_id = ? LIMIT 1');
if (!$managerAuthStmt) {
    header('Location: login.php');
    exit;
}
mysqli_stmt_bind_param($managerAuthStmt, 'i', $managerId);
mysqli_stmt_execute($managerAuthStmt);
$managerAuth = mysqli_fetch_assoc(mysqli_stmt_get_result($managerAuthStmt));
mysqli_stmt_close($managerAuthStmt);
if (!$managerAuth || $managerAuth['role'] !== 'Manager' || $managerAuth['account_status'] !== 'Active') {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

function mr_escape($value): string {
    return htmlspecialchars((string)($value ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mr_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $key => &$value) $bind[] = &$value;
        call_user_func_array([$stmt, 'bind_param'], $bind);
        unset($value);
    }
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return []; }
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
    }
    mysqli_stmt_close($stmt);
    return $rows;
}
function mr_scalar(mysqli $conn, string $sql, string $types, array $params): float {
    $rows = mr_rows($conn, $sql, $types, $params);
    return (float)($rows[0]['total'] ?? 0);
}

/* Filter controls: only this Manager's bookings and related customers are selectable. */
$manager_sid = session_id();
$fromDate = trim((string)($_GET['from'] ?? ''));
$toDate = trim((string)($_GET['to'] ?? ''));
$customerId = max(0, (int)($_GET['customer_id'] ?? 0));
$customerSearch = trim((string)($_GET['customer_search'] ?? ''));
$bookingStatus = trim((string)($_GET['booking_status'] ?? 'All'));
$packageStatus = trim((string)($_GET['package_status'] ?? 'All'));
$allowedBookingStatuses = ['All','Pending','Confirmed','Completed','Cancelled'];
$allowedPackageStatuses = ['All','Active','Inactive'];
if (!in_array($bookingStatus, $allowedBookingStatuses, true)) $bookingStatus = 'All';
if (!in_array($packageStatus, $allowedPackageStatuses, true)) $packageStatus = 'All';
$include = $_GET['include'] ?? ['summary','bookings','packages','earnings','activity'];
if (!is_array($include)) $include = [];
$allowedSections = ['summary','bookings','packages','earnings','activity'];
$include = array_values(array_intersect($allowedSections, array_map('strval', $include)));
if (!$include) $include = ['summary'];
if ($fromDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) $fromDate = '';
if ($toDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) $toDate = '';
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) { $swap=$fromDate; $fromDate=$toDate; $toDate=$swap; }

$scope = "(b.manager_id = ? OR EXISTS (SELECT 1 FROM booking_details bd_scope WHERE bd_scope.booking_id=b.booking_id AND bd_scope.manager_id=?))";
$customerOptions = [];
/* Searchable customer lookup: only fetch a small matching set instead of rendering every customer. */
$customerSearchLike = '%' . $customerSearch . '%';
$customerSearchSql = "SELECT DISTINCT u.user_id, CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) customer_name, u.email
    FROM bookings b INNER JOIN users u ON u.user_id=b.user_id
    WHERE b.admin_removed=0 AND $scope";
$customerSearchTypes = 'ii';
$customerSearchParams = [$managerId, $managerId];
if ($customerSearch !== '') {
    $customerSearchSql .= " AND (CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) LIKE ? OR u.email LIKE ? OR CAST(u.user_id AS CHAR) = ?)";
    $customerSearchTypes .= 'sss';
    $customerSearchParams[] = $customerSearchLike;
    $customerSearchParams[] = $customerSearchLike;
    $customerSearchParams[] = $customerSearch;
}
$customerSearchSql .= ' ORDER BY customer_name, u.user_id LIMIT 100';
$customerOptions = mr_rows($conn, $customerSearchSql, $customerSearchTypes, $customerSearchParams);
/* Validate selected customer independently of the limited search results. */
if ($customerId > 0) {
    $validSelected = mr_rows($conn, "SELECT DISTINCT u.user_id, CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) customer_name, u.email
        FROM bookings b INNER JOIN users u ON u.user_id=b.user_id
        WHERE b.admin_removed=0 AND $scope AND u.user_id=? LIMIT 1", 'iii', [$managerId,$managerId,$customerId]);
    if (!$validSelected) $customerId = 0;
    elseif (!array_filter($customerOptions, static fn($r)=>(int)$r['user_id']===$customerId)) $customerOptions[] = $validSelected[0];
}

$where = "b.admin_removed=0 AND $scope";
$whereTypes = 'ii';
$whereParams = [$managerId, $managerId];
if ($fromDate !== '') { $where .= ' AND DATE(b.booking_date) >= ?'; $whereTypes .= 's'; $whereParams[] = $fromDate; }
if ($toDate !== '') { $where .= ' AND DATE(b.booking_date) <= ?'; $whereTypes .= 's'; $whereParams[] = $toDate; }
if ($customerId > 0) { $where .= ' AND b.user_id = ?'; $whereTypes .= 'i'; $whereParams[] = $customerId; }
if ($bookingStatus !== 'All') { $where .= ' AND b.booking_status = ?'; $whereTypes .= 's'; $whereParams[] = $bookingStatus; }
$base = "FROM bookings b WHERE $where";
$metrics = [];
$bookings = [];
$packages = [];
$globalEarnings = [];
$globalEarningsTotal = 0.0;
$activity = [];
if (in_array('summary',$include,true)) {
    $metrics = [
        'Bookings handled' => (int)mr_scalar($conn, "SELECT COUNT(*) total $base", $whereTypes, $whereParams),
        'Pending' => (int)mr_scalar($conn, "SELECT COUNT(*) total $base AND b.booking_status='Pending'", $whereTypes, $whereParams),
        'Confirmed' => (int)mr_scalar($conn, "SELECT COUNT(*) total $base AND b.booking_status='Confirmed'", $whereTypes, $whereParams),
        'Completed' => (int)mr_scalar($conn, "SELECT COUNT(*) total $base AND b.booking_status='Completed'", $whereTypes, $whereParams),
        'Cancelled' => (int)mr_scalar($conn, "SELECT COUNT(*) total $base AND b.booking_status='Cancelled'", $whereTypes, $whereParams),
        'Booking value (BDT)' => mr_scalar($conn, "SELECT COALESCE(SUM(b.total_price),0) total $base", $whereTypes, $whereParams),
        'My service packages' => (int)mr_scalar($conn, "SELECT COUNT(*) total FROM service_providers WHERE manager_id=?", 'i', [$managerId]),
    ];
}
if (in_array('bookings',$include,true)) {
    $bookings = mr_rows($conn, "SELECT b.booking_id, b.booking_status, b.total_price, b.booking_date, b.confirmed_at, b.completed_at,
        CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS customer_name, u.email AS customer_email, u.user_id,
        GROUP_CONCAT(DISTINCT CONCAT(COALESCE(sp.package_name,'Service'), ' (', bd.quantity, ')') ORDER BY sp.package_name SEPARATOR ', ') AS services,
        MIN(bd.event_date) AS event_date
        FROM bookings b
        LEFT JOIN users u ON u.user_id=b.user_id
        LEFT JOIN booking_details bd ON bd.booking_id=b.booking_id
        LEFT JOIN service_providers sp ON sp.provider_id=bd.provider_id
        WHERE $where
        GROUP BY b.booking_id, b.booking_status, b.total_price, b.booking_date, b.confirmed_at, b.completed_at, u.first_name, u.last_name, u.email, u.user_id
        ORDER BY b.booking_date DESC, b.booking_id DESC LIMIT 500", $whereTypes, $whereParams);
}
if (in_array('packages',$include,true) || in_array('earnings',$include,true)) {
    $packageWhere = 'sp.manager_id=?'; $packageTypes = 'i'; $packageParams = [$managerId];
    if ($packageStatus !== 'All') { $packageWhere .= ' AND sp.status=?'; $packageTypes .= 's'; $packageParams[] = $packageStatus; }
    $packages = mr_rows($conn, "SELECT sp.provider_id, sp.provider_name, sp.package_name, sp.price, sp.status, s.service_name,
        COALESCE(SUM(CASE WHEN b.booking_status IN ('Confirmed','Completed') THEN bd.quantity * COALESCE(NULLIF(bd.unit_price,0),sp.price) ELSE 0 END),0) AS earnings,
        COALESCE(SUM(CASE WHEN b.booking_status IN ('Pending','Confirmed','Completed','Cancelled') THEN bd.quantity ELSE 0 END),0) AS units_booked,
        COUNT(DISTINCT CASE WHEN b.booking_status IN ('Pending','Confirmed','Completed','Cancelled') THEN b.booking_id END) AS booking_count,
        COALESCE(SUM(CASE WHEN b.booking_status='Pending' THEN 1 ELSE 0 END),0) AS pending_count,
        COALESCE(SUM(CASE WHEN b.booking_status='Confirmed' THEN 1 ELSE 0 END),0) AS confirmed_count,
        COALESCE(SUM(CASE WHEN b.booking_status='Completed' THEN 1 ELSE 0 END),0) AS completed_count,
        COALESCE(SUM(CASE WHEN b.booking_status='Cancelled' THEN 1 ELSE 0 END),0) AS cancelled_count
        FROM service_providers sp LEFT JOIN services s ON s.service_id=sp.service_id
        LEFT JOIN booking_details bd ON bd.provider_id=sp.provider_id AND bd.manager_id=sp.manager_id
        LEFT JOIN bookings b ON b.booking_id=bd.booking_id AND b.admin_removed=0
        WHERE $packageWhere GROUP BY sp.provider_id, sp.provider_name, sp.package_name, sp.price, sp.status, s.service_name
        ORDER BY sp.status, sp.package_name", $packageTypes, $packageParams);
}
/* Global earnings ledger: every Manager and package, filtered ONLY by booking date range. */
if (in_array('earnings',$include,true)) {
    $earningsWhere = "b.admin_removed=0 AND b.booking_status IN ('Confirmed','Completed')";
    $earningsTypes = '';
    $earningsParams = [];
    if ($fromDate !== '') { $earningsWhere .= ' AND DATE(b.booking_date) >= ?'; $earningsTypes .= 's'; $earningsParams[] = $fromDate; }
    if ($toDate !== '') { $earningsWhere .= ' AND DATE(b.booking_date) <= ?'; $earningsTypes .= 's'; $earningsParams[] = $toDate; }
    $globalEarnings = mr_rows($conn, "SELECT b.booking_id, b.booking_status, b.booking_date, bd.event_date,
        bd.quantity, COALESCE(NULLIF(bd.unit_price,0), ROUND(sp.price * (1 - (LEAST(100,GREATEST(0,COALESCE(sp.discount_percent,0))) / 100)), 2)) AS unit_earning,
        (bd.quantity * COALESCE(NULLIF(bd.unit_price,0), ROUND(sp.price * (1 - (LEAST(100,GREATEST(0,COALESCE(sp.discount_percent,0))) / 100)), 2))) AS line_earning,
        s.service_name, sp.provider_id, sp.provider_name, COALESCE(sp.package_name, sp.provider_name) AS package_name,
        sp.status AS package_status, owner.user_id AS owner_user_id,
        TRIM(CONCAT(COALESCE(owner.first_name,''),' ',COALESCE(owner.last_name,''))) AS owner_name,
        customer.user_id AS customer_id,
        TRIM(CONCAT(COALESCE(customer.first_name,''),' ',COALESCE(customer.last_name,''))) AS customer_name,
        customer.email AS customer_email
        FROM booking_details bd
        INNER JOIN bookings b ON b.booking_id=bd.booking_id
        INNER JOIN service_providers sp ON sp.provider_id=bd.provider_id
        LEFT JOIN services s ON s.service_id=sp.service_id
        LEFT JOIN users owner ON owner.user_id=sp.manager_id
        LEFT JOIN users customer ON customer.user_id=b.user_id
        WHERE $earningsWhere
        ORDER BY b.booking_date DESC, b.booking_id DESC, bd.detail_id ASC LIMIT 5000", $earningsTypes, $earningsParams);
    foreach ($globalEarnings as $earningRow) $globalEarningsTotal += (float)$earningRow['line_earning'];
}

if (in_array('activity',$include,true)) {
    $activityWhere = 'manager_id=?'; $activityTypes='i'; $activityParams=[$managerId];
    if ($fromDate !== '') { $activityWhere .= ' AND DATE(created_at) >= ?'; $activityTypes .= 's'; $activityParams[]=$fromDate; }
    if ($toDate !== '') { $activityWhere .= ' AND DATE(created_at) <= ?'; $activityTypes .= 's'; $activityParams[]=$toDate; }
    if ($customerId > 0) { $activityWhere .= ' AND booking_id IN (SELECT b2.booking_id FROM bookings b2 WHERE b2.user_id=? AND b2.admin_removed=0 AND (b2.manager_id=? OR EXISTS (SELECT 1 FROM booking_details bd2 WHERE bd2.booking_id=b2.booking_id AND bd2.manager_id=?)))'; $activityTypes .= 'iii'; $activityParams[]=$customerId; $activityParams[]=$managerId; $activityParams[]=$managerId; }
    $activity = mr_rows($conn, "SELECT action_type, booking_id, provider_id, amount, details, created_at FROM manager_activity_log WHERE $activityWhere ORDER BY created_at DESC, activity_id DESC LIMIT 500", $activityTypes, $activityParams);
}
$selectedCustomerLabel = 'All customers';
foreach ($customerOptions as $opt) if ((int)$opt['user_id'] === $customerId) $selectedCustomerLabel = trim($opt['customer_name']) . ' · ' . $opt['email'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manager Reports | Smart Matrimony</title>
<style>
:root{--ink:#17312f;--muted:#617774;--line:#dce8e5;--accent:#0f766e;--soft:#eff8f6;--paper:#fff}*{box-sizing:border-box}body{margin:0;background:#f2f6f5;color:var(--ink);font:14px/1.55 system-ui,-apple-system,"Noto Sans Bengali","Nirmala UI",sans-serif}.wrap{max-width:1240px;margin:26px auto;padding:0 18px}.toolbar,.panel,.controls{background:var(--paper);border:1px solid var(--line);border-radius:15px;padding:20px;margin-bottom:18px}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.eyebrow{font-size:11px;letter-spacing:.13em;text-transform:uppercase;color:var(--accent);font-weight:800}.toolbar h1{margin:4px 0;font-size:27px;letter-spacing:-.025em}.toolbar p{margin:0;color:var(--muted)}.actions{display:flex;gap:9px;flex-wrap:wrap}.actions a,.actions button,.control-submit{border:1px solid var(--line);border-radius:9px;padding:10px 14px;background:var(--soft);color:var(--accent);font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.actions .primary,.control-submit{background:var(--accent);border-color:var(--accent);color:#fff}.controls h2,.panel h2{font-size:18px;margin:0 0 14px}.filter-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}.field label{display:block;font-size:12px;font-weight:800;margin-bottom:5px}.field input,.field select{width:100%;min-height:42px;border:1px solid #cddbd7;border-radius:9px;padding:9px 10px;background:#fff;color:var(--ink);font:inherit}.section-options{display:flex;gap:10px 18px;flex-wrap:wrap;margin:15px 0}.section-options label{display:flex;gap:7px;align-items:center;font-weight:650}.filter-actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}.metric{padding:15px;border:1px solid var(--line);border-radius:12px;background:#fbfdfc;break-inside:avoid}.metric span{display:block;color:var(--muted);font-size:12px}.metric strong{display:block;margin-top:5px;font-size:24px;overflow-wrap:anywhere}.table-wrap{width:100%;max-width:100%;overflow:auto;-webkit-overflow-scrolling:touch}table{width:100%;border-collapse:collapse;table-layout:auto;font-size:12px}th,td{text-align:left;vertical-align:top;padding:10px 9px;border-bottom:1px solid #e8efed;overflow-wrap:anywhere;word-break:normal;hyphens:auto}th{color:var(--muted);font-size:11px;background:#f7fbfa;white-space:normal}.earnings-panel tfoot th{font-size:15px;font-weight:800;color:var(--ink);background:#eff8f6;padding-top:14px;padding-bottom:14px;border-top:2px solid #0f766e}.earnings-panel tfoot th:last-child{font-size:18px;font-weight:900;color:#0f766e;white-space:nowrap}td{white-space:pre-wrap}.status{display:inline-block;border-radius:7px;padding:4px 7px;background:var(--soft);color:var(--accent);font-weight:800;font-size:10px;line-height:1.25;white-space:normal;overflow-wrap:normal;word-break:normal;min-width:66px;text-align:center}.muted{color:var(--muted)}.empty{padding:16px;color:var(--muted);background:#fbfdfc;border-radius:10px}.report-meta{font-size:11px;color:var(--muted);margin-top:4px}.no-print{}@media(max-width:850px){.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.toolbar h1{font-size:23px}.filter-grid{grid-template-columns:1fr}}@media(max-width:640px){.wrap{width:100%;max-width:100%;margin:10px auto;padding:0 8px;min-width:0;overflow:visible}.toolbar,.panel,.controls{min-width:0;max-width:100%;width:100%;padding:13px;border-radius:11px}.toolbar{align-items:flex-start}.toolbar h1{font-size:21px;overflow-wrap:anywhere}.panel,.controls{max-height:none;overflow:visible;overscroll-behavior:auto}.table-wrap{display:block;width:100%;max-width:100%;max-height:none;overflow-x:auto;overflow-y:hidden;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch;touch-action:pan-x pan-y;scrollbar-width:thin}.table-wrap table{min-width:760px;width:max-content;max-width:none}.earnings-panel .table-wrap table{min-width:1250px}.metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.metric{min-width:0;padding:10px}.metric strong{font-size:20px}.actions a,.actions button,.control-submit{max-width:100%;white-space:normal}.section-options label{align-items:flex-start}.filter-actions>*{max-width:100%}}@media print{@page{size:A4 landscape;margin:10mm}body{background:#fff;font-size:9pt;color:#182d2b}.wrap{max-width:none;margin:0;padding:0}.toolbar{padding:0 0 10px;border:0;border-radius:0}.toolbar h1{font-size:22pt}.toolbar p{font-size:8pt}.actions,.controls{display:none!important}.panel{border:0;border-radius:0;padding:0;margin:0 0 13px;break-inside:auto}.panel h2{font-size:12pt;margin-bottom:7px}.metrics{grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}.metric{padding:7px;border-radius:5px}.metric strong{font-size:13pt}.metric span{font-size:8pt}.table-wrap{overflow:visible}table{width:100%;table-layout:auto;font-size:7.5pt}th,td{padding:5px 4px;overflow-wrap:break-word;word-break:normal}th{font-size:7pt;white-space:normal}.status{font-size:7pt;padding:3px 4px;min-width:0;white-space:normal;break-inside:avoid}thead{display:table-header-group}tfoot{display:table-row-group}tr{break-inside:avoid;page-break-inside:avoid}.panel .report-meta,.toolbar p{display:none!important}.report-meta{font-size:7.5pt}}
</style>
</head>
<body><main class="wrap">
<section class="toolbar"><div><div class="eyebrow">Smart Matrimony · Manager Workspace</div><h1>Booking & Service Reports</h1><p>Custom report · Generated <?= date('d M Y, H:i'); ?></p><div class="report-meta">Period: <?= mr_escape($fromDate ?: 'Any date'); ?> to <?= mr_escape($toDate ?: 'Any date'); ?> · Customer: <?= mr_escape($selectedCustomerLabel); ?></div></div><div class="actions"><button class="primary" type="button" onclick="window.print()">Print / Save as PDF</button><a href="dashboard.php?manager_sid=<?= htmlspecialchars($manager_sid, ENT_QUOTES, 'UTF-8'); ?>">Back to Manager Dashboard</a></div></section>
<section class="controls no-print"><h2>Customize your PDF report</h2><form method="get" action="reports.php"><input type="hidden" name="manager_sid" value="<?= htmlspecialchars($manager_sid, ENT_QUOTES, 'UTF-8'); ?>"><div class="filter-grid"><div class="field"><label for="customer_search">Find customer (name, email or exact ID)</label><input id="customer_search" type="search" name="customer_search" value="<?= mr_escape($customerSearch); ?>" placeholder="Search name / email / user ID"><small class="report-meta">Shows up to 100 matches; search again to narrow large lists.</small></div><div class="field"><label for="from">From booking/activity date</label><input id="from" type="date" name="from" value="<?= mr_escape($fromDate === '' ? '' : $fromDate); ?>"></div><div class="field"><label for="to">To booking/activity date</label><input id="to" type="date" name="to" value="<?= mr_escape($toDate === '' ? '' : $toDate); ?>"></div><div class="field"><label for="customer_id">Specific customer</label><select id="customer_id" name="customer_id"><option value="0">All customers</option><?php foreach($customerOptions as $opt): ?><option value="<?= (int)$opt['user_id']; ?>" <?= $customerId === (int)$opt['user_id'] ? 'selected' : ''; ?>><?= mr_escape(trim($opt['customer_name']) ?: 'Customer'); ?> · <?= mr_escape($opt['email']); ?> (ID #<?= (int)$opt['user_id']; ?>)</option><?php endforeach; ?></select></div><div class="field"><label for="booking_status">Booking status</label><select id="booking_status" name="booking_status"><?php foreach($allowedBookingStatuses as $statusOption): ?><option value="<?= mr_escape($statusOption); ?>" <?= $bookingStatus === $statusOption ? 'selected' : ''; ?>><?= mr_escape($statusOption); ?></option><?php endforeach; ?></select></div><div class="field"><label for="package_status">Package status</label><select id="package_status" name="package_status"><?php foreach($allowedPackageStatuses as $statusOption): ?><option value="<?= mr_escape($statusOption); ?>" <?= $packageStatus === $statusOption ? 'selected' : ''; ?>><?= mr_escape($statusOption); ?></option><?php endforeach; ?></select></div></div><div class="section-options" aria-label="Choose report sections"><label><input type="checkbox" name="include[]" value="summary" <?= in_array('summary',$include,true)?'checked':''; ?>> Summary & statistics</label><label><input type="checkbox" name="include[]" value="bookings" <?= in_array('bookings',$include,true)?'checked':''; ?>> Booking details</label><label><input type="checkbox" name="include[]" value="packages" <?= in_array('packages',$include,true)?'checked':''; ?>> My service packages</label><label><input type="checkbox" name="include[]" value="earnings" <?= in_array('earnings',$include,true)?'checked':''; ?>> Earnings by service/package</label><label><input type="checkbox" name="include[]" value="activity" <?= in_array('activity',$include,true)?'checked':''; ?>> Manager activity</label></div><div class="filter-actions"><button class="control-submit" type="submit">Apply report settings</button><a class="actions a" href="reports.php?manager_sid=<?= urlencode($manager_sid); ?>">Reset filters</a></div></form></section>
<?php if (in_array('summary',$include,true)): ?><section class="panel"><h2>Report summary</h2><div class="metrics"><?php foreach ($metrics as $label=>$value): ?><article class="metric"><span><?= mr_escape($label); ?></span><strong><?= $label==='Booking value (BDT)' ? '৳'.number_format((float)$value,2) : number_format((int)$value); ?></strong></article><?php endforeach; ?></div></section><?php endif; ?>
<?php if (in_array('bookings',$include,true)): ?><section class="panel"><h2>Booking & confirmation report <span class="muted">(latest 500)</span></h2><?php if ($bookings): ?><div class="table-wrap"><table><thead><tr><th>Booking</th><th>Customer</th><th>Services / packages</th><th>Event date</th><th>Status</th><th>Amount (BDT)</th><th>Booked</th><th>Confirmed</th><th>Completed</th></tr></thead><tbody><?php foreach($bookings as $b): ?><tr><td>#<?= (int)$b['booking_id']; ?></td><td><?= mr_escape(trim((string)$b['customer_name']) ?: 'Member'); ?><br><span class="muted">ID #<?= (int)$b['user_id']; ?></span></td><td><?= mr_escape($b['services'] ?: '—'); ?></td><td><?= mr_escape($b['event_date']); ?></td><td><span class="status"><?= mr_escape($b['booking_status']); ?></span></td><td><?= number_format((float)$b['total_price'],2); ?></td><td><?= mr_escape($b['booking_date']); ?></td><td><?= mr_escape($b['confirmed_at']); ?></td><td><?= mr_escape($b['completed_at']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="empty">No bookings match the selected report filters.</p><?php endif; ?></section><?php endif; ?>
<?php if (in_array('packages',$include,true)): ?><section class="panel"><h2>My service packages</h2><?php if ($packages): ?><div class="table-wrap"><table><thead><tr><th>Package ID</th><th>Service</th><th>Provider</th><th>Package</th><th>Price (BDT)</th><th>Bookings</th><th>Pending</th><th>Confirmed</th><th>Completed</th><th>Cancelled</th><th>Status</th></tr></thead><tbody><?php foreach($packages as $p): ?><tr><td>#<?= (int)$p['provider_id']; ?></td><td><?= mr_escape($p['service_name']); ?></td><td><?= mr_escape($p['provider_name']); ?></td><td><?= mr_escape($p['package_name']); ?></td><td><?= number_format((float)$p['price'],2); ?></td><td><?= (int)$p['booking_count']; ?></td><td><?= (int)$p['pending_count']; ?></td><td><?= (int)$p['confirmed_count']; ?></td><td><?= (int)$p['completed_count']; ?></td><td><?= (int)$p['cancelled_count']; ?></td><td><span class="status"><?= mr_escape($p['status']); ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="empty">No service packages match the selected package status.</p><?php endif; ?></section><?php endif; ?>
<?php if (in_array('earnings',$include,true)): ?><section class="panel earnings-panel"><h2>Global Earnings Ledger · All Managers &amp; Packages</h2><div class="metrics"><article class="metric"><span>Earning line items shown</span><strong><?= number_format(count($globalEarnings)); ?></strong></article><article class="metric"><span>Total earned value in selected period (BDT)</span><strong>৳<?= number_format($globalEarningsTotal,2); ?></strong></article></div><?php if ($globalEarnings): ?><div class="table-wrap"><table><thead><tr><th>No.</th><th>Booking / Status</th><th>Booking date</th><th>Event date</th><th>Service</th><th>Package</th><th>Package owner / Manager</th><th>Booked by customer</th><th>Qty</th><th>Unit value (BDT)</th><th>Line earning (BDT)</th></tr></thead><tbody><?php foreach($globalEarnings as $earningIndex=>$e): ?><tr><td><?= number_format($earningIndex + 1); ?></td><td>#<?= (int)$e['booking_id']; ?><br><span class="status"><?= mr_escape($e['booking_status']); ?></span></td><td><?= mr_escape($e['booking_date']); ?></td><td><?= mr_escape($e['event_date']); ?></td><td><?= mr_escape($e['service_name']); ?></td><td><?= mr_escape($e['package_name']); ?><br><span class="muted">Package #<?= (int)$e['provider_id']; ?> · <?= mr_escape($e['package_status']); ?></span></td><td><?= mr_escape(trim((string)$e['owner_name']) ?: 'Unknown Manager'); ?><br><span class="muted">User ID #<?= (int)$e['owner_user_id']; ?></span><br><?= mr_escape($e['provider_name']); ?></td><td><?= mr_escape(trim((string)$e['customer_name']) ?: 'Member'); ?><br><span class="muted">User ID #<?= (int)$e['customer_id']; ?></span><br><?= mr_escape($e['customer_email']); ?></td><td><?= (int)$e['quantity']; ?></td><td><?= number_format((float)$e['unit_earning'],2); ?></td><td><strong>৳<?= number_format((float)$e['line_earning'],2); ?></strong></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="10" style="text-align:right">Total earned value (shown rows)</th><th>৳<?= number_format($globalEarningsTotal,2); ?></th></tr></tfoot></table></div><?php else: ?><p class="empty">No Confirmed or Completed booking earnings found for the selected booking-date range.</p><?php endif; ?></section><?php endif; ?>
<?php if (in_array('activity',$include,true)): ?><section class="panel"><h2>Manager activity <span class="muted">(latest 500)</span></h2><?php if ($activity): ?><div class="table-wrap"><table><thead><tr><th>Time</th><th>Action</th><th>Booking</th><th>Provider</th><th>Amount (BDT)</th><th>Details</th></tr></thead><tbody><?php foreach($activity as $a): ?><tr><td><?= mr_escape($a['created_at']); ?></td><td><?= mr_escape($a['action_type']); ?></td><td><?= $a['booking_id'] ? '#'.(int)$a['booking_id'] : '—'; ?></td><td><?= $a['provider_id'] ? '#'.(int)$a['provider_id'] : '—'; ?></td><td><?= number_format((float)$a['amount'],2); ?></td><td><?= mr_escape($a['details']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="empty">No activity entries match the selected report filters.</p><?php endif; ?></section><?php endif; ?>
</main></body></html>
