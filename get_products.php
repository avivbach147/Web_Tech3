<?php
session_start();
if (!isset($_SESSION["uid"]) || ($_SESSION["role"] ?? null) != 3) { // Product Manager = role 3
    header("Location: login.php");
    exit();
} 
$pageTitle = "מנהל מוצר";
include __DIR__ . '/core/header.php';
include("db.php");
$result = $conn->query("SELECT p_id, p_data FROM product");
$products = [];
while ($row = $result->fetch_assoc()) {
  $row['p_data'] = json_decode($row['p_data'], true);
  $products[] = $row;
}
header('Content-Type: application/json');
echo json_encode($products, JSON_UNESCAPED_UNICODE);
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
include __DIR__ . '/core/footer.php';
?>