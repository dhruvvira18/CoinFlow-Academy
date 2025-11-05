<?php
$pageTitle = 'Marketplace';

// START Output Buffer
ob_start();
?>

<?php require_once '../global_navigation.php'; ?>

<?php
// Authentication Check
if (!$user_id) {
    // Redirect to login page if user is not authenticated
    header("Location: ../account/login.html");
    exit();
}

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// --- Timezone Configuration for Consistent Marketplace Rotation ---
// Use Asia/Kolkata (IST) as the reference timezone for all rotation and expiration times.
$marketplaceTimezone = new DateTimeZone('Asia/Kolkata');

// -- Data Retrieval --
$raw_star_points = str_replace(',', '', $star_points);

// Fetch Weekly Deals Rotation Items
$dealsQuery = "
    SELECT
        t1.avatar_cosmetic_id,
        t1.frame_cosmetic_id,
        t1.badge_cosmetic_id
    FROM
        weekly_deals t1
    WHERE
        t1.rotation_id = (
            -- 1. Calculate the total number of full weeks passed since a fixed start date (Epoch).
            FLOOR(DATEDIFF(CURDATE(), '2025-11-02') / 7)
            -- 2. Use modulo 4 to create a looping index (0, 1, 2, 3) for the 4 rows.
            % 4
            -- 3. Add 1 because table primary keys/rotation_ids usually start at 1.
            + 1
        );
";
$resultDeals = $mysqli->query($dealsQuery);
$weeklyDeals = $resultDeals->fetch_assoc();
$resultDeals->close();

// --- Calculate Next Weekly Refresh Time (Start of Next Sunday) ---
// Find the next Sunday at 00:00:00 IST.
$nextSunday = new DateTime('next Sunday', $marketplaceTimezone);
$nextSunday->setTime(0, 0, 0); // Set time to midnight IST
$weeklyRefreshTimestamp = $nextSunday->getTimestamp() * 1000; // Convert to milliseconds for JS


$weeklyAvatarId = $weeklyDeals['avatar_cosmetic_id'];
$weeklyFrameId = $weeklyDeals['frame_cosmetic_id'];
$weeklyBadgeId = $weeklyDeals['badge_cosmetic_id'];

