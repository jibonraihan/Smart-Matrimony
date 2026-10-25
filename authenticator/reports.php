<?php
// Authenticator's reports are restricted to the currently signed-in active authenticator.
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_AUTH_SESSION');
    session_start();
}
require_once '../config/db.php';

$authenticator_id = (int)($_SESSION['authenticator_user_id'] ?? 0);
if ($authenticator_id < 1) { header('Location: login.php'); exit; }

$authStmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, role, account_status FROM users WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($authStmt, 'i', $authenticator_id);
mysqli_stmt_execute($authStmt);
$auth = mysqli_fetch_assoc(mysqli_stmt_get_result($authStmt));
mysqli_stmt_close($authStmt);
if (!$auth || $auth['role'] !== 'Authenticator' || $auth['account_status'] !== 'Active') {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php?reason=access');
    exit;
}

function arpt_h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function arpt_date(string $value): string {
    if ($value === '') return '';
    $dt = DateTime::createFromFormat('!Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value ? $value : '';
}
function arpt_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    if ($types !== '') {
        $bind = [$stmt, $types];
        foreach ($params as &$param) $bind[] = &$param;
        call_user_func_array('mysqli_stmt_bind_param', $bind);
        unset($param);
    }
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return []; }
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result) { while ($row = mysqli_fetch_assoc($result)) $rows[] = $row; mysqli_free_result($result); }
    mysqli_stmt_close($stmt);
    return $rows;
}
function arpt_count(mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $rows = arpt_rows($conn, $sql, $types, $params);
    return (int)($rows[0]['total'] ?? 0);
}

$from = arpt_date(trim((string)($_GET['from'] ?? '')));
$to = arpt_date(trim((string)($_GET['to'] ?? '')));
if ($from !== '' && $to !== '' && $from > $to) { [$from, $to] = [$to, $from]; }
$action = (string)($_GET['action'] ?? 'All');
if (!in_array($action, ['All','Verified','Rejected'], true)) $action = 'All';
$gender = (string)($_GET['gender'] ?? 'All');
if (!in_array($gender, ['All','Male','Female','Other'], true)) $gender = 'All';
$search = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
$limit = (int)($_GET['limit'] ?? 50);
if (!in_array($limit, [25,50,100,200], true)) $limit = 50;

// Section selection follows the same server-rendered pattern as Admin Reports:
// unchecked sections are not rendered on the page or included in the PDF.
$validSections = ['overview', 'daily', 'activity'];
$sections = isset($_GET['sections']) && is_array($_GET['sections'])
    ? array_values(array_intersect($validSections, array_map('strval', $_GET['sections'])))
    : $validSections;
if (!$sections) { $sections = ['overview']; }
$pdfOverview = in_array('overview', $sections, true);
$pdfDaily = in_array('daily', $sections, true);
$pdfActivity = in_array('activity', $sections, true);

