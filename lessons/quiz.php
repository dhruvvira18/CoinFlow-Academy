<?php
// quiz.php
// Dedicated page for the Final Quiz (Lesson Index 10)
require_once __DIR__ . '/../setup.php';

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../account/login.html');
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);

if ($course_id === 0) {
    header("Location: ../skill_tree/skill_tree.php");
    exit;
}

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'coinflow_academy_db';
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_errno) die("DB connection failed");

// --- Fetch Course Metadata ---
$stmt = $conn->prepare("SELECT course_name, total_lessons FROM Courses WHERE course_id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header("Location: ../skill_tree/skill_tree.php");
    exit;
}
$course_name = $course['course_name'];

// --- Fetch Final Quiz Lesson ID ---
$last_lesson_index = $course['total_lessons'];

$query = "SELECT lesson_id FROM Course_Lessons WHERE course_id = ? AND lesson_index = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $course_id, $last_lesson_index);
$stmt->execute();
$quiz_lesson_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz_lesson_data) {
    die("Final quiz lesson data not found.");
}
$lesson_id = $quiz_lesson_data['lesson_id'];

// --- Query the NEW Quiz_Questions table for question details ---
$stmt = $conn->prepare("
    SELECT 
        quiz_question_id, question_text, option_a, option_b, option_c, option_d
    FROM Quiz_Questions 
    WHERE lesson_id = ?
    ORDER BY quiz_question_id ASC
");
$stmt->bind_param("i", $lesson_id);
$stmt->execute();
$result_questions = $stmt->get_result();

$quiz_questions = [];
while ($row = $result_questions->fetch_assoc()) {
    $quiz_questions[] = [
        'question_id' => $row['quiz_question_id'], // PK used only for internal tracking
        'question_text' => htmlspecialchars(htmlspecialchars_decode($row['question_text'])),
        'options' => [
            'A' => htmlspecialchars(htmlspecialchars_decode($row['option_a'])),
            'B' => htmlspecialchars(htmlspecialchars_decode($row['option_b'])),
            'C' => htmlspecialchars(htmlspecialchars_decode($row['option_c'])),
            'D' => htmlspecialchars(htmlspecialchars_decode($row['option_d'])),
        ]
    ];
}

$stmt->close();
$conn->close();

if (count($quiz_questions) !== 5) {
     die("Error: Expected 5 questions in Quiz_Questions table but found " . count($quiz_questions));
}
// =========================================================================

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Final Quiz: <?= htmlspecialchars($course_name) ?> | CoinFlow Academy</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../global_styles.css">
<style>
/* Base Styling (omitted for brevity, assume the previous styles are here) */
body {
    background: var(--primary-background-color); 
    color: var(--text-color);
    font-family: var(--font-family);
}

.quiz-container {
    max-width: 900px;
    margin: 40px auto;
    padding: 0 20px 60px;
}

.quiz-header {
    color: var(--primary-accent-color);
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--secondary-accent-color);
    padding-bottom: 10px;
}
.question-card {
    background: var(--secondary-background-color);
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
}

