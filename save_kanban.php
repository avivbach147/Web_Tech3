<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

if (!isset($_POST['pid'], $_POST['updated_process'], $_POST['all_tasks'])) {
    http_response_code(400);
    echo 'Missing fields';
    exit;
}

$pid = (int)$_POST['pid'];
$updated_process_raw = (string)$_POST['updated_process'];
$all_tasks_raw       = (string)$_POST['all_tasks'];

// Decode JSON safely
$updated_process = json_decode($updated_process_raw, true);
$all_tasks       = json_decode($all_tasks_raw, true);

if (!is_array($updated_process) || !is_array($all_tasks)) {
    http_response_code(400);
    echo 'Invalid JSON payload';
    exit;
}

// Fetch current product JSON
$stmt = $conn->prepare('SELECT p_data FROM product WHERE p_id = ?');
$stmt->bind_param('i', $pid);
if (!$stmt->execute()) {
    http_response_code(500);
    echo 'DB read error';
    exit;
}
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo 'Product not found';
    exit;
}

$p_data = json_decode($row['p_data'], true);
if (!is_array($p_data)) $p_data = [];

// Update JSON
if (!isset($p_data['kanban']) || !is_array($p_data['kanban'])) {
    $p_data['kanban'] = [];
}
$p_data['kanban']['tasks']   = $all_tasks;
$p_data['kanban']['process'] = $updated_process;

// Encode JSON (keep unicode, avoid invalid utf8 issues)
$new_json = json_encode(
    $p_data,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);

// Persist
$upd = $conn->prepare('UPDATE product SET p_data = ? WHERE p_id = ?');
$upd->bind_param('si', $new_json, $pid);
if (!$upd->execute()) {
    http_response_code(500);
    echo 'DB write error';
    exit;
}
$upd->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>עדכון לוח קנבן</title>
  <style>
    body { font-family: Arial, sans-serif; text-align: center; padding-top: 100px; background:#f2f2f2; }
    .message-box { display:inline-block; padding:30px; border-radius:8px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; box-shadow:0 0 10px rgba(0,0,0,0.1); }
    a { display:inline-block; margin-top:20px; text-decoration:none; color:#155724; font-weight:bold; }
  </style>
</head>
<body>
  <div class="message-box">
    <h2>🎉 המשימות נשמרו בהצלחה!</h2>
    <a href="kanban.php?pid=<?= htmlspecialchars((string)$pid, ENT_QUOTES, 'UTF-8') ?>">⬅ חזרה ללוח הקנבן</a>
  </div>
</body>
</html>
