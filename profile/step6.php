<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/dropdowns.php';
require_once '../includes/profile_completion.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

$user_stmt = mysqli_prepare($conn, 'SELECT gender FROM users WHERE user_id=? LIMIT 1');
mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
mysqli_stmt_execute($user_stmt);
$user_row = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt)) ?: [];
mysqli_stmt_close($user_stmt);

$pref = [];
$pref_stmt = mysqli_prepare($conn, 'SELECT preferred_gender, min_age, max_age, min_height_cm, max_height_cm, complexion, min_weight_kg, max_weight_kg, religion, marital_status, family_status, madhhab, prayer_status, halal_lifestyle, mahram_maintained, islamic_knowledge, hijab_status, beard_status, personality_type, education, profession, min_monthly_income, division_id, district_id, upazila_id, blood_group, accept_smoker, accept_alcohol, accept_disability, accept_chronic_disease, additional_preferences, partner_description FROM search_preferences WHERE user_id=? LIMIT 1');
if ($pref_stmt) {
    mysqli_stmt_bind_param($pref_stmt, 'i', $user_id);
    mysqli_stmt_execute($pref_stmt);
    $pref = mysqli_fetch_assoc(mysqli_stmt_get_result($pref_stmt)) ?: [];
    mysqli_stmt_close($pref_stmt);
}

$default_gender = (($user_row['gender'] ?? '') === 'Male') ? 'Female' : 'Male';
$form = [
    // Preferred gender is always the opposite gender of the user's own gender.
    'preferred_gender' => $default_gender,
    'min_age' => $pref['min_age'] ?? '',
    'max_age' => $pref['max_age'] ?? '',
    'min_height_cm' => $pref['min_height_cm'] ?? '',
    'max_height_cm' => $pref['max_height_cm'] ?? '',
    'complexion' => $pref['complexion'] ?? '',
    'min_weight_kg' => $pref['min_weight_kg'] ?? '',
    'max_weight_kg' => $pref['max_weight_kg'] ?? '',
    'min_height_feet' => '',
    'min_height_inches' => '',
    'max_height_feet' => '',
    'max_height_inches' => '',
    'religion' => $pref['religion'] ?? '',
    'marital_status' => $pref['marital_status'] ?? '',
    'family_status' => $pref['family_status'] ?? '',
    'madhhab' => $pref['madhhab'] ?? '',
    'prayer_status' => $pref['prayer_status'] ?? '',
    'halal_lifestyle' => $pref['halal_lifestyle'] ?? '',
    'mahram_maintained' => isset($pref['mahram_maintained']) ? (string)$pref['mahram_maintained'] : '',
    'islamic_knowledge' => $pref['islamic_knowledge'] ?? '',
    'hijab_status' => $pref['hijab_status'] ?? '',
    'beard_status' => $pref['beard_status'] ?? '',
    'personality_type' => $pref['personality_type'] ?? '',
    'education' => $pref['education'] ?? '',
    'profession' => $pref['profession'] ?? '',
    'min_monthly_income' => $pref['min_monthly_income'] ?? '',
    'division_id' => $pref['division_id'] ?? '',
    'district_id' => $pref['district_id'] ?? '',
    'upazila_id' => $pref['upazila_id'] ?? '',
    'blood_group' => $pref['blood_group'] ?? '',
    'accept_smoker' => isset($pref['accept_smoker']) ? (string)$pref['accept_smoker'] : '1',
    'accept_alcohol' => isset($pref['accept_alcohol']) ? (string)$pref['accept_alcohol'] : '1',
    'accept_disability' => isset($pref['accept_disability']) ? (string)$pref['accept_disability'] : '1',
    'accept_chronic_disease' => isset($pref['accept_chronic_disease']) ? (string)$pref['accept_chronic_disease'] : '1',
    'additional_preferences' => $pref['additional_preferences'] ?? '',
    'partner_description' => $pref['partner_description'] ?? ''
];

