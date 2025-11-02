<?php
/**
 * unlock_vault.php - AJAX Endpoint for purchasing a Vault
 * This script handles the transaction: deducting skill points and INSERTING 
 * a progress record with last_lesson_completed = 0 and progress_percentage = 0.
 */

// Include global configuration for session and database credentials
include_once '../setup.php'; 

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$vault_id = filter_input(INPUT_POST, 'vault_id', FILTER_VALIDATE_INT);
$cost = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_INT);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}
if (!$vault_id || $cost === false || $cost < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Vault or cost provided.']);
    exit();
}

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$mysqli->begin_transaction();
$response = ['success' => false, 'message' => 'Transaction failed.', 'new_skill_points' => 0];

try {
    // Check Current Skill Points (uses FOR UPDATE to lock the row for the transaction)
    $stmt_check = $mysqli->prepare("SELECT skill_points FROM User_Stats WHERE user_id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $stats = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$stats) {
        throw new Exception("User stats record not found.");
    }
    
    $current_skill_points = (int)$stats['skill_points'];
    
    if ($current_skill_points < $cost) {
        throw new Exception("Insufficient Skill Points (Need " . number_format($cost) . " Skill Points).");
    }

    // Deduct Skill Points
    $new_skill_points = $current_skill_points - $cost;
    $stmt_update_stats = $mysqli->prepare("UPDATE User_Stats SET skill_points = ? WHERE user_id = ?");
    $stmt_update_stats->bind_param("ii", $new_skill_points, $user_id);
    if (!$stmt_update_stats->execute()) {
        throw new Exception("Failed to update skill points.");
    }
    $stmt_update_stats->close();

    // Unlock Vault
    $stmt_unlock = $mysqli->prepare("
        UPDATE User_Course_Progress 
        SET status = 'InProgress',
            progress_percentage = 0, 
            last_lesson_completed = 0, 
            completed_at = NULL 
        WHERE user_id = ? AND course_id = ? AND status = 'Unlocked'
    ");
    $stmt_unlock->bind_param("ii", $user_id, $vault_id);
    
    if (!$stmt_unlock->execute()) {
        throw new Exception("Failed to update progress record (unlock vault).");
    }
    $stmt_unlock->close();

    // Commit Transaction
    $mysqli->commit();

    // Success response
    $response['success'] = true;
    $response['message'] = "Vault unlocked! {$cost} Skill Points deducted.";
    $response['new_skill_points'] = $new_skill_points;

} catch (Exception $e) {
    // Rollback transaction on any error
    $mysqli->rollback();
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

$mysqli->close();
echo json_encode($response);
?>