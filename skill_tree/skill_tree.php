<?php
$pageTitle = 'Skill Tree';

// START Output Buffer
ob_start();
?>

<?php require_once '../global_navigation.php'; ?>
<?php
// Authentication Check
if (!$user_id) {
    // Redirect to login page if user is not authenticated
    header("Location: ../account/login.html");
    exit();
}

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Data Retrieval
// A. Fetch All Tiers
$tiers = [];
$result_tiers = $mysqli->query("SELECT tier_id, tier_name, prerequisite_tier_id FROM Course_Tiers ORDER BY tier_id");
while ($row = $result_tiers->fetch_assoc()) {
    $tiers[$row['tier_id']] = $row;
    $tiers[$row['tier_id']]['courses'] = [];
}
$result_tiers->free();

// B. Fetch All Courses (Vaults)
$courses = [];
$result_courses = $mysqli->query("SELECT course_id, tier_id, course_name, core_topic, skill_point_cost FROM Courses ORDER BY tier_id, course_id");
while ($row = $result_courses->fetch_assoc()) {
    $courses[$row['course_id']] = $row;
    if (isset($tiers[$row['tier_id']])) {
        $tiers[$row['tier_id']]['courses'][] = $row['course_id'];
    }
}
$result_courses->free();

// C. Fetch User Progress (If a record exists, the course is UNLOCKED)
$progress = [];
$stmt_progress = $mysqli->prepare("SELECT course_id, status, last_lesson_completed, progress_percentage FROM User_Course_Progress WHERE user_id = ?");
$stmt_progress->bind_param("i", $user_id);
$stmt_progress->execute();
$result_progress = $stmt_progress->get_result();
while ($row = $result_progress->fetch_assoc()) {
    $progress[$row['course_id']] = $row;
}
$stmt_progress->close();

// D. Get Total Lessons per Course (Used for course-specific logic and potential checks)
$total_lessons_map = [];
$result_lessons = $mysqli->query("SELECT course_id, COUNT(lesson_id) as total_lessons FROM Course_Lessons GROUP BY course_id");
while ($row = $result_lessons->fetch_assoc()) {
    $total_lessons_map[$row['course_id']] = max(1, (int)$row['total_lessons']);
}
$result_lessons->free();

$mysqli->close();


$raw_skill_points = str_replace(',', '', $skill_points);

