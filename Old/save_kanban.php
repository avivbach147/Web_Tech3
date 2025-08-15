<?php
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["updated_process"], $_POST["pid"], $_POST["all_tasks"])) {
    $pid = intval($_POST["pid"]);
    $updated_process = json_decode($_POST["updated_process"], true);
    $all_tasks = json_decode($_POST["all_tasks"], true);

    $result = $conn->query("SELECT p_data FROM product WHERE p_id = $pid");
    $product = $result->fetch_assoc();
    $p_data = json_decode($product["p_data"], true);

    $p_data["kanban"]["tasks"] = $all_tasks;
    $p_data["kanban"]["process"] = $updated_process;

    $new_json = json_encode($p_data, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("UPDATE product SET p_data = ? WHERE p_id = ?");
    $stmt->bind_param("si", $new_json, $pid);
    $stmt->execute();
    ?>
    <!DOCTYPE html>
    <html lang="he" dir="rtl">
    <head>
      <meta charset="UTF-8">
      <title>עדכון לוח קנבן</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          text-align: center;
          padding-top: 100px;
          background-color: #f2f2f2;
        }
        .message-box {
          display: inline-block;
          padding: 30px;
          border-radius: 8px;
          background-color: #d4edda;
          color: #155724;
          border: 1px solid #c3e6cb;
          box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        a {
          display: inline-block;
          margin-top: 20px;
          text-decoration: none;
          color: #155724;
          font-weight: bold;
        }
      </style>
    </head>
    <body>
      <div class="message-box">
        <h2>🎉 המשימות נשמרו בהצלחה!</h2>
        <a href="kanban.php?pid=<?= $pid ?>">⬅ חזרה ללוח הקנבן</a>
      </div>
    </body>
    </html>
    <?php
} else {
    echo "שגיאה בעדכון המשימות.";
}