.question-text {
    font-weight: bold;
    color: var(--quaternary-accent-color);
    font-size: 1.6rem;
    margin-bottom: 15px;
}
.quiz-option {
    background: rgba(255,255,255,0.08);
    padding: 12px;
    border-radius: 8px;
    margin-top: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
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
    border: 2px solid var(--primary-accent-color);
    color: var(--primary-accent-color);
    font-weight: bold;
    border-radius: 10px;
    padding: 12px 25px;
    font-size: 1.4rem;
    transition: all 0.3s;
    width: 100%;
}
.reward-popup {
    background: var(--secondary-background-color);
    color: var(--text-color);
    border-radius: 15px;
    padding: 40px;
    box-shadow: 0 0 30px var(--quaternary-accent-color);
    border: 3px solid var(--tertiary-accent-color);
}
.reward-line { display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 5px; }
.reward-details { display: flex; align-items: center; justify-content: flex-start; width: 350px; }
.reward-value-container { display: flex; justify-content: flex-end; align-items: center; min-width: 80px; padding-right: 5px; }
.reward-value { font-weight: bold; font-size: 1.6rem; color: var(--tertiary-accent-color); }
.reward-icon { height: 28px; width: 28px; margin: 0 5px; }
.reward-description { text-align: left; color: var(--text-color); margin-left: 5px; }
.reward-popup h3 { color: var(--primary-accent-color); font-weight: bold; font-size: 2.2rem; margin-bottom: 20px; text-align: center; }
.feedback-summary { font-size: 1.6rem; font-weight: bold; text-align: center; }
.feedback-message {
    font-size: 1.25rem;
    padding: 10px;
    margin-top: 15px;
    text-align: center;
    border-radius: 8px;
    color: var(--primary-accent-color); 
    background-color: transparent; 
}
@media (max-width: 768px) {
    .quiz-container { margin: 20px auto; padding: 0 10px 40px; }
    .reward-details { width: 100%; justify-content: center; flex-wrap: wrap; }
    .reward-line { flex-direction: column; }
}
</style>
</head>
<body>

<div class="quiz-container">
    <h1 class="quiz-header">Final Vault Challenge: <?= htmlspecialchars($course_name) ?></h1>
    <p id="instruction-text" style="text-align:center; color:var(--quaternary-accent-color);">
        Score 3 out of 5 correct to master the course and unlock the next Tier!
    </p>

    <form id="finalQuizForm">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <input type="hidden" name="lesson_id" value="<?= $lesson_id ?>">
        
        <?php foreach ($quiz_questions as $index => $q): ?>
            <div class="question-card" data-question-id="<?= $q['question_id'] ?>">
                <div class="question-text"><?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?></div>
                
                <?php foreach ($q['options'] as $key => $option_text): ?>
                    <?php if (!empty($option_text)): ?>
                        <div 
                            class="quiz-option" 
                            data-q-db-id="<?= $q['question_id'] ?>" 
                            data-option="<?= $key ?>"
                        >
                            <?= html_entity_decode($option_text) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <input type="hidden" name="q<?= $index + 1 ?>_answer" id="q<?= $index + 1 ?>_answer">
            </div>
        <?php endforeach; ?>

        <div id="submit-section" class="mt-5">
            <button type="submit" id="submitFinalQuiz" class="btn-main">Submit Final Quiz</button>
        </div>
        <div id="quiz-message" class="feedback-message" style="display:none;"></div>
        
    </form>
</div>

