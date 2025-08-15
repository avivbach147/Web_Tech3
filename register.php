<?php
session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "הרשמה וניהול משתמשים";
include __DIR__ . '/core/header.php';

$registration_complete = false;
$new_user = null;
$updated_user = null;
$deleted_user = null;
$deleted_links = []; // summary rows of associations removed

$roles_map = [
    1 => "מנהל חברה",
    2 => "בעל מוצר",
    3 => "סקרם מאסטר",
    4 => "חבר צוות"
];

/* === Registration === */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? '') === "register") {
    $stmt = $conn->prepare("INSERT INTO user (login, password, name, role, lastused) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssi", $_POST["login"], $_POST["password"], $_POST["name"], $_POST["role"]);
    $stmt->execute();
    $new_id = $stmt->insert_id;

    $role = (int)$_POST['role'];
    if ($role === 3) {
        $scrum_data = [
            "uid" => $new_id,
            "name" => $_POST["name"],
            "email" => $_POST["email"] ?? '',
            "phone" => $_POST["phone"] ?? '',
            "experience_years" => (int)($_POST["experience"] ?? 0),
            "kanban" => ["log" => [], "ver" => "2025a", "tasks" => new stdClass(), "status" => ["New","To Do","Doing","Test","Done"], "process" => new stdClass()]
        ];
        $stmtScrum = $conn->prepare("INSERT INTO scrum_team (c_id, c_data) VALUES (?, ?)");
        $stmtScrum->bind_param("is", $new_id, json_encode($scrum_data, JSON_UNESCAPED_UNICODE));
        $stmtScrum->execute();
    }
    if ($role === 4) {
        $member_data = [
            "email" => $_POST["email"] ?? '',
            "role" => $_POST["member_role"] ?? 'Team Member',
            "skills" => array_filter(array_map('trim', explode(',', $_POST["skills"] ?? ''))),
            "kanban" => ["log" => [], "ver" => "2025a", "tasks" => new stdClass(), "status" => ["New","To Do","Doing","Test","Done"], "process" => new stdClass(), "memberid" => $new_id]
        ];
        $stmtMember = $conn->prepare("INSERT INTO member (m_id, s_id, m_data) VALUES (?, ?, ?)");
        $s_id = 1;
        $stmtMember->bind_param("iis", $new_id, $s_id, json_encode($member_data, JSON_UNESCAPED_UNICODE));
        $stmtMember->execute();
    }

    $new_user = $conn->query("SELECT * FROM user WHERE uid = " . (int)$new_id)->fetch_assoc();
    $registration_complete = true;
}

/* === Update user === */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? '') === "update") {
    $uid  = (int)($_POST["uid"] ?? 0);
    $login = $_POST["login"] ?? '';
    $password = $_POST["password"] ?? '';
    $name = $_POST["name"] ?? '';
    $role = (int)($_POST["role"] ?? 0);

    $stmt = $conn->prepare("UPDATE user SET login=?, password=?, name=?, role=? WHERE uid=?");
    $stmt->bind_param("sssii", $login, $password, $name, $role, $uid);
    $stmt->execute();

    $updated_user = $conn->query("SELECT * FROM user WHERE uid = " . $uid)->fetch_assoc();
}

