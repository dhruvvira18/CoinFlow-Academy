<?php
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root'; 
$DB_PASS = '';
$DB_NAME = 'coinflow_academy_db';

function handle_error_redirect($message) {
    $encoded_message = urlencode($message);
    // Redirect back to login.html on error
    header("Location: message_display.php?type=error&message={$encoded_message}&return_page=login.html");
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
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    handle_error_redirect("Please enter both username and password.");
}

// Database connection
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_error) {
    handle_error_redirect("Database connection failed: " . $mysqli->connect_error);
}

try {
    // Retrieve the ID and the hashed password from database
    $stmt = $mysqli->prepare("SELECT user_id, password_hash FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($user_id, $hashed_password);
    $stmt->fetch();
    $stmt->close();

    // Verify credentials
    if ($user_id && password_verify($password, $hashed_password)) { // Login successful
        $_SESSION['user_id'] = $user_id;

        $success_message = "Login successful! Welcome back, {$username}.";
        handle_success_redirect($success_message);
        
    } else { // Login failed
        throw new Exception("Invalid username or password.");
    }

} catch (Exception $e) {
    handle_error_redirect("Login failed: " . $e->getMessage());
} finally {
    $mysqli->close();
}
?>
