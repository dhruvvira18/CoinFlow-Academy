<?php
$pageTitle = 'Dashboard';

// START Output Buffer
ob_start();
?>

<!-- To Be Filled -->
<h1>Dashboard Content</h1>

<?php
// CAPTURE the buffered output and store it in a variable
$pageContent = ob_get_clean();

// 4. Load the complete layout
require '../layout.php';
?>