<?php
$page_css = 'assets/css/budget-finder.css';
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const BUDGET_FINDER_TOLERANCE = 0.10; // User-approved fixed 10% flexibility.
const BUDGET_FINDER_MAX_BUDGET = 10000000.00;

if (empty($_SESSION['budget_finder_csrf'])) {
    $_SESSION['budget_finder_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['budget_finder_csrf'];
$user_logged_in = isset($_SESSION['user_id']);

/* Add all recommended প্যাকেজs directly from Budget Finder without changing cart.php. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_many_budget') {
    if (!$user_logged_in) {
        header('Location: ../login.php');
        exit;
    }

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $provider_ids = array_values(array_unique(array_filter(
        array_map('intval', (array)($_POST['provider_ids'] ?? [])),
        static function ($id) { return $id > 0; }
    )));

    if (!$provider_ids) {
        header('Location: budget_finder.php');
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "INSERT INTO service_cart_items (user_id, provider_id, quantity)
        SELECT ?, provider_id, 1
        FROM service_providers
        WHERE provider_id = ? AND status = 'Active'
        ON DUPLICATE KEY UPDATE quantity = quantity + 1");

    if (!$stmt) {
        http_response_code(500);
        exit('Unable to add প্যাকেজs to cart.');
    }

    mysqli_begin_transaction($conn);
    try {
        foreach ($provider_ids as $provider_id) {
            mysqli_stmt_bind_param($stmt, 'ii', $user_id, $provider_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Unable to add selected প্যাকেজs to cart.');
            }
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
        header('Location: ../cart.php');
        exit;
    } catch (Throwable $e) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        http_response_code(500);
        exit('Unable to add প্যাকেজs to cart.');
    }
}

$search_error = '';
$search_info = '';
$selected_service_ids = [];
$budget = 0.0;
$max_budget = 0.0;
$selected_services = [];
$packages_by_service = [];
$recommendation = null;
$missing_services = [];


function budget_finder_bn(string $value): string {
    $map = [
        'Photography'=>'ফটোগ্রাফি','Catering'=>'খাবার ও আপ্যায়ন','Decoration'=>'সাজসজ্জা','Car Rental'=>'গাড়ি ভাড়া','Makeup Artist'=>'মেকআপ শিল্পী','Wedding Planner'=>'বিয়ের পরিকল্পনাকারী','Convention Center'=>'কনভেনশন সেন্টার','Resort / Lawn'=>'রিসোর্ট / লন','Mehendi Artist'=>'মেহেদি শিল্পী','Wedding Flowers'=>'বিয়ের ফুল','Basor Ghor Decoration'=>'বাসর ঘর সাজসজ্জা','Wedding Dress'=>'বিয়ের পোশাক','Groom Dress'=>'বরের পোশাক','Bridal Accessories'=>'কনের সাজসজ্জার সামগ্রী','Sound & Lighting'=>'সাউন্ড ও লাইটিং','Invitation & Printing'=>'নিমন্ত্রণপত্র ও মুদ্রণ',
        'Wedding photography service'=>'বিয়ের ফটোগ্রাফি সেবা','Food and catering service'=>'খাবার ও আপ্যায়ন সেবা','Wedding decoration service'=>'বিয়ের সাজসজ্জার সেবা','Wedding car rental service'=>'বিয়ের গাড়ি ভাড়ার সেবা','Bridal makeup service'=>'কনের মেকআপ সেবা','Complete wedding planning'=>'বিয়ের সম্পূর্ণ পরিকল্পনা','Wedding hall and convention center rental'=>'বিয়ের হল ও কনভেনশন সেন্টার ভাড়া','Open lawn and resort wedding venue'=>'খোলা লন ও রিসোর্টে বিয়ের আয়োজন','Bridal bouquet and floral arrangements'=>'কনের ফুলের তোড়া ও ফুলের সাজ','Basor ghor and room decoration service'=>'বাসর ঘর ও কক্ষ সাজানোর সেবা','Bridal wedding dress and attire'=>'কনের বিয়ের পোশাক','Groom wedding dress and attire'=>'বরের বিয়ের পোশাক','Jewellery, shoes and bridal accessories'=>'গয়না, জুতা ও কনের সাজসজ্জার সামগ্রী','Wedding sound system and lighting setup'=>'বিয়ের সাউন্ড সিস্টেম ও আলোর ব্যবস্থা','Wedding invitation cards and printing'=>'বিয়ের নিমন্ত্রণপত্র ও মুদ্রণ'
    ];
    return $map[$value] ?? $value;
}

function budget_finder_money(float $amount): string {
    return '৳' . number_format($amount, 2);
}

function budget_finder_clean_service_ids(array $raw): array {
    $ids = [];
    foreach ($raw as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false) $ids[(int)$id] = true;
    }
    return array_keys($ids);
}

function budget_finder_bind_params(mysqli_stmt $stmt, string $types, array $values): void {
    $refs = [$types];
    foreach ($values as $index => $value) {
        $values[$index] = $value;
        $refs[] = &$values[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function budget_finder_final_price(array $প্যাকেজ): float {
    $price = max(0, (float)($প্যাকেজ['price'] ?? 0));
    $discount = max(0, min(100, (float)($প্যাকেজ['discount_percent'] ?? 0)));
    return round($price * (1 - ($discount / 100)), 2);
}

/*
 * Exact branch-and-bound search for one প্যাকেজ per selected service.
 * It first prefers a total inside the user's actual budget. If none exists,
 * it uses the 10% tolerance window. If even that is impossible, the caller
 * falls back to the cheapest valid প্যাকেজ from every selected service.
 */
function budget_finder_find_combination(array $groups, float $budget, float $allowed_max): ?array {
    if (!$groups) return null;

    usort($groups, function ($a, $b) {
        $count_compare = count($a['packages']) <=> count($b['packages']);
        if ($count_compare !== 0) return $count_compare;
        return $a['service_id'] <=> $b['service_id'];
    });

    $count = count($groups);
    $min_অবশিষ্ট = array_fill(0, $count + 1, 0.0);
    $max_অবশিষ্ট = array_fill(0, $count + 1, 0.0);
    for ($i = $count - 1; $i >= 0; $i--) {
        $minimum = INF;
        $maximum = 0.0;
        foreach ($groups[$i]['packages'] as $প্যাকেজ) {
            $price = (float)$প্যাকেজ['_final_price'];
            $minimum = min($minimum, $price);
            $maximum = max($maximum, $price);
        }
        $min_অবশিষ্ট[$i] = $min_অবশিষ্ট[$i + 1] + $minimum;
        $max_অবশিষ্ট[$i] = $max_অবশিষ্ট[$i + 1] + $maximum;
    }

    $bestActual = null;
    $bestActualTotal = -1.0;
    $bestActualRating = -1.0;
    $bestActualReviews = -1;
    $bestAllowed = null;
    $bestAllowedTotal = -1.0;
    $bestAllowedRating = -1.0;
    $bestAllowedReviews = -1;

    $visit = function (int $index, float $total, array $chosen, float $ratingSum, int $রিভিউSum) use (&$visit, &$groups, &$min_অবশিষ্ট, &$max_অবশিষ্ট, $count, $budget, $allowed_max, &$bestActual, &$bestActualTotal, &$bestActualRating, &$bestActualReviews, &$bestAllowed, &$bestAllowedTotal, &$bestAllowedRating, &$bestAllowedReviews) {
        if ($total + $min_অবশিষ্ট[$index] > $allowed_max + 0.009) return;
        if ($index < $count && $total + $max_অবশিষ্ট[$index] < $bestActualTotal - 0.009 && $total + $max_অবশিষ্ট[$index] < $bestAllowedTotal - 0.009) return;

        if ($index === $count) {
            if ($total <= $budget + 0.009) {
                $better = $total > $bestActualTotal + 0.009
                    || (abs($total - $bestActualTotal) <= 0.009 && ($ratingSum > $bestActualRating + 0.001 || (abs($ratingSum - $bestActualRating) <= 0.001 && $রিভিউSum > $bestActualReviews)));
                if ($better) {
                    $bestActualTotal = $total;
                    $bestActualRating = $ratingSum;
                    $bestActualReviews = $রিভিউSum;
                    $bestActual = $chosen;
                }
            }
            if ($total <= $allowed_max + 0.009) {
                $better = $total > $bestAllowedTotal + 0.009
                    || (abs($total - $bestAllowedTotal) <= 0.009 && ($ratingSum > $bestAllowedRating + 0.001 || (abs($ratingSum - $bestAllowedRating) <= 0.001 && $রিভিউSum > $bestAllowedReviews)));
                if ($better) {
                    $bestAllowedTotal = $total;
                    $bestAllowedRating = $ratingSum;
                    $bestAllowedReviews = $রিভিউSum;
                    $bestAllowed = $chosen;
                }
            }
            return;
        }

        $প্যাকেজs = $groups[$index]['packages'];
        usort($প্যাকেজs, function ($a, $b) use ($budget, $total) {
            $aDist = abs($budget - ($total + (float)$a['_final_price']));
            $bDist = abs($budget - ($total + (float)$b['_final_price']));
            return $aDist <=> $bDist;
        });

        foreach ($প্যাকেজs as $প্যাকেজ) {
            $nextTotal = $total + (float)$প্যাকেজ['_final_price'];
            if ($nextTotal > $allowed_max + 0.009) continue;
            $chosen[$groups[$index]['service_id']] = $প্যাকেজ;
            $visit(
                $index + 1,
                $nextTotal,
                $chosen,
                $ratingSum + (float)$প্যাকেজ['rating'],
                $রিভিউSum + (int)$প্যাকেজ['review_count']
            );
            unset($chosen[$groups[$index]['service_id']]);
        }
    };

    $visit(0, 0.0, [], 0.0, 0);

    if ($bestActual !== null) {
        return ['packages' => $bestActual, 'total' => $bestActualTotal, 'mode' => 'within_budget'];
    }
    if ($bestAllowed !== null) {
        return ['packages' => $bestAllowed, 'total' => $bestAllowedTotal, 'mode' => 'within_tolerance'];
    }
    return null;
}

function budget_finder_cheapest_combination(array $groups): array {
    $chosen = [];
    $total = 0.0;
    foreach ($groups as $group) {
        usort($group['packages'], function ($a, $b) {
            return (float)$a['_final_price'] <=> (float)$b['_final_price'];
        });
        $প্যাকেজ = $group['packages'][0];
        $chosen[$group['service_id']] = $প্যাকেজ;
        $total += (float)$প্যাকেজ['_final_price'];
    }
    return ['packages' => $chosen, 'total' => $total, 'mode' => 'closest_available'];
}

$search_completed = false;

/* Search / recommendation request. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'find_packages') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $search_error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পৃষ্ঠাটি রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        $selected_service_ids = budget_finder_clean_service_ids((array)($_POST['service_ids'] ?? []));
        $budget = (float)($_POST['budget'] ?? 0);
        $budget = round($budget, 2);
        $max_budget = round($budget * (1 + BUDGET_FINDER_TOLERANCE), 2);

        if (!$selected_service_ids) {
            $search_error = 'অন্তত একটি সেবা নির্বাচন করুন।';
        } elseif ($budget <= 0 || $budget > BUDGET_FINDER_MAX_BUDGET) {
            $search_error = '৳১ থেকে ৳১,০০,০০,০০০-এর মধ্যে সঠিক বাজেট লিখুন।';
        } else {
            $placeholders = implode(',', array_fill(0, count($selected_service_ids), '?'));
            $types = str_repeat('i', count($selected_service_ids));
            $stmt = mysqli_prepare($conn, "SELECT service_id, service_name, service_name_bn, description, description_bn FROM services WHERE is_active=1 AND service_id IN ($placeholders) ORDER BY service_id");
            budget_finder_bind_params($stmt, $types, $selected_service_ids);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $service_map = [];
            while ($row = mysqli_fetch_assoc($result)) $service_map[(int)$row['service_id']] = $row;
            mysqli_stmt_close($stmt);

            foreach ($selected_service_ids as $service_id) {
                if (!isset($service_map[$service_id])) continue;
                $selected_services[] = $service_map[$service_id];
                $packages_by_service[$service_id] = [];
            }

            if (count($selected_services) !== count($selected_service_ids)) {
                $search_error = 'নির্বাচিত এক বা একাধিক সেবা আর উপলভ্য নেই।';
            } else {
                $stmt = mysqli_prepare($conn, "SELECT provider_id, package_code, service_id, provider_name, package_name, package_details, image, price, discount_percent, rating, review_count FROM service_providers WHERE status='Active' AND service_id IN ($placeholders) ORDER BY service_id, price ASC, rating DESC, review_count DESC, provider_id DESC");
                budget_finder_bind_params($stmt, $types, $selected_service_ids);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $row['_final_price'] = budget_finder_final_price($row);
                    $packages_by_service[(int)$row['service_id']][] = $row;
                }
                mysqli_stmt_close($stmt);

                $groups = [];
                foreach ($selected_services as $service) {
                    $service_id = (int)$service['service_id'];
                    if (empty($packages_by_service[$service_id])) {
                        $missing_services[] = $service;
                    } else {
                        $groups[] = ['service_id' => $service_id, 'packages' => $packages_by_service[$service_id]];
                    }
                }

                if ($missing_services) {
                    $search_error = 'নির্বাচিত কিছু সেবার জন্য এখনো সক্রিয় প্যাকেজ নেই। সেগুলো সরিয়ে আবার খুঁজুন।';
                } else {
                    $recommendation = budget_finder_find_combination($groups, $budget, $max_budget);
                    if ($recommendation === null) {
                        $recommendation = budget_finder_cheapest_combination($groups);
                    }
                    $search_info = match ($recommendation['mode']) {
                        'within_budget' => 'আপনার বাজেটের মধ্যে উপযুক্ত প্যাকেজের সমন্বয় পাওয়া গেছে।',
                        'within_tolerance' => 'আপনার বাজেটের সঙ্গে পুরোপুরি মেলে এমন সমন্বয় পাওয়া যায়নি। অনুমোদিত ১০% অতিরিক্ত সীমার মধ্যে সবচেয়ে কাছের সমন্বয়টি দেখানো হয়েছে।',
                        default => 'নির্বাচিত সেবাগুলো ১০% অতিরিক্ত সীমার মধ্যেও রাখা সম্ভব হয়নি। সবচেয়ে কাছের উপলভ্য সমন্বয়টি দেখানো হয়েছে।',
                    };
                    $search_completed = true;
                }
            }
        }
    }
}

/* Load all services for the selection UI. */
$all_services = [];
$service_result = mysqli_query($conn, "
    SELECT s.service_id, s.service_name, s.service_name_bn, s.description, s.description_bn,
           COUNT(sp.provider_id) AS package_count
    FROM services s
    LEFT JOIN service_providers sp
      ON sp.service_id = s.service_id AND sp.status = 'Active'
    WHERE s.is_active=1
    GROUP BY s.service_id, s.service_name, s.service_name_bn, s.description, s.description_bn
    ORDER BY s.service_id
");
if ($service_result) {
    while ($row = mysqli_fetch_assoc($service_result)) {
        $row['package_count'] = (int)($row['package_count'] ?? 0);
        $all_services[] = $row;
    }
}

/* All active প্যাকেজs are also loaded once for real-time প্যাকেজ switching/add-service UI. */
$budget_ui_packages = [];
$budget_ui_result = mysqli_query($conn, "
    SELECT provider_id, package_code, service_id, provider_name, package_name, package_details, image,
           price, discount_percent, rating, review_count
    FROM service_providers
    WHERE status = 'Active'
    ORDER BY service_id, price ASC, rating DESC, review_count DESC, provider_id DESC
");
if ($budget_ui_result) {
    while ($row = mysqli_fetch_assoc($budget_ui_result)) {
        $row['discount_percent'] = max(0, min(100, (float)($row['discount_percent'] ?? 0)));
        $row['_final_price'] = budget_finder_final_price($row);
        $budget_ui_packages[(int)$row['service_id']][] = $row;
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<main class="budget-finder-page">
    <section class="budget-finder-hero">
        <div class="budget-container">
            <div class="budget-hero-layout">
                <div class="budget-hero-copy">
                    <div class="budget-hero-nav">
                        <a class="budget-back-home" href="#" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '<?= BASE_URL; ?>'; } return false;"><i class="fa-solid fa-arrow-left"></i> ফিরে যান</a>
                    </div>
                    <span class="budget-hero-badge"><i class="fa-solid fa-wallet"></i> বিয়ের বাজেট নির্ধারক</span>
                    <h1>আপনার বাজেটের মধ্যেই <span>বিয়ের পরিকল্পনা করুন।</span></h1>
                    <p>বিয়ের জন্য প্রয়োজনীয় সেবাগুলো বেছে নিন, বাজেট নির্ধারণ করুন এবং উপযুক্ত প্যাকেজের সমন্বয় পান।</p>
                </div>

                <div class="budget-hero-visual" aria-hidden="true">
                    <div class="budget-visual-glow"></div>
                    <div class="budget-visual-card">
                        <div class="budget-visual-icon"><i class="fa-solid fa-wallet"></i></div>
                        <div class="budget-visual-flow">
                            <span><i class="fa-solid fa-list-check"></i></span>
                            <b>+</b>
                            <span><i class="fa-solid fa-bangladeshi-taka-sign"></i></span>
                            <b>→</b>
                            <span class="is-result"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                        </div>
                        <strong>স্মার্ট বিয়ের পরিকল্পনা</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="budget-finder-main budget-container">
        <?php if ($search_error): ?>
            <div class="budget-alert budget-alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($search_error); ?></span></div>
        <?php endif; ?>

        <?php if ($search_info): ?>
            <div class="budget-alert budget-alert-info"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($search_info); ?></span></div>
        <?php endif; ?>

        <form method="post" class="budget-search-card" id="budgetSearchForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
            <input type="hidden" name="action" value="find_packages">

            <section class="budget-step-card budget-step-budget">
                <div class="budget-search-heading">
                    <div>
                        <span class="budget-kicker">ধাপ ১</span>
                        <h2>বিয়ের মোট বাজেট নির্ধারণ করুন</h2>
                        <p>নির্বাচিত সেবাগুলোর জন্য আপনি মোট কত টাকা খরচ করতে চান, তা লিখুন।</p>
                    </div>
                </div>

                <div class="budget-input-row">
                    <div class="budget-input-block">
                        <label for="budgetAmount">আপনার মোট বাজেট</label>
                        <div class="budget-input-wrap"><span>৳</span><input id="budgetAmount" name="budget" type="number" min="1" max="10000000" step="1" value="<?= $budget > 0 ? htmlspecialchars((string)$budget) : ''; ?>" placeholder="200000" required></div>
                    </div>
                    <div class="budget-flexibility-block">
                        <span class="budget-kicker">স্মার্ট নমনীয়তা</span>
                        <strong>বাজেটের চেয়ে সর্বোচ্চ ১০% বেশি</strong>
                        <p>প্রয়োজনে আপনার বাজেটের চেয়ে সর্বোচ্চ ১০% বেশি খরচের সমন্বয়ও বিবেচনা করা হবে।</p>
                    </div>
                </div>
            </section>

            <section class="budget-step-card budget-step-services">
                <div class="budget-search-heading">
                    <div>
                        <span class="budget-kicker">ধাপ ২</span>
                        <h2>প্রয়োজনীয় সেবাগুলো বেছে নিন</h2>
                        <p>আপনার পরিকল্পনায় যে সেবাগুলো রাখতে চান, সেগুলো নির্বাচন করুন।</p>
                    </div>
                    <div class="budget-selection-actions">
                        <button type="button" id="selectAllServices"><i class="fa-solid fa-check-double"></i> সব নির্বাচন</button>
                        <button type="button" id="clearAllServices"><i class="fa-solid fa-eraser"></i> মুছে ফেলুন</button>
                    </div>
                </div>

                <div class="budget-service-grid" id="budgetServiceGrid">
                    <?php foreach ($all_services as $service): ?>
                        <?php $checked = in_array((int)$service['service_id'], $selected_service_ids, true); ?>
                        <label class="budget-service-option<?= $checked ? ' is-checked' : ''; ?>">
                            <input type="checkbox" name="service_ids[]" value="<?= (int)$service['service_id']; ?>"<?= $checked ? ' checked' : ''; ?>>
                            <span class="budget-service-check"><i class="fa-solid fa-check"></i></span>
                            <span class="budget-service-copy">
                                <strong><?= htmlspecialchars((($service['service_name_bn'] ?? '') !== '' ? $service['service_name_bn'] : budget_finder_bn($service['service_name']))); ?></strong>
                                <small><?= htmlspecialchars($service['description'] ?? 'বিয়ের সেবা'); ?></small>
                                <?php if ((int)$service['package_count'] > 0): ?>
                                    <em class="budget-package-count"><i class="fa-solid fa-box-open"></i> <?= (int)$service['package_count']; ?> প্যাকেজ<?= (int)$service['package_count'] === 1 ? '' : 's'; ?> উপলভ্য</em>
                                <?php else: ?>
                                    <em class="budget-package-count is-empty"><i class="fa-solid fa-circle-exclamation"></i> কোনো প্যাকেজ নেই</em>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="budget-step-submit">
                    <button class="budget-find-btn" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> আমার জন্য প্যাকেজ খুঁজুন</button>
                </div>
            </section>
        </form>

        <?php if ($recommendation && !$search_error): ?>
            <?php $recommended_packages = $recommendation['packages']; ?>
            <?php $recommendation_total = (float)$recommendation['total']; ?>
            <?php $difference = round($budget - $recommendation_total, 2); ?>
            <section class="budget-results" id="budgetResults">
                <div class="budget-results-heading">
                    <div>
                        <span class="budget-kicker">স্মার্ট সমন্বয়</span>
                        <h2>আপনার জন্য প্রস্তাবিত প্যাকেজের সমন্বয়</h2>
                        <p><?= htmlspecialchars($search_info); ?></p>
                    </div>
                    <a href="#budgetSearchForm" class="budget-edit-link"><i class="fa-solid fa-sliders"></i> সেবা পরিবর্তন করুন</a>
                </div>

                <div class="budget-summary-card">
                    <div><small>আপনার বাজেট</small><strong><?= budget_finder_money($budget); ?></strong></div>
                    <div><small>প্রস্তাবিত মোট খরচ</small><strong id="budgetSummaryTotal"><?= budget_finder_money($recommendation_total); ?></strong></div>
                    <div><small>অবস্থা</small><strong id="budgetSummaryStatus" class="budget-status <?= $difference >= 0 ? 'within' : 'over'; ?>"><?= $difference >= 0 ? '৳' . number_format($difference, 2) . ' অবশিষ্ট' : '৳' . number_format(abs($difference), 2) . ' বেশি'; ?></strong></div>
                </div>

                <div class="budget-package-list" id="budgetPackageList">
                    <?php foreach ($selected_services as $service): ?>
                        <?php $service_id = (int)$service['service_id']; if (!isset($recommended_packages[$service_id])) continue; $প্যাকেজ = $recommended_packages[$service_id]; ?>
                        <?php $discount = max(0, min(100, (float)$প্যাকেজ['discount_percent'])); $final_price = (float)$প্যাকেজ['_final_price']; ?>
                        <article class="budget-package-row" data-service-id="<?= $service_id; ?>" data-provider-id="<?= (int)$প্যাকেজ['provider_id']; ?>" data-service-name="<?= htmlspecialchars($service['service_name'], ENT_QUOTES, 'UTF-8'); ?>" data-price="<?= htmlspecialchars((string)$final_price); ?>">
                            <div class="budget-package-image">
                                <?php if (!empty($প্যাকেজ['image'])): ?><img src="<?= BASE_URL; ?>uploads/services/<?= htmlspecialchars($প্যাকেজ['image']); ?>" alt="<?= htmlspecialchars($প্যাকেজ['package_name'] ?: $প্যাকেজ['provider_name']); ?>" loading="lazy"><?php else: ?><i class="fa-solid fa-ring"></i><?php endif; ?>
                            </div>
                            <div class="budget-package-copy">
                                <span><?= htmlspecialchars((($service['service_name_bn'] ?? '') !== '' ? $service['service_name_bn'] : budget_finder_bn($service['service_name']))); ?></span>
                                <?php if (!empty($প্যাকেজ['package_code'])): ?><span class="package-code-badge"><?= htmlspecialchars($প্যাকেজ['package_code']); ?></span><?php endif; ?>
                                <h3><?= htmlspecialchars($প্যাকেজ['package_name'] ?: $প্যাকেজ['provider_name']); ?></h3>
                                <p><?= htmlspecialchars($প্যাকেজ['provider_name']); ?></p>
                                <?php if (!empty($প্যাকেজ['package_details'])): ?><small><?= htmlspecialchars($প্যাকেজ['package_details']); ?></small><?php endif; ?>
                            </div>
                            <div class="budget-package-price">
                                <?php if ($discount > 0): ?><del>৳<?= number_format((float)$প্যাকেজ['price'], 2); ?></del><?php endif; ?>
                                <strong>৳<?= number_format($final_price, 2); ?></strong>
                                <?php if ($discount > 0): ?><em><?= rtrim(rtrim(number_format($discount, 2), '0'), '.'); ?>% ছাড়</em><?php endif; ?>
                            </div>
                            <div class="budget-package-rating"><i class="fa-solid fa-star"></i> <?= number_format((float)$প্যাকেজ['rating'], 1); ?><?php if ((int)$প্যাকেজ['review_count'] > 0): ?><small><?= (int)$প্যাকেজ['review_count']; ?> <?= (int)$প্যাকেজ['review_count'] === 1 ? 'রিভিউ' : 'টি রিভিউ'; ?></small><?php else: ?><small>এখনো কোনো রিভিউ নেই</small><?php endif; ?></div>
                            <div class="budget-package-actions">
                                <select class="budget-package-select" data-service-id="<?= $service_id; ?>" aria-label="প্যাকেজ পরিবর্তন করুন: <?= htmlspecialchars((($service['service_name_bn'] ?? '') !== '' ? $service['service_name_bn'] : budget_finder_bn($service['service_name']))); ?>">
                                    <?php foreach ($packages_by_service[$service_id] as $option): ?>
                                        <?php $option_price = (float)$option['_final_price']; ?>
                                        <option value="<?= (int)$option['provider_id']; ?>" data-price="<?= htmlspecialchars((string)$option_price); ?>" data-original="<?= htmlspecialchars((string)$option['price']); ?>" data-discount="<?= htmlspecialchars((string)$option['discount_percent']); ?>" data-name="<?= htmlspecialchars($option['package_name'] ?: $option['provider_name'], ENT_QUOTES, 'UTF-8'); ?>" data-provider="<?= htmlspecialchars($option['provider_name'], ENT_QUOTES, 'UTF-8'); ?>" data-details="<?= htmlspecialchars($option['package_details'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-image="<?= htmlspecialchars($option['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-rating="<?= htmlspecialchars((string)$option['rating'], ENT_QUOTES, 'UTF-8'); ?>" data-reviews="<?= (int)$option['review_count']; ?>" data-code="<?= htmlspecialchars($option['package_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"<?= (int)$option['provider_id'] === (int)$প্যাকেজ['provider_id'] ? ' selected' : ''; ?>><?= htmlspecialchars($option['package_name'] ?: $option['provider_name']); ?> — <?= budget_finder_money($option_price); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="budget-remove-btn" data-remove-service="<?= $service_id; ?>" title="এই সেবাটি সরান"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="budget-add-service-row">
                    <label for="addServiceSelect"><i class="fa-solid fa-plus"></i> আরেকটি সেবা যোগ করুন</label>
                    <select id="addServiceSelect">
                        <option value="">একটি সেবা বেছে নিন...</option>
                        <?php foreach ($all_services as $service): ?>
                            <?php if (in_array((int)$service['service_id'], $selected_service_ids, true) || (int)$service['package_count'] === 0) continue; ?>
                            <option value="<?= (int)$service['service_id']; ?>"><?= htmlspecialchars((($service['service_name_bn'] ?? '') !== '' ? $service['service_name_bn'] : budget_finder_bn($service['service_name']))); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="addServiceBtn"><i class="fa-solid fa-plus"></i> যোগ করুন ও পুনরায় হিসাব করুন</button>
                </div>

                <div class="budget-result-footer">
                    <div class="budget-total-block"><small>বর্তমান নির্বাচন</small><strong id="budgetLiveTotal"><?= budget_finder_money($recommendation_total); ?></strong><span id="budgetLiveStatus" class="<?= $difference >= 0 ? 'within' : 'over'; ?>"><?= $difference >= 0 ? 'আপনার বাজেটের মধ্যে' : 'বাজেটের বেশি—তবে অনুমোদিত সীমার মধ্যে'; ?></span></div>
                    <?php if ($user_logged_in): ?>
                        <form method="post" action="budget_finder.php" id="budgetAddAllForm">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="action" value="add_many_budget">
                            <div id="budgetSelectedProviders"></div>
                            <button type="submit" class="budget-add-all-btn"><i class="fa-solid fa-cart-plus"></i> সব কার্টে যোগ করুন</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="budget-add-all-btn budget-guest-cart-btn"><i class="fa-solid fa-cart-plus"></i> সব কার্টে যোগ করুন</button>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </section>
</main>

<script>
window.SMART_BUDGET_FINDER = <?= json_encode([
    'baseUrl' => BASE_URL,
    'loggedIn' => $user_logged_in,
    'language' => (($current_language ?? ($_SESSION['sm_language'] ?? 'bn')) === 'en') ? 'en' : 'bn',
    'guestCartPopup' => (($current_language ?? ($_SESSION['sm_language'] ?? 'bn')) === 'en') ? [
        'title' => 'Login Required',
        'message' => 'Please log in or register to access all available services.',
        'login' => 'Login / Register',
        'continue' => 'Continue Browsing'
    ] : [
        'title' => 'লগইন প্রয়োজন',
        'message' => 'সকল সেবা ব্যবহার করতে লগইন করুন অথবা নিবন্ধন করুন।',
        'login' => 'লগইন / নিবন্ধন',
        'continue' => 'ব্রাউজিং চালিয়ে যান'
    ],
    'budget' => $budget,
    'tolerance' => BUDGET_FINDER_TOLERANCE,
    'csrf' => $csrf,
    'selectedServiceIds' => $selected_service_ids,
    'recommendationTotal' => $recommendation['total'] ?? 0,
    'packagesByService' => $budget_ui_packages,
    'services' => array_map(static function ($service) { $service['service_name'] = (($service['service_name_bn'] ?? '') !== '') ? $service['service_name_bn'] : budget_finder_bn($service['service_name']); $service['description'] = (($service['description_bn'] ?? '') !== '') ? $service['description_bn'] : budget_finder_bn($service['description'] ?? ''); return $service; }, $all_services),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?= BASE_URL; ?>assets/js/budget-finder.js?v=2"></script>
<?php if ($search_completed): ?>
<script>
window.addEventListener('load', function () {
    var results = document.getElementById('budgetResults');
    if (results) {
        setTimeout(function () {
            results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 60);
    }
});
</script>
<?php endif; ?>

<script src="<?= BASE_URL; ?>assets/js/package-image-preview.js?v=2"></script>
<?php include '../includes/footer.php'; ?>
