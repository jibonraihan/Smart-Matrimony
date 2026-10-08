<?php
/**
 * Smart Matrimony — Service Reviews backend
 *
 * Reviews are tied to a real completed booking_detail. A user can review a
 * particular booked package once. Only Published reviews contribute to the
 * provider rating cache stored in service_providers.rating/review_count.
 */

function service_review_json_error(string $message, int $status = 400): void
{
    throw new RuntimeException($message, $status);
}

function service_review_get_provider_summary(mysqli $conn, int $provider_id): array
{
    if ($provider_id <= 0) {
        return ['rating' => 0.0, 'review_count' => 0];
    }

    $stmt = mysqli_prepare($conn, "
        SELECT
            COALESCE(ROUND(AVG(rating), 1), 0.0) AS rating,
            COUNT(*) AS review_count
        FROM service_reviews
        WHERE provider_id = ? AND status = 'Published'
    ");
    if (!$stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    mysqli_stmt_bind_param($stmt, 'i', $provider_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);

    return [
        'rating' => round((float) ($row['rating'] ?? 0), 1),
        'review_count' => (int) ($row['review_count'] ?? 0),
    ];
}

function service_review_get_rating_breakdown(mysqli $conn, array $provider_ids): array
{
    $provider_ids = array_values(array_unique(array_filter(array_map('intval', $provider_ids), static fn($id) => $id > 0)));
    if (!$provider_ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($provider_ids), '?'));
    $types = str_repeat('i', count($provider_ids));
    $stmt = mysqli_prepare($conn, "
        SELECT provider_id, rating, COUNT(*) AS rating_count
        FROM service_reviews
        WHERE status = 'Published'
          AND provider_id IN ($placeholders)
        GROUP BY provider_id, rating
        ORDER BY provider_id ASC, rating DESC
    ");
    if (!$stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    mysqli_stmt_bind_param($stmt, $types, ...$provider_ids);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    $breakdown = [];
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $provider_id = (int) $row['provider_id'];
        $rating = (int) $row['rating'];
        if (!isset($breakdown[$provider_id])) {
            $breakdown[$provider_id] = [];
        }
        $breakdown[$provider_id][$rating] = (int) $row['rating_count'];
    }
    mysqli_stmt_close($stmt);

    return $breakdown;
}

function service_review_sync_provider_rating(mysqli $conn, int $provider_id): array
{
    if ($provider_id <= 0) {
        throw new RuntimeException('Invalid service provider.');
    }

    $summary = service_review_get_provider_summary($conn, $provider_id);

    $stmt = mysqli_prepare($conn, '
        UPDATE service_providers
        SET rating = ?, review_count = ?
        WHERE provider_id = ?
        LIMIT 1
    ');
    if (!$stmt) {
        throw new RuntimeException('Unable to update the service rating.');
    }

    mysqli_stmt_bind_param(
        $stmt,
        'dii',
        $summary['rating'],
        $summary['review_count'],
        $provider_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Unable to update the service rating.');
    }
    mysqli_stmt_close($stmt);

    return $summary;
}

/**
 * Returns the booking/package a user is allowed to review, or null when the
 * booking detail does not belong to that user, is not completed, or has a
 * provider mismatch.
 */
function service_review_get_eligible_booking_detail(mysqli $conn, int $user_id, int $booking_detail_id): ?array
{
    if ($user_id <= 0 || $booking_detail_id <= 0) {
        return null;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT
            bd.detail_id AS booking_detail_id,
            bd.booking_id,
            bd.provider_id,
            b.user_id,
            b.booking_status,
            COALESCE(sp.package_name, sp.provider_name) AS package_name,
            sp.provider_name,
            s.service_name
        FROM booking_details bd
        INNER JOIN bookings b ON b.booking_id = bd.booking_id
        INNER JOIN service_providers sp ON sp.provider_id = bd.provider_id
        INNER JOIN services s ON s.service_id = sp.service_id
        WHERE bd.detail_id = ?
          AND b.user_id = ?
          AND b.booking_status = 'Completed'
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    mysqli_stmt_bind_param($stmt, 'ii', $booking_detail_id, $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    return $row;
}

function service_review_get_for_booking(mysqli $conn, int $user_id, int $booking_id): array
{
    if ($user_id <= 0 || $booking_id <= 0) {
        return [];
    }

    $stmt = mysqli_prepare($conn, "
        SELECT review_id, booking_id, booking_detail_id, provider_id,
               rating, review_text, status, created_at, updated_at
        FROM service_reviews
        WHERE user_id = ? AND booking_id = ?
        ORDER BY review_id ASC
    
    " );
    if (!$stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $booking_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    $reviews = [];
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $reviews[(int) $row['booking_detail_id']] = $row;
    }
    mysqli_stmt_close($stmt);

    return $reviews;
}

function service_review_get_published_for_providers(mysqli $conn, array $provider_ids, int $per_provider = 3): array
{
    $provider_ids = array_values(array_unique(array_filter(array_map('intval', $provider_ids), static fn($id) => $id > 0)));
    $per_provider = max(1, min(10, $per_provider));

    if (!$provider_ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($provider_ids), '?'));
    $types = str_repeat('i', count($provider_ids));
    $sql = "
        SELECT sr.review_id, sr.provider_id, sr.rating, sr.review_text, sr.created_at,
               u.first_name, u.last_name
        FROM service_reviews sr
        INNER JOIN users u ON u.user_id = sr.user_id
        WHERE sr.status = 'Published' AND sr.provider_id IN ($placeholders)
        ORDER BY sr.created_at DESC, sr.review_id DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    mysqli_stmt_bind_param($stmt, $types, ...$provider_ids);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    $reviews = [];
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $provider_id = (int) $row['provider_id'];
        if (!isset($reviews[$provider_id])) {
            $reviews[$provider_id] = [];
        }
        if (count($reviews[$provider_id]) < $per_provider) {
            $reviews[$provider_id][] = $row;
        }
    }
    mysqli_stmt_close($stmt);

    return $reviews;
}



function service_review_get_provider_review_page(
    mysqli $conn,
    int $provider_id,
    int $page = 1,
    int $per_page = 10,
    int $rating_filter = 0,
    string $sort = 'newest'
): array {
    $page = max(1, $page);
    $per_page = max(5, min(20, $per_page));
    $rating_filter = ($rating_filter >= 1 && $rating_filter <= 5) ? $rating_filter : 0;
    $sort = in_array($sort, ['newest', 'highest', 'lowest'], true) ? $sort : 'newest';

    $provider_stmt = mysqli_prepare($conn, "
        SELECT sp.provider_id, sp.provider_name, sp.package_name, sp.service_id,
               s.service_name
        FROM service_providers sp
        INNER JOIN services s ON s.service_id = sp.service_id
        WHERE sp.provider_id = ? AND sp.status = 'Active'
        LIMIT 1
    ");
    if (!$provider_stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }
    mysqli_stmt_bind_param($provider_stmt, 'i', $provider_id);
    if (!mysqli_stmt_execute($provider_stmt)) {
        mysqli_stmt_close($provider_stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }
    $provider = mysqli_fetch_assoc(mysqli_stmt_get_result($provider_stmt)) ?: null;
    mysqli_stmt_close($provider_stmt);

    if (!$provider) {
        return ['provider' => null, 'reviews' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page, 'total_pages' => 0];
    }

    $where = "WHERE sr.provider_id = ? AND sr.status = 'Published'";
    $count_types = 'i';
    $count_params = [$provider_id];
    if ($rating_filter > 0) {
        $where .= ' AND sr.rating = ?';
        $count_types .= 'i';
        $count_params[] = $rating_filter;
    }

    $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM service_reviews sr $where");
    if (!$count_stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }
    mysqli_stmt_bind_param($count_stmt, $count_types, ...$count_params);
    if (!mysqli_stmt_execute($count_stmt)) {
        mysqli_stmt_close($count_stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }
    $total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'] ?? 0);
    mysqli_stmt_close($count_stmt);

    $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;
    if ($total_pages > 0) {
        $page = min($page, $total_pages);
    } else {
        $page = 1;
    }
    $offset = ($page - 1) * $per_page;
    $order_by = match ($sort) {
        'highest' => 'sr.rating DESC, sr.created_at DESC, sr.review_id DESC',
        'lowest' => 'sr.rating ASC, sr.created_at DESC, sr.review_id DESC',
        default => 'sr.created_at DESC, sr.review_id DESC',
    };

    $sql = "SELECT sr.review_id, sr.booking_id, sr.rating, sr.review_text, sr.created_at,
                   u.first_name, u.last_name
            FROM service_reviews sr
            INNER JOIN users u ON u.user_id = sr.user_id
            $where
            ORDER BY $order_by
            LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }
    $types = $count_types . 'ii';
    $params = $count_params;
    $params[] = $per_page;
    $params[] = $offset;
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    $reviews = [];
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $reviews[] = $row;
    }
    mysqli_stmt_close($stmt);

    return [
        'provider' => $provider,
        'reviews' => $reviews,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'rating_filter' => $rating_filter,
        'sort' => $sort,
    ];
}

function service_review_get_manager_reviews(mysqli $conn, int $manager_id, string $status = 'All'): array
{
    if ($manager_id <= 0) return [];
    $allowed = ['All', 'Published', 'Hidden'];
    if (!in_array($status, $allowed, true)) $status = 'All';

    $sql = "SELECT sr.review_id, sr.booking_id, sr.booking_detail_id, sr.provider_id,
                   sr.rating, sr.review_text, sr.status, sr.created_at,
                   u.first_name, u.last_name,
                   COALESCE(sp.package_name, sp.provider_name) AS package_name,
                   sp.provider_name, s.service_name
            FROM service_reviews sr
            INNER JOIN service_providers sp ON sp.provider_id = sr.provider_id
            INNER JOIN services s ON s.service_id = sp.service_id
            INNER JOIN users u ON u.user_id = sr.user_id
            INNER JOIN bookings b ON b.booking_id = sr.booking_id
            INNER JOIN booking_details bd ON bd.detail_id = sr.booking_detail_id
            WHERE (sp.manager_id = ? OR b.manager_id = ? OR bd.manager_id = ?)";
    if ($status !== 'All') $sql .= " AND sr.status = ?";
    $sql .= " ORDER BY sr.created_at DESC, sr.review_id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new RuntimeException('The service review system is temporarily unavailable.');
    if ($status === 'All') mysqli_stmt_bind_param($stmt, 'iii', $manager_id, $manager_id, $manager_id);
    else mysqli_stmt_bind_param($stmt, 'iiis', $manager_id, $manager_id, $manager_id, $status);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }
    $rows = [];
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function service_review_get_manager_counts(mysqli $conn, int $manager_id): array
{
    $counts = ['All' => 0, 'Published' => 0, 'Hidden' => 0];
    if ($manager_id <= 0) return $counts;
    $stmt = mysqli_prepare($conn, "SELECT sr.status, COUNT(*) AS total
        FROM service_reviews sr
        INNER JOIN service_providers sp ON sp.provider_id = sr.provider_id
        INNER JOIN bookings b ON b.booking_id = sr.booking_id
        INNER JOIN booking_details bd ON bd.detail_id = sr.booking_detail_id
        WHERE (sp.manager_id = ? OR b.manager_id = ? OR bd.manager_id = ?)
        GROUP BY sr.status");
    if (!$stmt) throw new RuntimeException('The service review system is temporarily unavailable.');
    mysqli_stmt_bind_param($stmt, 'iii', $manager_id, $manager_id, $manager_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['total'];
    }
    mysqli_stmt_close($stmt);
    $counts['All'] = $counts['Published'] + $counts['Hidden'];
    return $counts;
}

function service_review_update_manager_status(mysqli $conn, int $manager_id, int $review_id, string $status): bool
{
    if ($manager_id <= 0 || $review_id <= 0 || !in_array($status, ['Published', 'Hidden'], true)) return false;
    $stmt = mysqli_prepare($conn, "UPDATE service_reviews sr
        INNER JOIN service_providers sp ON sp.provider_id = sr.provider_id
        INNER JOIN bookings b ON b.booking_id = sr.booking_id
        INNER JOIN booking_details bd ON bd.detail_id = sr.booking_detail_id
        SET sr.status = ?
        WHERE sr.review_id = ? AND (sp.manager_id = ? OR b.manager_id = ? OR bd.manager_id = ?)
        LIMIT 1");
    if (!$stmt) throw new RuntimeException('The service review system is temporarily unavailable.');
    mysqli_stmt_bind_param($stmt, 'siiii', $status, $review_id, $manager_id, $manager_id, $manager_id);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt) >= 1;
    mysqli_stmt_close($stmt);
    if ($changed) {
        $stmt = mysqli_prepare($conn, "SELECT provider_id FROM service_reviews WHERE review_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $review_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row) service_review_sync_provider_rating($conn, (int)$row['provider_id']);
    }
    return $changed;
}

function service_review_get_existing_for_detail(mysqli $conn, int $user_id, int $booking_detail_id): ?array
{
    $stmt = mysqli_prepare($conn, "
        SELECT review_id, booking_id, booking_detail_id, provider_id,
               rating, review_text, status, created_at, updated_at
        FROM service_reviews
        WHERE user_id = ? AND booking_detail_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $booking_detail_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('The service review system is temporarily unavailable.');
    }

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
    return $row;
}

/**
 * Submit one verified customer review and immediately synchronize the
 * provider's derived rating/count cache.
 */
function service_review_submit(
    mysqli $conn,
    int $user_id,
    int $booking_detail_id,
    int $rating,
    string $review_text = ''
): array {
    if ($user_id <= 0) {
        throw new RuntimeException('Please log in again.');
    }

    if ($booking_detail_id <= 0) {
        throw new RuntimeException('Invalid booking item.');
    }

    if ($rating < 1 || $rating > 5) {
        throw new RuntimeException('Please choose a rating from 1 to 5 stars.');
    }

    $review_text = trim($review_text);
    if (mb_strlen($review_text) > 2000) {
        throw new RuntimeException('Your review must be 2000 characters or less.');
    }

    mysqli_begin_transaction($conn);

    try {
        /* Lock the booking item so eligibility cannot change mid-submission. */
        $stmt = mysqli_prepare($conn, "
            SELECT
                bd.detail_id AS booking_detail_id,
                bd.booking_id,
                bd.provider_id,
                b.user_id,
                b.booking_status,
                COALESCE(sp.package_name, sp.provider_name) AS package_name,
                sp.provider_name
            FROM booking_details bd
            INNER JOIN bookings b ON b.booking_id = bd.booking_id
            INNER JOIN service_providers sp ON sp.provider_id = bd.provider_id
            WHERE bd.detail_id = ?
              AND b.user_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        if (!$stmt) {
            throw new RuntimeException('The service review system is temporarily unavailable.');
        }
        mysqli_stmt_bind_param($stmt, 'ii', $booking_detail_id, $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('The service review system is temporarily unavailable.');
        }
        $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);

        if (!$booking) {
            throw new RuntimeException('This booking item is not available for your account.');
        }

        if (($booking['booking_status'] ?? '') !== 'Completed') {
            throw new RuntimeException('You can review a service only after the booking is completed.');
        }

        $existing = service_review_get_existing_for_detail($conn, $user_id, $booking_detail_id);
        if ($existing) {
            throw new RuntimeException('You have already reviewed this booked service.');
        }

        $review_text_db = $review_text !== '' ? $review_text : null;
        $stmt = mysqli_prepare($conn, "
            INSERT INTO service_reviews
                (booking_id, booking_detail_id, user_id, provider_id, rating, review_text, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Published')
        ");
        if (!$stmt) {
            throw new RuntimeException('The service review system is temporarily unavailable.');
        }
        mysqli_stmt_bind_param(
            $stmt,
            'iiiiss',
            $booking['booking_id'],
            $booking['booking_detail_id'],
            $user_id,
            $booking['provider_id'],
            $rating,
            $review_text_db
        );

        if (!mysqli_stmt_execute($stmt)) {
            $errno = mysqli_stmt_errno($stmt);
            mysqli_stmt_close($stmt);
            if ($errno === 1062) {
                throw new RuntimeException('You have already reviewed this booked service.');
            }
            throw new RuntimeException('Your review could not be saved. Please try again.');
        }
        $review_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $summary = service_review_sync_provider_rating($conn, (int) $booking['provider_id']);

        mysqli_commit($conn);

        return [
            'review_id' => (int) $review_id,
            'booking_id' => (int) $booking['booking_id'],
            'booking_detail_id' => (int) $booking['booking_detail_id'],
            'provider_id' => (int) $booking['provider_id'],
            'package_name' => (string) ($booking['package_name'] ?? ''),
            'provider_name' => (string) ($booking['provider_name'] ?? ''),
            'rating' => $rating,
            'review_text' => $review_text,
            'provider_rating' => $summary['rating'],
            'provider_review_count' => $summary['review_count'],
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}
