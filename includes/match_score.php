<?php
/**
 * Mutual profile compatibility scoring.
 * User-configured weights are read from user_match_weights by callers.
 * No database writes are performed here.
 */

function sm_normalize_match_value($value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function sm_default_match_weights(): array
{
    return [
        'age_weight' => 8.0,
        'height_weight' => 5.0,
        'religion_weight' => 10.0,
        'islamic_practice_weight' => 10.0,
        'marital_status_weight' => 10.0,
        'education_weight' => 10.0,
        'profession_weight' => 10.0,
        'location_weight' => 10.0,
        'lifestyle_weight' => 8.0,
        'qna_weight' => 5.0,
        'skin_colour_weight' => 10.0,
        'weight_weight' => 4.0,
        'family_status_weight' => 0.0,
    ];
}

function sm_sanitize_match_weights(?array $weights): array
{
    $defaults = sm_default_match_weights();
    $out = [];
    foreach ($defaults as $key => $default) {
        $value = $weights[$key] ?? $default;
        $out[$key] = is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : $default;
    }
    return $out;
}

function sm_match_date_age(?string $dob): ?int
{
    if (!$dob) return null;
    try {
        $birth = new DateTime($dob);
        $today = new DateTime('today');
        return (int) $birth->diff($today)->y;
    } catch (Throwable $e) {
        return null;
    }
}

function sm_match_exact_score($preferred, $actual): ?float
{
    $preferred = sm_normalize_match_value($preferred);
    $actual = sm_normalize_match_value($actual);
    if ($preferred === '' || $actual === '') return null;
    if ($preferred === $actual) return 100.0;
    if (mb_strlen($preferred, 'UTF-8') >= 4 && mb_strlen($actual, 'UTF-8') >= 4) {
        if (mb_stripos($actual, $preferred, 0, 'UTF-8') !== false || mb_stripos($preferred, $actual, 0, 'UTF-8') !== false) {
            return 60.0;
        }
    }
    return 0.0;
}

function sm_match_range_score($value, $min, $max): ?float
{
    if ($value === null || $value === '') return null;
    $value = (float) $value;
    $has_min = $min !== null && $min !== '';
    $has_max = $max !== null && $max !== '';
    if (!$has_min && !$has_max) return null;
    $min = $has_min ? (float) $min : null;
    $max = $has_max ? (float) $max : null;
    if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
        $distance = $value < ($min ?? $value) ? (($min - $value) / max(1.0, abs($min))) : (($value - $max) / max(1.0, abs($max)));
        return max(0.0, min(80.0, 100.0 - ($distance * 100.0)));
    }
    return 100.0;
}

function sm_match_location_score(array $pref, array $profile): ?float
{
    $fields = [
        ['upazila_id', 100.0],
        ['district_id', 80.0],
        ['division_id', 60.0],
    ];
    $specified = false;
    foreach ($fields as [$field, $score]) {
        $pref_value = (int) ($pref[$field] ?? 0);
        if ($pref_value > 0) {
            $specified = true;
            $actual = (int) ($profile[$field] ?? 0);
            if ($actual <= 0) return 0.0;
            return $actual === $pref_value ? $score : 0.0;
        }
    }
    return $specified ? 0.0 : null;
}

function sm_match_category(array $scores): ?float
{
    $scores = array_values(array_filter($scores, static fn($v) => $v !== null));
    if (!$scores) return null;
    return array_sum($scores) / count($scores);
}

function sm_fmt_number($value, string $suffix = ''): string
{
    if ($value === null || $value === '') return 'Not provided';
    $number = (float) $value;
    $text = rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    return $text . $suffix;
}

function sm_fmt_range($min, $max, string $suffix = ''): string
{
    $has_min = $min !== null && $min !== '';
    $has_max = $max !== null && $max !== '';
    if (!$has_min && !$has_max) return 'No preference';
    if ($has_min && $has_max) return sm_fmt_number($min, $suffix) . ' – ' . sm_fmt_number($max, $suffix);
    return $has_min ? 'From ' . sm_fmt_number($min, $suffix) : 'Up to ' . sm_fmt_number($max, $suffix);
}

function sm_fmt_age($age): string { return $age === null ? 'Not provided' : ((int) $age) . ' years'; }

