<?php
/**
 * Mutual profile compatibility scoring.
 * Presentation consumers should call sm_calculate_mutual_match_score().
 * No database writes are performed here.
 */

function sm_normalize_match_value($value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
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

    // Helpful for values such as education/profession labels that may be
    // stored with a small wording variation, without treating empty data as a mismatch.
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
            if ($actual === $pref_value) return $score;
            // A more specific preference cannot be satisfied by a different location.
            return 0.0;
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

function sm_directional_match_score(array $preferences, array $profile, array $trait_preferences, array $trait_answers): ?float
{
    if (!$preferences) return null;

    $weighted = 0.0;
    $used_weight = 0.0;

    // Age — 8%
    $age_score = sm_match_range_score(
        sm_match_date_age($profile['date_of_birth'] ?? null),
        $preferences['min_age'] ?? null,
        $preferences['max_age'] ?? null
    );
    if ($age_score !== null) { $weighted += $age_score * 0.08; $used_weight += 0.08; }

    // Height — 5%
    $height_score = sm_match_range_score($profile['height_cm'] ?? null, $preferences['min_height_cm'] ?? null, $preferences['max_height_cm'] ?? null);
    if ($height_score !== null) { $weighted += $height_score * 0.05; $used_weight += 0.05; }

    // Skin Colour / Complexion — 10%
    // Complexion is a categorical preference: an exact match satisfies it;
    // a different complexion does not. Missing preference/profile data is ignored.
    $skin_colour_score = null;
    $preferred_complexion = sm_normalize_match_value($preferences['complexion'] ?? '');
    $actual_complexion = sm_normalize_match_value($profile['complexion'] ?? '');
    if ($preferred_complexion !== '' && $actual_complexion !== '') {
        $skin_colour_score = ($preferred_complexion === $actual_complexion) ? 100.0 : 0.0;
    }
    if ($skin_colour_score !== null) { $weighted += $skin_colour_score * 0.10; $used_weight += 0.10; }

    // Weight — 4%
    $weight_score = sm_match_range_score(
        $profile['weight_kg'] ?? null,
        $preferences['min_weight_kg'] ?? null,
        $preferences['max_weight_kg'] ?? null
    );
    if ($weight_score !== null) { $weighted += $weight_score * 0.04; $used_weight += 0.04; }

    // Religion — 10%
    $religion_score = sm_match_exact_score($preferences['religion'] ?? null, $profile['religion'] ?? null);
    if ($religion_score !== null) { $weighted += $religion_score * 0.10; $used_weight += 0.10; }

    // Islamic Practice — 10%
    $islamic_scores = [];
    foreach ([
        'madhhab' => 'madhhab',
        'prayer_status' => 'prayer_status',
        'halal_lifestyle' => 'halal_lifestyle',
        'mahram_maintained' => 'mahram_maintained',
        'islamic_knowledge' => 'islamic_knowledge',
        'hijab_status' => 'hijab_status',
        'beard_status' => 'beard_status',
    ] as $pref_field => $profile_field) {
        if (!array_key_exists($pref_field, $preferences) || $preferences[$pref_field] === null || $preferences[$pref_field] === '') continue;
        if ($pref_field === 'mahram_maintained') {
            $actual = $profile[$profile_field] ?? null;
            if ($actual === null || $actual === '') continue;
            $islamic_scores[] = ((int) $preferences[$pref_field] === (int) $actual) ? 100.0 : 0.0;
        } else {
            $islamic_scores[] = sm_match_exact_score($preferences[$pref_field], $profile[$profile_field] ?? null);
        }
    }
    $islamic_score = sm_match_category($islamic_scores);
    if ($islamic_score !== null) { $weighted += $islamic_score * 0.10; $used_weight += 0.10; }

    // Marital Status — 10%
    $marital_score = sm_match_exact_score($preferences['marital_status'] ?? null, $profile['marital_status'] ?? null);
    if ($marital_score !== null) { $weighted += $marital_score * 0.10; $used_weight += 0.10; }

    // Education — 10%
    $education_score = sm_match_exact_score($preferences['education'] ?? null, $profile['highest_education'] ?? null);
    if ($education_score !== null) { $weighted += $education_score * 0.10; $used_weight += 0.10; }

    // Profession — 10%
    $profession_score = sm_match_exact_score($preferences['profession'] ?? null, $profile['profession'] ?? null);
    if ($profession_score !== null) { $weighted += $profession_score * 0.10; $used_weight += 0.10; }

    // Location — 10%
    $location_score = sm_match_location_score($preferences, $profile);
    if ($location_score !== null) { $weighted += $location_score * 0.10; $used_weight += 0.10; }

    // Lifestyle / Personality — 8%
    $lifestyle_scores = [];
    $personality_score = sm_match_exact_score($preferences['personality_type'] ?? null, $profile['personality_type'] ?? null);
    if ($personality_score !== null) $lifestyle_scores[] = $personality_score;
    if (array_key_exists('accept_smoker', $preferences) && $preferences['accept_smoker'] !== null && $preferences['accept_smoker'] !== '') {
        $smoker = sm_normalize_match_value($profile['smoking_status'] ?? '');
        if ($smoker !== '') {
            $accept = (int) $preferences['accept_smoker'] === 1;
            $lifestyle_scores[] = $accept || in_array($smoker, ['never', 'quit'], true) ? 100.0 : 0.0;
        }
    }
    $lifestyle_score = sm_match_category($lifestyle_scores);
    if ($lifestyle_score !== null) { $weighted += $lifestyle_score * 0.08; $used_weight += 0.08; }

    // Trait / Q&A compatibility — 5%
    $trait_scores = [];
    foreach ($trait_preferences as $question_id => $preferred_answer) {
        $actual_answer = $trait_answers[$question_id] ?? null;
        if ($actual_answer === null || trim((string) $actual_answer) === '') continue;
        $trait_scores[] = sm_match_exact_score($preferred_answer, $actual_answer);
    }
    $trait_score = sm_match_category($trait_scores);
    if ($trait_score !== null) { $weighted += $trait_score * 0.05; $used_weight += 0.05; }

    if ($used_weight <= 0) return null;
    return max(0.0, min(100.0, $weighted / $used_weight));
}

function sm_calculate_mutual_match_score(array $viewer_preferences, array $viewer_profile, array $candidate_preferences, array $candidate_profile, array $viewer_trait_preferences = [], array $viewer_trait_answers = [], array $candidate_trait_preferences = [], array $candidate_trait_answers = []): ?int
{
    $forward = sm_directional_match_score($viewer_preferences, $candidate_profile, $viewer_trait_preferences, $candidate_trait_answers);
    $reverse = sm_directional_match_score($candidate_preferences, $viewer_profile, $candidate_trait_preferences, $viewer_trait_answers);

    if ($forward === null && $reverse === null) return null;
    if ($forward === null) return (int) round($reverse);
    if ($reverse === null) return (int) round($forward);
    return (int) round(($forward + $reverse) / 2);
}