// Core Logic Function to determine vault status and related display data
function get_vault_status($course, $progress, $tiers, $user_skill_points) {
    
    $course_id = $course['course_id'];

    $course_progress_record = $progress[$course_id] ?? null; 
    $db_status = $course_progress_record ? $course_progress_record['status'] : 'Locked';
    $progress_percent = $course_progress_record ? (int)$course_progress_record['progress_percentage'] : 0;

    $user_sp_int = (int)floatval($user_skill_points); 
    $cost_int = (int)$course['skill_point_cost'];

    // CRITICAL OVERRIDE: If course is 0-cost (Tier 1) and status is 'Locked', treat it as 'Unlocked'.
    if ($cost_int === 0 && $db_status === 'Locked') {
        $db_status = 'Unlocked';
    }

    $data = [
        'status' => $db_status,
        'progress_percent' => $progress_percent,
        'button_text' => 'Locked',
        'button_class' => 'btn-secondary disabled',
        'img_path' => '../images/vault_locked.png',
        'extra_class' => '',
        'link' => '#',
        'affordability' => true
    ];

    // --- Case 1: Active Progress States (Completed, InProgress) ---
    switch ($db_status) {
        case 'Completed':
            $data['progress_percent'] = 100;
            $data['button_text'] = 'Mastered';
            $data['button_class'] = 'btn-success disabled';
            $data['img_path'] = '../images/vault_overflowing.png';
            $data['extra_class'] = 'vault-completed';
            $data['link'] = "../lessons/view_lesson.php?course_id={$course_id}";
            return $data;

        case 'InProgress':
            $data['button_text'] = "Continue Journey ({$progress_percent}%)";
            $data['button_class'] = 'btn-primary vault-start-btn';
            if ($progress_percent < 9) {
                $data['img_path'] = '../images/vault_unlocked.png';
            } else {
                $data['img_path'] = '../images/vault_filled.png';
            }
            $data['extra_class'] = 'vault-progress';
            $data['link'] = "../lessons/view_lesson.php?course_id={$course_id}";
            return $data;

        case 'Unlocked':
            // --- Case 2: Unlocked (Ready to Purchase) ---
            $can_afford = $user_sp_int >= $cost_int;
            
            $data['progress_percent'] = 0;
            $data['affordability'] = $can_afford;
            $data['button_text'] = 'Unlock (' . number_format($cost_int) . ' Skill Points)';
            
            if ($cost_int === 0 || $can_afford) {
                // Affordable or 0-cost: ready to be unlocked
                $data['button_class'] = 'btn-warning vault-purchase-btn';
                $data['extra_class'] = 'vault-afford';
            } else {
                // Unaffordable
                $data['button_class'] = 'btn-danger disabled';
                $data['extra_class'] = 'vault-cannot-afford';
            }

            $data['img_path'] = '../images/vault_locked.png';
            $data['link'] = '#';
            return $data;
            
        case 'Locked':
            // --- Case 3: Locked (Prerequisite Check) ---
            // Course is Locked. We check prereqs to inform the user *why* it's locked.
            
            $tier_id = $course['tier_id'];
            $tier_prereq_id = $tiers[$tier_id]['prerequisite_tier_id'] ?? null;
            $is_prereq_met = true;

            if ($tier_prereq_id > 0) {
                if (isset($tiers[$tier_prereq_id])) {
                    
                    foreach ($tiers[$tier_prereq_id]['courses'] as $prereq_course_id) {
                        $p_progress_record = $progress[$prereq_course_id] ?? null;

                        if (!($p_progress_record && ($p_progress_record['status'] === 'Completed'))) {
                            $is_prereq_met = false;
                            break;
                        }
                    }
                }
            }

            if (!$is_prereq_met) {
                // Truly Locked: Prerequisites are not met.
                $data['button_text'] = 'Tier ' . $tier_prereq_id . ' Mastery Required';
                $data['extra_class'] = 'vault-truly-locked';
            } else {
                // Prereqs are met, but the course is still 'Locked' in the DB.
                $data['button_text'] = 'Locked - Error/Pending Unlock';
                $data['extra_class'] = 'vault-error-state';
            }

            $data['button_class'] = 'btn-secondary disabled';
            $data['img_path'] = '../images/vault_locked.png';
            return $data;
    }
}
?>

