<?php
session_start();
include_once '../setup.php';

// --- ACCESS CONTROL ---
// Authentication Check
if (!$user_id) {
    // Redirect to login page if user is not authenticated
    header("Location: ../account/login.html");
    exit();
}

$current_user_id = $_SESSION['user_id'];

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- FETCH LEADERBOARD DATA ---
$query = "
    SELECT 
        u.user_id,
        u.username,
        s.star_points,
        s.skill_points,
        s.leaderboard_points
    FROM Users u
    JOIN User_Stats s ON u.user_id = s.user_id
    ORDER BY s.leaderboard_points DESC
    LIMIT 20;
";
$result = $conn->query($query);

// --- FETCH CURRENT USER RANK ---
$rankQuery = "
    SELECT COUNT(*) + 1 AS user_rank
    FROM User_Stats
    WHERE leaderboard_points > (
        SELECT leaderboard_points FROM User_Stats WHERE user_id = ?
    );
";
$rankStmt = $conn->prepare($rankQuery);
$rankStmt->bind_param("i", $current_user_id);
$rankStmt->execute();
$rankResult = $rankStmt->get_result()->fetch_assoc();
$userRank = $rankResult ? $rankResult['user_rank'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard</title>

    <!-- MODIFIED: Corrected all paths to include your project folder -->
    
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../global_styles.css">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <link rel="stylesheet" href="./style.css">
    
</head>

<body>
    <?php 
    // MODIFIED: Corrected the path
    require_once '../global_navigation.php';
    ?>

    <div class="container mt-5 leaderboard-container">
        <h1 class="page-header text-center">Global Leaderboard</h1>
        <p class="text-center text-white-50 mb-4">Top players ranked by total Leaderboard Points</p>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table leaderboard-table table-borderless align-middle text-center">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Username</th>
                            <!-- MODIFIED: Corrected all paths -->
                            <th>Star Points</th>
                            <th> Skill Points</th>
                            <th>Leaderboard Points</th>
                        </tr>
                    </thead>        
                    <tbody>
                        <?php
                        $rank = 1;
                        while ($result && $row = $result->fetch_assoc()):
                            $isCurrent = ($row['user_id'] == $current_user_id);
                            $highlightClass = $isCurrent ? 'current-user' : '';
                        ?>
                        <tr class="<?php echo $highlightClass; ?>">
                            <td class="rank-cell">
                                <?php
                                    if ($rank == 1) echo "🥇";
                                    elseif ($rank == 2) echo "🥈";
                                    elseif ($rank == 3) echo "🥉";
                                    else echo "#" . $rank;
                                ?>
                            </td>
                            <td class="username-cell"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td class="currency-cell"><?php echo number_format($row['star_points']); ?></td>
                            <td class="currency-cell"><?php echo number_format($row['skill_points']); ?></td>
                            <td class="points-cell"><?php echo number_format($row['leaderboard_points']); ?></td>
                        </tr>
                        <?php $rank++; endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($userRank): ?>
        <div class="user-rank-info text-center mt-4">
            <h5>Your Global Rank</h5>
            <h2 class="rank-highlight">#<?php echo $userRank; ?></h2>
            <p class="mb-0">Keep learning to climb higher!</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- MODIFIED: Corrected path -->
    <script src="/CoinFlow-Academy/global_navigation.js"></script> 
</body>
</html>