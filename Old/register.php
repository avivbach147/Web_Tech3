<?php
session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "הרשמה וניהול משתמשים";
include __DIR__ . '/core/header.php';

$registration_complete = false;
$new_user = null;
$deleted_summary = [];

// Register new user with additional info for roles 3 and 4
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["action"] === "register") {
    $stmt = $conn->prepare("INSERT INTO user (login, password, name, role, lastused) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssi", $_POST["login"], $_POST["password"], $_POST["name"], $_POST["role"]);
    $stmt->execute();
    $new_id = $stmt->insert_id;

    $role = intval($_POST['role']);

    if ($role === 2) { // Scrum Master
        $scrum_data = [
            "uid" => $new_id,
            "name" => $_POST["name"],
            "email" => $_POST["email"] ?? '',
            "phone" => $_POST["phone"] ?? '',
            "experience_years" => intval($_POST["experience"] ?? 0),
            "kanban" => ["log" => [], "ver" => "2025a", "tasks" => new stdClass(), "status" => ["New","To Do","Doing","Test","Done"], "process" => new stdClass()]
        ];
        $scrum_json = json_encode($scrum_data);
        $stmtScrum = $conn->prepare("INSERT INTO scrum_team (c_id, c_data) VALUES (?, ?)");
        $stmtScrum->bind_param("is", $new_id, $scrum_json);
        $stmtScrum->execute();
    }

    if ($role === 4) { // Team Member
        $member_data = [
            "email" => $_POST["email"] ?? '',
            "role" => $_POST["member_role"] ?? 'Team Member',
            "skills" => explode(',', $_POST["skills"] ?? ''),
            "kanban" => ["log" => [], "ver" => "2025a", "tasks" => new stdClass(), "status" => ["New","To Do","Doing","Test","Done"], "process" => new stdClass(), "memberid" => $new_id]
        ];
        $member_json = json_encode($member_data);
        $s_id = 1;
        $stmtMember = $conn->prepare("INSERT INTO member (m_id, s_id, m_data) VALUES (?, ?, ?)");
        $stmtMember->bind_param("iis", $new_id, $s_id, $member_json);
        $stmtMember->execute();
    }

    $new_user = $conn->query("SELECT * FROM user WHERE uid = $new_id")->fetch_assoc();
    $registration_complete = true;
}

