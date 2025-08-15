<?php
include("db.php");
$data = json_decode(file_get_contents("php://input"), true);

$p_id = intval($data["p_id"]);
$task_id = $data["task_id"];
$new_status_index = intval($data["new_status_index"]);

$row = $conn->query("SELECT p_data FROM product WHERE p_id = $p_id")->fetch_assoc();
$p_data = json_decode($row["p_data"], true);

// Update the status index
$p_data["kanban"]["process"][$task_id] = strval($new_status_index);

// Save back to DB
$updated_json = json_encode($p_data, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare("UPDATE product SET p_data = ? WHERE p_id = ?");
$stmt->bind_param("si", $updated_json, $p_id);
$success = $stmt->execute();

echo json_encode(["success" => $success]);