$trait_questions = [];
$trait_answer_map = [];
$trait_stmt = mysqli_prepare($conn, "
    SELECT tq.question_id, tq.question_text, tq.gender, tq.answer_type, tq.options, tq.display_order,
           uta.answer AS saved_answer
    FROM trait_questions tq
    LEFT JOIN user_trait_answers uta
        ON uta.question_id = tq.question_id AND uta.user_id = ?
    WHERE tq.is_active = 1 AND (tq.gender = 'Both' OR tq.gender = ?)
    ORDER BY tq.display_order, tq.question_id
");
if ($trait_stmt) {
    mysqli_stmt_bind_param($trait_stmt, 'is', $user_id, $user_row['gender']);
    mysqli_stmt_execute($trait_stmt);
    $trait_result = mysqli_stmt_get_result($trait_stmt);
    if ($trait_result) {
        while ($row = mysqli_fetch_assoc($trait_result)) {
            $trait_questions[] = $row;
            $trait_answer_map[(int)$row['question_id']] = $row['saved_answer'] ?? '';
        }
    }
    mysqli_stmt_close($trait_stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'  && isset($_POST['save_step6'])) {
    // Preferred gender is not user-editable; always derive it from the logged-in user's gender.
    $form['preferred_gender'] = $default_gender;
    $form['min_age'] = trim($_POST['min_age'] ?? '');
    $form['max_age'] = trim($_POST['max_age'] ?? '');
    $form['min_height_feet'] = trim($_POST['min_height_feet'] ?? '');
    $form['min_height_inches'] = trim($_POST['min_height_inches'] ?? '');
    $form['max_height_feet'] = trim($_POST['max_height_feet'] ?? '');
    $form['max_height_inches'] = trim($_POST['max_height_inches'] ?? '');
    $form['complexion'] = trim($_POST['complexion'] ?? '');
    $form['min_weight_kg'] = trim($_POST['min_weight_kg'] ?? '');
    $form['max_weight_kg'] = trim($_POST['max_weight_kg'] ?? '');
    $form['religion'] = trim($_POST['religion'] ?? '');
    $form['marital_status'] = trim($_POST['marital_status'] ?? '');
    $form['family_status'] = trim($_POST['family_status'] ?? '');
    $form['madhhab'] = trim($_POST['madhhab'] ?? '');
    $form['prayer_status'] = trim($_POST['prayer_status'] ?? '');
    $form['halal_lifestyle'] = trim($_POST['halal_lifestyle'] ?? '');
    $form['mahram_maintained'] = trim($_POST['mahram_maintained'] ?? '');
    $form['islamic_knowledge'] = trim($_POST['islamic_knowledge'] ?? '');
    $form['hijab_status'] = trim($_POST['hijab_status'] ?? '');
    $form['beard_status'] = trim($_POST['beard_status'] ?? '');
    $form['personality_type'] = trim($_POST['personality_type'] ?? '');
    $form['education'] = trim($_POST['education'] ?? '');
    $form['profession'] = trim($_POST['profession'] ?? '');
    $form['min_monthly_income'] = trim($_POST['min_monthly_income'] ?? '');
    $form['division_id'] = trim($_POST['division_id'] ?? '');
    $form['district_id'] = trim($_POST['district_id'] ?? '');
    $form['upazila_id'] = trim($_POST['upazila_id'] ?? '');
    $form['blood_group'] = trim($_POST['blood_group'] ?? '');
    $form['accept_smoker'] = trim($_POST['accept_smoker'] ?? '1');
    $form['accept_alcohol'] = trim($_POST['accept_alcohol'] ?? '1');
    $form['accept_disability'] = trim($_POST['accept_disability'] ?? '1');
    $form['accept_chronic_disease'] = trim($_POST['accept_chronic_disease'] ?? '1');
    $form['additional_preferences'] = trim($_POST['additional_preferences'] ?? '');
    $form['partner_description'] = trim($_POST['partner_description'] ?? '');
    $trait_answers_post = $_POST['trait_answer'] ?? [];
    if (!is_array($trait_answers_post)) {
        $trait_answers_post = [];
    }

    $min_age = (int) $form['min_age'];
    $max_age = (int) $form['max_age'];
    $min_height_feet = $form['min_height_feet'] === '' ? null : (int) $form['min_height_feet'];
    $min_height_inches = $form['min_height_inches'] === '' ? null : (int) $form['min_height_inches'];
    $max_height_feet = $form['max_height_feet'] === '' ? null : (int) $form['max_height_feet'];
    $max_height_inches = $form['max_height_inches'] === '' ? null : (int) $form['max_height_inches'];
    $min_weight_kg = $form['min_weight_kg'] === '' ? null : (float) $form['min_weight_kg'];
    $max_weight_kg = $form['max_weight_kg'] === '' ? null : (float) $form['max_weight_kg'];

    $min_height = ($min_height_feet !== null && $min_height_inches !== null) ? round((($min_height_feet * 12) + $min_height_inches) * 2.54) : null;
    $max_height = ($max_height_feet !== null && $max_height_inches !== null) ? round((($max_height_feet * 12) + $max_height_inches) * 2.54) : null;
    $min_income = $form['min_monthly_income'] === '' ? null : (float) $form['min_monthly_income'];
    $division_id = $form['division_id'] === '' ? null : (int) $form['division_id'];
    $district_id = $form['district_id'] === '' ? null : (int) $form['district_id'];
    $upazila_id = $form['upazila_id'] === '' ? null : (int) $form['upazila_id'];

    $valid_religions = $religions;
    $valid_marital = $marital_statuses;
    $valid_family_statuses = $family_statuses;
    $valid_education = $education_levels;
    $valid_professions = $user_professions;
    $valid_madhhabs = $madhhabs;
    $valid_prayer_statuses = $prayer_statuses;
    $valid_halal_lifestyles = $halal_lifestyle_options;
    $valid_islamic_knowledge = $islamic_knowledge_levels;
    $valid_hijab_statuses = $hijab_statuses;
    $valid_beard_statuses = $beard_statuses;
    $valid_personality_types = $personality_types;

    if (!in_array($form['preferred_gender'], ['Male', 'Female'], true)) {
        $error = sm_text('Please select a valid preferred gender.', 'অনুগ্রহ করে সঠিক পছন্দের লিঙ্গ নির্বাচন করুন।');
    } elseif ($min_age < 15 || $max_age < 15 || $min_age > $max_age || $max_age > 80) {
        $error = sm_text('Please enter a valid age range between 15 and 80, with minimum age not greater than maximum age.', 'অনুগ্রহ করে ১৫ থেকে ৮০ বছরের মধ্যে সঠিক বয়সসীমা দিন। সর্বনিম্ন বয়স সর্বোচ্চ বয়সের চেয়ে বেশি হতে পারবে না।');
    } elseif (($min_height_feet !== null && ($min_height_feet < 4 || $min_height_feet > 7)) || ($max_height_feet !== null && ($max_height_feet < 4 || $max_height_feet > 7)) || ($min_height_inches !== null && ($min_height_inches < 0 || $min_height_inches > 11)) || ($max_height_inches !== null && ($max_height_inches < 0 || $max_height_inches > 11)) || (($min_height_feet === null) !== ($min_height_inches === null)) || (($max_height_feet === null) !== ($max_height_inches === null)) || ($min_height !== null && $max_height !== null && $min_height > $max_height)) {
        $error = sm_text('Please enter a valid height range.', 'অনুগ্রহ করে সঠিক উচ্চতার সীমা দিন।');
    } elseif (($min_weight_kg !== null && ($min_weight_kg < 1 || $min_weight_kg > 500)) || ($max_weight_kg !== null && ($max_weight_kg < 1 || $max_weight_kg > 500)) || ($min_weight_kg !== null && $max_weight_kg !== null && $min_weight_kg > $max_weight_kg)) {
        $error = sm_text('Please enter a valid weight range between 1 and 500 kg, with minimum weight not greater than maximum weight.', 'অনুগ্রহ করে ১ থেকে ৫০০ কেজির মধ্যে সঠিক ওজনসীমা দিন। সর্বনিম্ন ওজন সর্বোচ্চ ওজনের চেয়ে বেশি হতে পারবে না।');
    } elseif ($form['complexion'] !== '' && !in_array($form['complexion'], $complexions, true)) {
        $error = sm_text('Please select a valid complexion preference.', 'অনুগ্রহ করে গায়ের রং সম্পর্কে সঠিক পছন্দ নির্বাচন করুন।');
    } elseif ($form['religion'] === '') {
        $error = sm_text('Please select a preferred religion.', 'অনুগ্রহ করে পছন্দের ধর্ম নির্বাচন করুন।');
    } elseif (!in_array($form['religion'], $valid_religions, true)) {
        $error = sm_text('Please select a valid religion preference.', 'অনুগ্রহ করে সঠিক ধর্মের পছন্দ নির্বাচন করুন।');
    } elseif ($form['marital_status'] !== '' && !in_array($form['marital_status'], $valid_marital, true)) {
        $error = sm_text('Please select a valid marital status preference.', 'অনুগ্রহ করে সঠিক বৈবাহিক অবস্থার পছন্দ নির্বাচন করুন।');
    } elseif ($form['education'] !== '' && !in_array($form['education'], $valid_education, true)) {
        $error = sm_text('Please select a valid education preference.', 'অনুগ্রহ করে সঠিক শিক্ষাগত যোগ্যতার পছন্দ নির্বাচন করুন।');
    } elseif ($form['family_status'] !== '' && !in_array($form['family_status'], $valid_family_statuses, true)) {
        $error = sm_text('Please select a valid family status preference.', 'অনুগ্রহ করে সঠিক পারিবারিক অবস্থার পছন্দ নির্বাচন করুন।');
    } elseif ($form['profession'] !== '' && !in_array($form['profession'], $valid_professions, true)) {
        $error = sm_text('Please select a valid profession preference.', 'অনুগ্রহ করে সঠিক পেশার পছন্দ নির্বাচন করুন।');
    } elseif ($min_income !== null && ($min_income < 0 || $min_income > 99999999.99)) {
        $error = sm_text('Please enter a valid minimum income.', 'অনুগ্রহ করে সঠিক সর্বনিম্ন মাসিক আয় লিখুন।');
    } elseif (!in_array($form['blood_group'], ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
        $error = sm_text('Please select a valid blood group preference.', 'অনুগ্রহ করে সঠিক রক্তের গ্রুপ নির্বাচন করুন।');
    } elseif (!in_array($form['accept_smoker'], ['0', '1'], true) || !in_array($form['accept_alcohol'], ['0', '1'], true) || !in_array($form['accept_disability'], ['0', '1'], true) || !in_array($form['accept_chronic_disease'], ['0', '1'], true)) {
        $error = sm_text('Please select valid lifestyle and health preferences.', 'অনুগ্রহ করে জীবনযাপন ও স্বাস্থ্যসংক্রান্ত সঠিক পছন্দ নির্বাচন করুন।');
    } elseif ($form['madhhab'] !== '' && !in_array($form['madhhab'], $valid_madhhabs, true)) {
        $error = sm_text('Please select a valid madhhab preference.', 'অনুগ্রহ করে সঠিক মাজহাবের পছন্দ নির্বাচন করুন।');
    } elseif ($form['prayer_status'] !== '' && !in_array($form['prayer_status'], $valid_prayer_statuses, true)) {
        $error = sm_text('Please select a valid prayer status preference.', 'অনুগ্রহ করে নামাজের অভ্যাস সম্পর্কে সঠিক পছন্দ নির্বাচন করুন।');
    } elseif ($form['halal_lifestyle'] !== '' && !in_array($form['halal_lifestyle'], $valid_halal_lifestyles, true)) {
        $error = sm_text('Please select a valid halal lifestyle preference.', 'অনুগ্রহ করে হালাল জীবনযাপন সম্পর্কে সঠিক পছন্দ নির্বাচন করুন।');
    } elseif ($form['mahram_maintained'] !== '' && !in_array($form['mahram_maintained'], ['0', '1'], true)) {
        $error = sm_text('Please select a valid mahram preference.', 'অনুগ্রহ করে মাহরাম-সংক্রান্ত সঠিক পছন্দ নির্বাচন করুন।');
    } elseif ($form['islamic_knowledge'] !== '' && !in_array($form['islamic_knowledge'], $valid_islamic_knowledge, true)) {
        $error = sm_text('Please select a valid Islamic knowledge preference.', 'অনুগ্রহ করে ইসলামি জ্ঞান সম্পর্কে সঠিক পছন্দ নির্বাচন করুন।');
    } elseif ($form['hijab_status'] !== '' && !in_array($form['hijab_status'], $valid_hijab_statuses, true)) {
        $error = sm_text('Please select a valid hijab preference.', 'অনুগ্রহ করে হিজাব সম্পর্কে সঠিক পছন্দ নির্বাচন করুন।');
    } elseif ($form['beard_status'] !== '' && !in_array($form['beard_status'], $valid_beard_statuses, true)) {
        $error = sm_text('Please select a valid beard preference.', 'অনুগ্রহ করে দাড়ি সম্পর্কে সঠিক পছন্দ নির্বাচন করুন।');
    } elseif ($form['personality_type'] !== '' && !in_array($form['personality_type'], $valid_personality_types, true)) {
        $error = sm_text('Please select a valid personality preference.', 'অনুগ্রহ করে ব্যক্তিত্বের ধরন সম্পর্কে সঠিক পছন্দ নির্বাচন করুন।');
    } elseif (mb_strlen($form['additional_preferences']) > 5000) {
        $error = sm_text('Additional preferences must be 5000 characters or fewer.', 'বিশেষ পছন্দের লেখা সর্বোচ্চ ৫০০০ অক্ষরের মধ্যে হতে হবে।');
    } elseif (mb_strlen($form['partner_description']) > 5000) {
        $error = sm_text('Partner description must be 5000 characters or fewer.', 'প্রত্যাশিত জীবনসঙ্গীর বর্ণনা সর্বোচ্চ ৫০০০ অক্ষরের মধ্যে হতে হবে।');
    }

    $validated_trait_answers = [];
    if ($error === '') {
        // Validate and normalize the submitted answers for the active questions.
        // These answers belong in user_trait_answers (not partner_trait_preferences).
        $valid_question_map = [];
        foreach ($trait_questions as $question) {
            $qid = (int)$question['question_id'];
            $qtype = $question['answer_type'] ?? 'Text';
            $qoptions = array_values(array_filter(
                array_map('trim', explode(',', (string)($question['options'] ?? ''))),
                static fn($option) => $option !== ''
            ));
            $valid_question_map[$qid] = [
                'answer_type' => $qtype,
                'options' => $qoptions
            ];
        }

        foreach ($valid_question_map as $question_id => $question_meta) {
            $raw_answer = $trait_answers_post[$question_id] ?? '';

            if (is_array($raw_answer)) {
                $error = sm_text('Please provide valid answers for all questions.', 'অনুগ্রহ করে সব প্রশ্নের সঠিক উত্তর দিন।');
                break;
            }

            $answer = trim((string)$raw_answer);

            if ($answer === '') {
                $error = sm_text('Please answer all profile questions before continuing.', 'এগিয়ে যাওয়ার আগে প্রোফাইলের সব প্রশ্নের উত্তর দিন।');
                break;
            }

            if (mb_strlen($answer) > 255) {
                $error = sm_text('Each question answer must be 255 characters or fewer.', 'প্রতিটি প্রশ্নের উত্তর সর্বোচ্চ ২৫৫ অক্ষরের মধ্যে হতে হবে।');
                break;
            }

            if ($question_meta['answer_type'] === 'Yes/No') {
                if (!in_array($answer, ['Yes', 'No'], true)) {
                    $error = sm_text('Please provide a valid answer for all questions.', 'অনুগ্রহ করে সব প্রশ্নের সঠিক উত্তর দিন।');
                    break;
                }
            } elseif ($question_meta['answer_type'] === 'Dropdown') {
                if (!in_array($answer, $question_meta['options'], true)) {
                    $error = sm_text('Please provide a valid answer for all questions.', 'অনুগ্রহ করে সব প্রশ্নের সঠিক উত্তর দিন।');
                    break;
                }
            }

            $validated_trait_answers[$question_id] = $answer;
        }
    }

    if ($error === '') {
        $religion = $form['religion'] === '' ? null : $form['religion'];
        $marital = $form['marital_status'] === '' ? null : $form['marital_status'];
        $family_status = $form['family_status'] === '' ? null : $form['family_status'];
        $madhhab = $form['madhhab'] === '' ? null : $form['madhhab'];
        $prayer_status = $form['prayer_status'] === '' ? null : $form['prayer_status'];
        $halal_lifestyle = $form['halal_lifestyle'] === '' ? null : $form['halal_lifestyle'];
        $mahram_maintained = $form['mahram_maintained'] === '' ? null : (int)$form['mahram_maintained'];
        $islamic_knowledge = $form['islamic_knowledge'] === '' ? null : $form['islamic_knowledge'];
        $hijab_status = $form['hijab_status'] === '' ? null : $form['hijab_status'];
        $beard_status = $form['beard_status'] === '' ? null : $form['beard_status'];
        $personality_type = $form['personality_type'] === '' ? null : $form['personality_type'];

        if ($form['religion'] !== 'Islam') {
            $madhhab = $prayer_status = $halal_lifestyle = $mahram_maintained = $islamic_knowledge = $hijab_status = $beard_status = null;
        } elseif ($form['preferred_gender'] === 'Female') {
            $beard_status = null;
        } elseif ($form['preferred_gender'] === 'Male') {
            $hijab_status = null;
        }

        $education = $form['education'] === '' ? null : $form['education'];
        $profession = $form['profession'] === '' ? null : $form['profession'];

        $check = mysqli_prepare($conn, 'SELECT preference_id FROM search_preferences WHERE user_id=? LIMIT 1');
        mysqli_stmt_bind_param($check, 'i', $user_id);
        mysqli_stmt_execute($check);
        $exists = mysqli_num_rows(mysqli_stmt_get_result($check)) > 0;
        mysqli_stmt_close($check);

        $blood_group = $form['blood_group'] === '' ? null : $form['blood_group'];
        $accept_smoker = (int) $form['accept_smoker'];
        $accept_alcohol = (int) $form['accept_alcohol'];
        $accept_disability = (int) $form['accept_disability'];
        $accept_chronic = (int) $form['accept_chronic_disease'];
        $additional_preferences = $form['additional_preferences'] === '' ? null : $form['additional_preferences'];
        $partner_description = $form['partner_description'] === '' ? null : $form['partner_description'];
        $complexion = $form['complexion'] === '' ? null : $form['complexion'];

        mysqli_begin_transaction($conn);
        try {
            if ($exists) {
                $sql = 'UPDATE search_preferences SET preferred_gender=?, min_age=?, max_age=?, min_height_cm=?, max_height_cm=?, complexion=?, min_weight_kg=?, max_weight_kg=?, religion=?, marital_status=?, family_status=?, madhhab=?, prayer_status=?, halal_lifestyle=?, mahram_maintained=?, islamic_knowledge=?, hijab_status=?, beard_status=?, personality_type=?, education=?, profession=?, min_monthly_income=?, division_id=?, district_id=?, upazila_id=?, blood_group=?, accept_smoker=?, accept_alcohol=?, accept_disability=?, accept_chronic_disease=?, additional_preferences=?, partner_description=? WHERE user_id=?';
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) throw new Exception('Preference update could not be prepared.');
                mysqli_stmt_bind_param($stmt, 'siiddsddssssssissssssdiiisiiiissi', $form['preferred_gender'], $min_age, $max_age, $min_height, $max_height, $complexion, $min_weight_kg, $max_weight_kg, $religion, $marital, $family_status, $madhhab, $prayer_status, $halal_lifestyle, $mahram_maintained, $islamic_knowledge, $hijab_status, $beard_status, $personality_type, $education, $profession, $min_income, $division_id, $district_id, $upazila_id, $blood_group, $accept_smoker, $accept_alcohol, $accept_disability, $accept_chronic, $additional_preferences, $partner_description, $user_id);
            } else {
                $sql = 'INSERT INTO search_preferences (user_id, preferred_gender, min_age, max_age, min_height_cm, max_height_cm, complexion, min_weight_kg, max_weight_kg, religion, marital_status, family_status, madhhab, prayer_status, halal_lifestyle, mahram_maintained, islamic_knowledge, hijab_status, beard_status, personality_type, education, profession, min_monthly_income, division_id, district_id, upazila_id, blood_group, accept_smoker, accept_alcohol, accept_disability, accept_chronic_disease, additional_preferences, partner_description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) throw new Exception('Preference insert could not be prepared.');
                mysqli_stmt_bind_param($stmt, 'isiiddsddssssssissssssdiiisiiiiss', $user_id, $form['preferred_gender'], $min_age, $max_age, $min_height, $max_height, $complexion, $min_weight_kg, $max_weight_kg, $religion, $marital, $family_status, $madhhab, $prayer_status, $halal_lifestyle, $mahram_maintained, $islamic_knowledge, $hijab_status, $beard_status, $personality_type, $education, $profession, $min_income, $division_id, $district_id, $upazila_id, $blood_group, $accept_smoker, $accept_alcohol, $accept_disability, $accept_chronic, $additional_preferences, $partner_description);
            }

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new Exception('Partner preferences could not be saved.');
            }
            mysqli_stmt_close($stmt);

            $trait_insert = mysqli_prepare($conn, 'INSERT INTO user_trait_answers (user_id, question_id, answer) VALUES (?,?,?) ON DUPLICATE KEY UPDATE answer=VALUES(answer), answered_at=CURRENT_TIMESTAMP');
            if (!$trait_insert) throw new Exception('Question answers could not be prepared.');
            foreach ($validated_trait_answers as $question_id => $answer) {
                mysqli_stmt_bind_param($trait_insert, 'iis', $user_id, $question_id, $answer);
                if (!mysqli_stmt_execute($trait_insert)) {
                    mysqli_stmt_close($trait_insert);
                    throw new Exception('Question answers could not be saved.');
                }
            }
            mysqli_stmt_close($trait_insert);

            mysqli_commit($conn);
            header('Location: ../dashboard.php');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = sm_text('We could not save your profile questions and partner preferences. Please try again.', 'আপনার প্রোফাইলের প্রশ্ন ও জীবনসঙ্গী-সংক্রান্ত পছন্দ সংরক্ষণ করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।');
        }
    }
}

// Convert saved centimetre preferences to feet/inches for display.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach ([['min_height_cm', 'min_height_feet', 'min_height_inches'], ['max_height_cm', 'max_height_feet', 'max_height_inches']] as $heightSet) {
        $cm = (int)($form[$heightSet[0]] ?? 0);
        if ($cm > 0) {
            $totalInches = (int) round($cm / 2.54);
            $form[$heightSet[1]] = (string) floor($totalInches / 12);
            $form[$heightSet[2]] = (string) ($totalInches % 12);
        }
    }
}