$where = ['vl.authenticator_id=?'];
$types = 'i';
$params = [$authenticator_id];
if ($from !== '') { $where[] = 'vl.action_at >= ?'; $types .= 's'; $params[] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = 'vl.action_at <= ?'; $types .= 's'; $params[] = $to . ' 23:59:59'; }
if ($action !== 'All') { $where[] = 'vl.action=?'; $types .= 's'; $params[] = $action; }
if ($gender !== 'All') { $where[] = 'up.gender=?'; $types .= 's'; $params[] = $gender; }
if ($search !== '') {
    $where[] = "(CAST(vl.profile_id AS CHAR) LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ? OR CONCAT(COALESCE(up.first_name,''),' ',COALESCE(up.last_name,'')) LIKE ? OR vl.remarks LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);
$logRows = arpt_rows($conn,
    "SELECT vl.log_id, vl.action_at, vl.profile_id, vl.action, vl.remarks,
            up.first_name AS profile_first_name, up.last_name AS profile_last_name,
            up.verification_status, up.gender, u.user_id,
            vl.authenticator_id, au.first_name AS authenticator_first_name,
            au.last_name AS authenticator_last_name
     FROM verification_logs vl
     LEFT JOIN user_profiles up ON up.profile_id=vl.profile_id
     LEFT JOIN users u ON u.user_id=up.user_id
     LEFT JOIN users au ON au.user_id=vl.authenticator_id
     WHERE $whereSql
     ORDER BY vl.action_at DESC, vl.log_id DESC
     LIMIT $limit",
    $types, $params
);

$rangeSql = '';
$rangeTypes = 'i';
$rangeParams = [$authenticator_id];
if ($from !== '') { $rangeSql .= ' AND action_at >= ?'; $rangeTypes .= 's'; $rangeParams[] = $from . ' 00:00:00'; }
if ($to !== '') { $rangeSql .= ' AND action_at <= ?'; $rangeTypes .= 's'; $rangeParams[] = $to . ' 23:59:59'; }
$statsRows = arpt_rows($conn,
    "SELECT COUNT(*) AS total,
       SUM(action='Verified') AS verified,
       SUM(action='Rejected') AS rejected
     FROM verification_logs WHERE authenticator_id=? $rangeSql",
    $rangeTypes, $rangeParams
);
$stats = $statsRows[0] ?? ['total'=>0,'verified'=>0,'rejected'=>0];
$periodTotal = (int)($stats['total'] ?? 0);
$periodVerified = (int)($stats['verified'] ?? 0);
$periodRejected = (int)($stats['rejected'] ?? 0);
$verifiedPct = $periodTotal > 0 ? (int)round(($periodVerified / $periodTotal) * 100) : 0;
$rejectedPct = $periodTotal > 0 ? (int)round(($periodRejected / $periodTotal) * 100) : 0;
$pendingCount = arpt_count($conn, "SELECT COUNT(*) AS total FROM user_profiles WHERE verification_status='Pending'");
// Daily breakdown follows the same selected filters as the activity report.
$dailyRows = arpt_rows($conn,
    "SELECT DATE(vl.action_at) AS activity_date, COUNT(*) AS total,
            SUM(vl.action='Verified') AS verified,
            SUM(vl.action='Rejected') AS rejected
     FROM verification_logs vl
     LEFT JOIN user_profiles up ON up.profile_id=vl.profile_id
     WHERE $whereSql
     GROUP BY DATE(vl.action_at)
     ORDER BY activity_date DESC",
    $types, $params
);
$dailyMax = 1;
foreach ($dailyRows as $dailyRow) { $dailyMax = max($dailyMax, (int)$dailyRow['total']); }
$rangeLabel = $from !== '' && $to !== '' ? $from . ' to ' . $to : ($from !== '' ? 'From ' . $from : ($to !== '' ? 'Through ' . $to : 'All dates'));

if (($_GET['export'] ?? '') === 'csv') {
    $filename = 'authenticator_verification_' . $authenticator_id . '_' . ($from ?: 'all') . '_to_' . ($to ?: 'all') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['No.','Log ID','Action time','Authenticator','Profile ID','Profile name','Gender','Current verification status','Action','Remarks']);
    foreach ($logRows as $i => $r) {
        fputcsv($out, [$i+1, $r['log_id'], $r['action_at'],
            (trim(($r['authenticator_first_name'] ?? '') . ' ' . ($r['authenticator_last_name'] ?? '')) ?: 'Authenticator unavailable') . ' (ID: ' . ($r['authenticator_id'] ?? 'N/A') . ')',
            $r['profile_id'],
            trim(($r['profile_first_name'] ?? '') . ' ' . ($r['profile_last_name'] ?? '')) ?: 'Profile unavailable',
            $r['gender'] ?? '', $r['verification_status'] ?? '', $r['action'], $r['remarks'] ?? '']);
    }
    fclose($out); exit;
}
$exportQuery = $_GET; $exportQuery['export'] = 'csv';
$csvUrl = 'reports.php?' . http_build_query($exportQuery);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verification Reports | Smart Matrimony</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--ink:#153936;--muted:#667e7a;--line:#dce9e6;--teal:#0f8b84;--deep:#075e5b;--bg:#f2f8f7;--paper:#fff;--soft:#e9f7f3;--gold:#9a6413}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top left,#e2f5ef 0,transparent 35%),var(--bg);color:var(--ink);font:14px/1.55 Poppins,system-ui,sans-serif}.wrap{max-width:1400px;margin:24px auto;padding:0 18px 42px}.hero{background:linear-gradient(125deg,#064e4a,#0f8b84 70%,#36a79b);color:#fff;border-radius:22px;padding:25px 28px;margin-bottom:18px;box-shadow:0 14px 38px #164d4315}.hero-top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.eyebrow{font-size:11px;font-weight:800;letter-spacing:2px;color:#c9f5eb}.hero h1{font-size:clamp(24px,3vw,34px);line-height:1.2;margin:6px 0}.hero p{margin:4px 0;color:#e2f8f3}.actions{display:flex;gap:8px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #cfe3de;border-radius:10px;padding:10px 14px;background:#fff;color:#125d56;font:inherit;font-weight:800;text-decoration:none;cursor:pointer}.btn.primary{background:#e2f8f1;border-color:#e2f8f1}.btn.csv{background:linear-gradient(135deg,#087f5b,#12a878);border-color:#087f5b;color:#fff;box-shadow:0 5px 14px #087f5b26}.btn.csv:hover{background:linear-gradient(135deg,#066649,#0b9367);box-shadow:0 7px 18px #087f5b38;transform:translateY(-1px)}.btn:focus-visible{outline:3px solid #75d7b5;outline-offset:2px}.btn{transition:background .18s ease,box-shadow .18s ease,transform .18s ease}.btn.dark{background:#ffffff16;color:#fff;border-color:#ffffff66}.panel{background:var(--paper);border:1px solid var(--line);border-radius:17px;padding:20px;margin-bottom:17px;box-shadow:0 10px 30px #123d3808;min-width:0}.heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}.heading h2{font-size:18px;margin:0}.sub{font-size:12px;color:var(--muted);margin:5px 0 0}.filters{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:11px}.field{min-width:0}.field label{display:block;font-size:11px;font-weight:800;color:var(--muted);margin-bottom:5px}.field input,.field select{width:100%;min-width:0;min-height:42px;border:1px solid #cbded9;border-radius:9px;padding:9px 10px;font:inherit;background:#fff;color:var(--ink)}.field.search{grid-column:span 2}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.stat{border:1px solid var(--line);border-radius:14px;padding:16px;background:linear-gradient(145deg,#fff,#f3fbf8);min-width:0}.stat span{font-size:11px;color:var(--muted);font-weight:800}.stat strong{display:block;font-size:clamp(24px,3vw,32px);line-height:1.25;margin-top:7px;color:var(--deep)}.table-wrap{width:100%;overflow:auto;border:1px solid var(--line);border-radius:12px;-webkit-overflow-scrolling:touch}table{width:100%;min-width:880px;border-collapse:separate;border-spacing:0;font-size:12px}th,td{text-align:left;vertical-align:top;padding:10px;border-bottom:1px solid #e7efed;overflow-wrap:anywhere;max-width:340px}th{background:#edf8f4;color:#496963;font-size:10px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}tbody tr:nth-child(even){background:#fbfdfc}tbody tr:hover{background:#effaf6}.num{text-align:center;width:48px;min-width:48px;font-variant-numeric:tabular-nums;color:#56736c}.pill{display:inline-block;border-radius:99px;padding:3px 8px;font-size:10px;font-weight:800;background:#e2f5ec;color:#176b50;white-space:nowrap}.pill.bad{background:#ffeded;color:#a33d3d}.pill.pending{background:#fff2d9;color:#8b5b0a}.empty{padding:25px;text-align:center;color:var(--muted);border:1px dashed var(--line);border-radius:12px}.foot{font-size:11px;color:var(--muted);margin:12px 2px}
@media(max-width:950px){.filters{grid-template-columns:repeat(3,minmax(0,1fr))}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.daily-table{min-width:520px}.daily-table th:first-child,.daily-table td:first-child{width:104px;min-width:104px;white-space:nowrap}.wrap{padding:0 10px 25px;margin:10px auto}.hero{padding:20px 16px;border-radius:15px}.panel{padding:13px;border-radius:13px}.filters{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.field.search{grid-column:span 2}.actions{width:100%}.actions .btn{flex:1}.stats{gap:8px}.stat{padding:12px}.stat strong{font-size:25px}}
@media(max-width:370px){.filters{grid-template-columns:1fr}.field.search{grid-column:span 1}.stats{grid-template-columns:1fr 1fr}}
.daily-table{min-width:520px}.daily-table th,.daily-table td{padding:8px 10px}.daily-table th:first-child,.daily-table td:first-child{width:100px;min-width:100px;white-space:nowrap;overflow-wrap:normal}.daily-table th:nth-child(2),.daily-table td:nth-child(2){min-width:105px}.daily-bar{height:8px;min-width:3px;background:linear-gradient(90deg,#0f8b84,#55c5a8);border-radius:99px;display:block}.daily-total{font-weight:800;color:var(--deep);font-variant-numeric:tabular-nums}.print-meta{display:none}.section-kicker{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--teal);font-weight:800;margin-bottom:4px}.decision-chart{display:grid;gap:13px;margin-top:17px;padding:16px;border:1px solid var(--line);border-radius:13px;background:linear-gradient(135deg,#fbfefd,#f1faf7)}.chart-head{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:12px;font-weight:800}.chart-track{height:12px;display:flex;overflow:hidden;border-radius:99px;background:#e7efed;margin-top:7px}.chart-verified{height:100%;background:linear-gradient(90deg,#087f5b,#2ebd8d)}.chart-rejected{height:100%;background:linear-gradient(90deg,#d95757,#f08a8a)}.chart-legend{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;font-size:11px;color:var(--muted)}.legend-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px}.legend-dot.good{background:#12a878}.legend-dot.bad{background:#e46b6b}.stat-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.stat-mark{width:32px;height:32px;display:grid;place-items:center;border-radius:10px;background:#e1f6ef;color:#087f5b;font-weight:900}.stat.reject .stat-mark{background:#ffeded;color:#b34444}.stat.pending-card .stat-mark{background:#fff2d9;color:#8b5b0a}.stat.total-card .stat-mark{background:#e5f0ff;color:#245c9b}.stat .mini-track{height:5px;background:#e8f0ee;border-radius:99px;overflow:hidden;margin-top:12px}.stat .mini-track span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#0f8b84,#55c5a8)}
@media print{@page{size:A4 landscape;margin:12mm 10mm 14mm;@bottom-right{content:"Page " counter(page) " of " counter(pages);font:8pt Poppins,sans-serif;color:#55716b}}html,body{background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}.wrap{max-width:none;width:100%;padding:0;margin:0}.hero{background:#fff!important;color:var(--ink);border-radius:0;box-shadow:none;padding:0 0 10px;margin-bottom:12px;border-bottom:2px solid var(--teal)}.hero p,.eyebrow{color:#536d68}.no-print{display:none!important}.panel{box-shadow:none;border:0;border-radius:0;padding:0;margin-bottom:14px}.stats{grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}.stat{padding:9px;border-radius:6px;break-inside:avoid}.stat strong{font-size:17pt}.table-wrap{overflow:visible;border-radius:0}table{min-width:0;width:100%;font-size:7.5pt}th,td{padding:5px;max-width:none}thead{display:table-header-group}tr{break-inside:avoid;page-break-inside:avoid}.actions{display:none}.print-meta{display:block;font-size:8pt;color:#526b66;margin:0 0 8mm}.section-kicker{color:#356d65}.panel{break-inside:avoid-page}.daily-table{font-size:8pt}.daily-table th,.daily-table td{padding:4px 6px}.daily-bar{background:#0f8b84!important}.foot{font-size:8pt;border-top:1px solid #dce9e6;padding-top:6px}.decision-chart{break-inside:avoid;background:#fff!important;padding:10px}.chart-track{border:1px solid #dce9e6}.stat-mark{print-color-adjust:exact;-webkit-print-color-adjust:exact}.stat .mini-track{margin-top:7px}a{color:inherit;text-decoration:none}}
.filter-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.filter-actions strong{margin-right:4px}.pdf-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}.pdf-checks label{display:flex;align-items:center;gap:9px;padding:12px;border:1px solid var(--line);border-radius:11px;background:#f8fcfb;font-size:12px;font-weight:600;cursor:pointer}.pdf-checks input{width:17px;height:17px;accent-color:#0f8b84;flex:0 0 auto}@media(max-width:600px){.pdf-checks{grid-template-columns:1fr}}@media print{.pdf-section.pdf-hidden{display:none!important}}
</style>
</head>
<body>
<main class="wrap">
<header class="hero">
  <div class="hero-top">
    <div><div class="eyebrow">SMART MATRIMONY · AUTHENTICATOR WORKSPACE</div><h1>Verification Reports</h1>
    <p>Personal verification activity, decisions and audit history.</p>
    <p style="font-size:11px">Authenticator: <?= arpt_h(trim($auth['first_name'].' '.$auth['last_name'])) ?> · Report period: <?= arpt_h($rangeLabel) ?> · Generated <?= date('d M Y, h:i A') ?></p></div>
    <div class="actions no-print"><button class="btn primary" type="button" onclick="window.print()">Print / Save PDF</button><a class="btn dark" href="dashboard.php">← Dashboard</a></div>
  </div>
</header>
<div class="print-meta">Prepared for <?= arpt_h(trim($auth['first_name'].' '.$auth['last_name'])) ?> · Authenticator ID: <?= (int)$authenticator_id ?> · Period: <?= arpt_h($rangeLabel) ?> · Generated <?= date('d M Y, h:i A') ?></div>
<section class="panel no-print">
  <div class="heading"><div><h2>Report filters</h2></div><div class="actions"><button class="btn primary" type="submit" form="filters">Apply filters</button><a class="btn" href="reports.php">Reset</a></div></div>
  <form id="filters" method="get"><div class="filters">
    <div class="field"><label for="from">From date</label><input type="date" id="from" name="from" value="<?= arpt_h($from) ?>"></div>
    <div class="field"><label for="to">To date</label><input type="date" id="to" name="to" value="<?= arpt_h($to) ?>"></div>
    <div class="field"><label for="action">Decision</label><select id="action" name="action"><?php foreach (['All','Verified','Rejected'] as $v): ?><option value="<?= arpt_h($v) ?>" <?= $action===$v?'selected':'' ?>><?= arpt_h($v) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="limit">Rows per report</label><select id="limit" name="limit"><?php foreach ([25,50,100,200] as $v): ?><option value="<?= $v ?>" <?= $limit===$v?'selected':'' ?>><?= $v ?> rows</option><?php endforeach; ?></select></div>
    <div class="field"><label for="gender">Gender</label><select id="gender" name="gender"><?php foreach (['All','Male','Female','Other'] as $v): ?><option value="<?= arpt_h($v) ?>" <?= $gender===$v?'selected':'' ?>><?= arpt_h($v) ?></option><?php endforeach; ?></select></div>
    <div class="field search"><label for="q">Search profile name, profile ID or remarks</label><input id="q" name="q" value="<?= arpt_h($search) ?>" placeholder="Enter name or ID"></div>
  </div>
  <div class="filter-actions" style="margin-top:14px;margin-bottom:8px"><strong style="font-size:12px">PDF sections</strong><button class="btn" type="button" id="selectAllSections">Select all</button><button class="btn" type="button" id="selectNoSections">Select none</button></div>
  <div class="pdf-checks filter-pdf-checks"><label><input type="checkbox" name="sections[]" value="overview" <?= $pdfOverview?'checked':'' ?>> Verification overview &amp; chart</label><label><input type="checkbox" name="sections[]" value="daily" <?= $pdfDaily?'checked':'' ?>> Date-wise summary</label><label><input type="checkbox" name="sections[]" value="activity" <?= $pdfActivity?'checked':'' ?>> My verification activity</label></div>
  </form>
</section>
<?php if ($pdfOverview): ?><section class="panel pdf-section" data-pdf-section="overview">
  <div class="heading"><div><h2>Verification overview</h2><p class="sub">Summary for <?= arpt_h($rangeLabel) ?>.</p></div></div>
  <div class="stats">
    <article class="stat pending-card"><div class="stat-top"><span>Pending profiles · all workspace</span><span class="stat-mark" aria-hidden="true">…</span></div><strong><?= number_format($pendingCount) ?></strong><div class="sub">Current workspace queue</div></article>
    <article class="stat total-card"><div class="stat-top"><span>Your decisions in period</span><span class="stat-mark" aria-hidden="true">Σ</span></div><strong><?= number_format($periodTotal) ?></strong><div class="sub">All logged decisions in date range</div><div class="mini-track"><span style="width:100%"></span></div></article>
    <article class="stat"><div class="stat-top"><span>Profiles verified by you</span><span class="stat-mark" aria-hidden="true">✓</span></div><strong><?= number_format($periodVerified) ?></strong><div class="sub"><?= $verifiedPct ?>% of decisions in period</div><div class="mini-track"><span style="width:<?= $verifiedPct ?>%"></span></div></article>
    <article class="stat reject"><div class="stat-top"><span>Profiles rejected by you</span><span class="stat-mark" aria-hidden="true">×</span></div><strong><?= number_format($periodRejected) ?></strong><div class="sub"><?= $rejectedPct ?>% of decisions in period</div><div class="mini-track"><span style="width:<?= $rejectedPct ?>%;background:linear-gradient(90deg,#d95757,#f08a8a)"></span></div></article>
  </div>
  <div class="decision-chart" aria-label="Verified and rejected decision comparison">
    <div class="chart-head"><span>Decision breakdown</span><span><?= number_format($periodTotal) ?> total</span></div>
    <div class="chart-track" role="img" aria-label="<?= $verifiedPct ?> percent verified and <?= $rejectedPct ?> percent rejected">
      <span class="chart-verified" style="width:<?= $verifiedPct ?>%"></span><span class="chart-rejected" style="width:<?= $rejectedPct ?>%"></span>
    </div>
    <div class="chart-legend"><span><i class="legend-dot good"></i>Verified: <?= number_format($periodVerified) ?> (<?= $verifiedPct ?>%)</span><span><i class="legend-dot bad"></i>Rejected: <?= number_format($periodRejected) ?> (<?= $rejectedPct ?>%)</span></div>
  </div>
</section><?php endif; ?>
<?php if ($pdfDaily): ?><section class="panel pdf-section" data-pdf-section="daily">
  <div class="heading"><div><div class="section-kicker">Activity trend</div><h2>Date-wise verification summary</h2><p class="sub">Daily decision counts using the selected report filters.</p></div><div class="sub"><?= count($dailyRows) ?> active dates</div></div>
  <?php if ($dailyRows): ?><div class="table-wrap"><table class="daily-table"><thead><tr><th>Date</th><th>Total decisions</th><th>Verified</th><th>Rejected</th><th>Daily volume</th></tr></thead><tbody>
  <?php foreach ($dailyRows as $day): ?><tr><td><?= arpt_h(date('d M Y', strtotime($day['activity_date']))) ?></td><td class="daily-total"><?= number_format((int)$day['total']) ?></td><td><span class="pill"><?= number_format((int)$day['verified']) ?></span></td><td><span class="pill bad"><?= number_format((int)$day['rejected']) ?></span></td><td><span class="daily-bar" style="width:<?= max(1, (int)round(((int)$day['total'] / $dailyMax) * 100)) ?>%"></span></td></tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><div class="empty">No daily activity matches the selected filters.</div><?php endif; ?>
</section><?php endif; ?>
<?php if ($pdfActivity): ?><section class="panel pdf-section" data-pdf-section="activity">
  <div class="heading"><div><h2>My verification activity</h2><p class="sub">Latest matching decisions recorded against your authenticator account.</p></div><div class="actions no-print"><span class="sub"><?= count($logRows) ?> rows shown · max <?= $limit ?></span><a class="btn csv" href="<?= arpt_h($csvUrl) ?>"><span aria-hidden="true" style="margin-right:7px">↓</span>Export CSV</a></div></div>
  <?php if ($logRows): ?><div class="table-wrap"><table><thead><tr><th class="num">No.</th><th>Log ID</th><th>Action time</th><th>Authenticator</th><th>Profile ID</th><th>Profile name</th><th>Gender</th><th>Current status</th><th>Decision</th><th>Remarks</th></tr></thead><tbody>
  <?php foreach ($logRows as $i=>$r): ?><tr><td class="num"><?= $i+1 ?></td><td><?= (int)$r['log_id'] ?></td><td><?= arpt_h($r['action_at']) ?></td><td><?= arpt_h((trim(($r['authenticator_first_name']??'').' '.($r['authenticator_last_name']??'')) ?: 'Authenticator unavailable') . ' (ID: ' . ($r['authenticator_id'] ?? 'N/A') . ')') ?></td><td>SM-<?= str_pad((string)$r['profile_id'],6,'0',STR_PAD_LEFT) ?></td><td><?= arpt_h(trim(($r['profile_first_name']??'').' '.($r['profile_last_name']??'')) ?: 'Profile unavailable') ?></td><td><?= arpt_h($r['gender']??'—') ?></td><td><span class="pill <?= ($r['verification_status']??'')==='Pending'?'pending':(($r['verification_status']??'')==='Rejected'?'bad':'') ?>"><?= arpt_h($r['verification_status']??'Unavailable') ?></span></td><td><span class="pill <?= $r['action']==='Rejected'?'bad':'' ?>"><?= arpt_h($r['action']) ?></span></td><td><?= arpt_h($r['remarks']??'') ?: '—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><div class="empty">No verification activity matches the selected filters.</div><?php endif; ?>
  
</section><?php endif; ?>
</main>
<script>
(function(){
 const form=document.getElementById('filters');
 const boxes=()=>Array.from(form?.querySelectorAll('input[name="sections[]"]')||[]);
 document.getElementById('selectAllSections')?.addEventListener('click',()=>boxes().forEach(box=>box.checked=true));
 document.getElementById('selectNoSections')?.addEventListener('click',()=>boxes().forEach(box=>box.checked=false));
 form?.addEventListener('submit',()=>{if(!boxes().some(box=>box.checked)){const overview=boxes().find(box=>box.value==='overview');if(overview)overview.checked=true;}});
})();
</script>
</body>
</html>
