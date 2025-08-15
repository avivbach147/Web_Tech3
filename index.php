<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST["login"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT * FROM user WHERE login = ?");
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();

        // It's recommended to use password_verify() in real apps.
        if ($user["password"] === $password) { 
            $_SESSION["uid"] = $user["uid"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["role"] = $user["role"];

            // Update lastused field
            $update = $conn->prepare("UPDATE user SET lastused = NOW() WHERE uid = ?");
            $update->bind_param("i", $user["uid"]);
            $update->execute();

            // Redirect based on role
            if ($user["role"] == 1) {
                header("Location: company_dashboard.php");
                exit();
            } elseif ($user["role"] == 2) {
                header("Location: scrum_dashboard.php"); 
                exit();
            } elseif ($user["role"] == 3) {
                header("Location: product_dashboard.php");
                exit();
            } elseif ($user["role"] == 4) {
                header("Location: team_dashboard.php");
                exit();
            } else {
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $error = "סיסמה שגויה";
        }
    } else {
        $error = "שם משתמש לא קיים";
    }
}

$pageTitle = "התחברות";
include __DIR__ . '/core/header.php';
?>

<div class="container">
    <form method="POST">
        <h2>התחברות</h2>
        <input type="text" name="login" placeholder="שם משתמש" required><br>
        <input type="password" name="password" placeholder="סיסמה" required><br>
        <input type="submit" value="התחבר">
    </form>
    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <a class="register-link" href="register.php">אין חשבון? הרשמה</a>
</div>

<?php include __DIR__ . '/core/footer.php'; ?>
