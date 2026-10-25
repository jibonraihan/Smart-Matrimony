<?php
require_once __DIR__ . '/admin_guard.php';

function sm_service_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function sm_service_redirect(string $message, string $type = 'success'): void {
    header('Location: manage_services.php?notice=' . rawurlencode($message) . '&type=' . rawurlencode($type));
    exit;
}
function sm_service_prefix(string $name, mysqli $conn, int $ignoreId = 0): string {
    $words = preg_split('/[^A-Za-z0-9]+/', strtoupper(trim($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $candidate = '';
    if (count($words) > 1) {
        foreach ($words as $word) $candidate .= $word[0];
    } else {
        $candidate = substr($words[0] ?? 'SV', 0, 3);
    }
    $candidate = substr(preg_replace('/[^A-Z0-9]/', '', $candidate) ?: 'SV', 0, 5);
    if (strlen($candidate) < 2) $candidate = str_pad($candidate, 2, 'S');
    $base = $candidate;
    for ($i = 1; $i <= 99; $i++) {
        $test = $i === 1 ? $base : substr($base, 0, 3) . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $sql = 'SELECT service_id FROM services WHERE service_prefix=?' . ($ignoreId > 0 ? ' AND service_id<>?' : '') . ' LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if ($ignoreId > 0) mysqli_stmt_bind_param($stmt, 'si', $test, $ignoreId); else mysqli_stmt_bind_param($stmt, 's', $test);
        mysqli_stmt_execute($stmt);
        $found = mysqli_stmt_get_result($stmt);
        $taken = mysqli_fetch_assoc($found);
        mysqli_stmt_close($stmt);
        if (!$taken) return $test;
    }
    return 'SV' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
}

$notice = trim((string)($_GET['notice'] ?? ''));
$noticeTranslations = [
    'সার্ভিস আবার চালু হয়েছে।' => 'Service has been reactivated.',
    'সার্ভিস ওয়েবসাইট থেকে সরানো হয়েছে; পুরোনো প্যাকেজ ও বুকিং অক্ষত আছে।' => 'Service has been removed from the website. Existing packages and bookings are preserved.',
    'সার্ভিস ওয়েবসাইট থেকে সরানো হয়েছে; পুরোনো প্যাকেজ ও বুকিং অক্ষত আছে।' => 'Service has been removed from the website. Existing packages and bookings are preserved.',
    'সার্ভিস সফলভাবে যোগ করা হয়েছে।' => 'Service added successfully.',
    'সার্ভিস সফলভাবে আপডেট করা হয়েছে।' => 'Service updated successfully.',
    'সার্ভিসের তথ্য আপডেট হয়েছে।' => 'Service updated successfully.',
    'নতুন সার্ভিস যোগ হয়েছে। Package prefix স্বয়ংক্রিয়ভাবে তৈরি হয়েছে.' => 'New service added. A unique package prefix was generated automatically.',
];
$notice = $noticeTranslations[$notice] ?? $notice;
if (preg_match('/[\\x{0980}-\\x{09FF}]/u', $notice)) {
    $notice = 'The request has been processed.';
}

$noticeType = (($_GET['type'] ?? 'success') === 'error') ? 'error' : 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf'] ?? '');
    if (!$postedCsrf || !hash_equals($csrf, $postedCsrf)) {
        http_response_code(403); exit('Invalid request token. Please go back and try again.');
    }
    $action = (string)($_POST['action'] ?? '');
    $serviceId = max(0, (int)($_POST['service_id'] ?? 0));

    if ($action === 'save') {
        $name = trim((string)($_POST['service_name'] ?? ''));
        $nameBn = trim((string)($_POST['service_name_bn'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $descriptionBn = trim((string)($_POST['description_bn'] ?? ''));
        $icon = trim((string)($_POST['icon'] ?? 'fa-heart'));
        $allowedIcons = ['fa-camera-retro','fa-utensils','fa-wand-magic-sparkles','fa-car','fa-brush','fa-clipboard-list','fa-building-columns','fa-tree','fa-hand-sparkles','fa-seedling','fa-bed','fa-shirt','fa-user-tie','fa-gem','fa-lightbulb','fa-envelope-open-text','fa-ring','fa-music','fa-cake-candles','fa-champagne-glasses','fa-store','fa-heart','fa-calendar-check','fa-truck-fast','fa-bell-concierge'];
        if ($name === '' || $nameBn === '' || $description === '' || $descriptionBn === '' || !in_array($icon, $allowedIcons, true)) {
            sm_service_redirect('সব নাম, বিবরণ ও একটি বৈধ আইকন পূরণ করুন।', 'error');
        }
        if (mb_strlen($name) > 100 || mb_strlen($nameBn) > 100) sm_service_redirect('সার্ভিসের নাম সর্বোচ্চ ১০০ অক্ষর হতে পারবে।', 'error');
        if ($serviceId > 0) {
            $stmt = mysqli_prepare($conn, 'SELECT service_id, service_name, service_prefix FROM services WHERE service_id=? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'i', $serviceId); mysqli_stmt_execute($stmt);
            $existingService = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
            if (!$existingService) sm_service_redirect('সার্ভিসটি পাওয়া যায়নি।', 'error');

            // A renamed service only gets a new prefix when the Admin explicitly approves it.
            $applyNewPrefix = isset($_POST['apply_new_prefix']) && $_POST['apply_new_prefix'] === '1';
            $prefix = (string)($existingService['service_prefix'] ?? '');
            if ($applyNewPrefix) {
                $prefix = sm_service_prefix($name, $conn, $serviceId);
            }
            $stmt = mysqli_prepare($conn, 'UPDATE services SET service_name=?, service_name_bn=?, description=?, description_bn=?, icon_class=?, service_prefix=? WHERE service_id=?');
            mysqli_stmt_bind_param($stmt, 'ssssssi', $name, $nameBn, $description, $descriptionBn, $icon, $prefix, $serviceId);
        } else {
            $prefix = sm_service_prefix($name, $conn);
            $stmt = mysqli_prepare($conn, 'INSERT INTO services (service_name, service_name_bn, description, description_bn, icon_class, service_prefix, is_active) VALUES (?,?,?,?,?,?,1)');
            mysqli_stmt_bind_param($stmt, 'ssssss', $name, $nameBn, $description, $descriptionBn, $icon, $prefix);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt); mysqli_stmt_close($stmt);
            sm_service_redirect(str_contains($error, 'Duplicate') ? 'এই নামে সার্ভিস আগে থেকেই আছে।' : 'সার্ভিস সেভ করা যায়নি। আবার চেষ্টা করুন।', 'error');
        }
        mysqli_stmt_close($stmt);
        sm_service_redirect($serviceId > 0 ? 'সার্ভিসের তথ্য আপডেট হয়েছে।' : 'নতুন সার্ভিস যোগ হয়েছে। Package prefix স্বয়ংক্রিয়ভাবে তৈরি হয়েছে।');
    }

    if (in_array($action, ['deactivate','activate'], true) && $serviceId > 0) {
        $active = $action === 'activate' ? 1 : 0;
        $stmt = mysqli_prepare($conn, 'UPDATE services SET is_active=? WHERE service_id=?');
        mysqli_stmt_bind_param($stmt, 'ii', $active, $serviceId);
        $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        sm_service_redirect($ok ? ($active ? 'সার্ভিস আবার চালু হয়েছে।' : 'সার্ভিস ওয়েবসাইট থেকে সরানো হয়েছে; পুরোনো প্যাকেজ ও বুকিং অক্ষত আছে।') : 'পরিবর্তন করা যায়নি।', $ok ? 'success' : 'error');
    }
    sm_service_redirect('অনুরোধটি বোঝা যায়নি।', 'error');
}

$editId = max(0, (int)($_GET['edit'] ?? 0));
$edit = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM services WHERE service_id=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $editId); mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
}
$services = [];
$packageSort = (string)($_GET['package_sort'] ?? 'default');
if (!in_array($packageSort, ['default', 'asc', 'desc'], true)) $packageSort = 'default';
$orderBy = $packageSort === 'asc'
    ? 'package_count ASC, s.service_name ASC'
    : ($packageSort === 'desc' ? 'package_count DESC, s.service_name ASC' : 's.is_active DESC, s.service_name ASC');
$result = mysqli_query($conn, "SELECT s.*, (SELECT COUNT(*) FROM service_providers sp WHERE sp.service_id=s.service_id) AS package_count, (SELECT COUNT(*) FROM service_providers sp JOIN booking_details bd ON bd.provider_id=sp.provider_id WHERE sp.service_id=s.service_id) AS booking_line_count FROM services s ORDER BY " . $orderBy);
if ($result) while ($row = mysqli_fetch_assoc($result)) $services[] = $row;
$iconOptions = [
 'fa-camera-retro'=>'Camera / Photography','fa-utensils'=>'Utensils / Catering','fa-wand-magic-sparkles'=>'Sparkles / Decoration','fa-car'=>'Car rental','fa-brush'=>'Makeup brush','fa-clipboard-list'=>'Planner','fa-building-columns'=>'Convention center','fa-tree'=>'Resort / Lawn','fa-hand-sparkles'=>'Mehendi','fa-seedling'=>'Flowers','fa-bed'=>'Basor ghor','fa-shirt'=>'Dress','fa-user-tie'=>'Groom dress','fa-gem'=>'Accessories','fa-lightbulb'=>'Sound & lighting','fa-envelope-open-text'=>'Invitation / printing','fa-ring'=>'Wedding ring','fa-music'=>'Music','fa-cake-candles'=>'Cake','fa-champagne-glasses'=>'Celebration','fa-store'=>'Store','fa-heart'=>'Heart','fa-calendar-check'=>'Calendar','fa-truck-fast'=>'Delivery','fa-bell-concierge'=>'Concierge'
];
$admin_header_title = 'Manage Services';
$admin_header_subtitle = 'Add, edit, and safely manage service categories';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage Services · Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><style>
.ms-wrap{max-width:1320px;margin:28px auto;padding:0 20px 40px}.ms-heading{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:20px}.ms-heading h1{margin:0;color:#0b4f4c;font-size:clamp(25px,3vw,34px)}.ms-heading p{margin:7px 0 0;color:#67817f}.ms-back{color:#087c75;text-decoration:none;font-weight:800}.ms-alert{padding:13px 16px;border-radius:12px;margin-bottom:18px;font-weight:700}.ms-alert.success{background:#e8f8ef;color:#176842}.ms-alert.error{background:#fff0f0;color:#a83232}.ms-layout{display:grid;grid-template-columns:minmax(300px,.8fr) minmax(0,1.5fr);gap:20px;align-items:start}.ms-panel{background:#fff;border:1px solid #dcebea;border-radius:18px;padding:22px;box-shadow:0 10px 30px rgba(15,76,73,.045);min-width:0}.ms-panel h2{margin:0 0 7px;font-size:21px;color:#0b5f5b}.ms-muted{color:#718886;font-size:13px;line-height:1.55;margin:0 0 18px}.ms-form{display:grid;gap:13px}.ms-field{display:grid;gap:6px}.ms-field label{font-size:13px;font-weight:800;color:#315c59}.ms-field input,.ms-field textarea,.ms-field select{width:100%;min-width:0;border:1px solid #d7e7e5;border-radius:10px;padding:11px 12px;font:inherit;background:#fff;color:#123c3b}.ms-field textarea{min-height:78px;resize:vertical}.ms-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ms-icon-preview{display:flex;align-items:center;gap:10px;padding:11px 13px;background:#f3faf9;border-radius:11px;color:#0d8179;font-weight:800}.ms-icon-preview i{font-size:22px;width:28px;text-align:center}.ms-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px}.ms-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:10px;padding:11px 14px;background:#0d8179;color:white;text-decoration:none;font-weight:800;cursor:pointer;font:inherit;font-size:13px}.ms-btn.secondary{background:#eef7f6;color:#0b6c66}.ms-btn.danger{background:#fff0f0;color:#a83232}.ms-table-wrap{overflow:auto}.ms-table{width:100%;border-collapse:collapse;min-width:650px}.ms-table th,.ms-table td{padding:13px 10px;border-bottom:1px solid #edf3f2;text-align:left;vertical-align:middle}.ms-table th{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#718886}.ms-service{display:flex;align-items:center;gap:11px;min-width:190px}.ms-service-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:#e9f7f5;color:#087c75;font-size:18px;flex:0 0 auto}.ms-service strong{display:block;color:#174d49}.ms-service small{display:block;margin-top:3px;color:#718886}.ms-status{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:900}.ms-status.on{background:#e7f8ee;color:#197347}.ms-status.off{background:#fff3df;color:#925d0a}.ms-row-actions{display:flex;gap:6px;flex-wrap:wrap}.ms-row-actions .ms-btn{padding:8px 10px;font-size:12px}.ms-prefix{font-family:ui-monospace,monospace;font-weight:900;color:#315c59}.ms-note{font-size:12px;color:#718886;line-height:1.55;margin-top:12px}.ms-count{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;background:#eef7f6;border-radius:10px;color:#0b6c66;font-weight:800;font-size:12px;margin-bottom:15px}
.ms-prefix-change{display:flex;align-items:flex-start;gap:9px;color:#315c59;font-size:13px;line-height:1.5;font-weight:700}.ms-prefix-change input{margin-top:3px;accent-color:#0d8179;flex:0 0 auto}
@media(max-width:950px){.ms-layout{grid-template-columns:1fr}.ms-heading{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.ms-wrap{padding:0 12px 28px;margin-top:18px}.ms-panel{padding:16px}.ms-form-grid{grid-template-columns:1fr}.ms-heading h1{font-size:26px}}

.ms-sort-controls{display:inline-flex;align-items:center;gap:3px;margin-left:5px;vertical-align:middle}
.ms-sort-btn{display:inline-flex;align-items:center;justify-content:center;width:23px;height:23px;border:1px solid #d8e8e6;border-radius:6px;background:#fff;color:#688582;text-decoration:none;font-size:11px;transition:all .15s ease}
.ms-sort-btn:hover,.ms-sort-btn.selected{background:#e7f5f3;border-color:#8fc8c2;color:#087c75}
.ms-sort-btn:focus-visible{outline:2px solid #0b8179;outline-offset:2px}
</style><style>/* Keep the Service Catalog table scrollable even on large displays. */ .ms-table-wrap{max-height:calc(100vh - 280px);overflow-y:scroll;overflow-x:auto;scrollbar-gutter:stable;overscroll-behavior:contain} .ms-table-wrap .ms-table thead{position:sticky;top:0;z-index:2;background:#fff}</style></head><body>
<?php require_once __DIR__ . '/admin_header.php'; ?>
<main class="ms-wrap"><div class="ms-heading"><div><h1>Manage Services</h1><p>Add, edit, and control which service categories appear on the website.</p></div><a class="ms-back" href="operations.php"><i class="fa-solid fa-arrow-left"></i> Back to Operations</a></div>
<?php if ($notice !== ''): ?><div class="ms-alert <?=sm_service_h($noticeType)?>"><?=sm_service_h($notice)?></div><?php endif; ?>
<div class="ms-layout"><section class="ms-panel" id="service-form"><h2><?= $edit ? 'Edit Service' : 'Add New Service' ?></h2><p class="ms-muted">Enter the service details and choose an icon. The package prefix is generated when the service is created.</p>
<form method="post" class="ms-form"><input type="hidden" name="csrf" value="<?=sm_service_h($csrf)?>"><input type="hidden" name="action" value="save"><input type="hidden" name="service_id" value="<?= (int)($edit['service_id'] ?? 0) ?>">
<div class="ms-field"><label for="service_name">Service title (English) *</label><input id="service_name" name="service_name" maxlength="100" required value="<?=sm_service_h($edit['service_name'] ?? '')?>" placeholder="e.g. Wedding Cake"></div>
<div class="ms-field"><label for="service_name_bn">Service name (Bengali) *</label><input id="service_name_bn" name="service_name_bn" maxlength="100" required value="<?=sm_service_h($edit['service_name_bn'] ?? '')?>" placeholder="e.g. বিয়ের কেক"></div>
<div class="ms-form-grid"><div class="ms-field"><label for="description">Subtitle / short description (English) *</label><textarea id="description" name="description" required placeholder="A short service description"><?=sm_service_h($edit['description'] ?? '')?></textarea></div><div class="ms-field"><label for="description_bn">Subtitle / short description (Bengali) *</label><textarea id="description_bn" name="description_bn" required placeholder="Enter a short Bengali description"><?=sm_service_h($edit['description_bn'] ?? '')?></textarea></div></div>
<div class="ms-field"><label for="icon">Choose an icon *</label><select id="icon" name="icon" required><?php foreach ($iconOptions as $value=>$label): ?><option value="<?=sm_service_h($value)?>" <?= (($edit['icon_class'] ?? 'fa-heart') === $value) ? 'selected' : '' ?>><?=sm_service_h($label)?></option><?php endforeach; ?></select><div class="ms-icon-preview"><i id="ms-icon-preview" class="fa-solid <?=sm_service_h($edit['icon_class'] ?? 'fa-heart')?>"></i><span id="ms-icon-label">Icon preview</span></div></div>
<?php if ($edit): ?>
<div class="ms-note">Current prefix: <strong class="ms-prefix"><?=sm_service_h($edit['service_prefix'] ?? '')?></strong>. Existing package IDs will never change.</div>
<label class="ms-prefix-change"><input type="checkbox" name="apply_new_prefix" value="1" id="apply_new_prefix"> Apply a new unique prefix based on the updated service name for future packages</label>
<div class="ms-note">Suggested prefix: <strong class="ms-prefix" id="ms-prefix-suggestion"><?=sm_service_h(sm_service_prefix((string)($edit['service_name'] ?? ''), $conn, (int)$edit['service_id']))?></strong>. If another service already uses it, the system will select an available alternative.</div>
<?php else: ?><p class="ms-note">A unique package prefix will be generated automatically. If the suggested prefix is already in use, an alternative will be selected.</p><?php endif; ?>
<div class="ms-actions"><button class="ms-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= $edit ? 'Save Changes' : 'Add Service' ?></button><?php if ($edit): ?><a class="ms-btn secondary" href="manage_services.php">Cancel</a><?php endif; ?></div></form></section>
<section class="ms-panel"><h2>Service Catalog</h2><p class="ms-muted">Inactive services are hidden from new customers. Existing packages, bookings, and Manager work are preserved.</p><div class="ms-count"><i class="fa-solid fa-layer-group"></i> <?=count($services)?> total services</div><div class="ms-table-wrap"><table class="ms-table"><thead><tr><th>Service</th><th>Prefix</th><th>Packages <span class="ms-sort-controls" aria-label="Sort by package count"><a class="ms-sort-btn <?= $packageSort === 'asc' ? 'selected' : '' ?>" href="manage_services.php?package_sort=asc" title="Sort by fewest packages first" aria-label="Sort by fewest packages first"><i class="fa-solid fa-arrow-up-short-wide"></i></a><a class="ms-sort-btn <?= $packageSort === 'desc' ? 'selected' : '' ?>" href="manage_services.php?package_sort=desc" title="Sort by most packages first" aria-label="Sort by most packages first"><i class="fa-solid fa-arrow-down-wide-short"></i></a></span></th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($services as $service): ?><tr><td><div class="ms-service"><span class="ms-service-icon"><i class="fa-solid <?=sm_service_h($service['icon_class'] ?: 'fa-heart')?>"></i></span><span><strong><?=sm_service_h($service['service_name'])?></strong><small><?=sm_service_h($service['service_name_bn'] ?? '')?></small></span></div></td><td><span class="ms-prefix"><?=sm_service_h($service['service_prefix'] ?? '—')?></span></td><td><?=number_format((int)$service['package_count'])?><small><?=number_format((int)$service['booking_line_count'])?> booking item(s)</small></td><td><span class="ms-status <?=((int)$service['is_active']===1?'on':'off')?>"><?=((int)$service['is_active']===1?'Active':'Inactive')?></span></td><td><div class="ms-row-actions"><a class="ms-btn secondary" href="manage_services.php?edit=<?=(int)$service['service_id']?>#service-form"><i class="fa-solid fa-pen"></i> Edit</a><form method="post" onsubmit="return confirm(this.dataset.confirmText)" data-confirm-text="<?=((int)$service['is_active']===1?'Remove this service from the website? Existing packages and bookings will be preserved.':'Reactivate this service?')?>"><input type="hidden" name="csrf" value="<?=sm_service_h($csrf)?>"><input type="hidden" name="service_id" value="<?=(int)$service['service_id']?>"><input type="hidden" name="action" value="<?=((int)$service['is_active']===1?'deactivate':'activate')?>"><button class="ms-btn <?=((int)$service['is_active']===1?'danger':'')?>" type="submit"><i class="fa-solid <?=((int)$service['is_active']===1?'fa-eye-slash':'fa-rotate-left')?>"></i> <?=((int)$service['is_active']===1?'Deactivate':'Activate')?></button></form></div></td></tr><?php endforeach; ?>
<?php if (!$services): ?><tr><td colspan="5">No services found.</td></tr><?php endif; ?></tbody></table></div><p class="ms-note"><i class="fa-solid fa-shield-halved"></i> Permanent deletion is disabled to protect package and booking history. Deactivate hides a service; Activate restores it.</p></section></div></main>
<script>
const msIcon=document.getElementById('icon'),msPreview=document.getElementById('ms-icon-preview'),msLabel=document.getElementById('ms-icon-label');
function updateMsIcon(){if(!msIcon)return;msPreview.className='fa-solid '+msIcon.value;msLabel.textContent=msIcon.options[msIcon.selectedIndex]?.text||'Icon preview';}
if(msIcon)msIcon.addEventListener('change',updateMsIcon);updateMsIcon();
const msName=document.getElementById('service_name'),msPrefixSuggestion=document.getElementById('ms-prefix-suggestion');
function suggestPrefix(name){const words=(name.toUpperCase().match(/[A-Z0-9]+/g)||[]);let prefix=words.length>1?words.map(w=>w[0]).join(''):((words[0]||'SV').substring(0,3));prefix=prefix.replace(/[^A-Z0-9]/g,'').substring(0,5);if(prefix.length<2)prefix=prefix.padEnd(2,'S');return prefix;}
if(msName&&msPrefixSuggestion)msName.addEventListener('input',()=>{msPrefixSuggestion.textContent=suggestPrefix(msName.value)||'SV';});
</script></body></html>