// Fetch Weekly Cosmetic Details
$cosmeticIds = array_filter([$weeklyAvatarId, $weeklyFrameId, $weeklyBadgeId]);
$cosmetics = [];
$resultCosmetics = $mysqli->query("
    SELECT cosmetic_id, name, type, cost_star_points, image_url
    FROM Weekly_Cosmetics
    WHERE cosmetic_id IN (" . implode(',', $cosmeticIds) . ");
");
while ($row = $resultCosmetics->fetch_assoc()) {
    $cosmetics[$row['type']] = $row; // type will be 'Avatar', 'Frame', or 'Badge'
}
$resultCosmetics->close();

// --- Ownership Check for Weekly Deals ---
foreach ($cosmetics as $type => &$cosmetic) {
    $cosmeticId = $cosmetic['cosmetic_id'];
    $cosmeticCost = $cosmetic['cost_star_points'];

    $resultOwned = $mysqli->query("
        SELECT
            1
        FROM
            user_weekly_cosmetics
        WHERE
            user_id = '$user_id'
        AND
            cosmetic_id = $cosmeticId
        LIMIT 1;
    ");

    // If a row is returned, the user owns the item
    if ($resultOwned && $resultOwned->num_rows > 0) {
        $cosmetic['owned'] = true;
    } else {
        $cosmetic['owned'] = false;
    }

    $cosmetic['can_afford'] = $raw_star_points >= $cosmeticCost;

    if ($resultOwned) {
        $resultOwned->close();
    }
}
unset($cosmetic);

// Fetch Active Featured Bundles
$featuredBundles = [];
$resultBundles = $mysqli->query("
    SELECT
        set_id, set_name, description, bundle_cost_sp, start_date, end_date,
        -- Calculate the end date for the current year, or next year if the current date is past the end date.
        CASE
            WHEN DATE_FORMAT(CURDATE(), '%m-%d') > DATE_FORMAT(end_date, '%m-%d')
            THEN DATE_FORMAT(end_date, CONCAT(YEAR(CURDATE()) + 1, '-%m-%d'))
            ELSE DATE_FORMAT(end_date, CONCAT(YEAR(CURDATE()), '-%m-%d'))
        END AS current_end_date_full
    FROM
        featured_bundles
    WHERE
        -- Check if the current month-day is BETWEEN the start month-day
        -- and the end month-day of the bundle, ignoring the year.
        DATE_FORMAT(CURDATE(), '%m-%d')
        BETWEEN
        DATE_FORMAT(start_date, '%m-%d')
        AND
        DATE_FORMAT(end_date, '%m-%d');
");
while ($bundle = $resultBundles->fetch_assoc()) {
    // Convert the MySQL end date string to a JavaScript-friendly timestamp (milliseconds)
    $endTime = new DateTime($bundle['current_end_date_full'], $marketplaceTimezone);
    $bundle['end_timestamp_ms'] = $endTime->getTimestamp() * 1000;
    $featuredBundles[] = $bundle;
}
$resultBundles->close();

// Prepare final array to hold bundles the user hasn't fully acquired
$finalBundles = [];

// Fetch Bundle Items for Each Featured Bundle and check ownership
foreach ($featuredBundles as $bundle) {
    $bundleId = $bundle['set_id'];
    $bundleCost = $bundle['bundle_cost_sp'];
    $bundle['can_afford'] = $raw_star_points >= $bundleCost;

    $bundleItems = [];
    $hasOwnedItem = false;

    // 1. Fetch all cosmetics belonging to this bundle
    $resultItems = $mysqli->query("
        SELECT
            bc.cosmetic_id,
            bc.name,
            bc.type,
            bc.cost_star_points,
            bc.image_url
        FROM
            Bundle_Items bi
        JOIN
            Bundle_Cosmetics bc ON bi.cosmetic_id = bc.cosmetic_id
        WHERE
            bi.set_id = $bundleId;
    ");

    while ($item = $resultItems->fetch_assoc()) {
        $bundleItems[] = $item;

        // 2. Check if the current user already owns this specific item
        $cosmeticId = $item['cosmetic_id'];
        $resultCheck = $mysqli->query("
            SELECT
                1
            FROM
                user_bundle_cosmetics
            WHERE
                user_id = '$user_id'
            AND
                cosmetic_id = $cosmeticId
            LIMIT 1;
        ");

        if ($resultCheck->num_rows > 0) {
            $hasOwnedItem = true;
            $resultCheck->close();
            break; // Stop checking items for this bundle, user owns it
        }
        $resultCheck->close();
    }
    $resultItems->close();

    // 3. If the user owns NO items from this bundle, add it to the final list
    if (!$hasOwnedItem) {
        $bundle['items'] = $bundleItems;
        $finalBundles[] = $bundle;
    }
}

$mysqli->close();
?>

<div class="container-fluid marketplace-container">
    <div class="marketplace-tabs-container d-flex justify-content-center mt-2 mb-4">
        <button class="marketplace-tab-btn" data-tab="featured_bundles">Featured</button>
        <button class="marketplace-tab-btn active" data-tab="weekly_deals">Weekly Deals</button>
    </div>

    <!-- Purchase Notification Box -->
    <div id="purchase-notification" class="alert d-none text-center" role="alert" style="position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 1050; width: 80%; max-width: 400px;">
        <span id="notification-message"></span>
    </div>

    <div id="weekly_deals_content" class="marketplace-weekly-deals-content">

        <!-- Weekly Deals Countdown -->
        <div class="weekly-refresh-countdown-container text-end mb-4">
            <h4 class="weekly-refresh-countdown-title">Next Rotation In:</h4>
            <div id="weekly-refresh-timer" class="countdown-timer"></div>
        </div>

        <div class="row justify-content-center align-items-stretch">
            <?php foreach (['Frame', 'Avatar', 'Badge'] as $type): 
                if (!isset($cosmetics[$type])) continue;
                $cosmetic = $cosmetics[$type];
                $canAfford = $cosmetic['can_afford'];
            ?>
            <div class="weekly-deals-card-col col-md-4 mb-4">
                <div class="card weekly-deals-cosmetic-card h-100 text-center">
                    <img src="<?php echo htmlspecialchars($cosmetic['image_url']); ?>" class="card-img-top weekly-deals-cosmetic-card-image mx-auto mt-3" alt="<?php echo htmlspecialchars($cosmetic['name']); ?>">
                    <div class="card-body d-flex flex-column">
                        <h3 class="card-title weekly-deals-cosmetic-card-title"><?php echo htmlspecialchars($cosmetic['name']); ?></h3>
                        <p class="card-text weekly-deals-cosmetic-card-cost"><b>Cost: </b><span class="cost-value"><?php echo number_format($cosmetic['cost_star_points']); ?></span> Star Points</p>
                        <button class="btn <?php echo $canAfford && !$cosmetic['owned'] ? 'btn-success purchase-cosmetic-btn' : 'btn-secondary disabled purchase-unavailable-cosmetic-btn'; ?>" 
                                data-cosmetic-id="<?php echo $cosmetic['cosmetic_id']; ?>"
                                data-cosmetic-cost="<?php echo $cosmetic['cost_star_points']; ?>"
                                <?php echo $canAfford && !$cosmetic['owned'] ? '' : 'disabled'; ?>>
                            <?php echo $cosmetic['owned'] ? 'Item Owned' : ($canAfford ? 'Purchase' : 'Insufficient Star Points'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="featured_bundles_content" class="marketplace-featured-bundles-content">
        <div class="row justify-content-center align-items-stretch">
            <?php foreach ($finalBundles as $bundle): ?>
            <div class="featured-bundle-card-col col-md-12 mb-4">
                <div class="card featured-bundle-card h-100 text-center">
                    <div class="featured-bundle-card-row row d-flex justify-content-center align-items-stretch">

                        <div class="featured-bundle-card-items-col col-md-5 mb-4">
                            <div class="featured-bundle-items d-flex justify-content-center flex-wrap mb-3">
                                <?php foreach ($bundle['items'] as $item): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="featured-bundle-item-image" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="featured-bundle-card-details-col col-md-7 mb-2">
                            <div class="card-body d-flex flex-column text-end h-100">
                                <h1 class="card-title featured-bundle-card-title"><?php echo htmlspecialchars($bundle['set_name']); ?></h1>
                                <h3 class="card-text featured-bundle-card-description"><?php echo htmlspecialchars($bundle['description']); ?></h3>

                                <!-- Featured Bundle Countdown -->
                                <h4 class="offer-end-text">Offer ends in:</h4>
                                <div class="featured-bundle-timer countdown-timer" data-end-time="<?php echo $bundle['end_timestamp_ms']; ?>"></div>

                                <div class="featured-bundle-purchase-section mt-auto">
                                    <h3 class="card-text featured-bundle-card-cost"><b>Bundle Cost: </b><span class="cost-value"><?php echo number_format($bundle['bundle_cost_sp']); ?></span> Star Points</h3>
                                    <button class="btn <?php echo $bundle['can_afford'] ? 'btn-success purchase-bundle-btn' : 'btn-secondary disabled purchase-unavailable-bundle-btn'; ?>" 
                                            data-bundle-id="<?php echo $bundle['set_id']; ?>"
                                            data-bundle-cost="<?php echo $bundle['bundle_cost_sp']; ?>"
                                        <?php echo $bundle['can_afford'] ? '' : 'disabled'; ?>>
                                        <?php echo $bundle['can_afford'] ? 'Purchase Bundle' : 'Insufficient Star Points'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($finalBundles)): ?>
                <p class="text-center">No featured bundles available at the moment. Please check back later!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // PHP variable passed to JavaScript for the weekly deal refresh time
    const weeklyRefreshTimestamp = <?php echo $weeklyRefreshTimestamp; ?>;

    // Global variable to hold user's current star points (updated after successful purchase)
    let currentStarPoints = <?php echo $raw_star_points; ?>;

    function showNotification(message, type) {
        const notification = $('#purchase-notification');
        const messageSpan = $('#notification-message');

        // Clear existing classes and set new ones
        notification.removeClass('d-none alert-success alert-danger alert-warning');
        notification.addClass(type);
        messageSpan.text(message);
        
        // Show notification
        notification.slideDown(200).removeClass('d-none');

        // Automatically hide after 4 seconds
        setTimeout(() => {
            notification.slideUp(200, function() {
                notification.addClass('d-none');
            });
        }, 4000);
    }

    function switchTab(tabName) {
        document.getElementById('featured_bundles_content').style.display = 'none';
        document.getElementById('weekly_deals_content').style.display = 'none';

        // Remove 'active' class from all buttons
        document.querySelectorAll('.marketplace-tab-btn').forEach(button => {
            button.classList.remove('active');
        });

        document.getElementById(tabName + '_content').style.display = 'block';

        // Set the 'active' class on the clicked button
        document.querySelector(`.marketplace-tab-btn[data-tab="${tabName}"]`).classList.add('active');
    }
    
    function updateCountdown(element, endTimeMs) {
        const now = Date.now();
        const distance = endTimeMs - now;

        if (distance < 0) {
            element.innerHTML = "EXPIRED";
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        // Format the output
        let countdownHTML = '';
        if (days > 0) {
            countdownHTML += `<span class="countdown-value">${days}</span>D `;
        }
        countdownHTML += `<span class="countdown-value">${String(hours).padStart(2, '0')}</span>H 
                          <span class="countdown-value">${String(minutes).padStart(2, '0')}</span>M 
                          <span class="countdown-value">${String(seconds).padStart(2, '0')}</span>S`;
        
        element.innerHTML = countdownHTML;
    }

    // Initialize/Run countdowns every second
    function initializeCountdowns() {
        // 1. Weekly Deals Countdown
        const weeklyTimerElement = document.getElementById('weekly-refresh-timer');
        if (weeklyTimerElement) {
            updateCountdown(weeklyTimerElement, weeklyRefreshTimestamp);
        }

        // 2. Featured Bundles Countdowns
        document.querySelectorAll('.featured-bundle-timer').forEach(element => {
            const endTime = parseInt(element.getAttribute('data-end-time'));
            if (!isNaN(endTime)) {
                updateCountdown(element, endTime);
            }
        });
    }

    // Set interval for continuous updates
    setInterval(initializeCountdowns, 1000);

    // Updates the UI elements related to star points and button states.
    function updateMarketplaceUI() {
        // Update global navbar star points display
        const currencyValueElement = document.querySelector('.star-points-value');
        if (currencyValueElement) {
            currencyValueElement.textContent = currentStarPoints.toLocaleString();
        }

        // Update Weekly Deal buttons
        $('.purchase-cosmetic-btn').each(function() {
            const button = $(this);
            const cost = parseInt(button.data('cosmetic-cost'));
            
            if (cost > currentStarPoints) {
                button.removeClass('btn-success purchase-cosmetic-btn').addClass('btn-secondary disabled purchase-unavailable-cosmetic-btn');
                button.prop('disabled', true).text('Insufficient Star Points');
            }
        });

        // Update Featured Bundle buttons
        $('.purchase-bundle-btn').each(function() {
            const button = $(this);
            const cost = parseInt(button.data('bundle-cost'));
            
            if (cost > currentStarPoints) {
                button.removeClass('btn-success purchase-bundle-btn').addClass('btn-secondary disabled purchase-unavailable-bundle-btn');
                button.prop('disabled', true).text('Insufficient Star Points');
            }
        });
    }

    $(document).ready(function() {
        const activeTabButton = document.querySelector('.marketplace-tab-btn.active');
        if (activeTabButton) {
            const initialTab = activeTabButton.getAttribute('data-tab');
            document.getElementById('featured_bundles_content').style.display = 'none';
            document.getElementById('weekly_deals_content').style.display = 'none';
            document.getElementById(initialTab + '_content').style.display = 'block';
        }

        $('.marketplace-tab-btn').click(function() {
            const tabName = $(this).data('tab');
            switchTab(tabName);
        });

        // Initial call to set the countdowns immediately on page load
        initializeCountdowns();

        // --- Purchase Logic Event Listeners ---

        // Weekly Deal Purchase
        $(document).on('click', '.purchase-cosmetic-btn', function() {
            const button = $(this);
            const cosmeticId = button.data('cosmetic-id');
            const cost = button.data('cosmetic-cost');

            if (cost > currentStarPoints) {
                showNotification("Error: Insufficient Star Points.", 'alert-danger');
                return;
            }

            button.prop('disabled', true).text('Purchasing...');

            $.post('purchase.php', {
                action: 'purchase_weekly_cosmetic',
                cosmetic_id: cosmeticId
            })
            .done(function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    showNotification(data.message, 'alert-success');
                    
                    // Update global star points and button UI
                    currentStarPoints = parseInt(data.new_star_points);
                    updateMarketplaceUI();
                    
                    // Update the specific button
                    button.removeClass('btn-success purchase-cosmetic-btn').addClass('btn-secondary disabled purchase-unavailable-cosmetic-btn');
                    button.text('Item Owned');

                } else {
                    showNotification(data.message, 'alert-danger');
                    button.prop('disabled', false).text('Purchase'); // Re-enable on failure
                }
            })
            .fail(function(xhr) {
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    showNotification(errorData.message || 'An unknown error occurred during purchase.', 'alert-danger');
                } catch {
                    showNotification('A critical server error occurred.', 'alert-danger');
                }
                button.prop('disabled', false).text('Purchase'); // Re-enable on failure
            });
        });

        // Featured Bundle Purchase
        $(document).on('click', '.purchase-bundle-btn', function() {
            const button = $(this);
            const bundleId = button.data('bundle-id');
            const bundleCost = button.data('bundle-cost');

            if (bundleCost > currentStarPoints) {
                showNotification("Error: Insufficient Star Points.", 'alert-danger');
                return;
            }

            button.prop('disabled', true).text('Purchasing...');

            $.post('purchase.php', {
                action: 'purchase_bundle',
                bundle_id: bundleId,
                bundle_cost: bundleCost
            })
            .done(function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    showNotification(data.message, 'alert-success');
                    
                    // Update global star points and button UI
                    currentStarPoints = parseInt(data.new_star_points);
                    updateMarketplaceUI();

                    // Hide the entire bundle card on successful purchase
                    button.closest('.featured-bundle-card-col').fadeOut(400, function() {
                        $(this).remove();
                    });

                } else {
                    showNotification(data.message, 'alert-danger');
                    button.prop('disabled', false).text('Purchase Bundle'); // Re-enable on failure
                }
            })
            .fail(function(xhr) {
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    showNotification(errorData.message || 'An unknown error occurred during bundle purchase.', 'alert-danger');
                } catch {
                    showNotification('A critical server error occurred.', 'alert-danger');
                }
                button.prop('disabled', false).text('Purchase Bundle'); // Re-enable on failure
            });
        });
    });
</script>

<?php
// CAPTURE the buffered output and store it in a variable
$pageContent = ob_get_clean();

// Load the complete layout
require '../layout.php';
?>