$divisions = [];
$division_result = mysqli_query($conn, 'SELECT id, name_bn, name_en FROM divisions ORDER BY name_en');
if ($division_result) {
    while ($row = mysqli_fetch_assoc($division_result)) {
        $divisions[] = $row;
    }
}

$page_css = 'assets/css/profile-step6.css';

$currentStep = 6;
$completion = sm_get_profile_completion($conn, $user_id);
$progress = $completion['percentage'];
$steps = [
    1 => ['title' => sm_text('Personal', 'ব্যক্তিগত তথ্য'), 'icon' => 'bi-person', 'url' => 'step1.php'],
    2 => ['title' => sm_text('Family', 'পারিবারিক তথ্য'), 'icon' => 'bi-people', 'url' => 'step2.php'],
    3 => ['title' => sm_text('Lifestyle', 'জীবনযাপন'), 'icon' => 'bi-heart-pulse', 'url' => 'step3.php'],
    4 => ['title' => sm_text('Location', 'ঠিকানা'), 'icon' => 'bi-geo-alt', 'url' => 'step4.php'],
    5 => ['title' => sm_text('Privacy', 'গোপনীয়তা'), 'icon' => 'bi-shield-lock', 'url' => 'step5.php'],
    6 => ['title' => sm_text('PP & QnA', 'জীবনসঙ্গীর পছন্দ ও প্রশ্নোত্তর'), 'icon' => 'bi-patch-question', 'url' => 'step6.php']
];

