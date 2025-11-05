<?php
// quiz_handler.php - Handles grading and rewards for the Final Quiz (Lesson Index 10)

// FIX: Start session immediately at the very top.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to prevent header errors
ob_start();

// Suppress errors to prevent accidental output that breaks JSON
error_reporting(0); 

header('Content-Type: application/json');

// --- NEW FUNCTION: Tier Unlock Logic ---

/**
 * Checks if the current tier is complete and unlocks courses in the next tier.
 * @param mysqli $conn The database connection.
 * @param int $user_id The ID of the user.
 * @param int $completed_course_id The ID of the course just completed.
 * @param int $current_tier_id The tier of the course just completed.
 * @return int|null The ID of the tier just unlocked, or null.
 */
function unlock_next_tier_courses($conn, $user_id, $completed_course_id, $current_tier_id) {
    // 1. Check if ALL courses in the current tier are completed by the user
    $check_completion_query = "
        SELECT COUNT(c.course_id) AS total_in_tier, 
               SUM(CASE WHEN up.status = 'Completed' THEN 1 ELSE 0 END) AS completed_in_tier
        FROM Courses c
        LEFT JOIN User_Course_Progress up 
            ON c.course_id = up.course_id AND up.user_id = ?
        WHERE c.tier_id = ?;
    ";
    
    $stmt = $conn->prepare($check_completion_query);
    $stmt->bind_param("ii", $user_id, $current_tier_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total_in_tier = intval($result['total_in_tier']);
    $completed_in_tier = intval($result['completed_in_tier']);

    // Check if total completed equals total courses in the tier (all mastered)
    // We check if completed is less than the total, in which case we exit (return null).
    if ($total_in_tier === 0 || $completed_in_tier < $total_in_tier) {
        return null; // Tier mastery prerequisite not met yet
    }
    
    // 2. Find the ID of the NEXT Tier
    $stmt = $conn->prepare("SELECT tier_id FROM Course_Tiers WHERE prerequisite_tier_id = ?");
    $stmt->bind_param("i", $current_tier_id);
    $stmt->execute();
    $next_tier_id = $stmt->get_result()->fetch_assoc()['tier_id'] ?? null;
    $stmt->close();

    if (!$next_tier_id) {
        return null; // No next tier defined (e.g., Grandmaster tier)
    }

    // 3. Unlock all courses in the next tier (set status to 'Unlocked')
    $unlock_query = "
        UPDATE User_Course_Progress up
        JOIN Courses c ON up.course_id = c.course_id
        SET up.status = 'Unlocked'
        WHERE up.user_id = ? AND c.tier_id = ? AND up.status = 'Locked';
    ";
    
    $stmt = $conn->prepare($unlock_query);
    $stmt->bind_param("ii", $user_id, $next_tier_id);
    $stmt->execute();
    $stmt->close();
    
    return $next_tier_id; 
}


$response = ['success' => false, 'message' => 'Invalid Request.', 'passed' => false];

// Assign variables immediately after session start
$user_id = intval($_SESSION['user_id'] ?? 0);
$course_id = intval($_POST['course_id'] ?? 0);
$lesson_id = intval($_POST['lesson_id'] ?? 0);
$pass_threshold = 3; 

// FIX: Simplify validation. Check for POST method and user_id presence.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $user_id === 0) {
    $response['message'] = 'Access denied: User session not found or Invalid request method.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

if ($course_id === 0 || $lesson_id === 0) {
    $response['message'] = 'Missing course or lesson ID.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// NOTE: DB credentials are hardcoded here, assuming they are correct locally.
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'coinflow_academy_db'; 

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) {
    $response['message'] = 'Database connection failed: ' . $conn->connect_error;
    ob_end_clean();
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();
$score = 0;
$passed = false;
$correct_answers_map = []; 
$unlocked_next_tier_id = null; // Variable to hold the result of the new function

try {
    // 1. Fetch rewards and current star_points_earned
    $query_lesson = "
        SELECT 
            l.skill_points_reward, up.star_points_earned, c.tier_id
        FROM Course_Lessons l
        JOIN User_Course_Progress up ON l.course_id = up.course_id AND up.user_id = ?
        JOIN Courses c ON l.course_id = c.course_id
        WHERE l.lesson_id = ? 
    ";
    $stmt = $conn->prepare($query_lesson);
    $stmt->bind_param("ii", $user_id, $lesson_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$data) {
        throw new Exception("Lesson data or user progress not found.");
    }

    $skill_reward = intval($data['skill_points_reward']);
    $total_star_points_earned = intval($data['star_points_earned']);
    $current_tier_id = intval($data['tier_id']);

    // 2. Grade the Quiz by querying the new Quiz_Questions table
    $query_grading = "
        SELECT correct_option, quiz_question_id
        FROM Quiz_Questions 
        WHERE lesson_id = ?
        ORDER BY quiz_question_id ASC
    ";
    
    $stmt_grading = $conn->prepare($query_grading);
    $stmt_grading->bind_param("i", $lesson_id);
    $stmt_grading->execute();
    $result_grading = $stmt_grading->get_result();
    
    $i = 1; // Sequential counter for submitted answers (q1_answer, q2_answer, etc.)
    while ($row = $result_grading->fetch_assoc()) {
        $correct_a = strtoupper(trim($row['correct_option'])); 
        $correct_answers_map[$row['quiz_question_id']] = $correct_a; 
        
        $submitted_key = 'q' . $i . '_answer';
        $submitted_value = trim($_POST[$submitted_key] ?? ''); 
        
        if (!empty($submitted_value) && strtoupper($submitted_value) === $correct_a) {
            $score++;
        }
        $i++;
    }
    $stmt_grading->close();


    // 3. Determine Pass/Fail Status and Rewards
    if ($score >= $pass_threshold) {
        $passed = true;
        
        $stmt = $conn->prepare("SELECT status FROM User_Course_Progress WHERE user_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute();
        $progress_status = $stmt->get_result()->fetch_assoc()['status'] ?? 'Locked';
        $stmt->close();

        if ($progress_status !== 'Completed') {
            // A. Award Skill and Leaderboard Points
            $leaderboard_reward = $total_star_points_earned;

            $stmt = $conn->prepare("
                UPDATE User_Stats 
                SET 
                    skill_points = skill_points + ?, 
                    leaderboard_points = leaderboard_points + ?
                WHERE user_id = ?
            ");
            $stmt->bind_param("iii", $skill_reward, $leaderboard_reward, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // B. Mark Course as Completed (FIXED BINDING)
            $progress_percentage = 100;
            $status = 'Completed';
            
            $stmt = $conn->prepare("
                UPDATE User_Course_Progress
                SET 
                    status = ?,
                    progress_percentage = ?,
                    completed_at = NOW() 
                WHERE user_id = ? AND course_id = ?
            ");
            // The query needs 4 variables. Types: s (status), i (progress_percentage), i (user_id), i (course_id)
            $stmt->bind_param("siii", $status, $progress_percentage, $user_id, $course_id); 
            $stmt->execute();
            $stmt->close();
            
            // C. CRITICAL NEW LOGIC: Check for and unlock next tier courses
            $unlocked_next_tier_id = unlock_next_tier_courses($conn, $user_id, $course_id, $current_tier_id);
            
            // Send back the reward data and the next tier ID
            $response['earned_skill_points'] = $skill_reward;
            $response['earned_leaderboard_points'] = $leaderboard_reward;
            $response['next_tier'] = $unlocked_next_tier_id ?? $current_tier_id + 1; // Send next expected tier ID
            
        } else {
            // Already completed, return 0 rewards
            $response['earned_skill_points'] = 0;
            $response['earned_leaderboard_points'] = 0;
            $response['next_tier'] = $current_tier_id + 1;
        }
    } else {
        // Fail state, return 0 rewards
        $response['earned_skill_points'] = 0;
        $response['earned_leaderboard_points'] = 0;
        $response['next_tier'] = $current_tier_id + 1; // Default next tier ID
    }
    
    // Commit the transaction
    $conn->commit();

    $response['success'] = true;
    $response['passed'] = $passed;
    $response['score'] = $score;
    $response['message'] = $passed ? 'Quiz passed!' : 'Quiz failed.';
    // NOTE: We do NOT send correct answers back if failed, only if passed.
    $response['correct_answers'] = $passed ? $correct_answers_map : null; 

} catch (Exception $e) {
    $conn->rollback();
    // Return the specific exception message for front-end diagnosis
    $response['message'] = 'Critical Transaction Failure: ' . $e->getMessage() . ' on line ' . $e->getLine();
}

$conn->close();

ob_end_clean();
echo json_encode($response);