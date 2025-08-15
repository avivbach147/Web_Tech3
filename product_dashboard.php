<?php
session_start();
if (!isset($_SESSION["uid"]) || (int)$_SESSION["role"] !== 3) {
    header("Location: login.php");
    exit();
}


$name = htmlspecialchars($_SESSION["name"] ?? "משתמש", ENT_QUOTES, 'UTF-8');
$pageTitle = "מנהל מוצר";

include __DIR__ . '/core/header.php';
include __DIR__ . '/db.php';
?>

<div class="navbar" >
    <div class="navbar-left">
        <a href="about.html">אודות</a>
    </div>
    <div class="navbar-right">
        <a href="logout.php">התנתק</a>
    </div>
</div>

<div class="container" >
    <h2>שלום <?= $name ?> 👋</h2>
    <ul>
        <li><a href="product_manager.php">ניהול מוצרים</a></li>
        <li><a href="kanban_product.php">לוח קנבן</a></li>
    </ul>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<?php include __DIR__ . '/core/footer.php'; ?>
