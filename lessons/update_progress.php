<?php
// Start output buffering immediately to capture any stray output from included files
ob_start();

require_once __DIR__ . '/../setup.php';

// TEMPORARY: Suppress all warnings and notices which might corrupt JSON output
// Keep this until all logic is confirmed stable and working.
error_reporting(0);

header('Content-Type: application/json');

// --- Exit conditions (should use ob_end_clean() before outputting JSON) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
if ($user_id !== intval($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Security mismatch. Invalid user ID.']);
    exit;
}

$course_id = intval($_POST['course_id'] ?? 0);
$lesson_id = intval($_POST['lesson_id'] ?? 0);
$base_points = intval($_POST['base_points'] ?? 0);
$bonus_points = intval($_POST['bonus_points'] ?? 0);

if ($user_id <= 0 || $course_id <= 0 || $lesson_id <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing essential parameters']);
    exit;
}

$total_star_points = $base_points + $bonus_points;

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'coinflow_academy_db'; 

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$conn->begin_transaction();

$skill_reward = 0;
$leaderboard_reward = 0;
$course_star_points_earned = 0; 
$response_array = ['success' => false, 'message' => 'Processing failed.'];

try {
    // 1. Fetch lesson rewards and check if this lesson is already completed.
    $stmt = $conn->prepare("
        SELECT 
            cl.skill_points_reward, cl.leaderboard_points_reward,
            (SELECT COUNT(*) FROM User_Lesson_Completion WHERE user_id = ? AND lesson_id = ?) AS is_completed_before,
            up.star_points_earned
        FROM Course_Lessons cl
        JOIN User_Course_Progress up ON cl.course_id = up.course_id AND up.user_id = ?
        WHERE cl.lesson_id = ?
    ");
    $stmt->bind_param("iiii", $user_id, $lesson_id, $user_id, $lesson_id);
    $stmt->execute();
    $reward_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$reward_data) {
        throw new Exception("Lesson or course progress record not found.");
    }

    $is_completed_before = $reward_data['is_completed_before'] > 0;
    
    if ($is_completed_before) {
        $total_star_points = 0; 
        $course_star_points_earned = intval($reward_data['star_points_earned']);
    } else {
        $course_star_points_earned = intval($reward_data['star_points_earned']) + $total_star_points;

        $stmt = $conn->prepare("UPDATE User_Stats SET star_points = star_points + ? WHERE user_id = ?");
        $stmt->bind_param("ii", $total_star_points, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("
            INSERT INTO User_Lesson_Completion (user_id, lesson_id, date_completed)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ii", $user_id, $lesson_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // 2. Calculate new course progress
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS completed_lessons
        FROM User_Lesson_Completion ulc
        JOIN Course_Lessons cl ON ulc.lesson_id = cl.lesson_id
        WHERE ulc.user_id = ? AND cl.course_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    $completed_count = $stmt->get_result()->fetch_assoc()['completed_lessons'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT total_lessons FROM Courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $total_lessons_int = intval($stmt->get_result()->fetch_assoc()['total_lessons']);
    $stmt->close();

    $progress = ($completed_count / max(1, $total_lessons_int)) * 100;
    $status = ($completed_count >= $total_lessons_int) ? 'Completed' : 'InProgress';
    $progress_rounded = round($progress, 2);
    
    // 3. Update User_Course_Progress - FIXES ArgumentCountError (Line 129 in old script)
    
    // SQL uses NOW() implicitly when ? is passed in the third argument of IF()
    $stmt = $conn->prepare("
        UPDATE User_Course_Progress
        SET 
            status = ?,
            progress_percentage = ?,
            last_lesson_completed = ?,
            star_points_earned = ?,
            completed_at = IF(? = 'Completed', NOW(), completed_at) 
        WHERE user_id = ? AND course_id = ?
    ");
    
    // Types: s d i i s i i (7 types for 7 placeholders)
    // This is the source of the ArgumentCountError fix.
    $stmt->bind_param("sdiisii", 
        $status, 
        $progress_rounded, 
        $lesson_id, 
        $course_star_points_earned,
        $status, // Passed again for the IF condition check
        $user_id, 
        $course_id
    );
    $stmt->execute();
    $stmt->close();
    
    // 4. Award Skill & Leaderboard Points ONLY upon NEW COMPLETED status transition
    if ($status === 'Completed' && !$is_completed_before) {
        $skill_reward = intval($reward_data['skill_points_reward']);
        $final_leaderboard_points = $course_star_points_earned; 

        $stmt = $conn->prepare("
            UPDATE User_Stats 
            SET 
                skill_points = skill_points + ?, 
                leaderboard_points = leaderboard_points + ?
            WHERE user_id = ?
        ");
        $stmt->bind_param("iii", $skill_reward, $final_leaderboard_points, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $leaderboard_reward = $final_leaderboard_points;
    } else {
        $skill_reward = 0;
        $leaderboard_reward = 0;
    }

    $conn->commit();

    $response_array = [
        'success' => true,
        'message' => $is_completed_before ? 'Lesson already completed. Progress updated.' : 'Progress and rewards updated successfully.',
        'earned_star_points' => $total_star_points, 
        'earned_skill_points' => $skill_reward, 
        'earned_leaderboard_points' => $leaderboard_reward, 
        'progress_percentage' => $progress_rounded,
        'new_status' => $status
    ];

} catch (Exception $e) {
    $conn->rollback();
    // Use the detailed error information to help debug if the error persists
    $error_message = $e->getMessage() . ' (Line: ' . $e->getLine() . ')';
    $response_array = ['success' => false, 'message' => 'Critical DB Error: ' . $error_message];
}

$conn->close();

// Stop buffering and output the JSON response
ob_end_clean();
echo json_encode($response_array);
?>