<?php
require_once __DIR__ . '/../setup.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../account/login.html');
    exit;
}
// Use user_id from session for security
$user_id = $_SESSION['user_id'];

if (!isset($_GET['course_id']) || !isset($_GET['lesson_id'])) {
    // Redirect with error message if parameters are missing
    header('Location: ../account/message_display.php?type=error&message=Missing+course+or+lesson+parameters.&return_page=../skill_tree/skill_tree.php');
    exit;
}

$course_id = intval($_GET['course_id']);
$lesson_id = intval($_GET['lesson_id']);

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) die("DB connection failed");

// Fetch Lesson Content and Rewards
$query = "
    SELECT 
        l.lesson_id, l.course_id, l.lesson_index, l.lesson_title,
        l.lesson_definition, l.lesson_explanation, l.lesson_application,
        l.question, l.option_a, l.option_b, l.option_c, l.option_d, l.correct_option,
        l.star_points_reward, l.skill_points_reward, l.leaderboard_points_reward,
        c.course_name
    FROM Course_Lessons l
    JOIN Courses c ON l.course_id = c.course_id
    WHERE l.course_id = ? AND l.lesson_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $course_id, $lesson_id);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lesson) {
    // Lesson not found -> redirect to roadmap
    header("Location: view_lesson.php?course_id={$course_id}");
    exit;
}

