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
$search_error = '';
$search_info = '';
$selected_service_ids = [];
$budget = 0.0;
$max_budget = 0.0;
$selected_services = [];
$packages_by_service = [];
$recommendation = null;
$missing_services = [];

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

function budget_finder_final_price(array $package): float {
    $price = max(0, (float)($package['price'] ?? 0));
    $discount = max(0, min(100, (float)($package['discount_percent'] ?? 0)));
    return round($price * (1 - ($discount / 100)), 2);
}

/*
 * Exact branch-and-bound search for one package per selected service.
 * It first prefers a total inside the user's actual budget. If none exists,
 * it uses the 10% tolerance window. If even that is impossible, the caller
 * falls back to the cheapest valid package from every selected service.
 */
function budget_finder_find_combination(array $groups, float $budget, float $allowed_max): ?array {
    if (!$groups) return null;

    usort($groups, function ($a, $b) {
        $count_compare = count($a['packages']) <=> count($b['packages']);
        if ($count_compare !== 0) return $count_compare;
        return $a['service_id'] <=> $b['service_id'];
    });

    $count = count($groups);
    $min_remaining = array_fill(0, $count + 1, 0.0);
    $max_remaining = array_fill(0, $count + 1, 0.0);
    for ($i = $count - 1; $i >= 0; $i--) {
        $minimum = INF;
        $maximum = 0.0;
        foreach ($groups[$i]['packages'] as $package) {
            $price = (float)$package['_final_price'];
            $minimum = min($minimum, $price);
            $maximum = max($maximum, $price);
        }
        $min_remaining[$i] = $min_remaining[$i + 1] + $minimum;
        $max_remaining[$i] = $max_remaining[$i + 1] + $maximum;
    }

    $bestActual = null;
    $bestActualTotal = -1.0;
    $bestActualRating = -1.0;
    $bestActualReviews = -1;
    $bestAllowed = null;
    $bestAllowedTotal = -1.0;
    $bestAllowedRating = -1.0;
    $bestAllowedReviews = -1;

    $visit = function (int $index, float $total, array $chosen, float $ratingSum, int $reviewSum) use (&$visit, &$groups, &$min_remaining, &$max_remaining, $count, $budget, $allowed_max, &$bestActual, &$bestActualTotal, &$bestActualRating, &$bestActualReviews, &$bestAllowed, &$bestAllowedTotal, &$bestAllowedRating, &$bestAllowedReviews) {
        if ($total + $min_remaining[$index] > $allowed_max + 0.009) return;
        if ($index < $count && $total + $max_remaining[$index] < $bestActualTotal - 0.009 && $total + $max_remaining[$index] < $bestAllowedTotal - 0.009) return;

        if ($index === $count) {
            if ($total <= $budget + 0.009) {
                $better = $total > $bestActualTotal + 0.009
                    || (abs($total - $bestActualTotal) <= 0.009 && ($ratingSum > $bestActualRating + 0.001 || (abs($ratingSum - $bestActualRating) <= 0.001 && $reviewSum > $bestActualReviews)));
                if ($better) {
                    $bestActualTotal = $total;
                    $bestActualRating = $ratingSum;
                    $bestActualReviews = $reviewSum;
                    $bestActual = $chosen;
                }
            }
            if ($total <= $allowed_max + 0.009) {
                $better = $total > $bestAllowedTotal + 0.009
                    || (abs($total - $bestAllowedTotal) <= 0.009 && ($ratingSum > $bestAllowedRating + 0.001 || (abs($ratingSum - $bestAllowedRating) <= 0.001 && $reviewSum > $bestAllowedReviews)));
                if ($better) {
                    $bestAllowedTotal = $total;
                    $bestAllowedRating = $ratingSum;
                    $bestAllowedReviews = $reviewSum;
                    $bestAllowed = $chosen;
                }
            }
            return;
        }

        $packages = $groups[$index]['packages'];
        usort($packages, function ($a, $b) use ($budget, $total) {
            $aDist = abs($budget - ($total + (float)$a['_final_price']));
            $bDist = abs($budget - ($total + (float)$b['_final_price']));
            return $aDist <=> $bDist;
        });

        foreach ($packages as $package) {
            $nextTotal = $total + (float)$package['_final_price'];
            if ($nextTotal > $allowed_max + 0.009) continue;
            $chosen[$groups[$index]['service_id']] = $package;
            $visit(
                $index + 1,
                $nextTotal,
                $chosen,
                $ratingSum + (float)$package['rating'],
                $reviewSum + (int)$package['review_count']
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
        $package = $group['packages'][0];
        $chosen[$group['service_id']] = $package;
        $total += (float)$package['_final_price'];
    }
    return ['packages' => $chosen, 'total' => $total, 'mode' => 'closest_available'];
}

/* Search / recommendation request. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'find_packages') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $search_error = 'Security check failed. Please refresh the page and try again.';
    } else {
        $selected_service_ids = budget_finder_clean_service_ids((array)($_POST['service_ids'] ?? []));
        $budget = (float)($_POST['budget'] ?? 0);
        $budget = round($budget, 2);
        $max_budget = round($budget * (1 + BUDGET_FINDER_TOLERANCE), 2);

        if (!$selected_service_ids) {
            $search_error = 'Please select at least one service.';
        } elseif ($budget <= 0 || $budget > BUDGET_FINDER_MAX_BUDGET) {
            $search_error = 'Please enter a valid budget between ৳1 and ৳1,00,00,000.';
        } else {
            $placeholders = implode(',', array_fill(0, count($selected_service_ids), '?'));
            $types = str_repeat('i', count($selected_service_ids));
            $stmt = mysqli_prepare($conn, "SELECT service_id, service_name, description FROM services WHERE service_id IN ($placeholders) ORDER BY service_id");
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
                $search_error = 'One or more selected services are no longer available.';
            } else {
                $stmt = mysqli_prepare($conn, "SELECT provider_id, service_id, provider_name, package_name, package_details, image, price, discount_percent, rating, review_count FROM service_providers WHERE status='Active' AND service_id IN ($placeholders) ORDER BY service_id, price ASC, rating DESC, review_count DESC, provider_id DESC");
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
                    $search_error = 'Some selected services do not have an active package yet. Please remove those services and search again.';
                } else {
                    $recommendation = budget_finder_find_combination($groups, $budget, $max_budget);
                    if ($recommendation === null) {
                        $recommendation = budget_finder_cheapest_combination($groups);
                    }
                    $search_info = match ($recommendation['mode']) {
                        'within_budget' => 'A package combination was found within your budget.',
                        'within_tolerance' => 'No combination fit the budget exactly, so the closest fit within the allowed 10% flexibility is shown.',
                        default => 'The selected services cannot fit within your 10% flexibility window. The closest available combination is shown.',
                    };
                }
            }
        }
    }
}

/* Load all services for the selection UI. */
$all_services = [];
$service_result = mysqli_query($conn, "
    SELECT s.service_id, s.service_name, s.description,
           COUNT(sp.provider_id) AS package_count
    FROM services s
    LEFT JOIN service_providers sp
      ON sp.service_id = s.service_id AND sp.status = 'Active'
    GROUP BY s.service_id, s.service_name, s.description
    ORDER BY s.service_id
");
if ($service_result) {
    while ($row = mysqli_fetch_assoc($service_result)) {
        $row['package_count'] = (int)($row['package_count'] ?? 0);
        $all_services[] = $row;
    }
}

/* All active packages are also loaded once for real-time package switching/add-service UI. */
$budget_ui_packages = [];
$budget_ui_result = mysqli_query($conn, "
    SELECT provider_id, service_id, provider_name, package_name, package_details, image,
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
            <div class="budget-hero-copy">
                <a class="budget-back-home" href="<?= BASE_URL; ?>"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
                <span class="budget-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> Smart Budget Finder</span>
                <h1>Plan Your Wedding <span>Within Your Budget.</span></h1>
                <p>Pick the services you need and set your budget. We'll find a practical package combination for you.</p>
                <div class="budget-hero-points">
                    <span><i class="fa-solid fa-check"></i> One package per service</span>
                    <span><i class="fa-solid fa-percent"></i> Discounted prices</span>
                    <span><i class="fa-solid fa-sliders"></i> Up to 10% flexibility</span>
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

            <div class="budget-search-heading">
                <div>
                    <span class="budget-kicker">STEP 1</span>
                    <h2>Choose the services you need</h2>
                    <p>Select as many categories as you want. The finder will choose exactly one active package from each selected service.</p>
                </div>
                <div class="budget-selection-actions">
                    <button type="button" id="selectAllServices"><i class="fa-solid fa-check-double"></i> Select All</button>
                    <button type="button" id="clearAllServices"><i class="fa-solid fa-eraser"></i> Clear</button>
                </div>
            </div>

            <div class="budget-service-grid" id="budgetServiceGrid">
                <?php foreach ($all_services as $service): ?>
                    <?php $checked = in_array((int)$service['service_id'], $selected_service_ids, true); ?>
                    <label class="budget-service-option<?= $checked ? ' is-checked' : ''; ?>">
                        <input type="checkbox" name="service_ids[]" value="<?= (int)$service['service_id']; ?>"<?= $checked ? ' checked' : ''; ?>>
                        <span class="budget-service-check"><i class="fa-solid fa-check"></i></span>
                        <span class="budget-service-copy">
                            <strong><?= htmlspecialchars($service['service_name']); ?></strong>
                            <small><?= htmlspecialchars($service['description'] ?? 'Wedding service'); ?></small>
                            <?php if ((int)$service['package_count'] > 0): ?>
                                <em class="budget-package-count"><i class="fa-solid fa-box-open"></i> <?= (int)$service['package_count']; ?> package<?= (int)$service['package_count'] === 1 ? '' : 's'; ?> available</em>
                            <?php else: ?>
                                <em class="budget-package-count is-empty"><i class="fa-solid fa-circle-exclamation"></i> No package available</em>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="budget-input-row">
                <div class="budget-input-block">
                    <span class="budget-kicker">STEP 2</span>
                    <label for="budgetAmount">Your total budget</label>
                    <div class="budget-input-wrap"><span>৳</span><input id="budgetAmount" name="budget" type="number" min="1" max="10000000" step="1" value="<?= $budget > 0 ? htmlspecialchars((string)$budget) : ''; ?>" placeholder="200000" required></div>
                    <small>We'll first try to keep the total at or below this amount.</small>
                </div>
                <div class="budget-flexibility-block">
                    <span class="budget-kicker">SMART FLEXIBILITY</span>
                    <strong>Up to 10% over budget</strong>
                    <p>For example, a ৳2,00,000 budget can consider combinations up to ৳2,20,000 when no exact in-budget combination is available.</p>
                </div>
                <button class="budget-find-btn" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Find My Packages</button>
            </div>
        </form>

        <?php if ($recommendation && !$search_error): ?>
            <?php $recommended_packages = $recommendation['packages']; ?>
            <?php $recommendation_total = (float)$recommendation['total']; ?>
            <?php $difference = round($budget - $recommendation_total, 2); ?>
            <section class="budget-results" id="budgetResults">
                <div class="budget-results-heading">
                    <div>
                        <span class="budget-kicker">SMART MATCH</span>
                        <h2>Your recommended package combination</h2>
                        <p><?= htmlspecialchars($search_info); ?></p>
                    </div>
                    <a href="#budgetSearchForm" class="budget-edit-link"><i class="fa-solid fa-sliders"></i> Modify Services</a>
                </div>

                <div class="budget-summary-card">
                    <div><small>Your budget</small><strong><?= budget_finder_money($budget); ?></strong></div>
                    <div><small>Recommended total</small><strong id="budgetSummaryTotal"><?= budget_finder_money($recommendation_total); ?></strong></div>
                    <div><small>Status</small><strong id="budgetSummaryStatus" class="budget-status <?= $difference >= 0 ? 'within' : 'over'; ?>"><?= $difference >= 0 ? '৳' . number_format($difference, 2) . ' remaining' : '৳' . number_format(abs($difference), 2) . ' over'; ?></strong></div>
                </div>

                <div class="budget-package-list" id="budgetPackageList">
                    <?php foreach ($selected_services as $service): ?>
                        <?php $service_id = (int)$service['service_id']; if (!isset($recommended_packages[$service_id])) continue; $package = $recommended_packages[$service_id]; ?>
                        <?php $discount = max(0, min(100, (float)$package['discount_percent'])); $final_price = (float)$package['_final_price']; ?>
                        <article class="budget-package-row" data-service-id="<?= $service_id; ?>" data-provider-id="<?= (int)$package['provider_id']; ?>" data-service-name="<?= htmlspecialchars($service['service_name'], ENT_QUOTES, 'UTF-8'); ?>" data-price="<?= htmlspecialchars((string)$final_price); ?>">
                            <div class="budget-package-image">
                                <?php if (!empty($package['image'])): ?><img src="<?= BASE_URL; ?>uploads/services/<?= htmlspecialchars($package['image']); ?>" alt="<?= htmlspecialchars($package['package_name'] ?: $package['provider_name']); ?>" loading="lazy"><?php else: ?><i class="fa-solid fa-ring"></i><?php endif; ?>
                            </div>
                            <div class="budget-package-copy">
                                <span><?= htmlspecialchars($service['service_name']); ?></span>
                                <h3><?= htmlspecialchars($package['package_name'] ?: $package['provider_name']); ?></h3>
                                <p><?= htmlspecialchars($package['provider_name']); ?></p>
                                <?php if (!empty($package['package_details'])): ?><small><?= htmlspecialchars($package['package_details']); ?></small><?php endif; ?>
                            </div>
                            <div class="budget-package-price">
                                <?php if ($discount > 0): ?><del>৳<?= number_format((float)$package['price'], 2); ?></del><?php endif; ?>
                                <strong>৳<?= number_format($final_price, 2); ?></strong>
                                <?php if ($discount > 0): ?><em><?= rtrim(rtrim(number_format($discount, 2), '0'), '.'); ?>% OFF</em><?php endif; ?>
                            </div>
                            <div class="budget-package-rating"><i class="fa-solid fa-star"></i> <?= number_format((float)$package['rating'], 1); ?><small>(<?= (int)$package['review_count']; ?>)</small></div>
                            <div class="budget-package-actions">
                                <select class="budget-package-select" data-service-id="<?= $service_id; ?>" aria-label="Change package for <?= htmlspecialchars($service['service_name']); ?>">
                                    <?php foreach ($packages_by_service[$service_id] as $option): ?>
                                        <?php $option_price = (float)$option['_final_price']; ?>
                                        <option value="<?= (int)$option['provider_id']; ?>" data-price="<?= htmlspecialchars((string)$option_price); ?>" data-original="<?= htmlspecialchars((string)$option['price']); ?>" data-discount="<?= htmlspecialchars((string)$option['discount_percent']); ?>" data-name="<?= htmlspecialchars($option['package_name'] ?: $option['provider_name'], ENT_QUOTES, 'UTF-8'); ?>" data-provider="<?= htmlspecialchars($option['provider_name'], ENT_QUOTES, 'UTF-8'); ?>" data-details="<?= htmlspecialchars($option['package_details'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-image="<?= htmlspecialchars($option['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-rating="<?= htmlspecialchars((string)$option['rating'], ENT_QUOTES, 'UTF-8'); ?>" data-reviews="<?= (int)$option['review_count']; ?>"<?= (int)$option['provider_id'] === (int)$package['provider_id'] ? ' selected' : ''; ?>><?= htmlspecialchars($option['package_name'] ?: $option['provider_name']); ?> — <?= budget_finder_money($option_price); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="budget-remove-btn" data-remove-service="<?= $service_id; ?>" title="Remove this service"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="budget-add-service-row">
                    <label for="addServiceSelect"><i class="fa-solid fa-plus"></i> Add another service</label>
                    <select id="addServiceSelect">
                        <option value="">Choose a service...</option>
                        <?php foreach ($all_services as $service): ?>
                            <?php if (in_array((int)$service['service_id'], $selected_service_ids, true) || (int)$service['package_count'] === 0) continue; ?>
                            <option value="<?= (int)$service['service_id']; ?>"><?= htmlspecialchars($service['service_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="addServiceBtn"><i class="fa-solid fa-plus"></i> Add & Recalculate</button>
                </div>

                <div class="budget-result-footer">
                    <div class="budget-total-block"><small>Current selection</small><strong id="budgetLiveTotal"><?= budget_finder_money($recommendation_total); ?></strong><span id="budgetLiveStatus" class="<?= $difference >= 0 ? 'within' : 'over'; ?>"><?= $difference >= 0 ? 'Within your budget' : 'Above budget — still within the allowed flexibility'; ?></span></div>
                    <?php if ($user_logged_in): ?>
                        <form method="post" action="<?= BASE_URL; ?>cart.php" id="budgetAddAllForm">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['cart_csrf'] ?? ''); ?>">
                            <input type="hidden" name="action" value="add_many_budget">
                            <div id="budgetSelectedProviders"></div>
                            <button type="submit" class="budget-add-all-btn"><i class="fa-solid fa-cart-plus"></i> Add All to Cart</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="budget-add-all-btn budget-guest-cart-btn"><i class="fa-solid fa-cart-plus"></i> Add All to Cart</button>
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
    'budget' => $budget,
    'tolerance' => BUDGET_FINDER_TOLERANCE,
    'csrf' => $_SESSION['cart_csrf'] ?? '',
    'selectedServiceIds' => $selected_service_ids,
    'recommendationTotal' => $recommendation['total'] ?? 0,
    'packagesByService' => $budget_ui_packages,
    'services' => $all_services,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?= BASE_URL; ?>assets/js/budget-finder.js?v=2"></script>

<?php include '../includes/footer.php'; ?>