include '../includes/header.php';
include '../includes/navbar.php';
?>

<main class="step6-page">
    <div class="step6-shell">
        <header class="step6-wizard-head">
            <div class="step6-heading-row">
                <div>
                    <span class="step6-kicker"><?= sm_text('PROFILE BUILDER', 'প্রোফাইল তৈরি') ?></span>
                    <h1><?= sm_text('Step 6', 'ধাপ ৬') ?></h1>
                    <p><?= sm_text('Partner Preferences &amp; Compatibility for partner matching', 'জীবনসঙ্গীর পছন্দ ও সামঞ্জস্য') ?></p>
                </div>
                <a class="step6-logo" href="../dashboard.php" aria-label="Back to dashboard">
                    <img src="<?= BASE_URL ?>assets/images/logo/logo.png" alt="Smart Matrimony">
                </a>
            </div>

            <nav class="step6-step-nav" aria-label="Profile steps">
                <?php foreach ($steps as $stepNo => $step): ?>
                    <a class="step6-step <?= $stepNo === $currentStep ? 'is-current' : '' ?> <?= !empty($completion['steps'][$stepNo]) ? 'is-completed' : '' ?>" href="<?= htmlspecialchars($step['url']) ?>">
                        <span class="step6-step-icon"><i class="bi <?= $step['icon'] ?>"></i></span>
                        <span class="step6-step-label"><?= htmlspecialchars($step['title']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="step6-progress-wrap">
                <div class="step6-progress-track"><div class="step6-progress-fill" style="width: <?= $progress ?>%"></div></div>
                <div class="step6-progress-meta"><span><?= sm_text('Step 6 of 6', 'ধাপ ৬ / ৬') ?></span><strong><?= $progress ?><?= sm_text('% complete', '% সম্পন্ন') ?></strong></div>
            </div>
        </header>

        <?php if ($error !== ''): ?>
            <div class="step6-alert step6-alert-danger"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="step6-form" autocomplete="off" data-user-gender="<?= htmlspecialchars($user_row['gender'] ?? '') ?>">
            <section class="step6-section">
                <div class="step6-section-heading">
                    <span class="step6-section-icon"><i class="bi bi-heart"></i></span>
                    <div>
                        <h2><?= sm_text('Basic Partner Preferences', 'জীবনসঙ্গী সম্পর্কে প্রাথমিক পছন্দ') ?></h2>
                        <p><?= sm_text('Tell us the kind of partner you would generally like to meet.', 'আপনি কেমন জীবনসঙ্গী প্রত্যাশা করেন তা জানান।') ?></p>
                    </div>
                </div>

                <div class="step6-grid step6-grid-3">
                    <div class="step6-field">
                        <label for="preferred_gender"><?= sm_text('Expected Life Partner', 'প্রত্যাশিত জীবনসঙ্গী') ?> <em>*</em></label>
                        <select id="preferred_gender" name="preferred_gender_display" required disabled aria-disabled="true">
                            <option value="" disabled <?= $form['preferred_gender'] === '' ? 'selected' : '' ?>><?= sm_text('Select gender', 'লিঙ্গ নির্বাচন করুন') ?></option>
                            <option value="Male" <?= $form['preferred_gender'] === 'Male' ? 'selected' : '' ?>><?= sm_text('Male', 'পুরুষ') ?></option>
                            <option value="Female" <?= $form['preferred_gender'] === 'Female' ? 'selected' : '' ?>><?= sm_text('Female', 'নারী') ?></option>
                        </select>
                        <input type="hidden" name="preferred_gender" value="<?= htmlspecialchars($form['preferred_gender']) ?>">
                    </div>

                    <div class="step6-field">
                        <label for="min_age"><?= sm_text('Minimum Age', 'সর্বনিম্ন বয়স') ?> <em>*</em></label>
                        <input type="number" id="min_age" name="min_age" min="15" max="80" value="<?= htmlspecialchars((string)$form['min_age']) ?>" placeholder="e.g. 24" required>
                    </div>

                    <div class="step6-field">
                        <label for="max_age"><?= sm_text('Maximum Age', 'সর্বোচ্চ বয়স') ?> <em>*</em></label>
                        <input type="number" id="max_age" name="max_age" min="15" max="80" value="<?= htmlspecialchars((string)$form['max_age']) ?>" placeholder="e.g. 30" required>
                    </div>
                </div>

                <div class="step6-grid step6-grid-2">
                    <div class="step6-field">
                        <label for="family_status"><?= sm_text('Preferred Family Status', 'পছন্দের পারিবারিক অবস্থা') ?></label>
                        <select id="family_status" name="family_status">
                            <option value=""><?= sm_text('Any family status', 'যেকোনো পারিবারিক অবস্থা') ?></option>
                            <?php foreach ($family_statuses as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['family_status'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="step6-grid step6-grid-2">
                    <div class="step6-field">
                        <label><?= sm_text('Minimum Height', 'সর্বনিম্ন উচ্চতা') ?></label>
                        <div class="step6-height-grid">
                            <select id="min_height_feet" name="min_height_feet" aria-label="Minimum height feet">
                                <option value=""><?= sm_text('Select foot', 'ফুট নির্বাচন করুন') ?></option>
                                <?php for ($i = 4; $i <= 7; $i++): ?>
                                    <option value="<?= $i ?>" <?= (string)$form['min_height_feet'] === (string)$i ? 'selected' : '' ?>><?= $i ?> <?= sm_text('foot', 'ফুট') ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id="min_height_inches" name="min_height_inches" aria-label="Minimum height inches">
                                <option value=""><?= sm_text('Select inch', 'ইঞ্চি নির্বাচন করুন') ?></option>
                                <?php for ($i = 0; $i <= 11; $i++): ?>
                                    <option value="<?= $i ?>" <?= (string)$form['min_height_inches'] === (string)$i ? 'selected' : '' ?>><?= $i ?> <?= sm_text('inch', 'ইঞ্চি') ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="step6-field">
                        <label><?= sm_text('Maximum Height', 'সর্বোচ্চ উচ্চতা') ?></label>
                        <div class="step6-height-grid">
                            <select id="max_height_feet" name="max_height_feet" aria-label="Maximum height feet">
                                <option value=""><?= sm_text('Select foot', 'ফুট নির্বাচন করুন') ?></option>
                                <?php for ($i = 4; $i <= 7; $i++): ?>
                                    <option value="<?= $i ?>" <?= (string)$form['max_height_feet'] === (string)$i ? 'selected' : '' ?>><?= $i ?> <?= sm_text('foot', 'ফুট') ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id="max_height_inches" name="max_height_inches" aria-label="Maximum height inches">
                                <option value=""><?= sm_text('Select inch', 'ইঞ্চি নির্বাচন করুন') ?></option>
                                <?php for ($i = 0; $i <= 11; $i++): ?>
                                    <option value="<?= $i ?>" <?= (string)$form['max_height_inches'] === (string)$i ? 'selected' : '' ?>><?= $i ?> <?= sm_text('inch', 'ইঞ্চি') ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="step6-grid step6-grid-3">
                    <div class="step6-field">
                        <label for="complexion"><?= sm_text('Skin Colour', 'গায়ের রং') ?></label>
                        <select id="complexion" name="complexion">
                            <option value=""><?= sm_text('Select skin colour', 'গায়ের রং নির্বাচন করুন') ?></option>
                            <?php foreach ($complexions as $complexion_option): ?>
                                <option value="<?= htmlspecialchars($complexion_option) ?>" <?= $form['complexion'] === $complexion_option ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($complexion_option)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step6-field">
                        <label for="min_weight_kg"><?= sm_text('Minimum Weight', 'সর্বনিম্ন ওজন') ?></label>
                        <input type="number" id="min_weight_kg" name="min_weight_kg" min="1" max="500" step="1" value="<?= htmlspecialchars((string)$form['min_weight_kg']) ?>" placeholder="e.g. 45">
                    </div>

                    <div class="step6-field">
                        <label for="max_weight_kg"><?= sm_text('Maximum Weight', 'সর্বোচ্চ ওজন') ?></label>
                        <input type="number" id="max_weight_kg" name="max_weight_kg" min="1" max="500" step="1" value="<?= htmlspecialchars((string)$form['max_weight_kg']) ?>" placeholder="e.g. 70">
                    </div>
                </div>
            </section>

            <section class="step6-section">
                <div class="step6-section-heading">
                    <span class="step6-section-icon"><i class="bi bi-person-heart"></i></span>
                    <div>
                        <h2><?= sm_text('Religion &amp; Marital Preferences', 'ধর্ম ও বৈবাহিক পছন্দ') ?></h2>
                        <p><?= sm_text('Choose your preferred religion. This helps improve relevant recommendations and compatibility.', 'আপনার পছন্দের ধর্ম নির্বাচন করুন। এতে প্রাসঙ্গিক পরামর্শ ও সামঞ্জস্য নির্ধারণ সহজ হবে।') ?></p>
                    </div>
                </div>

                <div class="step6-grid step6-grid-3">
                    <div class="step6-field">
                        <label for="religion"><?= sm_text('Religion', 'ধর্ম') ?> <em>*</em></label>
                        <select id="religion" name="religion" required>
                            <option value=""><?= sm_text('Select religion', 'ধর্ম নির্বাচন করুন') ?></option>
                            <?php foreach ($religions as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['religion'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="marital_status"><?= sm_text('Marital Status', 'বৈবাহিক অবস্থা') ?></label>
                        <select id="marital_status" name="marital_status">
                            <option value=""><?= sm_text('Any marital status', 'যেকোনো বৈবাহিক অবস্থা') ?></option>
                            <?php foreach ($marital_statuses as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['marital_status'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field step6-islamic-preference">
                        <label for="madhhab"><?= sm_text('Madhhab', 'মাজহাব') ?></label>
                        <select id="madhhab" name="madhhab">
                            <option value=""><?= sm_text('Any madhhab', 'যেকোনো মাযহাব') ?></option>
                            <?php foreach ($madhhabs as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['madhhab'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field step6-islamic-preference">
                        <label for="prayer_status"><?= sm_text('Prayer Status', 'নামাজের অভ্যাস') ?></label>
                        <select id="prayer_status" name="prayer_status">
                            <option value=""><?= sm_text('Prayer status', 'নামাজের অভ্যাস নির্বাচন করুন') ?></option>
                            <?php foreach ($prayer_statuses as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['prayer_status'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field step6-islamic-preference">
                        <label for="halal_lifestyle"><?= sm_text('Halal Lifestyle', 'হালাল জীবনযাপন') ?></label>
                        <select id="halal_lifestyle" name="halal_lifestyle">
                            <option value=""><?= sm_text('Any halal lifestyle', 'যেকোনো হালাল জীবনযাপন') ?></option>
                            <?php foreach ($halal_lifestyle_options as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['halal_lifestyle'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field step6-islamic-preference">
                        <label for="mahram_maintained"><?= sm_text('Mahram Maintained', 'মাহরাম মেনে চলেন কি না') ?></label>
                        <select id="mahram_maintained" name="mahram_maintained">
                            <option value=""><?= sm_text('Any', 'যেকোনোটি') ?></option>
                            <option value="1" <?= $form['mahram_maintained'] === '1' ? 'selected' : '' ?>><?= sm_text('Yes', 'হ্যাঁ') ?></option>
                            <option value="0" <?= $form['mahram_maintained'] === '0' ? 'selected' : '' ?>><?= sm_text('No', 'না') ?></option>
                        </select>
                    </div>
                    <div class="step6-field step6-islamic-preference">
                        <label for="islamic_knowledge"><?= sm_text('Islamic Knowledge', 'ইসলামি জ্ঞান') ?></label>
                        <select id="islamic_knowledge" name="islamic_knowledge">
                            <option value=""><?= sm_text('Any level', 'যেকোনো স্তর') ?></option>
                            <?php foreach ($islamic_knowledge_levels as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['islamic_knowledge'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field step6-islamic-preference step6-gender-preference" data-preferred-gender="Female">
                        <label for="hijab_status"><?= sm_text('Hijab Status', 'হিজাবের অবস্থা') ?></label>
                        <select id="hijab_status" name="hijab_status">
                            <option value=""><?= sm_text('Any hijab status', 'যেকোনো হিজাবের অবস্থা') ?></option>
                            <?php foreach ($hijab_statuses as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['hijab_status'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field step6-islamic-preference step6-gender-preference" data-preferred-gender="Male">
                        <label for="beard_status"><?= sm_text('Beard Status', 'দাড়ির অবস্থা') ?></label>
                        <select id="beard_status" name="beard_status">
                            <option value=""><?= sm_text('Any beard status', 'যেকোনো দাড়ির অবস্থা') ?></option>
                            <?php foreach ($beard_statuses as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['beard_status'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="personality_type"><?= sm_text('Personality Type', 'ব্যক্তিত্বের ধরন') ?></label>
                        <select id="personality_type" name="personality_type">
                            <option value=""><?= sm_text('Any personality type', 'যেকোনো ব্যক্তিত্বের ধরন') ?></option>
                            <?php foreach ($personality_types as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['personality_type'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                </div>
            </section>

            <section class="step6-section">
                <div class="step6-section-heading">
                    <span class="step6-section-icon"><i class="bi bi-mortarboard"></i></span>
                    <div>
                        <h2><?= sm_text('Education &amp; Career Preferences', 'শিক্ষা ও পেশাগত পছন্দ') ?></h2>
                        <p><?= sm_text('Choose the education and career qualities that matter to you.', 'শিক্ষা ও পেশা সম্পর্কে আপনার পছন্দগুলো নির্বাচন করুন।') ?></p>
                    </div>
                </div>

                <div class="step6-grid step6-grid-2">
                    <div class="step6-field">
                        <label for="education"><?= sm_text('Preferred Education', 'পছন্দের শিক্ষাগত যোগ্যতা') ?></label>
                        <select id="education" name="education">
                            <option value=""><?= sm_text('Any education level', 'যেকোনো শিক্ষাগত যোগ্যতা') ?></option>
                            <?php foreach ($education_levels as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['education'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="profession"><?= sm_text('Preferred Profession', 'পছন্দের পেশা') ?></label>
                        <select id="profession" name="profession">
                            <option value=""><?= sm_text('Any profession', 'যেকোনো পেশা') ?></option>
                            <?php foreach ($user_professions as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $form['profession'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="step6-income-row">
                    <div class="step6-field">
                        <label for="min_monthly_income"><?= sm_text('Minimum Monthly Income (BDT)', 'সর্বনিম্ন মাসিক আয় (টাকা)') ?></label>
                        <div class="step6-income-control"><span>৳</span><input type="number" id="min_monthly_income" name="min_monthly_income" min="0" max="99999999" step="1" value="<?= htmlspecialchars((string)$form['min_monthly_income']) ?>" placeholder="<?= sm_text('No minimum', 'সর্বনিম্ন সীমা নেই') ?>"></div>
                        <small><?= sm_text('Leave empty if income is not an important criterion.', 'আয় গুরুত্বপূর্ণ শর্ত না হলে ঘরটি খালি রাখুন।') ?></small>
                    </div>
                </div>
            </section>

            <section class="step6-section">
                <div class="step6-section-heading">
                    <span class="step6-section-icon"><i class="bi bi-geo-alt"></i></span>
                    <div>
                        <h2><?= sm_text('Preferred Location', 'পছন্দের এলাকা') ?></h2>
                        <p><?= sm_text('Choose where you would generally prefer your future partner to be from.', 'আপনার জীবনসঙ্গী সাধারণত কোন এলাকার হলে ভালো হয়, তা নির্বাচন করুন।') ?></p>
                    </div>
                </div>

                <div class="step6-grid step6-grid-3">
                    <div class="step6-field">
                        <label for="division_id"><?= sm_text('Preferred Division', 'পছন্দের বিভাগ') ?></label>
                        <select id="division_id" name="division_id" data-selected="<?= htmlspecialchars((string)$form['division_id']) ?>">
                            <option value=""><?= sm_text('Any division', 'যেকোনো বিভাগ') ?></option>
                            <?php foreach ($divisions as $division): ?>
                                <option value="<?= (int)$division['id'] ?>" <?= (string)$form['division_id'] === (string)$division['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($GLOBALS['current_language'] ?? 'bn') === 'en' ? $division['name_en'] : $division['name_bn']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="district_id"><?= sm_text('Preferred District', 'পছন্দের জেলা') ?></label>
                        <select id="district_id" name="district_id" data-selected="<?= htmlspecialchars((string)$form['district_id']) ?>" <?= $form['division_id'] === '' ? 'disabled' : '' ?>>
                            <option value=""><?= sm_text('Any district', 'যেকোনো জেলা') ?></option>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="upazila_id"><?= sm_text('Preferred Upazila', 'পছন্দের উপজেলা') ?></label>
                        <select id="upazila_id" name="upazila_id" data-selected="<?= htmlspecialchars((string)$form['upazila_id']) ?>" <?= $form['district_id'] === '' ? 'disabled' : '' ?>>
                            <option value=""><?= sm_text('Any upazila', 'যেকোনো উপজেলা') ?></option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="step6-section">
                <div class="step6-section-heading">
                    <span class="step6-section-icon"><i class="bi bi-heart-pulse"></i></span>
                    <div>
                        <h2><?= sm_text('Lifestyle &amp; Health Preferences', 'জীবনযাপন ও স্বাস্থ্যসংক্রান্ত পছন্দ') ?></h2>
                        <p><?= sm_text('Optional preferences that can help make future recommendations more relevant.', 'ভবিষ্যতে আরও প্রাসঙ্গিক প্রস্তাব পেতে চাইলে এই ঐচ্ছিক পছন্দগুলো দিন।') ?></p>
                    </div>
                </div>

                <div class="step6-grid step6-grid-2">
                    <div class="step6-field">
                        <label for="blood_group"><?= sm_text('Preferred Blood Group', 'পছন্দের রক্তের গ্রুপ') ?></label>
                        <select id="blood_group" name="blood_group">
                            <option value=""><?= sm_text('Any blood group', 'যেকোনো রক্তের গ্রুপ') ?></option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= $form['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="accept_smoker"><?= sm_text('Smoking Preference', 'ধূমপান সম্পর্কে পছন্দ') ?></label>
                        <select id="accept_smoker" name="accept_smoker">
                            <option value="1" <?= $form['accept_smoker'] === '1' ? 'selected' : '' ?>><?= sm_text('No preference', 'বিশেষ পছন্দ নেই') ?></option>
                            <option value="0" <?= $form['accept_smoker'] === '0' ? 'selected' : '' ?>><?= sm_text('Prefer non-smoker', 'ধূমপান করেন না এমন') ?></option>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="accept_alcohol"><?= sm_text('Alcohol Preference', 'মদ্যপান সম্পর্কে পছন্দ') ?></label>
                        <select id="accept_alcohol" name="accept_alcohol">
                            <option value="1" <?= $form['accept_alcohol'] === '1' ? 'selected' : '' ?>><?= sm_text('No preference', 'বিশেষ পছন্দ নেই') ?></option>
                            <option value="0" <?= $form['accept_alcohol'] === '0' ? 'selected' : '' ?>><?= sm_text('Prefer non-drinker', 'মদ্যপান করেন না এমন') ?></option>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="accept_disability"><?= sm_text('Disability Preference', 'প্রতিবন্ধকতা সম্পর্কে পছন্দ') ?></label>
                        <select id="accept_disability" name="accept_disability">
                            <option value="1" <?= $form['accept_disability'] === '1' ? 'selected' : '' ?>><?= sm_text('No preference', 'বিশেষ পছন্দ নেই') ?></option>
                            <option value="0" <?= $form['accept_disability'] === '0' ? 'selected' : '' ?>><?= sm_text('Prefer without disability', 'প্রতিবন্ধকতা নেই এমন') ?></option>
                        </select>
                    </div>
                    <div class="step6-field">
                        <label for="accept_chronic_disease"><?= sm_text('Chronic Disease Preference', 'দীর্ঘমেয়াদি রোগ সম্পর্কে পছন্দ') ?></label>
                        <select id="accept_chronic_disease" name="accept_chronic_disease">
                            <option value="1" <?= $form['accept_chronic_disease'] === '1' ? 'selected' : '' ?>><?= sm_text('No preference', 'বিশেষ পছন্দ নেই') ?></option>
                            <option value="0" <?= $form['accept_chronic_disease'] === '0' ? 'selected' : '' ?>><?= sm_text('Prefer without chronic disease', 'দীর্ঘমেয়াদি রোগ নেই এমন') ?></option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="step6-section step6-questions-section">
                <div class="step6-section-heading">
                    <span class="step6-section-icon"><i class="bi bi-patch-question"></i></span>
                    <div>
                        <h2><?= sm_text('Questions (For You)', 'আপনার সম্পর্কে কিছু প্রশ্ন') ?></h2>
                        <p><?= sm_text('Answer these questions about yourself. These answers can help other members understand you better.', 'নিজের সম্পর্কে প্রশ্নগুলোর উত্তর দিন। এতে অন্য সদস্যরা আপনাকে আরও ভালোভাবে জানতে পারবেন।') ?></p>
                    </div>
                </div>

                <?php if ($trait_questions): ?>
                    <div class="step6-questions-grid">
                        <?php foreach ($trait_questions as $question): ?>
                            <?php
                            $question_id = (int)$question['question_id'];
                            $question_gender = $question['gender'] ?? 'Both';
                            $answer_type = $question['answer_type'] ?? 'Text';
                            $saved_answer = $trait_answer_map[$question_id] ?? '';
                            $options = array_values(array_filter(array_map('trim', explode(',', (string)($question['options'] ?? ''))), static fn($option) => $option !== ''));
                            ?>
                            <div class="step6-question-card" data-question-gender="<?= htmlspecialchars($question_gender) ?>">
                                <div class="step6-question-title-row">
                                    <label for="trait_answer_<?= $question_id ?>"><?= htmlspecialchars($question['question_text']) ?> <em>*</em></label>
                                    <span class="step6-question-type"><?= htmlspecialchars($answer_type) ?></span>
                                </div>

                                <?php if ($answer_type === 'Yes/No'): ?>
                                    <select id="trait_answer_<?= $question_id ?>" name="trait_answer[<?= $question_id ?>]" data-trait-question-input required>
                                        <option value="" disabled <?= $saved_answer === '' ? 'selected' : '' ?>><?= sm_text('Select an answer', 'উত্তর নির্বাচন করুন') ?></option>
                                        <option value="Yes" <?= $saved_answer === 'Yes' ? 'selected' : '' ?>><?= sm_text('Yes', 'হ্যাঁ') ?></option>
                                        <option value="No" <?= $saved_answer === 'No' ? 'selected' : '' ?>><?= sm_text('No', 'না') ?></option>
                                    </select>
                                <?php elseif ($answer_type === 'Dropdown' && $options): ?>
                                    <select id="trait_answer_<?= $question_id ?>" name="trait_answer[<?= $question_id ?>]" data-trait-question-input required>
                                        <option value="" disabled <?= $saved_answer === '' ? 'selected' : '' ?>><?= sm_text('Select an answer', 'উত্তর নির্বাচন করুন') ?></option>
                                        <?php foreach ($options as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>" <?= $saved_answer === $option ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" id="trait_answer_<?= $question_id ?>" name="trait_answer[<?= $question_id ?>]" value="<?= htmlspecialchars($saved_answer) ?>" maxlength="255" placeholder="<?= sm_text('Write your answer', 'আপনার উত্তর লিখুন') ?>" data-trait-question-input required>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="step6-empty-state"><i class="bi bi-info-circle"></i><p><?= sm_text('No partner questions are currently available.', 'এই মুহূর্তে কোনো প্রশ্ন পাওয়া যাচ্ছে না।') ?></p></div>
                <?php endif; ?>

                <div class="step6-field step6-additional-preferences">
                    <label for="additional_preferences"><?= sm_text('Any special conditions or preferences?', 'বিশেষ কোনো শর্ত বা চাওয়া আছে কি?') ?></label>
                    <textarea id="additional_preferences" name="additional_preferences" maxlength="5000" placeholder="<?= sm_text('Any special conditions or preferences?', 'বিশেষ কোনো শর্ত বা চাওয়া আছে কি?') ?>" rows="5"><?= htmlspecialchars($form['additional_preferences']) ?></textarea>
                    <small><?= sm_text('Optional. You can mention important preferences that are not covered above.', 'ঐচ্ছিক। উপরের তালিকায় নেই—এমন গুরুত্বপূর্ণ পছন্দ এখানে লিখতে পারেন।') ?></small>
                </div>
                <div class="step6-field step6-additional-preferences">
                    <label for="partner_description"><?= sm_text('Detailed Description of Your Ideal Partner (Optional)', 'আপনার প্রত্যাশিত জীবনসঙ্গীর বিস্তারিত বর্ণনা (ঐচ্ছিক)') ?></label>
                    <textarea id="partner_description" name="partner_description" maxlength="5000" placeholder="<?= sm_text('Describe the kind of life partner you are looking for, including qualities, values, lifestyle, or other important details.', 'আপনি কেমন জীবনসঙ্গী চান—তাঁর গুণাবলি, মূল্যবোধ, জীবনযাপন ও অন্যান্য গুরুত্বপূর্ণ বিষয় লিখুন।') ?>" rows="5"><?= htmlspecialchars($form['partner_description']) ?></textarea>
                    <small><?= sm_text('Optional. Share any additional details about your ideal life partner.', 'ঐচ্ছিক। আপনার প্রত্যাশিত জীবনসঙ্গী সম্পর্কে আরও তথ্য লিখুন।') ?></small>
                </div>
                <div class="step6-actions">
                    <a href="view_profile.php?user_id=<?= (int)$user_id ?>" class="step6-btn step6-btn-secondary"><i class="bi bi-arrow-left-circle"></i><span><?= sm_text('Back to Profile', 'প্রোফাইলে ফিরে যান') ?></span></a>
                    <button type="submit" name="save_step6" class="step6-btn step6-btn-primary">
                        <?= sm_text('Save &amp; Continue', 'সংরক্ষণ করে এগিয়ে যান') ?><i class="bi bi-arrow-right-circle"></i></button>
                </div>
            </section>
        </form>
    </div>
</main>

<script src="<?= BASE_URL ?>assets/js/profile-step6-location.js"></script>
<script src="<?= BASE_URL ?>assets/js/profile-step6-preferences.js"></script>
<?php include '../includes/footer.php'; ?>