/* === Delete user (+ detach links) === */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? '') === "delete") {
    $uid = (int)($_POST['uid'] ?? 0);

    // קח צילום מצב לפני מחיקה
    $deleted_user = $conn->query("SELECT * FROM user WHERE uid = " . $uid)->fetch_assoc();

    if ($deleted_user) {
        // scrum_team rows where this user is Scrum Master (c_id)
        $scrumRows = [];
        if ($result = $conn->query("SELECT c_id, c_data FROM scrum_team WHERE c_id = " . $uid)) {
            while ($r = $result->fetch_assoc()) {
                $j = json_decode($r['c_data'], true);
                $scrumRows[] = [
                    'table' => 'scrum_team',
                    'key'   => 'c_id',
                    'key_val' => (int)$r['c_id'],
                    'details' => 'שם: ' . htmlspecialchars($j['name'] ?? '', ENT_QUOTES, 'UTF-8')
                ];
            }
        }
        // member rows where this user is a team member (m_id)
        $memberRows = [];
        if ($result = $conn->query("SELECT m_id, s_id, m_data FROM member WHERE m_id = " . $uid)) {
            while ($r = $result->fetch_assoc()) {
                $j = json_decode($r['m_data'], true);
                $memberRows[] = [
                    'table' => 'member',
                    'key'   => 'm_id',
                    'key_val' => (int)$r['m_id'],
                    'details' => 'צוות/ספרינט: ' . ((int)$r['s_id']) .
                                 ' | תפקיד: ' . htmlspecialchars($j['role'] ?? '', ENT_QUOTES, 'UTF-8') .
                                 ' | דוא"ל: ' . htmlspecialchars($j['email'] ?? '', ENT_QUOTES, 'UTF-8')
                ];
            }
        }

        // בצע מחיקות בפועל
        $conn->query("DELETE FROM scrum_team WHERE c_id = " . $uid);
        $conn->query("DELETE FROM member WHERE m_id = " . $uid);
        $conn->query("DELETE FROM user WHERE uid = " . $uid);

        // מזג את הסיכומים
        $deleted_links = array_merge($scrumRows, $memberRows);
    }
}
?>
<div class="container">
    <div class="section-box">
        <h2>רישום משתמש חדש</h2>

        <!-- הטופס תמיד מוצג -->
        <form method="POST">
            <input type="hidden" name="action" value="register">

            <label>שם מלא:</label>
            <input type="text" name="name" required>

            <label>שם משתמש:</label>
            <input type="text" name="login" required>

            <label>סיסמה:</label>
            <input type="text" name="password" required>

            <label>תפקיד:</label>
            <select name="role" required>
                <option value="1">מנהל חברה</option>
                <option value="2">בעל מוצר</option>
                <option value="3">סקרם מאסטר</option>
                <option value="4">חבר צוות</option>
            </select>

            <input type="submit" value="רשום">
        </form>

        <?php if ($registration_complete && $new_user): ?>
            <div class="alert alert-success">✅ נרשם משתמש חדש</div>
            <table>
                <thead>
                    <tr>
                        <th>מזהה</th>
                        <th>שם משתמש</th>
                        <th>סיסמה</th>
                        <th>שם מלא</th>
                        <th>תפקיד</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= (int)$new_user["uid"] ?></td>
                        <td><?= htmlspecialchars($new_user["login"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($new_user["password"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($new_user["name"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $roles_map[(int)($new_user["role"] ?? 0)] ?? '-' ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($updated_user): ?>
        <div class="section-box">
            <div class="alert alert-success">🔄 המשתמש עודכן</div>
            <table>
                <thead>
                    <tr>
                        <th>מזהה</th>
                        <th>שם משתמש</th>
                        <th>סיסמה</th>
                        <th>שם מלא</th>
                        <th>תפקיד</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= (int)$updated_user["uid"] ?></td>
                        <td><?= htmlspecialchars($updated_user["login"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($updated_user["password"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($updated_user["name"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $roles_map[(int)($updated_user["role"] ?? 0)] ?? '-' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($deleted_user): ?>
        <div class="section-box">
            <div class="alert alert-danger">🗑️ המשתמש נמחק</div>

            <!-- פרטי המשתמש שנמחק -->
            <table>
                <thead>
                    <tr>
                        <th>מזהה</th>
                        <th>שם משתמש</th>
                        <th>סיסמה</th>
                        <th>שם מלא</th>
                        <th>תפקיד</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= (int)$deleted_user["uid"] ?></td>
                        <td><?= htmlspecialchars($deleted_user["login"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($deleted_user["password"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($deleted_user["name"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $roles_map[(int)($deleted_user["role"] ?? 0)] ?? '-' ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- שיוכים שהוסרו -->
            <h3>שיוכים שהוסרו</h3>
            <table>
                <thead>
                    <tr>
                        <th>טבלה</th>
                        <th>שדה מפתח</th>
                        <th>ערך</th>
                        <th>פרטים</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_links)): ?>
                        <tr><td colspan="4">לא נמצאו שיוכים למחיקה</td></tr>
                    <?php else: foreach ($deleted_links as $lnk): ?>
                        <tr>
                            <td><?= htmlspecialchars($lnk['table'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($lnk['key'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$lnk['key_val'] ?></td>
                            <td><?= $lnk['details'] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="section-box" id="user-management">
        <h2>כל המשתמשים</h2>
        <table>
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
                <?php
                $users = $conn->query("SELECT * FROM user");
                while ($row = $users->fetch_assoc()):
                ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="uid" value="<?= (int)$row['uid'] ?>">
                        <td><?= (int)$row['uid'] ?></td>
                        <td><input type="text" name="login" value="<?= htmlspecialchars($row['login'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></td>
                        <td><input type="text" name="password" value="<?= htmlspecialchars($row['password'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></td>
                        <td><input type="text" name="name" value="<?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></td>
                        <td>
                            <select name="role">
                                <option value="1" <?= ((int)$row['role']===1) ? 'selected' : '' ?>>מנהל חברה</option>
                                <option value="2" <?= ((int)$row['role']===3) ? 'selected' : '' ?>>בעל מוצר</option>
                                <option value="3" <?= ((int)$row['role']===2) ? 'selected' : '' ?>>סקרם מאסטר</option>
                                <option value="4" <?= ((int)$row['role']===4) ? 'selected' : '' ?>>חבר צוות</option>
                            </select>
                        </td>
                        <td><input type="submit" value="עדכן"></td>
                    </form>
                    <form method="POST" onsubmit="return confirm('למחוק את המשתמש?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="uid" value="<?= (int)$row['uid'] ?>">
                        <td><input type="submit" value="מחק"></td>
                    </form>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/core/footer.php'; ?>