<div class="container-fluid skill-tree-container">
    <div class="row">
        <!-- Vaults Content -->
        <div class="col-md-9 skill-tree-main">
            <h1 class="skill-tree-header text-center mb-4">CoinFlow Skill Tree</h1>
            
            <?php 
            if (empty($tiers)) {
                echo '<p class="text-white-50">No Course Tiers found in the database. Please populate the Courses and Course_Tiers tables.</p>';
            }

            foreach ($tiers as $tier) {
                echo '<div class="vault-tier-header">' . htmlspecialchars($tier['tier_name']) . ' (Tier ' . $tier['tier_id'] . ')</div>';
                echo '<div class="row vault-cards-row">';
                
                if (empty($tier['courses'])) {
                        echo '<p class="text-white-50 ms-3">No vaults defined for this tier.</p>';
                }

                foreach ($tier['courses'] as $course_id) {
                    $course = $courses[$course_id];
                    $status_data = get_vault_status($course, $progress, $tiers, $raw_skill_points);
                    
                    $card_html = '
                    <div class="col-md-6 col-lg-4 vault-card-col">
                        <div 
                            class="vault-card card ' . htmlspecialchars($status_data['extra_class']) . '" 
                            data-vault-id="' . $course['course_id'] . '" 
                            data-vault-name="' . htmlspecialchars($course['course_name']) . '"
                            data-description="' . htmlspecialchars($course['core_topic']) . '"
                            data-cost="' . $course['skill_point_cost'] . '"
                            data-current-status="' . $status_data['status'] . '"
                            data-progress="' . $status_data['progress_percent'] . '"
                            data-link="' . ($status_data['link'] ?? '#') . '"
                            data-affordability="' . ($status_data['affordability'] ? 'true' : 'false') . '"
                            data-img-path="' . htmlspecialchars($status_data['img_path']) . '"
                        >
                            <img src="' . htmlspecialchars($status_data['img_path']) . '" class="img-fluid card-img-top vault-img" alt="' . $status_data['status'] . ' Vault Image">
                            <div class="card-body d-flex flex-column">
                                <h3 class="card-title vault-card-title">' . htmlspecialchars($course['course_name']) . '</h3>
                                <div class="mt-auto">
                                    <div class="vault-status-text small mb-2 text-end text-white-50">Status: ' . $status_data['status'] . '</div>
                                    <button class="btn ' . $status_data['button_class'] . ' vault-card-button"' . ($status_data['status'] == 'Locked' || $status_data['status'] == 'Completed' ? 'disabled' : '') . '>
                                        ' . $status_data['button_text'] . '
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    ';
                    echo $card_html;
                }
                echo '</div>';
            }
            ?>

        </div>

        <!-- Vault Details Content -->
        <div class="col-md-3 skill-tree-vault-details">
            <div class="vault-details-card card">
                <div class="card-body">
                    <h5 class="card-title vault-details-title" id="vault-details-title">Click a Vault to view details</h5>
                    <img id="vault-details-img" src="../images/vault_locked.png" class="img-fluid card-img-top p-4 vault-img" alt="Vault Image">
                    <h5 class="card-text vault-details-text" id="vault-details-text">
                        Select any Vault (course) from the left-hand menu to see its core topic, unlock cost, and your current progress.
                    </h5>
                    <h5 class="vault-details-progress" id="vault-details-progress"></h5>
                    <h5 class="vault-details-cost" id="vault-details-cost"></h5>
                    <button id="vault-details-button" class="btn btn-primary vault-details-button" disabled>Action Button</button>
                    <div id="vault-purchase-message" class="mt-3 small text-center"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const detailTitle = document.getElementById('vault-details-title');
        const detailText = document.getElementById('vault-details-text');
        const detailProgress = document.getElementById('vault-details-progress');
        const detailCost = document.getElementById('vault-details-cost');
        const detailButton = document.getElementById('vault-details-button');
        const purchaseMessage = document.getElementById('vault-purchase-message');
        const detailImage = document.getElementById('vault-details-img');
        
        let selectedVaultId = null;
        let currentVaultCost = 0;
        let currentVaultStatus = '';
        let currentVaultLink = '#';
        let currentAffordability = true;

        const updateDetailPanel = (vaultCard) => {
            const id = vaultCard.dataset.vaultId;
            const name = vaultCard.dataset.vaultName;
            const description = vaultCard.dataset.description;
            const cost = parseInt(vaultCard.dataset.cost);
            const status = vaultCard.dataset.currentStatus;
            const progress = parseInt(vaultCard.dataset.progress);
            const link = vaultCard.dataset.link;
            const affordability = vaultCard.dataset.affordability === 'true';
            const imgPath = vaultCard.dataset.imgPath;
            const buttonElement = vaultCard.querySelector('.vault-card-button');

            selectedVaultId = id;
            currentVaultCost = cost;
            currentVaultStatus = status;
            currentVaultLink = link;
            currentAffordability = affordability;

            detailTitle.textContent = name;
            detailImage.src = imgPath;
            detailText.textContent = description;
            detailCost.innerHTML = `<strong>Cost:</strong> ${cost.toLocaleString()} Skill Points`;
            purchaseMessage.textContent = ''; // Clear previous messages
            
            if (status === 'InProgress' || status === 'Completed') {
                detailProgress.innerHTML = `<strong>Progress:</strong> ${progress}% Complete`;
                // Bootstrap progress bar 
                const progressBar = `<div class="progress mt-2" style="height: 15px;"><div class="progress-bar ${progress === 100 ? 'bg-success' : 'bg-primary'}" role="progressbar" style="width: ${progress}%" aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100"></div></div>`;
                detailProgress.innerHTML += progressBar;
            } else {
                detailProgress.textContent = '';
            }

            // Remove cost text from button for cleaner look in the details panel
            detailButton.textContent = buttonElement.textContent.replace(/\s+\(\d{1,3}(,\d{3})*\s+Skill\sPoints\)/, '');

            detailButton.className = 'btn vault-details-button ' + buttonElement.className.replace('vault-card-button', '').trim();

            if (status === 'Unlocked' && currentAffordability) {
                detailButton.disabled = false;
                detailButton.classList.add('btn-warning');
            } else if (status === 'Unlocked' && !currentAffordability) {
                detailButton.disabled = true;
                detailButton.classList.add('btn-danger');
                detailButton.textContent = 'Cannot Afford';
            } else if (status === 'Unlocked' || status === 'InProgress') {
                // Start Lesson / Continue button
                detailButton.disabled = false;
                detailButton.classList.add('btn-primary');
            } else {
                // Locked or Completed
                detailButton.disabled = true;
            }
        };

        // --- Click Event for all Vault Cards ---
        $('.vault-card').on('click', function() {
            $('.vault-card').css('border', '1px solid var(--primary-accent-color)');
            $(this).css('border', '2px solid var(--tertiary-accent-color)');
            updateDetailPanel(this);
        });

        // --- Purchase/Start Button Handler ---
        detailButton.addEventListener('click', async () => {
            if (currentVaultStatus === 'Unlocked' && currentAffordability) {
                // Handle Purchase (Unlocking the Vault)
                purchaseMessage.innerHTML = '<span class="text-warning">Processing purchase...</span>';
                detailButton.disabled = true;

                try {
                    const response = await fetch('../skill_tree/unlock_vault.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `vault_id=${selectedVaultId}&cost=${currentVaultCost}`
                    });
                    
                    const result = await response.json();

                    if (result.success) {
                        purchaseMessage.innerHTML = `<span class="text-success">${result.message}</span>`;
                        
                        // Update global navbar skill points display
                        const currencyValueElement = document.querySelector('.skill-points-value');
                        if (currencyValueElement) {
                            currencyValueElement.textContent = result.new_skill_points.toLocaleString();
                        }
                        
                        // Reload the page to refresh all card statuses based on the new progress
                        setTimeout(() => {
                            window.location.reload(); 
                        }, 1000);

                    } else {
                        purchaseMessage.innerHTML = `<span class="text-danger">${result.message}</span>`;
                        detailButton.disabled = false;
                    }
                } catch (error) {
                    console.error('Purchase failed:', error);
                    purchaseMessage.innerHTML = '<span class="text-danger">A network error occurred. Try again.</span>';
                    detailButton.disabled = false;
                }
            } else if (currentVaultStatus === 'Completed' || currentVaultStatus === 'InProgress') {
                // Handle Navigation to Lesson Page 
                if (currentVaultLink && currentVaultLink !== '#') {
                    window.location.href = currentVaultLink;
                } else {
                        purchaseMessage.innerHTML = '<span class="text-danger">Error: Could not determine lesson link.</span>';
                }
            }
        });

        // Initially highlight the first vault (if any) and display its details
        // const firstVault = document.querySelector('.vault-card');
        // if (firstVault) {
        //     firstVault.click();
        // }
    });
</script>

<?php
// CAPTURE the buffered output and store it in a variable
$pageContent = ob_get_clean();

// Load the complete layout
require '../layout.php';
?>