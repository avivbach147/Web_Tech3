<?php
session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Guard: Company Manager only (role = 1)
if (!isset($_SESSION['uid']) || ($_SESSION['role'] ?? null) != 1) {
    header("Location: login.php");
    exit;
}

$pageTitle = "דו\"ח לפי חברה";
include __DIR__ . '/core/header.php';

$roles = [1 => "מנהל חברה", 3 => "בעל מוצר", 2 => "סקרם מאסטר", 4 => "חבר צוות"];
$username = htmlspecialchars($_SESSION["name"] ?? 'אורח');
?>

<div class="container">
    <h2>שלום <?= $username ?> 👋</h2>
</div>

<!-- Filter card -->
<div class="container">
    <h2>דו״ח לפי חברה</h2>
    <form method="GET">
        <label for="company_id">בחר חברה:</label>
        <select name="company_id" id="company_id" onchange="this.form.submit()">
            <option value="">-- בחר --</option>
            <?php
            $companies = $conn->query("SELECT c_id, c_data FROM company");
            while ($row = $companies->fetch_assoc()) {
                $cdata = json_decode($row['c_data'], true) ?: [];
                $name  = htmlspecialchars($cdata['name'] ?? ("Company #".$row['c_id']));
                $selected = (isset($_GET['company_id']) && $_GET['company_id'] == $row['c_id']) ? 'selected' : '';
                echo "<option value=\"{$row['c_id']}\" $selected>$name</option>";
            }
            ?>
        </select>
    </form>
</div>

<?php
if (isset($_GET['company_id']) && is_numeric($_GET['company_id'])) {
    $company_id = (int)$_GET['company_id'];

    // Company name
    $company_result = $conn->query("SELECT c_data FROM company WHERE c_id = $company_id");
    if ($company_result && $company_result->num_rows > 0) {
        $company_data = json_decode($company_result->fetch_assoc()['c_data'], true) ?: [];
        $company_name = htmlspecialchars($company_data['name'] ?? ("Company #".$company_id));
        ?>
        <div class="container">
            <h2>דו״ח עבור חברה: <?= $company_name ?></h2>

            <?php
            // Products in this company
            $products = $conn->query("SELECT p_id, p_data FROM product WHERE c_id = $company_id");
            if ($products && $products->num_rows > 0):
                while ($product = $products->fetch_assoc()):
                    $pdata = json_decode($product['p_data'], true) ?: [];
                    $pname = htmlspecialchars($pdata['name'] ?? ("Product #".$product['p_id']));
                    $pdesc = htmlspecialchars($pdata['description'] ?? "");
                    $pver  = htmlspecialchars($pdata['version'] ?? "");
                    $prls  = htmlspecialchars($pdata['release_date'] ?? "");
                    $pstat = htmlspecialchars($pdata['status'] ?? "");
                    $owner_id = (int)($pdata['uid'] ?? 0);

                    // Product Owner name
                    $owner_name = 'לא נמצא';
                    if ($owner_id > 0) {
                        $owner_query = $conn->query("SELECT name FROM user WHERE uid = $owner_id");
                        if ($owner_query && $owner_query->num_rows > 0) {
                            $owner_name = htmlspecialchars($owner_query->fetch_assoc()['name']);
                        }
                    }
            ?>
                <div class="container">
                    <h3>📦 מוצר: <?= $pname ?></h3>
                    <table>
                        <tbody>
                            <tr><th>בעל מוצר</th><td><?= $owner_name ?> (ID: <?= $owner_id ?>)</td></tr>
                            <tr><th>תיאור</th><td><?= $pdesc ?></td></tr>
                            <tr><th>גרסה</th><td><?= $pver ?></td></tr>
                            <tr><th>תאריך שחרור</th><td><?= $prls ?></td></tr>
                            <tr><th>סטטוס</th><td><?= $pstat ?></td></tr>
                        </tbody>
                    </table>

                    <?php
                    // Team members assigned to this product
                    $stmt = $conn->prepare("SELECT m_id FROM p_m WHERE p_id = ?");
                    $stmt->bind_param("i", $product['p_id']);
                    $stmt->execute();
                    $members_result = $stmt->get_result();
                    ?>

                    <h4 style="margin-top:20px; padding-top:20px; border-top: var(--border-color) 1px solid;">👥 חברי צוות </h4>
                    <?php if ($members_result->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr><th>מזהה</th><th>שם</th><th>שם משתמש</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($m = $members_result->fetch_assoc()):
                                $member_id = (int)$m['m_id'];
                                $user_result = $conn->query("SELECT uid, name, login FROM user WHERE uid = $member_id");
                                if ($user_result && $user_result->num_rows > 0):
                                    $user = $user_result->fetch_assoc();
                            ?>
                                <tr>
                                    <td><?= (int)$user['uid'] ?></td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['login']) ?></td>
                                </tr>
                            <?php endif; endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>>אין חברי צוות משויכים.</p>
                    <?php endif; ?>
                </div>
            <?php
                endwhile;
            else:
                echo '<p>לא נמצאו מוצרים לחברה זו.</p>';
            endif;
            ?>
        </div>
        <?php
    } else {
        echo '<div class="container"><p>חברה לא נמצאה.</p></div>';
    }
}
?>

    <a href="company_dashboard.php" class="register-link" style="margin-bottom:50px;">⬅ חזרה לעמוד הראשי</a>
</div>

<?php include __DIR__ . '/core/footer.php'; ?>
