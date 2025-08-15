<?php
// Bootstrap
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Page meta and layout includes
$pageTitle = "ניהול משתמשים";
include __DIR__ . '/core/header.php';

// DB connection
include __DIR__ . '/db.php';

// Collect UI alerts to render inside the page (avoid echoing before layout)
$alerts = [];

// Handle delete
if (isset($_POST["action"], $_POST["uid"]) && $_POST["action"] === "delete") {
    $stmt = $conn->prepare("DELETE FROM user WHERE uid = ?");
    $stmt->bind_param("i", $_POST["uid"]);
    if ($stmt->execute()) {
        $alerts[] = ["type" => "success", "text" => "✅ המשתמש נמחק"];
    } else {
        $alerts[] = ["type" => "error", "text" => "❌ שגיאה במחיקה"];
    }
    $stmt->close();
}

// Handle update
if (isset($_POST["action"], $_POST["uid"]) && $_POST["action"] === "update") {
    $stmt = $conn->prepare("UPDATE user SET login=?, password=?, name=?, role=? WHERE uid=?");
    $stmt->bind_param("sssii", $_POST["login"], $_POST["password"], $_POST["name"], $_POST["role"], $_POST["uid"]);
    if ($stmt->execute()) {
        $alerts[] = ["type" => "success", "text" => "✅ המשתמש עודכן בהצלחה"];
    } else {
        $alerts[] = ["type" => "error", "text" => "❌ שגיאה בעדכון"];
    }
    $stmt->close();
}

// Fetch users
$users = $conn->query("SELECT * FROM user");
?>

<div class="container">
  <h2>ניהול משתמשים</h2>

  <?php if (!empty($alerts)): ?>
    <div class="alerts">
      <?php foreach ($alerts as $a): ?>
        <p style="color: <?= $a['type'] === 'success' ? 'green' : 'red' ?>;"><?= htmlspecialchars($a['text'], ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <table border="1" cellpadding="5">
    <thead>
      <tr>
        <th>מזהה</th>
        <th>שם משתמש</th>
        <th>סיסמה</th>
        <th>שם מלא</th>
        <th>תפקיד</th>
        <th>עדכון</th>
        <th>מחיקה</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $users->fetch_assoc()): ?>
        <tr>
          <form method="POST">
            <td>
              <?= (int)$row['uid'] ?>
              <input type="hidden" name="uid" value="<?= (int)$row['uid'] ?>">
            </td>
            <td><input type="text" name="login" value="<?= htmlspecialchars($row['login'], ENT_QUOTES, 'UTF-8') ?>"></td>
            <td><input type="text" name="password" value="<?= htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8') ?>"></td>
            <td><input type="text" name="name" value="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"></td>
            <td>
              <select name="role">
                <option value="1" <?= ((int)$row['role'] === 1) ? 'selected' : '' ?>>מנהל חברה</option>
                <option value="2" <?= ((int)$row['role'] === 3) ? 'selected' : '' ?>>בעל מוצר</option>
                <option value="3" <?= ((int)$row['role'] === 2) ? 'selected' : '' ?>>סקרם מאסטר</option>
                <option value="4" <?= ((int)$row['role'] === 4) ? 'selected' : '' ?>>חבר צוות</option>
              </select>
            </td>
            <td>
              <input type="hidden" name="action" value="update">
              <input type="submit" value="עדכן">
            </td>
          </form>
          <form method="POST" onsubmit="return confirm('למחוק את המשתמש?');">
            <input type="hidden" name="uid" value="<?= (int)$row['uid'] ?>">
            <input type="hidden" name="action" value="delete">
            <td><input type="submit" value="מחק"></td>
          </form>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/core/footer.php'; ?>
