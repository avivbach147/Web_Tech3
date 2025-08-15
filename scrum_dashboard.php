<?php
session_start();
if (!isset($_SESSION["uid"]) || ($_SESSION["role"] ?? null) != 2) { // Scrum Master = role 2
    header("Location: login.php");
    exit();
}

$name = htmlspecialchars($_SESSION["name"] ?? "משתמש");
$pageTitle = "סקרם מאסטר";
include __DIR__ . '/core/header.php';
?>

<div class="container">
    <h2>שלום <?= $name ?> 👋</h2>

    <ul>
        <li><a href="kanban_scrum.php">לוח קנבן</a></li>
        <li><a href="scrum_manage.php">ניהול צוות סקרם</a></li>
    </ul>
</div>

<?php include __DIR__ . '/core/footer.php'; ?>
