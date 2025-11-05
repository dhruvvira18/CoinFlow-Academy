<?php
session_start();
require_once('../setup.php');

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- FETCH USER DETAILS ---
$userQuery = "SELECT username FROM Users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userResult = $stmt->get_result()->fetch_assoc();
$username = $userResult['username'] ?? "User";

// --- FETCH LAST LESSON COMPLETED ---
$lessonQuery = "
    SELECT 
        c.course_name, 
        l.lesson_title, 
        l.lesson_id, 
        c.course_id
    FROM User_Lesson_Completion ulc
    JOIN course_lessons l ON ulc.lesson_id = l.lesson_id
    JOIN Courses c ON l.course_id = c.course_id
    WHERE ulc.user_id = ?
    ORDER BY ulc.user_lesson_id DESC
    LIMIT 1;
";
$stmt = $conn->prepare($lessonQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$lessonResult = $stmt->get_result()->fetch_assoc();

$lastLesson = $lessonResult['lesson_title'] ?? "No lessons completed yet";
$lastCourse = $lessonResult['course_name'] ?? "Start your first lesson!";
$nextLessonLink = isset($lessonResult['lesson_id'])
    ? "../courses/view_lesson.php?course_id=" . urlencode($lessonResult['course_id']) . "&lesson_id=" . urlencode($lessonResult['lesson_id'])
    : "../courses/courses.php"; // Changed default link to courses page

// --- FETCH USER RANK ---
$rankQuery = "
    SELECT COUNT(*) + 1 AS user_rank
    FROM User_Stats
    WHERE leaderboard_points > (SELECT leaderboard_points FROM User_Stats WHERE user_id = ?)
";
$stmt = $conn->prepare($rankQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rankResult = $stmt->get_result()->fetch_assoc();
$userRank = $rankResult['user_rank'] ?? 1;

// --- FETCH USER PROGRESS ---
$statsQuery = "SELECT skill_points FROM User_Stats WHERE user_id = ?";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$skillPoints = $stats['skill_points'] ?? 0;
$progressPercent = min(100, ($skillPoints / 5000) * 100);

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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../global_styles.css">
    <link rel="stylesheet" href="./style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<?php include("../global_navigation.php"); ?>

<main class="dashboard-container">
    <h1 class="welcome-header">Welcome back, <?php echo htmlspecialchars($username); ?></h1>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card jump-back-card p-5">
                <h5>Jump Back In</h5>
                <p class="lesson-title"><?php echo htmlspecialchars($lastLesson); ?></p>
                <p class="course-name"><?php echo htmlspecialchars($lastCourse); ?></p>
                <a href="<?php echo htmlspecialchars($nextLessonLink); ?>" class="resume-btn mt-3">Continue Learning →</a>
            </div>
        </div>
    </div>

    <div class="row g-4 justify-content-center mt-4">
        <div class="col-lg-6 col-md-8">
            <div class="card p-4 progress-container">
                <h5>Progress to Grandmaster</h5>
                <div class="progress mt-3">
                    <div class="progress-bar" style="width: <?php echo $progressPercent; ?>%;"></div>
                </div>
                <p class="mt-2 text-center"><?php echo $rankName; ?> (<?php echo round($progressPercent, 1); ?>%)</p>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-5 justify-content-center">
        <div class="col-lg-5 col-md-6 mini-leaderboard">
            <div class="card p-4 text-center">
                <h5>Your Leaderboard Rank</h5>
                <h2 class="display-5 text-accent">#<?php echo $userRank; ?></h2>
                <p>Keep learning to climb higher!</p>
                <a href="../leaderboard/leaderboard.php" class="resume-btn mt-2">View Full Leaderboard</a>
            </div>
        </div>

        <div class="col-lg-5 col-md-6">
            <div class="card p-4 quick-links text-center">
                <h5>Quick Links</h5>
                <button onclick="window.location.href='../skill_tree/skill_tree.php'">Skill Tree</button>
                <button onclick="window.location.href='../leaderboard/leaderboard.php'">Leaderboard</button>
                <button onclick="window.location.href='../account/logout_handler.php'">Logout</button>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../global_navigation.js"></script>

</body>
</html>