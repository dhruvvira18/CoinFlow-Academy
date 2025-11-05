<?php
// view_lessons.php
// Shows the roadmap (9 lessons + quiz) for a given course.
// Assumptions:
// - setup.php creates $conn (mysqli) and starts session
// - content.php exists (handles lesson display & marking completion)
// - Tables follow your schema: Courses, Course_Lessons, User_Lesson_Completion, User_Course_Progress, User_Stats

require_once __DIR__ . '/../setup.php'; // adjust if your structure differs

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'coinflow_academy_db';
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Ensure session and user
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    // Not logged in -> redirect to login
    header('Location: ../account/login.html');
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Get course_id from query
if (!isset($_GET['course_id']) || !is_numeric($_GET['course_id'])) {
    echo "Invalid course selected.";
    exit;
}
$course_id = intval($_GET['course_id']);

// --- Fetch course metadata ---
$stmt = $conn->prepare("SELECT course_name, tier_id, total_lessons FROM Courses WHERE course_id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$res = $stmt->get_result();
$course = $res->fetch_assoc();
$stmt->close();

if (!$course) {
    echo "Course not found.";
    exit;
}
$course_name = $course['course_name'];
$total_lessons_config = intval($course['total_lessons']); // typically 10 (9 lessons + 1 quiz)
// We'll assume lesson_index 1..(total_lessons_config-1) are lessons and last index is quiz

// --- Fetch all lessons for this course ordered by lesson_index ---
$stmt = $conn->prepare("SELECT lesson_id, lesson_index, lesson_title, star_points_reward FROM Course_Lessons WHERE course_id = ? ORDER BY lesson_index ASC");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$res = $stmt->get_result();

$lessons = [];

while ($row = $res->fetch_assoc()) {
    $lessons[intval($row['lesson_index'])] = $row; // keyed by lesson_index
}
$stmt->close();

if (count($lessons) === 0) {
    echo "No lessons found for this course yet.";
    exit;
}

// --- Fetch completed lessons for this user & course ---
$lessonIds = array_column($lessons, 'lesson_id'); // numeric-indexed list
$completedLessons = []; // will hold lesson_index => true

if (!empty($lessonIds)) {
    // build placeholders for IN clause safely using prepared statement
    // Simpler query to get completed lessons for this user & course
    $stmt = $conn->prepare("
    SELECT cl.lesson_index 
    FROM User_Lesson_Completion ulc
    JOIN Course_Lessons cl ON ulc.lesson_id = cl.lesson_id
    WHERE ulc.user_id = ? AND cl.course_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    $r2 = $stmt->get_result();
    while ($row = $r2->fetch_assoc()) {
        $completedLessons[intval($row['lesson_index'])] = true;
    }
    // prepend types string
    // build final bind_param call:
    // but easier approach: run a simpler query with user_id + course_id to get completed lesson_indexes
    $stmt->close();
    $stmt = $conn->prepare("SELECT cl.lesson_index FROM User_Lesson_Completion ulc JOIN Course_Lessons cl ON ulc.lesson_id = cl.lesson_id WHERE ulc.user_id = ? AND cl.course_id = ?");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    $r2 = $stmt->get_result();
    while ($row = $r2->fetch_assoc()) {
        $completedLessons[intval($row['lesson_index'])] = true;
    }
    $stmt->close();
}

// --- Fetch user course status (Locked/Unlocked/InProgress/Completed) if exists ---
$course_status = 'Locked';
$stmt = $conn->prepare("SELECT status, progress_percentage FROM User_Course_Progress WHERE user_id = ? AND course_id = ?");
$stmt->bind_param("ii", $user_id, $course_id);
$stmt->execute();
$r3 = $stmt->get_result();
if ($row = $r3->fetch_assoc()) {
    $course_status = $row['status'];
    $progress_percentage = $row['progress_percentage'];
} else {
    // If no row exists, you might want to treat Tier 1 as unlocked by default.
    // But default behavior: locked. (Skill tree should have created rows earlier.)
    $course_status = 'Locked';
    $progress_percentage = 0;
}
$stmt->close();

// --- Determine next unlock (first lesson index not completed) ---
$all_indexes = array_keys($lessons);
sort($all_indexes, SORT_NUMERIC);

$next_unlocked_index = null;
foreach ($all_indexes as $idx) {
    if (!isset($completedLessons[$idx])) {
        $next_unlocked_index = intval($idx);
        break;
    }
}
// If all completed, next_unlocked_index will be null (means course finished)

// --- Compute completed count and progress ---
$total_checkpoints = count($all_indexes); // should equal total_lessons_config (e.g., 10)
$completed_count = count($completedLessons);
$progress_pct_calc = intval(($completed_count / max(1, $total_checkpoints)) * 100);

// --- Render HTML ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($course_name) ?> — Lessons | CoinFlow Academy</title>
    <link rel="stylesheet" href="../global_styles.css">
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* small fallback styles if lessons/style.css missing */
        .container { max-width:1100px; margin:30px auto; padding:20px; }
        .roadmap { display:flex; flex-direction:column; gap:12px; }
        .checkpoint { display:flex; align-items:center; gap:12px; padding:12px; border-radius:10px; border:1px solid rgba(255,255,255,0.06); }
        .checkpoint.locked { opacity:0.45; filter:grayscale(40%); }
        .checkpoint.completed { border-color: #27ae60; box-shadow: 0 0 10px rgba(39,174,96,0.12); }
        .checkpoint.current { border-color: #f9c4b6; box-shadow: 0 0 10px rgba(249,196,182,0.12); }
        .checkpoint .title { font-size:1.1rem; color:var(--text-color); }
        .checkpoint .meta { margin-left:auto; }
        .header { display:flex; align-items:center; gap:16px; margin-bottom:20px; }
        .progressbar { height:12px; background:#222; border-radius:8px; overflow:hidden; width:100%; }
        .progressbar > .fill { height:100%; background:linear-gradient(90deg,var(--tertiary-accent-color),var(--quaternary-accent-color)); width:<?= $progress_pct_calc ?>%; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1 style="margin:0; color:var(--primary-accent-color);"><?= htmlspecialchars($course_name) ?></h1>
            <div style="color:var(--text-color); opacity:0.9;">Course roadmap — <?= $completed_count ?> / <?= $total_checkpoints ?> completed</div>
        </div>
        <div style="margin-left:auto;">
            <a href="../skill_tree/skill_tree.php" class="btn" style="text-decoration:none;">← Back to Skill Tree</a>
        </div>
    </div>

    <div style="margin-bottom:14px;">
        <div class="progressbar" aria-hidden="true">
            <div class="fill" role="progressbar" aria-valuenow="<?= $progress_pct_calc ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>

    <div class="roadmap">
        <?php foreach ($all_indexes as $idx):
            $lesson = $lessons[$idx];
            $isCompleted = isset($completedLessons[$idx]);
            // If course_status is Locked, all lessons disabled
            $isUnlocked = ($course_status !== 'Locked') && ($isCompleted || $idx === $next_unlocked_index);
            // If next_unlocked_index is null and all completed, allow viewing but mark completed
            if ($next_unlocked_index === null) { // all completed
                $isUnlocked = true;
            }
            $isQuiz = intval($idx) === max($all_indexes); // last index treat as quiz
        ?>
            <div class="checkpoint <?= $isCompleted ? 'completed' : ($isUnlocked ? 'current' : 'locked') ?>">
                <div style="width:48px; text-align:center;">
                    <?php if ($isCompleted): ?>
                        <img src="../images/vault_overflowing.png" alt="done" style="height:36px;">
                    <?php elseif ($isUnlocked): ?>
                        <img src="../images/vault_unlocked.png" alt="open" style="height:36px;">
                    <?php else: ?>
                        <img src="../images/vault_locked.png" alt="locked" style="height:36px;">
                    <?php endif; ?>
                </div>

                <div class="title">
                    <strong>
                        <?= $isQuiz ? 'Quiz:' : 'Lesson ' . intval($idx) . ':' ?>
                    </strong>
                    <?= htmlspecialchars(preg_replace('/^Lesson\s*\d+:\s*/i', '', $lesson['lesson_title'])) ?>
                </div>

                <div class="meta">
                    <?php if ($isCompleted): ?>
                        <span style="color: #27ae60;">✔ Completed</span>
                    <?php elseif ($isUnlocked): ?>
                        <?php if ($isQuiz): ?>
                            <a class="btn" href="quiz.php?course_id=<?= $course_id ?>" style="text-decoration:none;">Start Quiz</a>
                        <?php else: ?>
                            <a class="btn" href="content.php?course_id=<?= $course_id ?>&lesson_id=<?= $lesson['lesson_id'] ?>" style="text-decoration:none;">Start Lesson</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--secondary-accent-color);">Locked</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