function sm_fmt_location(array $data, bool $preference = false): string
{
    if ($preference) {
        $up = trim((string) ($data['upazila_name'] ?? ''));
        $district = trim((string) ($data['district_name'] ?? ''));
        $division = trim((string) ($data['division_name'] ?? ''));
        if ($up !== '') return $up . ($district !== '' ? ', ' . $district : '');
        if ($district !== '') return $district . ($division !== '' ? ', ' . $division : '');
        if ($division !== '') return $division;
        return 'No preference';
    }
    $up = trim((string) ($data['upazila_name'] ?? $data['upazila'] ?? ''));
    $district = trim((string) ($data['district_name'] ?? $data['district'] ?? ''));
    $division = trim((string) ($data['division_name'] ?? $data['division'] ?? ''));
    if ($up !== '') return $up . ($district !== '' ? ', ' . $district : '');
    if ($district !== '') return $district . ($division !== '' ? ', ' . $division : '');
    if ($division !== '') return $division;
    return 'Not provided';
}

function sm_build_factor_details(array $preferences, array $profile, array $trait_preferences, array $trait_answers, array $weights, string $direction): array
{
    $details = [];
    $add = static function (&$details, $key, $label, $weight, $score, $icon, $want, $have, $subScores = []) {
        $details[$key] = [
            'label' => $label, 'weight' => (float) $weight, 'score' => $score, 'icon' => $icon,
            'want' => $want, 'have' => $have, 'sub_scores' => $subScores,
        ];
    };

    $age = sm_match_date_age($profile['date_of_birth'] ?? null);
    $add($details, 'age', 'Age', $weights['age_weight'], sm_match_range_score($age, $preferences['min_age'] ?? null, $preferences['max_age'] ?? null), 'fa-cake-candles', sm_fmt_range($preferences['min_age'] ?? null, $preferences['max_age'] ?? null, ' years'), sm_fmt_age($age));

    $height_score = sm_match_range_score($profile['height_cm'] ?? null, $preferences['min_height_cm'] ?? null, $preferences['max_height_cm'] ?? null);
    $add($details, 'height', 'Height', $weights['height_weight'], $height_score, 'fa-ruler-vertical', sm_fmt_range($preferences['min_height_cm'] ?? null, $preferences['max_height_cm'] ?? null, ' cm'), sm_fmt_number($profile['height_cm'] ?? null, ' cm'));

    $add($details, 'religion', 'Religion', $weights['religion_weight'], sm_match_exact_score($preferences['religion'] ?? null, $profile['religion'] ?? null), 'fa-mosque', $preferences['religion'] ?? null ?: 'No preference', $profile['religion'] ?? null ?: 'Not provided');

    $islamic_scores = [];
    $islamic_want = [];
    $islamic_have = [];
    foreach ([
        'madhhab' => 'madhhab', 'prayer_status' => 'prayer_status', 'halal_lifestyle' => 'halal_lifestyle',
        'mahram_maintained' => 'mahram_maintained', 'islamic_knowledge' => 'islamic_knowledge',
        'hijab_status' => 'hijab_status', 'beard_status' => 'beard_status'
    ] as $pref_field => $profile_field) {
        if (!array_key_exists($pref_field, $preferences) || $preferences[$pref_field] === null || $preferences[$pref_field] === '') continue;
        $want = $preferences[$pref_field];
        $have = $profile[$profile_field] ?? null;
        if ($pref_field === 'mahram_maintained') {
            if ($have === null || $have === '') continue;
            $score = ((int) $want === (int) $have) ? 100.0 : 0.0;
            $want = ((int) $want === 1) ? 'Yes' : 'No'; $have = ((int) $have === 1) ? 'Yes' : 'No';
        } else {
            $score = sm_match_exact_score($want, $have);
        }
        if ($score !== null) { $islamic_scores[] = $score; $islamic_want[] = $pref_field . ': ' . $want; $islamic_have[] = $profile_field . ': ' . ($have === null || $have === '' ? 'Not provided' : $have); }
    }
    $add($details, 'islamic_practice', 'Islamic Practice', $weights['islamic_practice_weight'], sm_match_category($islamic_scores), 'fa-star-and-crescent', $islamic_want ? implode(' · ', $islamic_want) : 'No preference', $islamic_have ? implode(' · ', $islamic_have) : 'Not provided');

    $add($details, 'marital_status', 'Marital Status', $weights['marital_status_weight'], sm_match_exact_score($preferences['marital_status'] ?? null, $profile['marital_status'] ?? null), 'fa-ring', $preferences['marital_status'] ?? null ?: 'No preference', $profile['marital_status'] ?? null ?: 'Not provided');
    $add($details, 'education', 'Education', $weights['education_weight'], sm_match_exact_score($preferences['education'] ?? null, $profile['highest_education'] ?? null), 'fa-graduation-cap', $preferences['education'] ?? null ?: 'No preference', $profile['highest_education'] ?? null ?: 'Not provided');
    $add($details, 'profession', 'Profession', $weights['profession_weight'], sm_match_exact_score($preferences['profession'] ?? null, $profile['profession'] ?? null), 'fa-briefcase', $preferences['profession'] ?? null ?: 'No preference', $profile['profession'] ?? null ?: 'Not provided');

    $location_want = sm_fmt_location($preferences, true);
    $location_have = sm_fmt_location($profile, false);
    $add($details, 'location', 'Location', $weights['location_weight'], sm_match_location_score($preferences, $profile), 'fa-location-dot', $location_want, $location_have);

    $lifestyle_scores = [];
    $lifestyle_want = []; $lifestyle_have = [];
    $personality_score = sm_match_exact_score($preferences['personality_type'] ?? null, $profile['personality_type'] ?? null);
    if ($personality_score !== null) { $lifestyle_scores[] = $personality_score; $lifestyle_want[] = 'Personality: ' . $preferences['personality_type']; $lifestyle_have[] = 'Personality: ' . ($profile['personality_type'] ?? 'Not provided'); }
    if (array_key_exists('accept_smoker', $preferences) && $preferences['accept_smoker'] !== null && $preferences['accept_smoker'] !== '') {
        $smoker = sm_normalize_match_value($profile['smoking_status'] ?? '');
        if ($smoker !== '') {
            $accept = (int) $preferences['accept_smoker'] === 1;
            $lifestyle_scores[] = $accept || in_array($smoker, ['never', 'quit'], true) ? 100.0 : 0.0;
            $lifestyle_want[] = 'Smoking: ' . ($accept ? 'No preference' : 'Prefer non-smoker'); $lifestyle_have[] = 'Smoking: ' . ($profile['smoking_status'] ?? 'Not provided');
        }
    }
    $add($details, 'lifestyle', 'Lifestyle', $weights['lifestyle_weight'], sm_match_category($lifestyle_scores), 'fa-person-running', $lifestyle_want ? implode(' · ', $lifestyle_want) : 'No preference', $lifestyle_have ? implode(' · ', $lifestyle_have) : 'Not provided');

    $trait_scores = []; foreach ($trait_preferences as $question_id => $preferred_answer) { $actual = $trait_answers[$question_id] ?? null; if ($actual === null || trim((string)$actual)==='') continue; $trait_scores[] = sm_match_exact_score($preferred_answer, $actual); }
    $add($details, 'qna', 'Q&A', $weights['qna_weight'], sm_match_category($trait_scores), 'fa-circle-question', $trait_preferences ? count($trait_preferences) . ' saved answers' : 'No preference', $trait_answers ? count($trait_answers) . ' answered' : 'Not provided');

    $preferred_complexion = sm_normalize_match_value($preferences['complexion'] ?? ''); $actual_complexion = sm_normalize_match_value($profile['complexion'] ?? '');
    $skin_score = ($preferred_complexion !== '' && $actual_complexion !== '') ? (($preferred_complexion === $actual_complexion) ? 100.0 : 0.0) : null;
    $add($details, 'skin_colour', 'Skin Colour', $weights['skin_colour_weight'], $skin_score, 'fa-palette', $preferences['complexion'] ?? null ?: 'No preference', $profile['complexion'] ?? null ?: 'Not provided');

    $weight_score = sm_match_range_score($profile['weight_kg'] ?? null, $preferences['min_weight_kg'] ?? null, $preferences['max_weight_kg'] ?? null);
    $add($details, 'weight', 'Weight', $weights['weight_weight'], $weight_score, 'fa-weight-scale', sm_fmt_range($preferences['min_weight_kg'] ?? null, $preferences['max_weight_kg'] ?? null, ' kg'), sm_fmt_number($profile['weight_kg'] ?? null, ' kg'));

    $family_score = sm_match_exact_score($preferences['family_status'] ?? null, $profile['family_status'] ?? null);
    $add($details, 'family_status', 'Family Status', $weights['family_status_weight'], $family_score, 'fa-house-user', $preferences['family_status'] ?? null ?: 'No preference', $profile['family_status'] ?? null ?: 'Not provided');

    return $details;
}

