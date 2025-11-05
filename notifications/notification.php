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

// --- HELPER FUNCTION FOR TIME ---
function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);

    if ($seconds <= 60) return "Just now";
    elseif ($minutes <= 60) return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    elseif ($hours <= 24) return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    elseif ($days <= 7) return $days == 1 ? "1 day ago" : "$days days ago";
    elseif ($weeks <= 4.3) return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    elseif ($months <= 12) return $months == 1 ? "1 month ago" : "$months months ago";
    else return $years == 1 ? "1 year ago" : "$years years ago";
}

// --- MARK ALL AS READ ---
// When the user visits this page, mark all their unread notifications as read.
$updateQuery = "UPDATE Notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// --- FETCH ALL NOTIFICATIONS ---
$fetchQuery = "SELECT message, link_url, timestamp FROM Notifications WHERE user_id = ? ORDER BY timestamp DESC";
$stmt = $conn->prepare($fetchQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | CoinFlow Academy</title>

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../global_styles.css">
    <link rel="stylesheet" href="style.css"> <!-- This new CSS file -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<?php include("../global_navigation.php"); ?>

<main class="notifications-container">
    <h1 class="page-header">Notifications</h1>

    <div class="notification-list">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $has_link = !empty($row['link_url']);
                    // Start tag is <a> if link exists, <div> otherwise
                    $tag = $has_link ? 'a' : 'div';
                    $href = $has_link ? 'href="' . htmlspecialchars($row['link_url']) . '"' : '';
                    $class = $has_link ? 'notification-item-link' : 'notification-item-no-link';
                ?>
                
                <<?php echo $tag; ?> <?php echo $href; ?> class="notification-item <?php echo $class; ?>">
                    <p class="notification-message"><?php echo htmlspecialchars($row['message']); ?></p>
                    <span class="notification-time"><?php echo time_ago($row['timestamp']); ?></span>
                </<?php echo $tag; ?>>

            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-notifications">You have no notifications yet.</p>
        <?php endif; ?>
    </div>

</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../global_navigation.js"></script>

</body>
</html>