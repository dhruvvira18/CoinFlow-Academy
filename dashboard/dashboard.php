<?php
session_start();
require_once('../setup.php');

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- FETCH USER DETAILS ---
$userQuery = "SELECT username FROM Users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userResult = $stmt->get_result()->fetch_assoc();
$username = $userResult['username'] ?? "User";

// --- FETCH USER STATS ---
$statsQuery = "SELECT star_points, skill_points, leaderboard_points FROM User_Stats WHERE user_id = ?";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$starPoints = $stats['star_points'] ?? 0;
$skillPoints = $stats['skill_points'] ?? 0;
$leaderboardPoints = $stats['leaderboard_points'] ?? 0;

// --- FETCH LAST LESSON COMPLETED ---
$lessonQuery = "
    SELECT c.course_name, l.lesson_name, l.lesson_id, c.course_id
    FROM User_Lesson_Completion ulc
    JOIN Lessons l ON ulc.lesson_id = l.lesson_id
    JOIN Courses c ON l.course_id = c.course_id
    WHERE ulc.user_id = ?
    ORDER BY ulc.completed_at DESC
    LIMIT 1;
";
$stmt = $conn->prepare($lessonQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$lessonResult = $stmt->get_result()->fetch_assoc();

$lastLesson = $lessonResult['lesson_name'] ?? "No lessons completed yet";
$lastCourse = $lessonResult['course_name'] ?? "";
$nextLessonLink = isset($lessonResult['lesson_id']) ? "../courses/view_lesson.php?course_id={$lessonResult['course_id']}&lesson_id={$lessonResult['lesson_id']}" : "#";

// --- FETCH MINI LEADERBOARD PREVIEW ---
$leaderboardQuery = "
    SELECT u.username, s.leaderboard_points
    FROM Users u
    JOIN User_Stats s ON u.user_id = s.user_id
    ORDER BY s.leaderboard_points DESC
    LIMIT 5;
";
$leaderboardResult = $conn->query($leaderboardQuery);

// --- CALCULATE PROGRESS TO GRANDMASTER ---
$progressPercent = min(100, ($skillPoints / 5000) * 100); // 5000 skill points = Grandmaster
$rankName = "Novice";
if ($progressPercent >= 80) $rankName = "Grandmaster";
elseif ($progressPercent >= 60) $rankName = "Expert";
elseif ($progressPercent >= 40) $rankName = "Pro";
elseif ($progressPercent >= 20) $rankName = "Intermediate";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CoinFlow Academy</title>

    <link rel="stylesheet" href="../global_styles.css">
    <link rel="stylesheet" href="./style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Custom Dashboard Styling */
        body {
            background-color: var(--secondary-background-color);
            color: var(--text-color);
            font-family: var(--font-family);
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 20px;
        }

        .welcome-header {
            text-align: center;
            margin-bottom: 40px;
            color: var(--tertiary-accent-color);
            text-shadow: 0 0 10px rgba(250, 151, 130, 0.6);
        }

        .card {
            background-color: var(--secondary-background-color);
            border: 2px solid var(--primary-accent-color);
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(250, 151, 130, 0.2);
            transition: all 0.3s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 25px rgba(250, 151, 130, 0.4);
        }

        .stat-card {
            text-align: center;
            padding: 20px;
        }

        .progress-container {
            background-color: var(--primary-background-color);
            border-radius: 20px;
            padding: 15px;
        }

        .progress {
            height: 25px;
            border-radius: 15px;
            background-color: rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg,
                var(--primary-accent-color),
                var(--secondary-accent-color),
                var(--tertiary-accent-color)
            );
            height: 100%;
            transition: width 1s ease-in-out;
        }

        .mini-leaderboard table {
            color: var(--text-color);
        }

        .jump-back-card h5 {
            color: var(--secondary-accent-color);
        }

        .resume-btn {
            background: var(--primary-accent-color);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 8px 20px;
            margin-top: 10px;
            transition: background 0.3s ease;
        }

        .resume-btn:hover {
            background: var(--tertiary-accent-color);
        }

        .quick-links button {
            background: transparent;
            border: 2px solid var(--secondary-accent-color);
            color: var(--text-color);
            padding: 10px 15px;
            border-radius: 12px;
            margin: 5px;
            transition: 0.3s;
        }

        .quick-links button:hover {
            background: var(--secondary-accent-color);
            color: #000;
        }
    </style>
</head>
<body>

<?php include("../global_navigation.php"); ?>

<div class="dashboard-container">
    <h1 class="welcome-header">Welcome back, <?php echo htmlspecialchars($username); ?> 👋</h1>

    <div class="row g-4">
        <!-- Jump Back In -->
        <div class="col-md-6">
            <div class="card jump-back-card p-4">
                <h5>📚 Jump Back In</h5>
                <p><strong><?php echo htmlspecialchars($lastLesson); ?></strong></p>
                <small><?php echo htmlspecialchars($lastCourse); ?></small><br>
                <a href="<?php echo $nextLessonLink; ?>" class="resume-btn">Continue Learning →</a>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="col-md-6">
            <div class="card p-4 progress-container">
                <h5>🎯 Progress to Grandmaster</h5>
                <div class="progress mt-3">
                    <div class="progress-bar" style="width: <?php echo $progressPercent; ?>%;"></div>
                </div>
                <p class="mt-2 text-center"><?php echo $rankName; ?> (<?php echo round($progressPercent, 1); ?>%)</p>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mt-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <h4>⭐ Star Points</h4>
                <h2><?php echo $starPoints; ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <h4>💡 Skill Points</h4>
                <h2><?php echo $skillPoints; ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <h4>🏆 Leaderboard Points</h4>
                <h2><?php echo $leaderboardPoints; ?></h2>
            </div>
        </div>
    </div>

    <!-- Mini Leaderboard + Quick Links -->
    <div class="row g-4 mt-5">
        <div class="col-md-6 mini-leaderboard">
            <div class="card p-4">
                <h5>🏅 Top 5 Players</h5>
                <table class="table mt-3">
                    <thead>
                        <tr><th>User</th><th>Points</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $leaderboardResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo $row['leaderboard_points']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <a href="../leaderboard/leaderboard.php" class="resume-btn mt-2">View Full Leaderboard</a>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-4 quick-links">
                <h5>⚡ Quick Links</h5>
                <button onclick="window.location.href='../skill_tree/skill_tree.php'">Skill Tree</button>
                <button onclick="window.location.href='../leaderboard/leaderboard.php'">Leaderboard</button>
                <button onclick="window.location.href='../account/logout_handler.php'">Logout</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