// Delete user and related entries
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["action"] === "delete") {
    $uid = intval($_POST["uid"]);
    $deleted_user = $conn->query("SELECT * FROM user WHERE uid = $uid")->fetch_assoc();
    $role = $deleted_user["role"];
    $conn->query("DELETE FROM user WHERE uid = $uid");

    if ($role == 4) { // Team Member
        $member_result = $conn->query("SELECT * FROM member WHERE m_id = $uid");
        while ($row = $member_result->fetch_assoc()) $deleted_summary["member"][] = $row;
        $conn->query("DELETE FROM member WHERE m_id = $uid");

        $pm_result = $conn->query("SELECT * FROM p_m WHERE m_id = $uid");
        while ($row = $pm_result->fetch_assoc()) $deleted_summary["p_m"][] = $row;
        $conn->query("DELETE FROM p_m WHERE m_id = $uid");
    }

    if ($role == 2) { // Scrum Master
        $scrum_result = $conn->query("SELECT * FROM scrum_team WHERE c_data LIKE '%\"uid\":$uid%'");
        while ($row = $scrum_result->fetch_assoc()) {
            $deleted_summary['scrum_team'][] = $row;
            $conn->query("DELETE FROM scrum_team WHERE s_id = " . intval($row['s_id']));
        }
    }

    if ($role == 3) { // Product Manager
        $prod_result = $conn->query("SELECT * FROM product WHERE p_data LIKE '%\"uid\":\"$uid\"%'");
        while ($row = $prod_result->fetch_assoc()) {
            $deleted_summary['product'][] = $row;
            $conn->query("DELETE FROM product WHERE p_id = " . intval($row['p_id']));
        }
    }

    echo "<h3>🗑️ נמחק המשתמש:</h3>";
    echo "<ul><li><strong>מזהה:</strong> {$deleted_user['uid']}</li>
          <li><strong>שם משתמש:</strong> " . htmlspecialchars($deleted_user['login']) . "</li>
          <li><strong>שם מלא:</strong> " . htmlspecialchars($deleted_user['name']) . "</li>
          <li><strong>תפקיד:</strong> " . $role . "</li></ul>";

    if (!empty($deleted_summary)) {
        echo "<h4>🚨 נמחקו גם רשומות קשורות:</h4>";
        foreach ($deleted_summary as $table => $rows) {
            echo "<h5>טבלה: $table</h5><table border='1'><tr>";
            if (!empty($rows)) {
                foreach (array_keys($rows[0]) as $header) echo "<th>" . htmlspecialchars($header) . "</th>";
                echo "</tr>";
                foreach ($rows as $r) {
                    echo "<tr>";
                    foreach ($r as $v) echo "<td>" . htmlspecialchars($v) . "</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
        }
    }
}

// Update user
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["action"] === "update") {
    $stmt = $conn->prepare("UPDATE user SET login=?, password=?, name=?, role=? WHERE uid=?");
    $stmt->bind_param("sssii", $_POST["login"], $_POST["password"], $_POST["name"], $_POST["role"], $_POST["uid"]);
    $stmt->execute();

    $roles = [1 => "מנהל חברה", 3 => "בעל מוצר", 2 => "סקרם מאסטר", 4 => "חבר צוות"];
    $updated_user = $conn->query("SELECT * FROM user WHERE uid = " . $_POST["uid"])->fetch_assoc();

    echo "<h3>🔄 המשתמש עודכן:</h3>
          <table border='1' cellpadding='5'>
          <tr><th>מזהה</th><td>{$updated_user["uid"]}</td></tr>
          <tr><th>שם משתמש</th><td>" . htmlspecialchars($updated_user["login"]) . "</td></tr>
          <tr><th>סיסמה</th><td>" . htmlspecialchars($updated_user["password"]) . "</td></tr>
          <tr><th>שם מלא</th><td>" . htmlspecialchars($updated_user["name"]) . "</td></tr>
          <tr><th>תפקיד</th><td>{$roles[$updated_user["role"]]}</td></tr></table>";
}
?>

<div class="container">
    <h2>רישום משתמש חדש</h2>
    <?php if (!$registration_complete): ?>
        <form method="POST">
            <input type="hidden" name="action" value="register">
            שם מלא: <input type="text" name="name" required><br>
            שם משתמש: <input type="text" name="login" required><br>
            סיסמה: <input type="text" name="password" required><br>
            תפקיד:
            <select name="role" required>
                <option value="1">מנהל חברה</option>
                <option value="3">בעל מוצר</option>
                <option value="2">סקרם מאסטר</option>
                <option value="4">חבר צוות</option>
            </select><br>
            <input type="submit" value="רשום">
        </form>
        </div>
    <?php endif; ?>

    <!-- Display registered user -->
    <?php if ($registration_complete && $new_user): ?>
        <h3>✅ נרשם משתמש חדש:</h3>
        <ul>
            <li><strong>מזהה:</strong> <?= $new_user["uid"] ?></li>
            <li><strong>שם משתמש:</strong> <?= htmlspecialchars($new_user["login"]) ?></li>
            <li><strong>סיסמה:</strong> <?= htmlspecialchars($new_user["password"]) ?></li>
            <li><strong>שם מלא:</strong> <?= htmlspecialchars($new_user["name"]) ?></li>
        </ul>
        
    <?php endif; ?>

    <!-- Display all users -->
    <hr>

   
    <div class="user-list">
         <h2>כל המשתמשים</h2>
    <table cellpadding="5">
        <thead>
            <tr>
                <th>מזהה</th><th>שם משתמש</th><th>סיסמה</th><th>שם מלא</th><th>תפקיד</th><th>עדכון</th><th>מחיקה</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $users = $conn->query("SELECT * FROM user");
            while ($row = $users->fetch_assoc()):
            ?>
            <tr>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="uid" value="<?= $row['uid'] ?>">
                    <td><?= $row['uid'] ?></td>
                    <td><input type="text" name="login" value="<?= $row['login'] ?>"></td>
                    <td><input type="text" name="password" value="<?= $row['password'] ?>"></td>
                    <td><input type="text" name="name" value="<?= $row['name'] ?>"></td>
                    <td>
                        <select name="role" style="width: 110px; margin: 1px;">
                            <option value="1" <?= $row['role']==1 ? 'selected' : '' ?>>מנהל חברה</option>
                            <option value="2" <?= $row['role']==3 ? 'selected' : '' ?>>בעל מוצר</option>
                            <option value="3" <?= $row['role']==2 ? 'selected' : '' ?>>סקרם מאסטר</option>
                            <option value="4" <?= $row['role']==4 ? 'selected' : '' ?>>חבר צוות</option>
                        </select>
                    </td>
                    <td><input type="submit" value="עדכן"></td>
                </form>
                <form method="POST" onsubmit="return confirm('למחוק את המשתמש?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="uid" value="<?= $row['uid'] ?>">
                    <td><input type="submit" value="מחק"></td>
                </form>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const roleSelect = document.querySelector('select[name="role"]');
    if (!roleSelect) return;

    const form = roleSelect.closest('form');
    const fieldsDiv = document.createElement('div');
    form.insertBefore(fieldsDiv, form.querySelector('input[type="submit"]'));

    function updateExtraFields() {
        fieldsDiv.innerHTML = '';
        const role = parseInt(roleSelect.value);
        if (role === 2) {
            fieldsDiv.innerHTML = `
                אימייל: <input type="email" name="email" required style="margin:12px;"><br>
                טלפון: <input type="text" name="phone" required style="margin:12px;"><br>
                שנות ניסיון: <input type="number" name="experience" required style="margin:12px;"><br>
            `;
        } else if (role === 4) {
            fieldsDiv.innerHTML = `
                אימייל: <input type="email" name="email" required style="margin:12px;"><br>
                תפקיד: <input type="text" name="member_role" required style="margin:12px;"><br>
                כישורים (מופרדים בפסיקים): <input type="text" name="skills" required style="margin:12px;"><br>
            `;
        }
    }

    roleSelect.addEventListener('change', updateExtraFields);
    updateExtraFields();
});
</script>

<?php include __DIR__ . '/core/footer.php'; ?>
