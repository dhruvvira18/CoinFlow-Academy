<?php
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root'; 
$DB_PASS = '';
$DB_NAME = 'coinflow_academy_db'; 

function handle_error_redirect($message) {
    $encoded_message = urlencode($message);
    // Redirect to register.html on error
    header("Location: message_display.php?type=error&message={$encoded_message}&return_page=register.html");
    exit();
}

function handle_success_redirect($message) {
    $encoded_message = urlencode($message);
    // Redirect to dashboard.php on success
    header("Location: message_display.php?type=success&message={$encoded_message}");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    handle_error_redirect("Invalid request method.");
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// SERVER-SIDE VALIDATION
// Password Match Check
if ($password !== $confirm_password) {
    handle_error_redirect("Password and Confirm Password do not match.");
}

// Username Validation: Alphanumeric and underscore only
if (!preg_match('/^\w+$/', $username)) {
    handle_error_redirect("Username must contain only letters, numbers, and underscores.");
}

// Email Validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    handle_error_redirect("Email address is not in a valid format.");
}

// Password Strength Validation: At least 8 chars, one uppercase, one lowercase, one number, one special character
$password_regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9\s]).{8,}$/';
if (!preg_match($password_regex, $password)) {
    handle_error_redirect("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.");
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);


// Database Connection
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_error) {
    handle_error_redirect("Database connection failed: " . $mysqli->connect_error);
}

$mysqli->begin_transaction();

try {
    // Username uniqueness check
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        throw new Exception("The username '{$username}' is already taken. Please choose another.");
    }
    
    $stmt = $mysqli->prepare("INSERT INTO Users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password_hash);
    
    if (!$stmt->execute()) {
        throw new Exception("User insertion failed: " . $stmt->error);
    }
    $new_user_id = $mysqli->insert_id;
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO User_Stats (user_id, star_points, skill_points, leaderboard_points) VALUES (?, 0, 0, 0)");
    $stmt->bind_param("i", $new_user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("User stats initialization failed: " . $stmt->error);
    }
    $stmt->close();

    $courses_result = $mysqli->query("SELECT course_id, tier_id FROM Courses");
    
    if ($courses_result->num_rows === 0) {
        throw new Exception("Course constants data is missing. Cannot initialize progress. Ensure insert_course_data.sql has been run.");
    }

    $progress_insert_sql = "INSERT INTO User_Course_Progress (user_id, course_id, status, last_lesson_completed, progress_percentage) VALUES (?, ?, ?, 0, 0)";
    $stmt_progress = $mysqli->prepare($progress_insert_sql);
    
    while ($course = $courses_result->fetch_assoc()) {
        $course_id = $course['course_id'];
        $tier_id = $course['tier_id'];
        
        $status = ($tier_id == 1) ? 'Unlocked' : 'Locked';

        $stmt_progress->bind_param("iis", $new_user_id, $course_id, $status);
        if (!$stmt_progress->execute()) {
            throw new Exception("Course progress initialization failed for course ID {$course_id}: " . $stmt_progress->error);
        }
    }
    
    $stmt_progress->close();
    $courses_result->free();

    $mysqli->commit();
    
    // Log in user to session variable
    $_SESSION['user_id'] = $new_user_id;

    $success_message = "Registration successful! Welcome to CoinFlow Academy. You are now logged in.";
    handle_success_redirect($success_message);

} catch (Exception $e) {
    // If any step fails, roll back the transaction
    $mysqli->rollback();
    handle_error_redirect("Registration failed: " . $e->getMessage());
} finally {
    $mysqli->close();
}
?>
