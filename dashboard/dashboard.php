<?php
session_start();
require_once('../setup.php'); // DB connection

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/login.html");
    exit();
}

// --- FETCH USER DATA ---
$user_id = $_SESSION['user_id'];

// Fetch user info
$userQuery = $conn->prepare("SELECT username, email FROM Users WHERE user_id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userResult = $userQuery->get_result()->fetch_assoc();

// Fetch stats
$statsQuery = $conn->prepare("SELECT star_points, skill_points, leaderboard_points FROM User_Stats WHERE user_id = ?");
$statsQuery->bind_param("i", $user_id);
$statsQuery->execute();
$stats = $statsQuery->get_result()->fetch_assoc();

// Fetch progress (optional summary)
$progressQuery = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status='Completed' THEN 1 END) AS completed_courses,
        COUNT(*) AS total_courses
    FROM User_Course_Progress 
    WHERE user_id = ?");
$progressQuery->bind_param("i", $user_id);
$progressQuery->execute();
$progress = $progressQuery->get_result()->fetch_assoc();

$completed_percent = ($progress['total_courses'] > 0)
    ? round(($progress['completed_courses'] / $progress['total_courses']) * 100)
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CoinFlow Academy</title>

    <link rel="stylesheet" href="../global_styles.css">
    <link rel="stylesheet" href="./style.css">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include("../global_navigation.php"); ?>

    <div class="container text-center mt-5">
        <h1 class="text-accent">Welcome back, <?php echo htmlspecialchars($userResult['username']); ?>!</h1>
        <p class="text-muted">Here’s your progress summary:</p>

        <div class="row justify-content-center mt-4">
            <div class="col-md-3">
                <div class="card p-3 shadow">
                    <h4>⭐ Star Points</h4>
                    <h2><?php echo $stats['star_points']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow">
                    <h4>💡 Skill Points</h4>
                    <h2><?php echo $stats['skill_points']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow">
                    <h4>🏆 Leaderboard Points</h4>
                    <h2><?php echo $stats['leaderboard_points']; ?></h2>
                </div>
            </div>
        </div>

        <div class="mt-5">
            <h4>Your Learning Progress</h4>
            <div class="progress" style="height: 25px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $completed_percent; ?>%">
                    <?php echo $completed_percent; ?>%
                </div>
            </div>
        </div>

        <div class="mt-5">
            <a href="../skill_tree/skill_tree.php" class="btn btn-primary m-2">Go to Skill Tree</a>
            <a href="../account/logout_handler.php" class="btn btn-danger m-2">Logout</a>
        </div>
    </div>
</body>
</html>
