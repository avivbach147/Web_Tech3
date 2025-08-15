<?php
include("db.php");
$p_id = intval($_GET["p_id"]);
$row = $conn->query("SELECT p_data FROM product WHERE p_id = $p_id")->fetch_assoc();
$data = json_decode($row["p_data"], true);
header('Content-Type: application/json');
echo json_encode($data["kanban"], JSON_UNESCAPED_UNICODE);