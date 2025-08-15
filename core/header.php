<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'My App'; ?></title>
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
</head>
<body>
    <?php 
        // Include navbar if it exists
        if (file_exists(__DIR__ . '/../navbar.php')) {
            include __DIR__ . '/../navbar.php';
        }
    ?>
   
        <div class="navbar">
        <div class="navbar-left">
            <a href="about.html">אודות</a>
        </div>
        <!-- if page is not "register.php or "index.php" or "login.php"-->
        
        <?php 
        if (!in_array(basename($_SERVER['PHP_SELF']), ['register.php', 'index.php', 'login.php', 'about.html'])): ?>
            <div class="navbar-center">
                <a href="index.php">דף הבית</a>
            </div>
       
        <div class="navbar-right">
            <a href="logout.php">התנתק</a>
        </div>
         <?php endif; ?>
    </div>
