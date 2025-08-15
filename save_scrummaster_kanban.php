<?php
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sid = $_POST["sid"] ?? null;
    $updated_process = json_decode($_POST["updated_process"] ?? "{}", true);
    $all_tasks = json_decode($_POST["all_tasks"] ?? "{}", true);

    if (!$sid || !$updated_process || !$all_tasks) {
        die("Missing data.");
    }

    $res = $conn->query("SELECT c_data FROM scrum_team WHERE s_id = $sid");
    if (!$res || $res->num_rows === 0) {
        die("Scrum Master not found.");
    }

    $row = $res->fetch_assoc();
    $c_data = json_decode($row["c_data"], true);

    // Track log
    $log = $c_data["kanban"]["log"] ?? [];
    $previous_process = $c_data["kanban"]["process"] ?? [];

    foreach ($updated_process as $tid => $new_status) {
        $old_status = $previous_process[$tid] ?? null;
        if ($old_status !== null && $old_status != $new_status) {
            $log[] = [
                "tid" => $tid,
                "froms" => $old_status,
                "tos" => $new_status,
                "uid" => $c_data["uid"] ?? null
            ];
        }
    }

    // Update kanban data
    $c_data["kanban"]["tasks"] = $all_tasks;
    $c_data["kanban"]["process"] = $updated_process;
    $c_data["kanban"]["log"] = $log;

    $c_data_json = $conn->real_escape_string(json_encode($c_data, JSON_UNESCAPED_UNICODE));
    $update_sql = "UPDATE scrum_team SET c_data = '$c_data_json' WHERE s_id = $sid";

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
            <a href="scrummaster_kanban.php?sid=<?= $sid ?>">⬅ חזרה ללוח הקנבן</a>
          </div>
        </body>
        </html>
        <?php
    } else {
        echo "Error saving kanban: " . $conn->error;
    }
}
?>