// Fetch Total Lessons
$stmt = $conn->prepare("SELECT COUNT(*) AS total_lessons FROM Course_Lessons WHERE course_id=?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$total_lessons = intval($stmt->get_result()->fetch_assoc()['total_lessons']);
$stmt->close();

// --- NEW/FIXED PHP VARIABLES ---
$current_lesson_index = intval($lesson['lesson_index']); // Index of the current lesson (1-10)
$last_regular_lesson_index = $total_lessons - 1; // Assumes Lesson 10 is the quiz, so Lesson 9 is the last regular one.
$is_last_regular_lesson = ($current_lesson_index === $last_regular_lesson_index);

// Find Next Lesson ID
$stmt = $conn->prepare("SELECT lesson_id FROM Course_Lessons WHERE course_id=? AND lesson_index=?");
$next_index = $current_lesson_index + 1;
$stmt->bind_param("ii", $course_id, $next_index);
$stmt->execute();
$next = $stmt->get_result()->fetch_assoc();
$next_lesson_id = $next['lesson_id'] ?? null;
$stmt->close();

$conn->close();

$base_points = intval($lesson['star_points_reward']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($lesson['lesson_title']) ?> | CoinFlow Academy</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../global_styles.css">
<style>
body {
    background: var(--primary-background-color); 
    color: var(--text-color);
    font-family: var(--font-family);
    margin: 0;
    padding: 0;
}

.header-bar {
    background: var(--secondary-background-color); 
    color: var(--text-color);
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--primary-accent-color);
}

.header-bar h4 {
    font-weight: bold;
    letter-spacing: 0.5px;
    margin: 0;
    color: var(--tertiary-accent-color);
}

.header-bar a {
    background: var(--tertiary-accent-color);
    color: var(--primary-accent-color);
    border: 1px solid var(--primary-accent-color);
    border-radius: 8px;
    padding: 6px 14px;
    text-decoration: none;
    transition: 0.2s;
    font-weight: bold;
}

.header-bar a:hover {
    background: var(--quaternary-accent-color);
    color: var(--primary-accent-color);
}

/* Tracker */
.progress-tracker {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    padding: 25px 10px;
    background: var(--secondary-background-color);
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

#modalNextLessonBtn{
    width: 100%;
    display: block;
    text-align: center;
}

.node {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    background: rgba(255,255,255,0.08);
    color: var(--text-color);
    transition: 0.3s;
    box-shadow: inset 0 0 10px rgba(0,0,0,0.3);
}

.node.active {
    background: var(--quaternary-accent-color);
    color: #121212; 
    box-shadow: 0 0 20px var(--quaternary-accent-color);
    transform: scale(1.15);
}

/* Lesson content */
.lesson-content {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 40px 60px;
}

.lesson-title {
    color: var(--primary-accent-color);
    font-size: 2.5rem;
    font-weight: bold;
    text-align: center;
    margin-bottom: 40px;
    letter-spacing: 1px;
    font-family: var(--font-family);
}

.section {
    margin-bottom: 35px;
    background: var(--secondary-background-color);
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.section h5 {
    font-weight: bold;
    color: var(--quaternary-accent-color);
    border-left: 5px solid var(--quaternary-accent-color);
    padding-left: 15px;
    margin-bottom: 15px;
    font-size: 1.8rem;
    font-family: var(--font-family);
}

.section p {
    line-height: 1.9;
    color: var(--text-color);
    font-size: 1.2rem;
    font-family: var(--font-family);
}

/* Quiz */
.quiz-box {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 25px;
}

.quiz-option {
    background: rgba(255,255,255,0.08);
    padding: 15px;
    border-radius: 8px;
    margin-top: 12px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 1.25rem;
}

.quiz-option:hover {
    background: var(--quaternary-accent-color);
    color: #121212; 
}

.quiz-option.selected {
    background: var(--tertiary-accent-color);
    color: #121212;
    border: 2px solid var(--primary-accent-color);
}

.btn-main {
    background: var(--tertiary-accent-color);
    border: none;
    color: var(--primary-accent-color);
    font-weight: bold;
    border-radius: 10px;
    padding: 12px 25px;
    font-size: 1.4rem;
    border: 2px solid var(--primary-accent-color);
    transition: all 0.3s;
}

.btn-main:hover {
    background: var(--quaternary-accent-color);
    color: var(--primary-accent-color);
}

/* Modal Content Styling (Reward Popup) */
.reward-popup {
    background: var(--secondary-background-color);
    color: var(--text-color);
    border-radius: 15px;
    padding: 40px;
    box-shadow: 0 0 30px var(--quaternary-accent-color);
    border: 3px solid var(--tertiary-accent-color);
}

/* New CSS for Alignment */
.reward-line {
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 1.5rem;
    margin-bottom: 5px;
}

.reward-details {
    display: flex; 
    align-items: center; 
    justify-content: flex-start; 
    width: 350px; 
}

.reward-value-container {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    min-width: 80px; 
    padding-right: 5px;
}

.reward-value-container .sign {
    color: var(--quaternary-accent-color);
    font-weight: bold;
    font-size: 1.2rem;
    margin-right: 3px;
}

.reward-value {
    font-weight: bold;
    font-size: 1.6rem;
    color: var(--tertiary-accent-color);
}

.reward-icon {
    height: 28px;
    width: 28px;
    margin: 0 5px;
}

.reward-description {
    text-align: left; 
    color: var(--text-color);
    margin-left: 5px;
}

.reward-popup h3 {
    color: var(--primary-accent-color);
    font-weight: bold;
    font-size: 2.2rem;
    margin-bottom: 20px;
    text-align: center; 
}

.reward-popup p.flavor-text {
    font-style: italic;
    color: var(--quaternary-accent-color);
    margin-bottom: 20px;
    text-align: center;
    font-size: 1.4rem;
}

</style>
</head>
<body>

<div class="header-bar">
    <a href="view_lesson.php?course_id=<?= $course_id ?>">← Back to Roadmap</a>
    <h4><?= htmlspecialchars($lesson['course_name']) ?></h4>
</div>

<div class="progress-tracker">
    <?php for ($i=1; $i<=$total_lessons; $i++): ?>
        <div class="node <?= $i == $lesson['lesson_index'] ? 'active' : '' ?>"><?= $i ?></div>
    <?php endfor; ?>
</div>

<div class="lesson-content">
    <h2 class="lesson-title"><?= htmlspecialchars($lesson['lesson_title']) ?></h2>

    <div class="section">
        <h5>Definition</h5>
        <p><?= nl2br($lesson['lesson_definition']) ?></p>
    </div>

    <div class="section">
        <h5>Explanation</h5>
        <p><?= nl2br($lesson['lesson_explanation']) ?></p>
    </div>

    <div class="section">
        <h5>Application (Game Scenario)</h5>
        <p><?= nl2br($lesson['lesson_application']) ?></p>
    </div>

    <div class="quiz-box">
        <h5>Quick Quiz</h5>
        <p><strong><?= htmlspecialchars($lesson['question']) ?></strong></p>
        <div id="quiz-options">
            <?php foreach (['A','B','C','D'] as $opt): ?>
                <div class="quiz-option" data-option="<?= $opt ?>"><?= $opt ?>. <?= htmlspecialchars($lesson['option_'.strtolower($opt)]) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 text-center">
            <button id="submitQuiz" class="btn-main">Submit Answer</button>
        </div>
        <div id="quiz-message" class="mt-3 text-center" style="font-weight:bold; font-size:1.1rem;"></div>
    </div>
</div>

<div class="modal fade" id="rewardModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content reward-popup">
      <h3>TREASURE VAULT LOOT!</h3>
      <p class="flavor-text">Checkpoint Mastered. Your rewards:</p>
      
      <div class="reward-line">
        <div class="reward-details">
            <div class="reward-value-container">
                <span id="earnedBase" class="reward-value"></span> 
            </div>
            <img class="reward-icon" src="../images/star_point_symbol.png" alt="Star Point Icon"> 
            <span class="reward-description text-white-50">Star Points (Base Reward)</span>
        </div>
      </div>
      
      <div id="bonusPointsLine" class="reward-line" style="display:none; color:var(--quaternary-accent-color);">
        <div class="reward-details">
            <div class="reward-value-container">
                <span class="sign">+</span><span id="earnedBonus" class="reward-value"></span> 
            </div>
            <img class="reward-icon" src="../images/star_point_symbol.png" alt="Star Point Icon"> 
            <span class="reward-description text-white-50">Star Points (Perfect Answer Bonus!)</span>
        </div>
      </div>
      
      <div id="skillPointsLine" class="reward-line" style="display:none; border-top: 1px dashed rgba(255,255,255,0.2); padding-top: 10px; margin-top: 10px;">
        <div class="reward-details">
            <div class="reward-value-container">
                <span id="earnedSkill" class="reward-value"></span> 
            </div>
            <img class="reward-icon" src="../images/skill_point_symbol.png" alt="Skill Point Icon"> 
            <span class="reward-description text-white-50">Skill Points (Tier Unlock Currency!)</span>
        </div>
      </div>
      
      <div id="leaderboardRewardSection" style="display:none; background: rgba(0,0,0,0.2); padding: 15px; margin-top: 15px; border-radius: 8px;">
          <h5 style="color: var(--tertiary-accent-color); margin-bottom: 5px; font-weight: bold; font-size: 1.6rem; text-align: center;">COURSE MASTERY BONUS:</h5>
          <div class="reward-line">
              <div class="reward-value-container">
                  <span id="earnedLeaderboard" class="reward-value" style="font-size: 1.8rem;"></span> 
              </div>
              <span class="reward-icon" style="height: 32px; width: 32px;"></span> 
              <span class="reward-description text-white">Leaderboard Points</span>
          </div>
      </div>
      
      <div class="mt-4">
        <?php if ($next_lesson_id): ?>
            <a id="modalNextLessonBtn" href="content.php?course_id=<?= $course_id ?>&lesson_id=<?= $next_lesson_id ?>" class="btn-main">Advance to Checkpoint <?= $next_index ?> →</a>
        <?php else: ?>
            <a id="modalViewSummaryBtn" href="view_lesson.php?course_id=<?= $course_id ?>" class="btn-main">Quest Complete! View Course Summary</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedOption = null;
const correctAnswer = "<?= strtoupper($lesson['correct_option']) ?>";
const basePoints = <?= $base_points ?>;
const lessonId = <?= $lesson_id ?>;
const courseId = <?= $course_id ?>;
const userId = <?= $user_id ?>;
const totalLessons = <?= $total_lessons ?>; // Total lessons (e.g., 10)
const isLastRegularLesson = <?= json_encode($is_last_regular_lesson) ?>; // True if current lesson index is 9

document.querySelectorAll('.quiz-option').forEach(opt => {
    opt.addEventListener('click', () => {
        document.querySelectorAll('.quiz-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        selectedOption = opt.dataset.option;
        document.getElementById('quiz-message').textContent = '';
    });
});

document.getElementById('submitQuiz').addEventListener('click', async () => {
    if (!selectedOption) {
        document.getElementById('quiz-message').innerHTML = '<span style="color:var(--primary-accent-color);">Select an answer before submitting.</span>';
        return;
    }
    
    // Disable button to prevent double-submission
    const submitButton = document.getElementById('submitQuiz');
    submitButton.disabled = true;
    submitButton.textContent = 'Lesson Completed';

    const isCorrect = selectedOption === correctAnswer;
    const bonusPoints = isCorrect ? Math.round(basePoints * 0.1) : 0;
    
    const messageElement = document.getElementById('quiz-message');
    
    // 1. Mark the quiz result visually
    if (isCorrect) {
        messageElement.innerHTML = '<span style="color:#27ae60;">Correct! Calculating rewards...</span>';
    } else {
        messageElement.innerHTML = '<span style="color:var(--secondary-accent-color);">Incorrect. The correct answer was ' + correctAnswer + '. Calculating rewards...</span>';
    }
    
    // 2. Send data to backend to update progress and rewards
    try {
        const response = await fetch('./update_progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                user_id: userId,
                course_id: courseId,
                lesson_id: lessonId,
                base_points: basePoints,
                bonus_points: bonusPoints
            })
        });
        
        const result = await response.json();

        if (result.success) {
            // 3. Update the modal with final rewards returned from the server
            const earnedStarPoints = result.earned_star_points; 
            const earnedSkillPoints = result.earned_skill_points; 
            const earnedLeaderboardPoints = result.earned_leaderboard_points; 
            
            // --- STAR POINTS DISPLAY FIX (Uses basePoints for display) ---
            document.getElementById('earnedBase').textContent = basePoints.toLocaleString();
            
            // BONUS DISPLAY
            if (bonusPoints > 0) {
                document.getElementById('bonusPointsLine').style.display = 'flex'; 
                document.getElementById('earnedBonus').textContent = bonusPoints.toLocaleString();
            } else {
                document.getElementById('bonusPointsLine').style.display = 'none';
            }
            
            // --- SKILL & LEADERBOARD POINTS DISPLAY (Only for Final Quiz) ---
            if (earnedSkillPoints > 0) {
                 document.getElementById('skillPointsLine').style.display = 'flex'; 
                 document.getElementById('earnedSkill').textContent = earnedSkillPoints.toLocaleString();

                 document.getElementById('leaderboardRewardSection').style.display = 'block';
                 document.getElementById('earnedLeaderboard').textContent = earnedLeaderboardPoints.toLocaleString();
            } else {
                 document.getElementById('skillPointsLine').style.display = 'none';
                 document.getElementById('leaderboardRewardSection').style.display = 'none';
            }
            
            // --- FIX: Change button action based on completion of Lesson 9 ---
            if (isLastRegularLesson) {
                // If this is the last regular lesson (Lesson 9), redirect to the quiz page.
                document.getElementById('modalNextLessonBtn').href = `quiz.php?course_id=${courseId}`;
                document.getElementById('modalNextLessonBtn').textContent = 'Advance to FINAL QUIZ →';
            }
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('rewardModal'));
            modal.show();

        } else {
            messageElement.innerHTML = '<span style="color:red;">Error updating progress: ' + result.message + '</span>';
            submitButton.disabled = false;
            submitButton.textContent = 'Submit Answer';
        }
    } catch (error) {
        messageElement.innerHTML = '<span style="color:red;">Network Error: Could not reach the server.</span>';
        console.error('Progress update failed:', error);
        submitButton.disabled = false;
        submitButton.textContent = 'Submit Answer';
    }
});
</script>

</body>
</html>