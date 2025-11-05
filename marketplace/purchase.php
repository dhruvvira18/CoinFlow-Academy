<?php
// Ensure this script is only accessed via AJAX POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing action.']);
    exit;
}

include_once '../setup.php'; 

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$mysqli->begin_transaction();
$action = $_POST['action'];

try {
    // 1. Fetch current user star points
    $userPointsQuery = $mysqli->prepare("SELECT star_points FROM User_Stats WHERE user_id = ?");
    if (!$userPointsQuery) {
        throw new Exception("Failed to prepare user points query: " . $mysqli->error);
    }
    $userPointsQuery->bind_param("s", $user_id);
    $userPointsQuery->execute();
    $result = $userPointsQuery->get_result();
    $user = $result->fetch_assoc();
    $current_star_points = intval(str_replace(',', '', $user['star_points']));
    $userPointsQuery->close();

    $cost = 0;
    $itemIds = [];
    $purchaseSuccess = false;

    if ($action === 'purchase_weekly_cosmetic') {
        // --- Weekly Deal Purchase Logic ---
        $cosmetic_id = intval($_POST['cosmetic_id']);

        // 2. Get cosmetic details (cost and check ownership again for security)
        $cosmeticDetailQuery = $mysqli->prepare("
            SELECT wc.cost_star_points, uc.cosmetic_id AS owned
            FROM Weekly_Cosmetics wc
            LEFT JOIN user_weekly_cosmetics uc
                ON wc.cosmetic_id = uc.cosmetic_id AND uc.user_id = ?
            WHERE wc.cosmetic_id = ?
        ");
        if (!$cosmeticDetailQuery) {
            throw new Exception("Failed to prepare cosmetic detail query: " . $mysqli->error);
        }
        $cosmeticDetailQuery->bind_param("si", $user_id, $cosmetic_id);
        $cosmeticDetailQuery->execute();
        $result = $cosmeticDetailQuery->get_result();
        $details = $result->fetch_assoc();
        $cosmeticDetailQuery->close();

        if (!$details) {
            throw new Exception("Cosmetic not found.");
        }

        $cost = $details['cost_star_points'];

        if ($details['owned']) {
            throw new Exception("You already own this item.");
        }
        if ($current_star_points < $cost) {
            throw new Exception("Insufficient Star Points to purchase this item.");
        }

        // 3. Insert into user_weekly_cosmetics
        $insertQuery = $mysqli->prepare("
            INSERT INTO user_weekly_cosmetics (user_id, cosmetic_id, date_acquired)
            VALUES (?, ?, NOW())
        ");
        if (!$insertQuery) {
            throw new Exception("Failed to prepare weekly insert query: " . $mysqli->error);
        }
        $insertQuery->bind_param("si", $user_id, $cosmetic_id);
        if (!$insertQuery->execute()) {
            throw new Exception("Failed to record weekly purchase: " . $insertQuery->error);
        }
        $insertQuery->close();

        $itemIds = [$cosmetic_id]; // Item purchased
        $purchaseSuccess = true;
        $successMessage = "Weekly Deal purchased successfully!";

    } elseif ($action === 'purchase_bundle') {
        // --- Featured Bundle Purchase Logic ---
        $bundle_id = intval($_POST['bundle_id']);
        $bundle_cost = intval($_POST['bundle_cost']);

        // 2. Verify cost and check for pre-existing ownership of ANY bundle item
        // This is a crucial security check as the front-end only hides owned bundles.
        $bundleItemsQuery = $mysqli->prepare("
            SELECT
                bi.cosmetic_id,
                bc.cost_star_points,
                (SELECT 1 FROM user_bundle_cosmetics ubc WHERE ubc.user_id = ? AND ubc.cosmetic_id = bi.cosmetic_id LIMIT 1) AS owned
            FROM
                Bundle_Items bi
            JOIN
                Bundle_Cosmetics bc ON bi.cosmetic_id = bc.cosmetic_id
            WHERE
                bi.set_id = ?
        ");
        if (!$bundleItemsQuery) {
            throw new Exception("Failed to prepare bundle item query: " . $mysqli->error);
        }
        $bundleItemsQuery->bind_param("si", $user_id, $bundle_id);
        $bundleItemsQuery->execute();
        $result = $bundleItemsQuery->get_result();

        $itemIds = [];
        $totalIndividualCost = 0;
        $isOwned = false;

        while ($item = $result->fetch_assoc()) {
            $itemIds[] = $item['cosmetic_id'];
            $totalIndividualCost += $item['cost_star_points'];
            if ($item['owned']) {
                $isOwned = true;
                break; // Found an owned item, bundle is ineligible
            }
        }
        $bundleItemsQuery->close();

        if (empty($itemIds)) {
            throw new Exception("Bundle contains no items or bundle ID is invalid.");
        }
        if ($isOwned) {
            throw new Exception("One or more items in this bundle are already owned.");
        }

        $cost = $bundle_cost; // Use the provided bundle cost (which should match the database value)

        if ($current_star_points < $cost) {
            throw new Exception("Insufficient Star Points to purchase this bundle.");
        }

        // 3. Insert ALL bundle items into user_bundle_cosmetics
        $insertQuery = $mysqli->prepare("
            INSERT INTO user_bundle_cosmetics (user_id, cosmetic_id, date_acquired)
            VALUES (?, ?, NOW())
        ");
        if (!$insertQuery) {
            throw new Exception("Failed to prepare bundle insert query: " . $mysqli->error);
        }

        foreach ($itemIds as $cosmeticId) {
            $insertQuery->bind_param("si", $user_id, $cosmeticId);
            if (!$insertQuery->execute()) {
                throw new Exception("Failed to record bundle purchase for cosmetic ID $cosmeticId: " . $insertQuery->error);
            }
        }
        $insertQuery->close();
        
        $purchaseSuccess = true;
        $successMessage = "Featured Bundle purchased successfully! All items have been added to your collection.";

    } else {
        throw new Exception("Invalid action specified.");
    }

    if ($purchaseSuccess) {
        // 4. Deduct cost from User's Star Points
        $new_star_points = $current_star_points - $cost;
        $updatePointsQuery = $mysqli->prepare("UPDATE User_Stats SET star_points = ? WHERE user_id = ?");
        if (!$updatePointsQuery) {
            throw new Exception("Failed to prepare points update query: " . $mysqli->error);
        }
        $updatePointsQuery->bind_param("ii", $new_star_points, $user_id);
        if (!$updatePointsQuery->execute()) {
            throw new Exception("Failed to update user star points: " . $updatePointsQuery->error);
        }
        $updatePointsQuery->close();

        // 5. Commit transaction and send success
        $mysqli->commit();
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'new_star_points' => $new_star_points
        ]);
    }

} catch (Exception $e) {
    // Rollback transaction on any error
    $mysqli->rollback();
    http_response_code(400); // Client error (insufficient funds, already owned) or 500 (server error)
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$mysqli->close();
?>