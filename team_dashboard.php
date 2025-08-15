<?php
session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "חבר צוות";
include __DIR__ . '/core/header.php';

// Redirect if not logged in or not a team member
if (!isset($_SESSION['uid']) || $_SESSION['role'] != 4) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION['uid'];
$username = $_SESSION['name'] ?? '';

// Fetch member info
$res = $conn->query("SELECT m_id, m_data FROM member WHERE m_id = $uid");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $m_data = json_decode($row['m_data'], true);
} else {
    $m_data = ["email" => "", "role" => "", "skills" => []];
}
?>

<div class="container">
    <h2>שלום <?= htmlspecialchars($username) ?> 👋</h2>

    <div style="text-align:center; margin-bottom: 20px;">
        <a href="team_member_kanban.php" class="register-link">🔁 מעבר ללוח סקראם</a>
    </div>
</div>
<div class="container">
    <div class="section-box">
        <h3>📝 עדכון פרטי משתמש</h3>
        <form method="POST" action="update_member_info.php">
            <label for="email">אימייל:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($m_data['email']) ?>" required>

            <label for="role">תפקיד:</label>
            <input type="text" id="role" name="role" value="<?= htmlspecialchars($m_data['role']) ?>">

            <label for="skills">מיומנויות (מופרדות בפסיקים):</label>
            <input type="text" id="skills" name="skills" value="<?= htmlspecialchars(implode(',', $m_data['skills'])) ?>">

            <input type="submit" value="💾 שמור שינויים">
        </form>
    </div>
</div>

<?php include __DIR__ . '/core/footer.php'; ?>