function sm_directional_match_breakdown(array $preferences, array $profile, array $trait_preferences, array $trait_answers, ?array $weights = null): ?array
{
    if (!$preferences) return null;
    $weights = sm_sanitize_match_weights($weights);
    $details = sm_build_factor_details($preferences, $profile, $trait_preferences, $trait_answers, $weights, 'forward');
    $weighted = 0.0; $used_weight = 0.0;
    foreach ($details as &$detail) {
        if ($detail['score'] !== null && $detail['weight'] > 0) {
            $weighted += $detail['score'] * ($detail['weight'] / 100.0);
            $used_weight += $detail['weight'] / 100.0;
        }
    }
    unset($detail);
    if ($used_weight <= 0) return null;
    $score = max(0.0, min(100.0, $weighted / $used_weight));
    foreach ($details as &$detail) {
        $detail['contribution'] = ($detail['score'] !== null && $detail['weight'] > 0) ? ($detail['score'] * $detail['weight'] / 100.0) : 0.0;
        $detail['normalized_contribution'] = ($detail['score'] !== null && $detail['weight'] > 0) ? (($detail['score'] * $detail['weight'] / 100.0) / $used_weight) : 0.0;
    }
    unset($detail);
    return ['score' => $score, 'details' => $details, 'used_weight' => $used_weight * 100.0];
}