<div class="modal fade" id="rewardModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content reward-popup">
      <h3 id="modalTitle">VAULT CHALLENGE RESULT</h3>
      <p id="modalFeedback"></p>
      
      <div id="rewardSummary">
        </div>
      
      <div class="mt-4 text-center">
        <a id="modalActionBtn" href="../skill_tree/skill_tree.php" class="btn-main">Return to Skill Tree</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    const totalQuestions = 5; 
    const passThreshold = 3; 

    // Get an array of sequential question numbers (1 to 5)
    let questionIndexes = [];
    for (let i = 1; i <= totalQuestions; i++) {
        questionIndexes.push(i);
    }
    
    // 1. Handle Selection and Hidden Input Update
    $('.quiz-option').on('click', function() {
        const questionCard = $(this).closest('.question-card');
        const questionIndex = questionCard.find('.question-text').text().split('.')[0].trim(); // Get the 1, 2, 3... number
        const selectedOption = $(this).data('option');
        
        // Remove selection from siblings and add to current
        questionCard.find('.quiz-option').removeClass('selected');
        $(this).addClass('selected');

        // Update hidden input using the sequential index (e.g., q1_answer)
        $(`input[name="q${questionIndex}_answer"]`).val(selectedOption);
    });

    // 2. Handle Form Submission
    $('#finalQuizForm').on('submit', async function(e) {
        e.preventDefault();
        
        let unansweredCount = 0;
        
        // Loop over the sequential question indexes (1 to 5)
        questionIndexes.forEach(index => {
            const answer = $(`input[name="q${index}_answer"]`).val();
            if (!answer) {
                unansweredCount++;
            }
        });

        if (unansweredCount > 0) {
            $('#quiz-message').text(`Please answer all ${totalQuestions} questions before submitting.`).css('display', 'block');
            return;
        }
        
        const $submitBtn = $('#submitFinalQuiz');
        $submitBtn.prop('disabled', true).text('Grading...');
        $('#quiz-message').text('').css('display', 'none');

        try {
            const response = await fetch('quiz_handler.php', {
                method: 'POST',
                body: new URLSearchParams($(this).serialize()), 
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
            });
            
            const result = await response.json();
            
            // --- MODAL FEEDBACK LOGIC ---
            const $modal = new bootstrap.Modal(document.getElementById('rewardModal'));
            const $rewardSummary = $('#rewardSummary');
            $rewardSummary.empty();

            if (result.success) {
                const pass = result.passed;
                const score = result.score;
                
                $('#modalFeedback').text(`You scored ${score} out of ${totalQuestions}.`);

                if (pass) {
                    // PASS STATE
                    // 1. Generate Correct Answers List (REMOVED)
                    // The feature request asked to remove the display of correct answers on pass.
                    
                    // 2. Final Modal Content
                    $('#modalTitle').text('QUIZ COMPLETED! COURSE MASTERED!').css('color', '#27ae60');
                    $rewardSummary.html(`
                        <div class="feedback-summary text-success mt-4">
                            Mastery achieved! You earned crucial resources:
                        </div>
                        <div class="reward-line">
                            <div class="reward-details">
                                <span class="reward-value-container"><span class="reward-value">${result.earned_skill_points.toLocaleString()}</span></span> 
                                <img class="reward-icon" src="../images/skill_point_symbol.png" alt="Skill Point Icon" style="height: 28px; width: 28px;"> 
                                <span class="reward-description text-white-50">Skill Points (Next Tier Access)</span>
                            </div>
                        </div>
                        <div class="reward-line mt-2">
                            <div class="reward-details">
                                <span class="reward-value-container"><span class="reward-value">${result.earned_leaderboard_points.toLocaleString()}</span></span> 
                                <img class="reward-icon" src="../images/leaderboard_point_symbol.png" alt="Trophy Icon" style="height: 32px; width: 32px;"> 
                                <span class="reward-description text-white-50">Leaderboard Points (Course Mastered)</span>
                            </div>
                        </div>
                    `);
                    $('#modalActionBtn').text('GO TO SKILL TREE →').attr('href', '../skill_tree/skill_tree.php');

                } else {
                    // FAIL STATE (No correct options revealed)
                    $('#modalTitle').text('CHALLENGE FAILED. RETAKE REQUIRED.').css('color', 'var(--primary-accent-color)');
                    $('#modalFeedback').text(`Your score of ${score}/${totalQuestions} is below the ${passThreshold}/5 threshold. Study the lessons and try again!`);
                    
                    $rewardSummary.html('<div class="text-white-50">No rewards were granted. You must pass to earn Skill Points.</div>');
                    $('#modalActionBtn').text('GIVE QUIZ AGAIN').attr('href', window.location.href); 
                }
            } else {
                // Backend failure
                $('#quiz-message').html(`SYSTEM ERROR: ${result.message}`).css('display', 'block').css('color', 'red');
                $submitBtn.prop('disabled', false).text('Submit Final Quiz');
            }

            $modal.show();

        } catch (error) {
            console.error('Quiz submission failed:', error);
            $('#quiz-message').text('Network or JSON error. Check server status.').css('display', 'block').css('color', 'red');
            $submitBtn.prop('disabled', false).text('Submit Final Quiz');
        }
    });
});
</script>

</body>
</html>