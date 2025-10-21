<?php
$message = htmlspecialchars($_GET['message'] ?? 'An unknown error occurred.');
$type = htmlspecialchars($_GET['type'] ?? 'error');
$error_return_page = htmlspecialchars($_GET['return_page'] ?? 'register.html');

$title = ($type === 'success') ? 'Success!' : 'Error';
$is_success = ($type === 'success');

$redirect_link = $is_success ? '../dashboard/dashboard.php' : $error_return_page; 
$button_text = $is_success ? 'Go to Dashboard' : 'Go Back';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Message</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <link rel="stylesheet" href="../global_styles.css">
    <link rel="icon" href="../images/logo.png" type="image/png">
    
    <style>
        .full-screen-center { min-height: 100vh; }

        .custom-card {
            background-color: var(--secondary-background-color);
        }

        .alert-custom {
            background-color: #333333;
            color: var(--text-color);
            border-left: 5px solid var(--primary-accent-color);
            border-right: 5px solid var(--primary-accent-color);
            padding: 5%;
            margin-bottom: 5%;
        }
        
        .title-accent {
            color: var(--primary-accent-color);
        }
    </style>
</head>
<body class="d-flex full-screen-center align-items-center justify-content-center">

    <div class="card custom-card border-0 rounded-3 p-4 p-md-5 text-center" style="max-width: 450px; width: 100%;">
        
        <h1 class="h3 fw-bold mb-3 title-accent">
            <?php echo $title; ?>
        </h1>
        
        <div class="alert-custom rounded-3" role="alert">
            <?php echo $message; ?>
        </div>

        <a href="<?php echo $redirect_link; ?>">
            <button>
                <?php echo $button_text; ?>
            </button>
        </a>
    </div>

</body>
</html>
