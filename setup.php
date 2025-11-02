<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$star_points = '???';
$skill_points = '???';
$username = 'Guest';

// DATABASE CONFIGURATION
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'coinflow_academy_db'; 

if ($user_id) {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    // Check connection
    if ($mysqli->connect_errno) {
        error_log("Failed to connect to MySQL: " . $mysqli->connect_error);
    } else {
        // --- QUERY A: Get Currency Stats ---
        $stmt_stats = $mysqli->prepare("SELECT star_points, skill_points FROM User_Stats WHERE user_id = ?");
        $stmt_stats->bind_param("i", $user_id); 
        $stmt_stats->execute();
        
        $stmt_stats->bind_result($fetched_star_points, $fetched_skill_points);
        
        if ($stmt_stats->fetch()) {
            $star_points = number_format($fetched_star_points);
            $skill_points = number_format($fetched_skill_points);
        }
        $stmt_stats->close();


        // --- QUERY B: Get Username from Users table ---
        $stmt_user = $mysqli->prepare("SELECT username FROM Users WHERE user_id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $stmt_user->bind_result($fetched_username);
        
        if ($stmt_user->fetch()) {
            $username = htmlspecialchars($fetched_username);
        }
        $stmt_user->close();
        
        $mysqli->close();
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>