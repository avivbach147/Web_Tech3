<?php
session_start();
if (!isset($_SESSION["uid"]) || $_SESSION["role"] != 1) {
    header("Location: login.php");
    exit();
}
$pageTitle = "מנהל חברה";
include __DIR__ . '/core/header.php';
?>

    <div class="navbar">
        <div class="navbar-left">
            <a href="about.html">אודות</a>
        </div>
        <div class="navbar-right">
            <a href="logout.php">התנתק</a>
        </div>
    </div>

    <div class="container">
        <h2>שלום <?= htmlspecialchars($_SESSION["name"]) ?> 👋</h2>
        <ul>
            <li><a href="companymanager.php">ניהול מוצרים</a></li>
            <li><a href="companyreport.php">דוחות</a></li>
        </ul>
    </div>
 
<?php
include 'footer.php';
?> 