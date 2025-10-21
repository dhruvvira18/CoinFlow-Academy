<?php
// Get the current script's file name (e.g., 'dashboard.php', 'skill_tree.php')
// $_SERVER is a PHP superglobal array that contains information about the server and execution environment.
// $_SERVER['PHP_SELF'] holds the path and file name of the current script, relative to the document root of your web server.
// basename() is a built-in PHP function that returns the trailing name component of a path. In simple terms, it extracts only the file name from the full path string.
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
                        <img class="currency_icon" src="../images/star_point_symbol.png"> <span class="currency-value">1000</span>
                        <div class="currency-tooltip">Star Points</div>
                    </a>
                </li>
                <li class="nav-item position-relative">
                    <a class="nav-link custom-currency-link d-flex align-items-center" href="#">
                        <img class="currency_icon" src="../images/skill_point_symbol.png"> <span class="currency-value">1000</span>
                        <div class="currency-tooltip">Skill Points</div>
                    </a>
                </li>   
            </ul>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-start custom-offcanvas-bg" tabindex="-1" id="mainMenuOffcanvas" aria-labelledby="mainMenuOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="mainMenuOffcanvasLabel">Academy Hub</h5>
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