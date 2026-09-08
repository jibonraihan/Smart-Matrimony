<?php
/**
 * Smart Matrimony - Central profile completion checker.
 *
 * Completion is based on actual saved required data, not the page the user
 * has visited. No database structure changes are required.
 */
function sm_is_filled($value): bool
{
    return $value !== null && trim((string) $value) !== '';
}

function sm_get_profile_completion(mysqli $conn, int $user_id): array
{
    $steps = array_fill(1, 6, false);

    $stmt = mysqli_prepare($conn, '
        SELECT *
        FROM user_profiles
        WHERE user_id=?
        LIMIT 1
    ');
    $profile = [];
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
    }

    if (!$profile) {
        return [
            'steps' => $steps,
            'completed_steps' => 0,
            'total_steps' => 6,
            'percentage' => 0
        ];
    }

    /* Step 1: Personal required fields. */
    $step1Required = [
        'date_of_birth', 'religion', 'madhhab', 'marital_status',
        'height_cm', 'weight_kg', 'complexion', 'highest_education', 'profession'
    ];
    $steps[1] = true;
    foreach ($step1Required as $field) {
        if (!sm_is_filled($profile[$field] ?? null)) {
            $steps[1] = false;
            break;
        }
    }

    /* Profession-dependent monthly income requirement used by Step 1. */
    if ($steps[1]) {
        $optionalIncomeProfessions = ['Student', 'Unemployed', 'Retired'];
        if (!in_array((string) ($profile['profession'] ?? ''), $optionalIncomeProfessions, true)
            && !sm_is_filled($profile['monthly_income'] ?? null)) {
            $steps[1] = false;
        }
    }

    /* Step 2: Family required fields + conditional Wali fields. */
    $step2Required = [
        'father_name', 'father_profession',
        'mother_name', 'mother_profession',
        'brothers_count', 'sisters_count',
        'family_type', 'family_status', 'living_with_family'
    ];
    $steps[2] = true;
    foreach ($step2Required as $field) {
        if (!sm_is_filled($profile[$field] ?? null)) {
            $steps[2] = false;
            break;
        }
    }

    $isFemale = (($profile['gender'] ?? '') === 'Female');
    $isIslam = (($profile['religion'] ?? '') === 'Islam');
    if ($steps[2] && $isFemale && $isIslam) {
        foreach (['guardian_name', 'guardian_relation', 'guardian_contact'] as $field) {
            if (!sm_is_filled($profile[$field] ?? null)) {
                $steps[2] = false;
                break;
            }
        }
    }

    /* Step 3: Required lifestyle / religious / health fields from the form. */
    $step3Required = [
        'smoking_status', 'prayer_status',
        'islamic_knowledge', 'halal_lifestyle',
        'sleep_pattern', 'personality_type', 'social_nature'
    ];
    $steps[3] = true;
    foreach ($step3Required as $field) {
        if (!sm_is_filled($profile[$field] ?? null)) {
            $steps[3] = false;
            break;
        }
    }

    /* Step 4: Required current location fields. */
    $step4Required = [
        'country', 'division_id', 'district_id', 'upazila_id',
        'area', 'area_type'
    ];
    $steps[4] = true;
    foreach ($step4Required as $field) {
        if (!sm_is_filled($profile[$field] ?? null)) {
            $steps[4] = false;
            break;
        }
    }

    /*
     * Step 5: the central profile/media identity is the saved profile photo.
     * Voice/video remain optional, exactly as the Step 5 UI describes them.
     */
    $steps[5] = sm_is_filled($profile['photo'] ?? null);

    /* Step 6: required partner-preference fields + all applicable own questions. */
    $prefStmt = mysqli_prepare($conn, '
        SELECT preferred_gender, min_age, max_age
        FROM search_preferences
        WHERE user_id=?
        LIMIT 1
    ');
    $preferences = [];
    if ($prefStmt) {
        mysqli_stmt_bind_param($prefStmt, 'i', $user_id);
        mysqli_stmt_execute($prefStmt);
        $preferences = mysqli_fetch_assoc(mysqli_stmt_get_result($prefStmt)) ?: [];
        mysqli_stmt_close($prefStmt);
    }

    $steps[6] = sm_is_filled($preferences['preferred_gender'] ?? null)
        && sm_is_filled($preferences['min_age'] ?? null)
        && sm_is_filled($preferences['max_age'] ?? null);

    if ($steps[6]) {
        $userStmt = mysqli_prepare($conn, 'SELECT gender FROM users WHERE user_id=? LIMIT 1');
        $userGender = '';
        if ($userStmt) {
            mysqli_stmt_bind_param($userStmt, 'i', $user_id);
            mysqli_stmt_execute($userStmt);
            $userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt)) ?: [];
            mysqli_stmt_close($userStmt);
            $userGender = (string) ($userRow['gender'] ?? '');
        }

        $qStmt = mysqli_prepare($conn, '
            SELECT tq.question_id
            FROM trait_questions tq
            WHERE tq.is_active=1
              AND (tq.gender="Both" OR tq.gender=?)
            ORDER BY tq.display_order, tq.question_id
        ');
        $requiredQuestionIds = [];
        if ($qStmt) {
            mysqli_stmt_bind_param($qStmt, 's', $userGender);
            mysqli_stmt_execute($qStmt);
            $qResult = mysqli_stmt_get_result($qStmt);
            while ($row = mysqli_fetch_assoc($qResult)) {
                $requiredQuestionIds[] = (int) $row['question_id'];
            }
            mysqli_stmt_close($qStmt);
        }

        if ($requiredQuestionIds) {
            $answerStmt = mysqli_prepare($conn, '
                SELECT question_id, answer
                FROM user_trait_answers
                WHERE user_id=?
            ');
            $answers = [];
            if ($answerStmt) {
                mysqli_stmt_bind_param($answerStmt, 'i', $user_id);
                mysqli_stmt_execute($answerStmt);
                $answerResult = mysqli_stmt_get_result($answerStmt);
                while ($row = mysqli_fetch_assoc($answerResult)) {
                    $answers[(int) $row['question_id']] = $row['answer'];
                }
                mysqli_stmt_close($answerStmt);
            }

            foreach ($requiredQuestionIds as $questionId) {
                if (!sm_is_filled($answers[$questionId] ?? null)) {
                    $steps[6] = false;
                    break;
                }
            }
        }
    }

    $completed = 0;
    foreach ($steps as $complete) {
        if ($complete) {
            $completed++;
        }
    }

    return [
        'steps' => $steps,
        'completed_steps' => $completed,
        'total_steps' => 6,
        'percentage' => (int) round(($completed / 6) * 100)
    ];
}
