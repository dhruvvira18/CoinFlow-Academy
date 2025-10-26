<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$star_points = '???';
$skill_points = '???';
$username = 'Guest';

// DATABASE CONFIGURATION
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'coinflow_academy_db'; 

if ($user_id) {
    $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    // Check connection
    if ($mysqli->connect_errno) {
        error_log("Failed to connect to MySQL: " . $mysqli->connect_error);
    } else {
        // --- QUERY A: Get Currency Stats ---
        $stmt_stats = $mysqli->prepare("SELECT star_points, skill_points FROM User_Stats WHERE user_id = ?");
        $stmt_stats->bind_param("i", $user_id); 
        $stmt_stats->execute();
        
        $stmt_stats->bind_result($fetched_star_points, $fetched_skill_points);
        
        if ($stmt_stats->fetch()) {
            $star_points = number_format($fetched_star_points);
            $skill_points = number_format($fetched_skill_points);
        }
        $stmt_stats->close();


        // --- QUERY B: Get Username from Users table ---
        $stmt_user = $mysqli->prepare("SELECT username FROM Users WHERE user_id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $stmt_user->bind_result($fetched_username);
        
        if ($stmt_user->fetch()) {
            $username = htmlspecialchars($fetched_username);
        }
        $stmt_user->close();
        
        $mysqli->close();
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark custom-navbar-bg">
    <div class="container-fluid">
        <button class="btn btn-link p-0 text-white me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainMenuOffcanvas" aria-controls="mainMenuOffcanvas" aria-label="Toggle Main Menu">
                <span class="navbar-toggler-icon"></span> 
        </button> 

        <a class="navbar-brand d-flex align-items-center custom-brand-text" href="index.html">
            <img src="../images/logo.png" alt="Website Logo" class="me-2 custom-logo">
            CoinFlow Academy
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item me-3 position-relative">
                    <a class="nav-link custom-currency-link d-flex align-items-center" href="#">
                        <img class="currency_icon" src="../images/star_point_symbol.png"> <span class="currency-value"><?php echo $star_points; ?></span>
                        <div class="currency-tooltip">Star Points</div>
                    </a>
                </li>
                <li class="nav-item position-relative">
                    <a class="nav-link custom-currency-link d-flex align-items-center" href="#">
                        <img class="currency_icon" src="../images/skill_point_symbol.png"> <span class="currency-value"><?php echo $skill_points; ?></span>
                        <div class="currency-tooltip">Skill Points</div>
                    </a>
                </li>   
            </ul>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-start custom-offcanvas-bg" tabindex="-1" id="mainMenuOffcanvas" aria-labelledby="mainMenuOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="mainMenuOffcanvasLabel">Welcome, <?php echo $username; ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
            <li class="nav-item">
                <a class="nav-link offcanvas-link<?php echo ($current_page === 'dashboard.php') ? ' active' : ''; ?>" 
                <?php echo ($current_page === 'dashboard.php') ? 'aria-current="page"' : ''; ?> href="../dashboard/dashboard.php">Home</a>
            </li>
            <li class="nav-item">
                <a class="nav-link offcanvas-link<?php echo ($current_page === 'skill_tree.php') ? ' active' : ''; ?>" 
                <?php echo ($current_page === 'skill_tree.php') ? 'aria-current="page"' : ''; ?> href="../skill_tree/skill_tree.php">Skill Tree</a>
            </li>
            <li class="nav-item">
                <a class="nav-link offcanvas-link" href="">Feature 02</a>
            </li>
            <li class="nav-item">
                <a class="nav-link offcanvas-link" href="">Feature 03</a>
            </li>
            <li class="nav-item mt-3 pt-3 border-top border-secondary">
                <a class="nav-link offcanvas-link" href="">About Us</a>
            </li>
            <li class="nav-item mt-3 pt-3 border-top border-secondary">
                <a class="nav-link offcanvas-link" href="../account/logout_handler.php">Logout</a>
            </li>
        </ul>
        <hr class="text-white-50">
        <div class="mt-4 offcanvas-footer-text">
            <p class="small">CoinFlow Academy</p>
            <p class="small">© 2025</p>
        </div>
    </div>
</div>