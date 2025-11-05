<?php
include_once '../setup.php';

header('Content-Type: application/json');

// Check if user is authenticated (assuming $user_id is available from global_config)
if (!isset($user_id) || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// Input validation
$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? '';

if (!in_array($type, ['Avatar', 'Frame', 'Badge'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid cosmetic type.']);
    exit();
}

if ($action === 'equip') {
    $cosmetic_id = (int) ($_POST['cosmetic_id'] ?? 0);
    $source = $_POST['source'] ?? '';

    if ($cosmetic_id <= 0 || !in_array($source, ['weekly', 'bundle'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid cosmetic ID or source.']);
        exit();
    }

    // Determine the table based on the source
    $ownershipTable = ($source === 'weekly') ? 'user_weekly_cosmetics' : 'user_bundle_cosmetics';
    // Get the opposing table for unequip query
    $opposingTable = ($source === 'weekly') ? 'user_bundle_cosmetics' : 'user_weekly_cosmetics';

    // Start Transaction
    $mysqli->begin_transaction();

    try {
        // --- STEP 1: UNEQUIP all existing cosmetics of the same type in BOTH ownership tables ---
        
        // A. Unequip from the CURRENT table first (if any are equipped)
        $unequipCurrentQuery = "
            UPDATE {$ownershipTable} uwc
            JOIN {$source}_cosmetics wc ON uwc.cosmetic_id = wc.cosmetic_id
            SET uwc.is_equipped = 0
            WHERE uwc.user_id = ? AND wc.type = ?
        ";
        $unequipCurrentStmt = $mysqli->prepare($unequipCurrentQuery);
        if (!$unequipCurrentStmt) { throw new Exception("Prepare Unequip Current failed: " . $mysqli->error); }
        $unequipCurrentStmt->bind_param("ss", $user_id, $type);
        $unequipCurrentStmt->execute();
        $unequipCurrentStmt->close();

        // B. Unequip from the OPPOSING table
        $unequipOpposingQuery = "
            UPDATE {$opposingTable} uoc
            JOIN " . ($opposingTable === 'user_weekly_cosmetics' ? 'Weekly_Cosmetics' : 'Bundle_Cosmetics') . " oc ON uoc.cosmetic_id = oc.cosmetic_id
            SET uoc.is_equipped = 0
            WHERE uoc.user_id = ? AND oc.type = ?
        ";
        $unequipOpposingStmt = $mysqli->prepare($unequipOpposingQuery);
        if (!$unequipOpposingStmt) { throw new Exception("Prepare Unequip Opposing failed: " . $mysqli->error); }
        $unequipOpposingStmt->bind_param("ss", $user_id, $type);
        $unequipOpposingStmt->execute();
        $unequipOpposingStmt->close();


        // --- STEP 2: EQUIP the new item ---
        $equipQuery = "
            UPDATE {$ownershipTable}
            SET is_equipped = 1
            WHERE user_id = ? AND cosmetic_id = ?
        ";
        $equipStmt = $mysqli->prepare($equipQuery);
        if (!$equipStmt) { throw new Exception("Prepare Equip failed: " . $mysqli->error); }
        $equipStmt->bind_param("si", $user_id, $cosmetic_id);
        $equipStmt->execute();
        
        if ($mysqli->affected_rows === 0) {
            throw new Exception("Cosmetic not found in inventory or already equipped.");
        }
        $equipStmt->close();

        // Commit Transaction
        $mysqli->commit();
        echo json_encode(['success' => true, 'message' => 'Cosmetic equipped successfully.', 'cosmetic_id' => $cosmetic_id]);

    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to equip cosmetic: ' . $e->getMessage()]);
    }

} elseif ($action === 'unequip') {
    // --- Handles Unequip (Setting to "None Equipped") ---
    
    // Unequipping requires setting is_equipped=0 in all ownership tables for the specific type.

    // Start Transaction
    $mysqli->begin_transaction();

    try {
        // 1. Unequip from user_weekly_cosmetics
        $weeklyTable = 'user_weekly_cosmetics';
        $weeklyCosmeticsTable = 'Weekly_Cosmetics';
        $unequipWeeklyQuery = "
            UPDATE {$weeklyTable} uwc
            JOIN {$weeklyCosmeticsTable} wc ON uwc.cosmetic_id = wc.cosmetic_id
            SET uwc.is_equipped = 0
            WHERE uwc.user_id = ? AND wc.type = ? AND uwc.is_equipped = 1
        ";
        $unequipWeeklyStmt = $mysqli->prepare($unequipWeeklyQuery);
        if (!$unequipWeeklyStmt) { throw new Exception("Prepare Unequip Weekly failed: " . $mysqli->error); }
        $unequipWeeklyStmt->bind_param("ss", $user_id, $type);
        $unequipWeeklyStmt->execute();
        $unequipWeeklyStmt->close();

        // 2. Unequip from user_bundle_cosmetics
        $bundleTable = 'user_bundle_cosmetics';
        $bundleCosmeticsTable = 'Bundle_Cosmetics';
        $unequipBundleQuery = "
            UPDATE {$bundleTable} ubc
            JOIN {$bundleCosmeticsTable} bc ON ubc.cosmetic_id = bc.cosmetic_id
            SET ubc.is_equipped = 0
            WHERE ubc.user_id = ? AND bc.type = ? AND ubc.is_equipped = 1
        ";
        $unequipBundleStmt = $mysqli->prepare($unequipBundleQuery);
        if (!$unequipBundleStmt) { throw new Exception("Prepare Unequip Bundle failed: " . $mysqli->error); }
        $unequipBundleStmt->bind_param("ss", $user_id, $type);
        $unequipBundleStmt->execute();
        $unequipBundleStmt->close();

        // Commit Transaction
        $mysqli->commit();
        echo json_encode(['success' => true, 'message' => 'Cosmetic unequipped successfully.']);
        
    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to unequip cosmetic: ' . $e->getMessage()]);
    }


} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
}

$mysqli->close();
?>