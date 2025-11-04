<?php
session_start();
require_once('../setup.php');

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/login.html");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
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
    <title>Leaderboard | CoinFlow Academy</title>

    <link rel="stylesheet" href="../global_styles.css">
    <link rel="stylesheet" href="./style.css">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include("../global_navigation.php"); ?>

    <div class="container mt-5 leaderboard-container">
        <h1 class="leaderboard-title text-center">🏆 Global Leaderboard</h1>
        <p class="text-center text-muted mb-4">Top players ranked by total Leaderboard Points</p>

        <div class="table-responsive">
            <table class="table leaderboard-table table-borderless align-middle text-center">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Username</th>
                        <th>⭐ Star Points</th>
                        <th>💡 Skill Points</th>
                        <th>🏆 Leaderboard Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1;
                    while ($row = $result->fetch_assoc()):
                        $isCurrent = ($row['user_id'] == $current_user_id);
                        $highlightClass = $isCurrent ? 'current-user' : '';
                    ?>
                    <tr class="<?php echo $highlightClass; ?>">
                        <td>
                            <?php
                                if ($rank == 1) echo "🥇";
                                elseif ($rank == 2) echo "🥈";
                                elseif ($rank == 3) echo "🥉";
                                else echo $rank;
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo $row['star_points']; ?></td>
                        <td><?php echo $row['skill_points']; ?></td>
                        <td><?php echo $row['leaderboard_points']; ?></td>
                    </tr>
                    <?php $rank++; endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($userRank): ?>
        <div class="user-rank-info text-center mt-4">
            <h5>Your Rank: <span class="rank-highlight">#<?php echo $userRank; ?></span></h5>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