function sm_calculate_mutual_match_breakdown(array $viewer_preferences, array $viewer_profile, array $candidate_preferences, array $candidate_profile, array $viewer_trait_preferences = [], array $viewer_trait_answers = [], array $candidate_trait_preferences = [], array $candidate_trait_answers = [], ?array $viewer_weights = null, ?array $candidate_weights = null): ?array
{
    $forward = sm_directional_match_breakdown($viewer_preferences, $candidate_profile, $viewer_trait_preferences, $candidate_trait_answers, $viewer_weights);
    $reverse = sm_directional_match_breakdown($candidate_preferences, $viewer_profile, $candidate_trait_preferences, $viewer_trait_answers, $candidate_weights);
    if ($forward === null && $reverse === null) return null;
    $categories = [];
    $keys = array_unique(array_merge(array_keys($forward['details'] ?? []), array_keys($reverse['details'] ?? [])));
    foreach ($keys as $key) {
        $f = $forward['details'][$key] ?? null; $r = $reverse['details'][$key] ?? null;
        $score = null;
        if (($f['score'] ?? null) !== null && ($r['score'] ?? null) !== null) $score = ($f['score'] + $r['score']) / 2.0;
        elseif (($f['score'] ?? null) !== null) $score = $f['score']; elseif (($r['score'] ?? null) !== null) $score = $r['score'];
        $categories[$key] = [
            'label' => $f['label'] ?? $r['label'] ?? $key, 'icon' => $f['icon'] ?? $r['icon'] ?? 'fa-circle',
            'forward' => $f['score'] ?? null, 'reverse' => $r['score'] ?? null, 'score' => $score,
            'forward_weight' => $f['weight'] ?? 0, 'reverse_weight' => $r['weight'] ?? 0,
            'forward_contribution' => $f['normalized_contribution'] ?? 0, 'reverse_contribution' => $r['normalized_contribution'] ?? 0,
            'want' => $f['want'] ?? 'Not available', 'have' => $f['have'] ?? 'Not available',
            'their_want' => $r['want'] ?? 'Not available', 'your_value' => $r['have'] ?? 'Not available',
        ];
    }
    $forward_score = $forward['score'] ?? null; $reverse_score = $reverse['score'] ?? null;
    $final = $forward_score === null ? $reverse_score : ($reverse_score === null ? $forward_score : (($forward_score + $reverse_score) / 2.0));
    return [
        'score' => $final === null ? null : max(0.0, min(100.0, $final)),
        'forward_score' => $forward_score, 'reverse_score' => $reverse_score,
        'categories' => $categories,
    ];
}
