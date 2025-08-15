<?php
session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only allow if logged in and is a team member
if (!isset($_SESSION["uid"]) || $_SESSION["role"] != 4) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mid = intval($_POST["mid"]);
    $updated_process = json_decode($_POST["updated_process"] ?? '{}', true);
    $all_tasks = json_decode($_POST["all_tasks"] ?? '{}', true);

    // Fetch existing m_data
    $res = $conn->query("SELECT m_data FROM member WHERE m_id = $mid");
    if (!$res || $res->num_rows === 0) {
        die("Team member not found.");
    }

    $row = $res->fetch_assoc();
    $m_data = json_decode($row["m_data"], true);

    // Update kanban
    $m_data["kanban"]["tasks"] = $all_tasks;
    $m_data["kanban"]["process"] = $updated_process;

    $updated_json = $conn->real_escape_string(json_encode($m_data, JSON_UNESCAPED_UNICODE));
    $update_sql = "UPDATE member SET m_data = '$updated_json' WHERE m_id = $mid";

    if ($conn->query($update_sql)) {
        // Styled success message
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
            <a href="team_member_kanban.php">⬅ חזרה ללוח הקנבן</a>
          </div>
        </body>
        </html>
        <?php
    } else {
        echo "שגיאה בשמירת הקנבן: " . $conn->error;
    }
} else {
    echo "גישה לא חוקית.";
}
?>
