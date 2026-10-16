<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$viewer_id = (int) $_SESSION['user_id'];
$profile_user_id = (int) ($_GET['user_id'] ?? 0);
if ($profile_user_id <= 0) {
    header('Location: ../dashboard.php');
    exit;
}

/* Match profile/view_profile.php access rules: own profile or active public profile. */
$profile_stmt = mysqli_prepare($conn, "
    SELECT
        u.user_id,
        u.account_status,
        up.first_name,
        up.last_name,
        up.gender,
        up.date_of_birth,
        up.religion,
        up.madhhab,
        up.marital_status,
        up.bio,
        up.height_cm,
        up.weight_kg,
        up.complexion,
        up.country,
        up.division_id,
        up.district_id,
        up.upazila_id,
        up.permanent_country,
        up.permanent_division_id,
        up.permanent_district_id,
        up.permanent_upazila_id,
        up.permanent_area,
        up.permanent_area_type,
        up.permanent_residence_type,
        up.permanent_post_office,
        up.permanent_postal_code,
        up.area,
        up.highest_education,
        up.profession,
        up.occupation_details,
        up.monthly_income,
        up.father_name,
        up.father_profession,
        up.mother_name,
        up.mother_profession,
        up.biodata_created_by,
        up.guardian_consent,
        up.father_alive,
        up.mother_alive,
        up.parents_details,
        up.brothers_details,
        up.sisters_details,
        up.paternal_uncles_details,
        up.paternal_aunts_details,
        up.guardian_name,
        up.guardian_relation,
        up.family_type,
        up.family_status,
        up.siblings,
        up.brothers_count,
        up.sisters_count,
        up.paternal_uncles_count,
        up.paternal_aunts_count,
        up.paternal_siblings_married,
        up.family_purdah_environment,
        up.family_religious_lifestyle,
        up.living_with_family,
        up.area_type,
        up.residence_type,
        up.post_office,
        up.postal_code,
        up.permanent_hometown,
        up.smoking_status,
        up.prayer_status,
        up.beard_status,
        up.hijab_details,
        up.quran_reading,
        up.fasting_status,
        up.religious_practice,
        up.favorite_scholars,
        up.favorite_books,
        up.religious_knowledge_sources,
        up.bidah_shrine_stance,
        up.islamic_knowledge,
        up.halal_lifestyle,
        up.islamic_activities,
        up.tea_coffee,
        up.diet_preference,
        up.sleep_pattern,
        up.personality_type,
        up.social_nature,
        up.travel_interest,
        up.spending_style,
        up.pets,
        up.free_time_interests,
        up.hijab_status,
        up.mahram_maintained,
        up.verification_status,
        up.profile_visibility,
        d.name_en AS district_name,
        dv.name_en AS division_name,
        uz.name_en AS upazila_name,
        pd.name_en AS permanent_district_name,
        pdv.name_en AS permanent_division_name,
        puz.name_en AS permanent_upazila_name
    FROM users u
    INNER JOIN user_profiles up ON up.user_id = u.user_id
    LEFT JOIN divisions dv ON dv.id = up.division_id
    LEFT JOIN districts d ON d.id = up.district_id
    LEFT JOIN upazilas uz ON uz.id = up.upazila_id
    LEFT JOIN divisions pdv ON pdv.id = up.permanent_division_id
    LEFT JOIN districts pd ON pd.id = up.permanent_district_id
    LEFT JOIN upazilas puz ON puz.id = up.permanent_upazila_id
    WHERE u.user_id = ?
      AND u.account_status = 'Active'
      AND (up.profile_visibility = 'Public' OR u.user_id = ?)
    LIMIT 1
");
if (!$profile_stmt) {
    http_response_code(500);
    exit('Unable to load the biodata at this time.');
}
mysqli_stmt_bind_param($profile_stmt, 'ii', $profile_user_id, $viewer_id);
mysqli_stmt_execute($profile_stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($profile_stmt));
mysqli_stmt_close($profile_stmt);

if (!$profile) {
    http_response_code(404);
    exit('This profile is not available for biodata export.');
}

$name = trim((string) ($profile['first_name'] ?? '') . ' ' . (string) ($profile['last_name'] ?? ''));
$name = $name !== '' ? $name : 'Smart Matrimony Member';
$age = null;
if (!empty($profile['date_of_birth'])) {
    try {
        $age = (new DateTime($profile['date_of_birth']))->diff(new DateTime('today'))->y;
    } catch (Throwable $e) {
        $age = null;
    }
}
$location_parts = array_filter([
    $profile['upazila_name'] ?? '',
    $profile['district_name'] ?? '',
    $profile['division_name'] ?? '',
    $profile['country'] ?? '',
], static fn($value) => trim((string) $value) !== '');
$location = implode(', ', array_unique($location_parts));

$preferences = null;
$preferences_stmt = mysqli_prepare($conn, "
    SELECT sp.*, dv.name_en AS preferred_division_name,
           d.name_en AS preferred_district_name, uz.name_en AS preferred_upazila_name
    FROM search_preferences sp
    LEFT JOIN divisions dv ON dv.id = sp.division_id
    LEFT JOIN districts d ON d.id = sp.district_id
    LEFT JOIN upazilas uz ON uz.id = sp.upazila_id
    WHERE sp.user_id = ? LIMIT 1
");
if ($preferences_stmt) {
    mysqli_stmt_bind_param($preferences_stmt, 'i', $profile_user_id);
    mysqli_stmt_execute($preferences_stmt);
    $preferences = mysqli_fetch_assoc(mysqli_stmt_get_result($preferences_stmt)) ?: null;
    mysqli_stmt_close($preferences_stmt);
}

$trait_answers = [];
$traits_stmt = mysqli_prepare($conn, "
    SELECT tq.question_text, uta.answer
    FROM user_trait_answers uta
    INNER JOIN trait_questions tq ON tq.question_id = uta.question_id
    WHERE uta.user_id = ? AND tq.is_active = 1 AND TRIM(uta.answer) <> ''
    ORDER BY tq.display_order ASC, tq.question_id ASC
");
if ($traits_stmt) {
    mysqli_stmt_bind_param($traits_stmt, 'i', $profile_user_id);
    mysqli_stmt_execute($traits_stmt);
    $traits_result = mysqli_stmt_get_result($traits_stmt);
    while ($trait = mysqli_fetch_assoc($traits_result)) {
        $trait_answers[] = $trait;
    }
    mysqli_stmt_close($traits_stmt);
}

$h = static function ($value): string {
    if (is_array($value) || is_object($value)) {
        $value = implode(', ', array_map('strval', (array) $value));
    }
    $value = trim((string) $value);
    return htmlspecialchars($value === '' ? 'Not provided' : $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$display = static function ($value) use ($h): string { return $h($value); };
$interest_chips = static function ($value) use ($h): string {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $value = $decoded;
        } else {
            $value = preg_split('/[,;\\|]+/u', $value) ?: [];
        }
    }
    if (!is_array($value)) { $value = []; }
    $items = [];
    foreach ($value as $item) {
        if (!is_scalar($item)) { continue; }
        $item = trim(trim((string) $item), "\"'");
        if ($item !== '' && !in_array($item, $items, true)) { $items[] = $item; }
    }
    if (!$items) { return '<span class="empty">Not provided</span>'; }
    return '<span class="interest-list">' . implode('', array_map(static fn($item) => '<span class="interest-chip">' . $h($item) . '</span>', $items)) . '</span>';
};
$profile_id = 'SM-' . str_pad((string) $profile_user_id, 6, '0', STR_PAD_LEFT);
$height_display = 'Not provided';
if (!empty($profile['height_cm']) && is_numeric($profile['height_cm'])) {
    $height_inches = (int) round((float) $profile['height_cm'] / 2.54);
    $height_display = (int) floor($height_inches / 12) . ' ft ' . ($height_inches % 12) . ' in';
}

$sections = [
    'personal' => 'Personal information',
    'education' => 'Education & career',
    'family' => 'Family background',
    'religion' => 'Religious practice & lifestyle',
    'location' => 'Location',
    'about' => 'About me',
    'preferences' => 'Partner preferences',
    'answers' => 'Question-based answers',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Biodata — <?= $h($name); ?> | Smart Matrimony</title>
    <style>
        :root{color-scheme:light;--ink:#17312f;--muted:#667b78;--line:#dce8e5;--accent:#0f766e;--soft:#eff8f6}
        *{box-sizing:border-box}body{margin:0;background:#f2f6f5;color:var(--ink);font:15px/1.55 system-ui,-apple-system,"Noto Sans Bengali","Nirmala UI",sans-serif}
        .toolbar{max-width:900px;margin:24px auto 14px;padding:18px 20px;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 8px 28px #163b3510}
        .toolbar h1{margin:0 0 5px;font-size:21px}.toolbar p{margin:0 0 14px;color:var(--muted);font-size:13px}
        .options{display:flex;flex-wrap:wrap;gap:9px 16px;margin:12px 0 16px}.options label{display:inline-flex;align-items:center;gap:7px;font-size:13px;cursor:pointer}
        .actions{display:flex;flex-wrap:wrap;gap:9px}.actions button,.actions a{border:0;border-radius:9px;padding:10px 15px;font:inherit;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer}
        .primary{background:var(--accent);color:#fff}.secondary{background:var(--soft);color:var(--accent)}
        .sheet{max-width:900px;margin:0 auto 28px;padding:34px 38px;background:#fff;border:1px solid var(--line);box-shadow:0 8px 28px #163b350a}
        .sheet-head{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:18px;border-bottom:2px solid var(--accent);padding:0 0 16px;margin-bottom:20px}.identity{display:flex;align-items:center;gap:14px;min-width:0}.site-logo{display:block;width:78px;max-width:100%;height:auto;object-fit:contain;flex:0 0 auto}.profile-heading{text-align:left;min-width:0}.profile-heading .brand{justify-content:flex-start}.profile-heading h2{margin-top:5px}.sheet-head .meta{justify-self:end}
        .brand{display:flex;align-items:center;gap:9px;font-size:12px;font-weight:800;letter-spacing:.12em;color:var(--accent);text-transform:uppercase}.brand-mark{width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:10px;background:var(--soft);border:1px solid var(--line);flex:none}.brand-mark svg{width:23px;height:23px;display:block}.interest-list{display:flex;flex-wrap:wrap;align-items:center;gap:5px 6px;max-width:100%}.interest-chip{display:inline-block;padding:3px 8px;border-radius:999px;background:#eff8f6;border:1px solid #cfe5df;color:#175e57;font-size:11px;font-weight:650;white-space:normal;break-inside:avoid;print-color-adjust:exact;-webkit-print-color-adjust:exact}.sheet h2{margin:5px 0 0;font-size:28px;line-height:1.2;overflow-wrap:anywhere}.meta{text-align:right;color:var(--muted);font-size:12px;white-space:nowrap}
        .section{margin:19px 0 0}.section.is-hidden{display:none!important}.field,.answer,.interest-chip{break-inside:avoid;page-break-inside:avoid}.fields{align-items:start}.section h3{margin:0 0 12px;padding:9px 12px;border-radius:7px;background:var(--soft);color:var(--accent);font-size:13px;font-weight:600}
        .fields{display:grid;grid-template-columns:1fr 1fr;gap:0 22px}.field{display:grid;grid-template-columns:minmax(110px,.8fr) minmax(0,1.2fr);gap:12px;padding:9px 0;border-bottom:1px solid #edf2f1;overflow-wrap:anywhere}.field span{color:var(--muted);font-size:12px}.field strong{font-size:13px;font-weight:400;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere}.wide{grid-column:1/-1}.empty{color:var(--muted);font-size:13px}.answers{display:grid;gap:10px}.answer{padding:12px 14px;border:1px solid var(--line);border-radius:8px;break-inside:avoid;page-break-inside:avoid}.answer strong{display:block;margin-bottom:6px;font-size:12px;font-weight:600}.answer p{margin:0;font-size:13px;line-height:1.6;white-space:pre-wrap}
        .sheet-foot{margin-top:26px;padding-top:12px;border-top:1px solid var(--line);color:var(--muted);font-size:10px;text-align:center}
        @media(max-width:650px){.toolbar{margin:12px;padding:14px}.sheet{margin:12px;padding:22px 18px}.sheet-head{grid-template-columns:minmax(0,1fr) auto;text-align:left}.identity{gap:9px}.site-logo{width:58px}.profile-heading h2{font-size:21px}.meta{text-align:right;justify-self:end!important}.fields{grid-template-columns:1fr}.field{grid-template-columns:110px 1fr}.wide{grid-column:auto}}
        @media print{@page{size:A4;margin:10mm 12mm}html,body{background:#fff!important;color:#17312f;font-size:10pt;line-height:1.5;-webkit-print-color-adjust:exact;print-color-adjust:exact}.toolbar{display:none!important}.sheet{width:100%;max-width:none;margin:0;padding:0;border:0;box-shadow:none}.sheet-head{grid-template-columns:minmax(0,1fr) auto;gap:12px;padding-bottom:9px;margin-bottom:9px;break-after:avoid;page-break-after:avoid}.identity{gap:10px}.site-logo{width:58px}.brand{font-size:8pt;letter-spacing:.06em}.profile-heading h2{font-size:18pt}.sheet h2{font-size:18pt;line-height:1.1}.meta{font-size:8pt}.section{margin:13px 0 0;break-inside:auto;page-break-inside:auto}.section h3{margin:0 0 7px;padding:7px 10px;font-size:10pt;font-weight:600;break-after:avoid;page-break-after:avoid}.fields{gap:0 12px}.field{grid-template-columns:minmax(82px,.8fr) minmax(0,1.2fr);gap:8px;padding:6px 0}.field span{font-size:8pt}.field strong{font-size:9pt;font-weight:400;line-height:1.5}.answer{padding:9px 11px}.answer strong,.answer p{font-size:9pt;line-height:1.5}.interest-list{gap:3px}.interest-chip{font-size:8pt;padding:1px 6px}.sheet-foot{margin-top:14px;padding-top:9px;font-size:8pt}.section h3,.field,.interest-chip{print-color-adjust:exact;-webkit-print-color-adjust:exact}}
        /* Premium visual refinement: presentation only; profile data and behavior remain unchanged. */
        .sheet{max-width:920px;padding:38px 44px;border-radius:14px;border-color:#e3ece9;box-shadow:0 12px 34px rgba(22,58,52,.07)}
        .sheet-head{position:relative;gap:24px;padding:0 0 23px;margin-bottom:25px;border-bottom:0}
        .sheet-head:before{content:"";position:absolute;left:0;right:0;bottom:0;height:1px;background:#d8e7e2}
        .sheet-head:after{content:"";position:absolute;left:0;right:0;bottom:-1px;width:100%;height:3px;border-radius:3px;background:linear-gradient(90deg,#08776f,#39a58c)}
        .identity{gap:17px}.site-logo{width:66px;height:66px;max-height:66px;padding:5px;object-fit:contain;border:1px solid #dcece6;border-radius:15px;background:linear-gradient(145deg,#ffffff,#f2faf7)}
        .brand{display:inline-flex;align-items:center;min-height:25px;padding:4px 9px;border:1px solid #d6ebe4;border-radius:999px;background:#f3faf7;font-size:9px;letter-spacing:.09em;font-weight:750;color:#08776f}
        .profile-heading h2{margin-top:7px;font-size:30px;line-height:1.2;letter-spacing:-.025em;font-weight:700;color:#173b38}
        .meta{font-size:11px;line-height:1.8;color:#637975}.meta strong{display:inline-block;margin-bottom:2px;padding:4px 9px;border:1px solid #d8ebe4;border-radius:7px;background:#f5faf8;font-size:10px;color:#245a54;font-weight:750;letter-spacing:.035em}.prepared-date{display:inline-flex;align-items:center;justify-content:flex-end;gap:5px}.calendar-icon{width:12px;height:12px;display:inline-block;flex:none;color:#0f8278}
        .section{margin:25px 0 0}.section h3{display:flex;align-items:center;gap:10px;margin:0 0 13px;padding:10px 13px;border:1px solid #e2eeea;border-left:3px solid #0f8278;border-radius:8px;background:linear-gradient(90deg,#f1f8f6 0%,#fbfdfc 100%);font-size:13px;line-height:1.45;font-weight:650;letter-spacing:.01em;color:#215e57}
        .fields{column-gap:30px}.field{grid-template-columns:minmax(120px,.85fr) minmax(0,1.15fr);gap:14px;padding:11px 0;border-bottom:1px solid #edf2f0}
        .field span{font-size:12px;line-height:1.6;color:#687d79}.field strong{font-size:13px;line-height:1.75;font-weight:500;color:#243d3a}
        .interest-list{gap:6px 7px}.interest-chip{padding:4px 10px;border-radius:7px;background:#f1f8f6;border-color:#d5e8e2;color:#28645c;font-size:11px;font-weight:500;line-height:1.5}
        .answer{padding:15px 17px;border-color:#e1ebe8;border-radius:10px;background:#fcfefd}.answer strong{margin-bottom:8px;font-size:12px;line-height:1.55;font-weight:650;color:#315f59}.answer p{font-size:13px;font-weight:500;line-height:1.8;color:#2a403d}
        .sheet-foot{margin-top:30px;padding-top:14px;border-top:1px solid #e3ece9;color:#788b87;font-size:10px;line-height:1.7}
        @media(max-width:650px){.sheet{padding:24px 20px;border-radius:10px}.sheet-head{gap:12px;padding-bottom:17px;margin-bottom:20px}.site-logo{width:48px;height:48px;max-height:48px;padding:4px;border-radius:11px}.identity{gap:10px}.brand{font-size:8px;letter-spacing:.045em;min-height:22px;padding:3px 7px}.profile-heading h2{font-size:22px}.section{margin-top:21px}.section h3{margin-bottom:10px}.field{padding:9px 0;gap:10px}}
        @media print{@page{size:A4;margin:13mm 14mm}html,body{font-size:10.5pt;line-height:1.65}.sheet{max-width:none;padding:0;border-radius:0}.sheet-head{gap:18px;padding-bottom:17px;margin-bottom:21px}.site-logo{width:54px;height:54px;max-height:54px;padding:4px;border-radius:10px}.brand{font-size:7pt;letter-spacing:.05em;min-height:0;padding:3px 7px}.profile-heading h2{font-size:21pt;line-height:1.25;font-weight:650}.meta{font-size:8pt;line-height:1.8}.meta strong{font-size:8pt}.calendar-icon{width:10px;height:10px}.section{margin:21px 0 0}.section h3{margin:0 0 10px;padding:8px 11px;font-size:10pt;font-weight:650}.fields{gap:0 22px}.field{grid-template-columns:minmax(88px,.85fr) minmax(0,1.15fr);gap:10px;padding:8px 0}.field span{font-size:8.5pt}.field strong{font-size:9.5pt;font-weight:500;line-height:1.7}.answer{padding:12px 14px}.answer strong{font-size:9pt}.answer p{font-size:9.5pt;font-weight:500;line-height:1.75}.interest-list{gap:4px 5px}.interest-chip{padding:3px 8px;font-size:8.5pt;line-height:1.45}.sheet-foot{margin-top:24px;padding-top:11px;font-size:8pt}}
    </style>
</head>
<body>
    <section class="toolbar" aria-label="Biodata PDF options">
        <h1>Customize your biodata</h1>
        <p>Select the sections you want to include, then choose <strong>Save as PDF</strong>. To remove the localhost URL from printed pages, open the print dialog’s <strong>More settings</strong> and turn off <strong>Headers and footers</strong> (the browser adds these; the page cannot disable them itself). Sensitive identity and direct contact fields are not included.</p>
        <div class="options">
            <?php foreach ($sections as $key => $label): ?>
                <label><input type="checkbox" class="section-toggle" value="<?= $h($key); ?>" checked> <?= $h($label); ?></label>
            <?php endforeach; ?>
        </div>
        <div class="actions">
            <button type="button" class="primary" id="savePdf">Print / Save as PDF</button>
            <button type="button" class="secondary" id="selectAll">Select all</button>
            <button type="button" class="secondary" id="selectNone">Clear selection</button>
            <a class="secondary" href="view_profile.php?user_id=<?= $profile_user_id; ?>">Back to profile</a>
        </div>
    </section>

    <main class="sheet">
        <header class="sheet-head">
            <div class="identity"><img class="site-logo" src="../assets/images/logo/logo.png" alt="Smart Matrimony logo">
            <div class="profile-heading"><div class="brand">SMART MATRIMONY · BIODATA</div><h2><?= $h($name); ?></h2></div></div>
            <div class="meta"><strong><?= $h($profile_id); ?></strong><br><?= $h($profile['verification_status'] ?? 'Pending'); ?> profile<br><span class="prepared-date"><svg class="calendar-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2" y="3.5" width="12" height="10.5" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 2v3M11 2v3M2.5 6.5h11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>Prepared <?= date('d M Y'); ?></span></div>
        </header>

        <section class="section" data-section="personal">
            <h3>Personal information</h3><div class="fields">
                <div class="field"><span>Gender</span><strong><?= $display($profile['gender'] ?? ''); ?></strong></div>
                <div class="field"><span>Age</span><strong><?= $age === null ? 'Not provided' : (int) $age . ' years'; ?></strong></div>
                <div class="field"><span>Religion</span><strong><?= $display($profile['religion'] ?? ''); ?></strong></div>
                <div class="field"><span>Madhhab</span><strong><?= $display($profile['madhhab'] ?? ''); ?></strong></div>
                <div class="field"><span>Marital status</span><strong><?= $display($profile['marital_status'] ?? ''); ?></strong></div>
                <div class="field"><span>Height</span><strong><?= $h($height_display); ?></strong></div>
                <div class="field"><span>Weight</span><strong><?= !empty($profile['weight_kg']) ? $h($profile['weight_kg'] . ' kg') : 'Not provided'; ?></strong></div>
                <div class="field"><span>Complexion</span><strong><?= $display($profile['complexion'] ?? ''); ?></strong></div>
                <div class="field"><span>Biodata created by</span><strong><?= $display($profile['biodata_created_by'] ?? ''); ?></strong></div>
                <?php if (($profile['biodata_created_by'] ?? '') === 'Self'): ?><div class="field"><span>Guardian consent for biodata creation</span><strong><?= (($profile['guardian_consent'] ?? null) === null || ($profile['guardian_consent'] ?? '') === '') ? 'Not provided' : ((int) $profile['guardian_consent'] === 1 ? 'Yes' : 'No'); ?></strong></div><?php endif; ?>
            </div>
        </section>

        <section class="section" data-section="education">
            <h3>Education & career</h3><div class="fields">
                <div class="field"><span>Education</span><strong><?= $display($profile['highest_education'] ?? ''); ?></strong></div>
                <div class="field"><span>Profession</span><strong><?= $display($profile['profession'] ?? ''); ?></strong></div>
                <div class="field wide"><span>Occupation details</span><strong><?= $display($profile['occupation_details'] ?? ''); ?></strong></div>
                <div class="field"><span>Monthly income</span><strong><?= !empty($profile['monthly_income']) ? $h(number_format((float) $profile['monthly_income'], 2) . ' BDT') : 'Not provided'; ?></strong></div>
            </div>
        </section>

        <section class="section" data-section="family">
            <h3>Family background</h3><div class="fields">
                <?php foreach ([
                    'Father' => 'father_name', 'Father alive' => 'father_alive', 'Father profession' => 'father_profession',
                    'Mother' => 'mother_name', 'Mother alive' => 'mother_alive', 'Mother profession' => 'mother_profession',
                    'Guardian' => 'guardian_name', 'Guardian relation' => 'guardian_relation',
                    'Family type' => 'family_type', 'Family status' => 'family_status',
                    'Siblings' => 'siblings', 'Brothers' => 'brothers_count', 'Sisters' => 'sisters_count',
                    'Paternal uncles' => 'paternal_uncles_count', 'Paternal aunts' => 'paternal_aunts_count',
                    'Paternal siblings married' => 'paternal_siblings_married',
                    'Living with family' => 'living_with_family', 'Family purdah environment' => 'family_purdah_environment',
                    'Family religious lifestyle' => 'family_religious_lifestyle',
                ] as $label => $key): ?>
                    <?php if (in_array($key, ['father_profession', 'mother_profession'], true) && (($profile[$key === 'father_profession' ? 'father_alive' : 'mother_alive'] ?? '') === 'No')) { continue; } ?>
                    <div class="field<?= $key === 'free_time_interests' ? ' wide' : ''; ?>"><span><?= $h($label); ?></span><strong><?= $key === 'free_time_interests' ? $interest_chips($profile[$key] ?? '') : $display($profile[$key] ?? ''); ?></strong></div>
                <?php endforeach; ?>
                <?php foreach ([
                    'Additional details about parents' => 'parents_details',
                    'Brothers details' => 'brothers_details',
                    'Sisters details' => 'sisters_details',
                    "Father's brothers details" => 'paternal_uncles_details',
                    "Father's sisters details" => 'paternal_aunts_details',
                ] as $label => $key): ?>
                    <?php if (trim((string) ($profile[$key] ?? '')) !== ''): ?><div class="field wide"><span><?= $h($label); ?></span><strong><?= $display($profile[$key]); ?></strong></div><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section" data-section="religion">
            <h3>Religious practice & lifestyle</h3><div class="fields">
                <?php foreach ([
                    'Prayer' => 'prayer_status', 'Beard' => 'beard_status', 'Hijab status' => 'hijab_status',
                    'Hijab details' => 'hijab_details', 'Mahram maintained' => 'mahram_maintained',
                    'Quran reading' => 'quran_reading', 'Fasting' => 'fasting_status',
                    'Religious practice' => 'religious_practice', 'Islamic knowledge' => 'islamic_knowledge',
                    'Halal lifestyle' => 'halal_lifestyle', 'Islamic activities' => 'islamic_activities',
                    'Smoking' => 'smoking_status', 'Tea / coffee' => 'tea_coffee', 'Diet' => 'diet_preference',
                    'Sleep pattern' => 'sleep_pattern', 'Personality' => 'personality_type',
                    'Social nature' => 'social_nature', 'Travel interest' => 'travel_interest',
                    'Spending style' => 'spending_style', 'Pets' => 'pets', 'Free-time interests' => 'free_time_interests',
                ] as $label => $key): ?>
                    <?php if (in_array($key, ['father_profession', 'mother_profession'], true) && (($profile[$key === 'father_profession' ? 'father_alive' : 'mother_alive'] ?? '') === 'No')) { continue; } ?>
                    <div class="field<?= $key === 'free_time_interests' ? ' wide' : ''; ?>"><span><?= $h($label); ?></span><strong><?= $key === 'free_time_interests' ? $interest_chips($profile[$key] ?? '') : ($key === 'mahram_maintained' ? (((string) ($profile[$key] ?? '')) === '1' ? 'Yes' : (((string) ($profile[$key] ?? '')) === '0' ? 'No' : 'Not provided')) : $display($profile[$key] ?? '')); ?></strong></div>
                <?php endforeach; ?>
                <?php foreach ([
                    'Favorite Islamic Scholars' => 'favorite_scholars',
                    'Favorite Islamic Books' => 'favorite_books',
                    'Religious Learning Sources' => 'religious_knowledge_sources',
                    'Views on Bid’ah and Shrine Worship' => 'bidah_shrine_stance',
                ] as $label => $key): ?>
                    <?php if (trim((string) ($profile[$key] ?? '')) !== ''): ?><div class="field wide"><span><?= $h($label); ?></span><strong><?= $display($profile[$key]); ?></strong></div><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <?php
            $currentAddressKeys = ['country','division_id','district_id','upazila_id','area','area_type','residence_type','post_office','postal_code'];
            $permanentAddressKeys = ['permanent_country','permanent_division_id','permanent_district_id','permanent_upazila_id','permanent_area','permanent_area_type','permanent_residence_type','permanent_post_office','permanent_postal_code'];
            $hasPermanentAddress = false;
            foreach ($permanentAddressKeys as $addressKey) { if (trim((string)($profile[$addressKey] ?? '')) !== '') { $hasPermanentAddress = true; break; } }
            $sameAsCurrent = !$hasPermanentAddress;
            if ($hasPermanentAddress) {
                $sameAsCurrent = true;
                foreach (array_combine($currentAddressKeys, $permanentAddressKeys) as $currentKey => $permanentKey) {
                    if (trim((string)($profile[$currentKey] ?? '')) !== trim((string)($profile[$permanentKey] ?? ''))) { $sameAsCurrent = false; break; }
                }
            }
        ?>
        <section class="section" data-section="location">
            <h3>Location</h3><div class="fields">
                <div class="field wide"><span>Current Address</span><strong><?= $display($location); ?></strong></div>
                <div class="field"><span>Country</span><strong><?= $display($profile['country'] ?? ''); ?></strong></div>
                <div class="field"><span>Division</span><strong><?= $display($profile['division_name'] ?? ''); ?></strong></div>
                <div class="field"><span>District</span><strong><?= $display($profile['district_name'] ?? ''); ?></strong></div>
                <div class="field"><span>Upazila</span><strong><?= $display($profile['upazila_name'] ?? ''); ?></strong></div>
                <div class="field"><span>Area</span><strong><?= $display($profile['area'] ?? ''); ?></strong></div>
                <div class="field"><span>Area type</span><strong><?= $display($profile['area_type'] ?? ''); ?></strong></div>
                <div class="field"><span>Residential Details</span><strong><?= $display($profile['residence_type'] ?? ''); ?></strong></div>
                <div class="field"><span>Post office</span><strong><?= $display($profile['post_office'] ?? ''); ?></strong></div>
                <div class="field"><span>Postal code</span><strong><?= $display($profile['postal_code'] ?? ''); ?></strong></div>
                <div class="field wide"><span>Permanent Address</span><strong><?= $sameAsCurrent ? 'Same as current location' : $display(implode(', ', array_filter([$profile['permanent_upazila_name'] ?? '', $profile['permanent_district_name'] ?? '', $profile['permanent_division_name'] ?? '', $profile['permanent_country'] ?? '']))); ?></strong></div>
                <?php if (!$sameAsCurrent): ?>
                    <div class="field"><span>Permanent Country</span><strong><?= $display($profile['permanent_country'] ?? ''); ?></strong></div>
                    <div class="field"><span>Permanent Division</span><strong><?= $display($profile['permanent_division_name'] ?? ''); ?></strong></div>
                    <div class="field"><span>Permanent District</span><strong><?= $display($profile['permanent_district_name'] ?? ''); ?></strong></div>
                    <div class="field"><span>Permanent Upazila</span><strong><?= $display($profile['permanent_upazila_name'] ?? ''); ?></strong></div>
                    <div class="field"><span>Permanent Area</span><strong><?= $display($profile['permanent_area'] ?? ''); ?></strong></div>
                    <div class="field"><span>Permanent Area Type</span><strong><?= $display($profile['permanent_area_type'] ?? ''); ?></strong></div>
                    <div class="field"><span>Permanent Residential Details</span><strong><?= $display($profile['permanent_residence_type'] ?? ''); ?></strong></div>
                    <div class="field"><span>Permanent Post Office</span><strong><?= $display($profile['permanent_post_office'] ?? ''); ?></strong></div>
                    <div class="field"><span>Permanent Postal Code</span><strong><?= $display($profile['permanent_postal_code'] ?? ''); ?></strong></div>
                <?php endif; ?>
                <div class="field wide"><span>Permanent Home / Hometown</span><strong><?= $display($profile['permanent_hometown'] ?? ''); ?></strong></div>
            </div>
        </section>

        <section class="section" data-section="about">
            <h3>About me</h3><div class="fields"><div class="field wide"><span>Introduction</span><strong><?= $display($profile['bio'] ?? ''); ?></strong></div></div>
        </section>

        <section class="section" data-section="preferences">
            <h3>Partner preferences</h3>
            <?php if ($preferences): ?>
                <div class="fields">
                    <?php foreach ([
                        'Preferred gender' => 'preferred_gender', 'Age range' => null,
                        'Minimum height' => 'min_height_cm', 'Maximum height' => 'max_height_cm',
                        'Religion' => 'religion', 'Madhhab' => 'madhhab', 'Marital status' => 'marital_status',
                        'Family status' => 'family_status', 'Education' => 'education', 'Profession' => 'profession',
                        'Prayer status' => 'prayer_status', 'Halal lifestyle' => 'halal_lifestyle',
                        'Hijab status' => 'hijab_status', 'Beard status' => 'beard_status',
                        'Division' => 'preferred_division_name', 'District' => 'preferred_district_name',
                        'Upazila' => 'preferred_upazila_name', 'Blood group' => 'blood_group',
                    ] as $label => $key): ?>
                        <?php
                        if ($key === null) {
                            $minAge = $preferences['min_age'] ?? '';
                            $maxAge = $preferences['max_age'] ?? '';
                            $value = ($minAge !== '' || $maxAge !== '') ? ($minAge ?: 'Any') . ' – ' . ($maxAge ?: 'Any') . ' years' : 'Not provided';
                        } elseif (in_array($key, ['min_height_cm', 'max_height_cm'], true)) {
                            $raw = $preferences[$key] ?? '';
                            $value = $raw !== '' && is_numeric($raw) ? round((float) $raw / 2.54, 1) . ' inches' : 'Not provided';
                        } else {
                            $value = $preferences[$key] ?? '';
                        }
                        ?>
                        <div class="field"><span><?= $h($label); ?></span><strong><?= $display($value); ?></strong></div>
                    <?php endforeach; ?>
                    <?php if (!empty($preferences['additional_preferences'])): ?><div class="field wide"><span>Additional preferences</span><strong><?= $display($preferences['additional_preferences']); ?></strong></div><?php endif; ?>
                    <?php if (!empty($preferences['partner_description'])): ?><div class="field wide"><span>Detailed description of your ideal partner</span><strong><?= $display($preferences['partner_description']); ?></strong></div><?php endif; ?>
                </div>
            <?php else: ?><p class="empty">No partner preferences have been added.</p><?php endif; ?>
        </section>

        <section class="section" data-section="answers">
            <h3>Question-based answers</h3>
            <?php if ($trait_answers): ?><div class="answers">
                <?php foreach ($trait_answers as $answer): ?><article class="answer"><strong><?= $h($answer['question_text']); ?></strong><p><?= $h($answer['answer']); ?></p></article><?php endforeach; ?>
            </div><?php else: ?><p class="empty">No question-based answers have been added.</p><?php endif; ?>
        </section>
        <footer class="sheet-foot">Smart Matrimony · This biodata excludes direct contact details and sensitive identity numbers.</footer>
    </main>
    <script>
    (function () {
        const toggles = Array.from(document.querySelectorAll('.section-toggle'));
        function syncSections() {
            toggles.forEach(function (toggle) {
                const section = document.querySelector('[data-section="' + toggle.value + '"]');
                if (section) section.classList.toggle('is-hidden', !toggle.checked);
            });
        }
        toggles.forEach(function (toggle) { toggle.addEventListener('change', syncSections); });
        document.getElementById('selectAll').addEventListener('click', function () { toggles.forEach(function (item) { item.checked = true; }); syncSections(); });
        document.getElementById('selectNone').addEventListener('click', function () { toggles.forEach(function (item) { item.checked = false; }); syncSections(); });
        var originalDocumentTitle = document.title;
        window.addEventListener('beforeprint', function () { document.title = ''; });
        window.addEventListener('afterprint', function () { document.title = originalDocumentTitle; });
        document.getElementById('savePdf').addEventListener('click', function () {
            if (!toggles.some(function (item) { return item.checked; })) {
                window.alert('Select at least one section before saving your biodata.');
                return;
            }
            window.print();
        });
        syncSections();
    }());
    </script>
</body>
</html>
