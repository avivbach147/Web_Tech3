<?php
session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "הוספת מוצר";
include __DIR__ . '/core/header.php';

$product_added = false;
$added_product = null;
$deleted_product = null;
$deleted_assignments = [];

// Add new product
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add") {
    $c_id     = $_POST["company_id"];
    $uid      = $_POST["owner_uid"];
    $name     = $_POST["name"];
    $desc     = $_POST["description"];
    $version  = $_POST["version"];
    $release  = $_POST["release_date"];
    $status   = $_POST["status"];

    $product_data = [
        "uid"          => $uid,
        "name"         => $name,
        "description"  => $desc,
        "version"      => $version,
        "release_date" => $release,
        "status"       => $status,
        "kanban"       => [
            "tasks"   => new stdClass(),
            "process" => new stdClass(),
            "log"     => [],
            "status"  => ["New","To Do","Doing","Test","Done"],
            "ver"     => "init"
        ]
    ];

    $stmt = $conn->prepare("INSERT INTO product (c_id, p_data) VALUES (?, ?)");
    $json = json_encode($product_data, JSON_UNESCAPED_UNICODE);
    $stmt->bind_param("is", $c_id, $json);
    $stmt->execute();

    $product_added = true;
    $added_product = $product_data;
}

// Delete product + assignments
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $pid = intval($_POST["p_id"]);

    $result = $conn->query("SELECT * FROM product WHERE p_id = $pid");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $deleted_product = json_decode($row["p_data"], true);

        $res = $conn->query("SELECT m_id FROM p_m WHERE p_id = $pid");
        while ($r = $res->fetch_assoc()) {
            $deleted_assignments[] = $r["m_id"];
        }

        $conn->query("DELETE FROM p_m WHERE p_id = $pid");
        $conn->query("DELETE FROM product WHERE p_id = $pid");
    }
}
?>

<div class="container"><!-- Use global container styles -->
    <h2>הוספת מוצר חדש</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add">

        <label>שם המוצר:
            <input type="text" name="name" required>
        </label>

        <label>תיאור:
            <input type="text" name="description" required>
        </label>

        <label>גרסה:
            <input type="text" name="version" required>
        </label>

        <label>תאריך שחרור:
            <input type="date" name="release_date" required>
        </label>

        <label>סטטוס:
            <select name="status" required>
                <option value="In Development">In Development</option>
                <option value="Released">Released</option>
                <option value="Archived">Archived</option>
            </select>
        </label>

        <label>בחר חברה:
            <select name="company_id" required>
                <?php
                $companies = $conn->query("SELECT c_id, c_data FROM company");
                while ($row = $companies->fetch_assoc()) {
                    $cdata = json_decode($row['c_data'], true);
                    $company_name = $cdata['name'] ?? "Unknown";
                    echo "<option value='{$row['c_id']}'>".htmlspecialchars($company_name)."</option>";
                }
                ?>
            </select>
        </label>

        <label>בעל מוצר:
            <select name="owner_uid" required>
                <?php
                // Product Manager is role=2
                $owners = $conn->query("SELECT * FROM user WHERE role = 2");
                while ($u = $owners->fetch_assoc()):
                ?>
                    <option value="<?= $u['uid'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['uid'] ?>)</option>
                <?php endwhile; ?>
            </select>
        </label>

        <input type="submit" value="הוסף מוצר">
    </form>
</div>

<?php if ($product_added && $added_product): ?>
    <div class="container" style="margin-top:20px;">
        <h3>✅ מוצר חדש נוסף:</h3>
        <table>
            <tbody>
                <tr><th>שם</th><td><?= htmlspecialchars($added_product['name']) ?></td></tr>
                <tr><th>תיאור</th><td><?= htmlspecialchars($added_product['description']) ?></td></tr>
                <tr><th>גרסה</th><td><?= htmlspecialchars($added_product['version']) ?></td></tr>
                <tr><th>תאריך שחרור</th><td><?= htmlspecialchars($added_product['release_date']) ?></td></tr>
                <tr><th>סטטוס</th><td><?= htmlspecialchars($added_product['status']) ?></td></tr>
                <tr><th>בעל מוצר</th><td><?= htmlspecialchars($added_product['uid']) ?></td></tr>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($deleted_product): ?>
    <div class="container" style="margin-top:20px;">
        <h3>🗑️ המוצר נמחק:</h3>
        <table>
            <tbody>
                <tr><th>שם</th><td><?= htmlspecialchars($deleted_product['name']) ?></td></tr>
                <tr><th>תיאור</th><td><?= htmlspecialchars($deleted_product['description']) ?></td></tr>
                <tr><th>גרסה</th><td><?= htmlspecialchars($deleted_product['version']) ?></td></tr>
                <tr><th>תאריך שחרור</th><td><?= htmlspecialchars($deleted_product['release_date']) ?></td></tr>
                <tr><th>סטטוס</th><td><?= htmlspecialchars($deleted_product['status']) ?></td></tr>
                <tr><th>בעל מוצר</th><td><?= htmlspecialchars($deleted_product['uid']) ?></td></tr>
            </tbody>
        </table>

        <?php if (!empty($deleted_assignments)): ?>
            <p>❌ נמחקו גם שיוכים עם אנשי צוות (m_id): <?= htmlspecialchars(implode(", ", $deleted_assignments)) ?></p>
        <?php else: ?>
            <p>לא היו שיוכים של המוצר לאנשי צוות.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="container"><!-- Use global container so the table inherits container table styles -->
    <h2>‌רשימת מוצרים</h2>
    <table>
        <thead>
            <tr>
                <th>מזהה</th>
                <th>שם</th>
                <th>תיאור</th>
                <th>בעל מוצר</th>
                <th>סטטוס</th>
                <th>מחיקה</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $products = $conn->query("SELECT * FROM product");
        while ($p = $products->fetch_assoc()):
            $p_data = json_decode($p['p_data'], true);
        ?>
            <tr>
                <td><?= $p['p_id'] ?></td>
                <td><?= htmlspecialchars($p_data['name']) ?></td>
                <td><?= htmlspecialchars($p_data['description']) ?></td>
                <td><?= htmlspecialchars($p_data['uid']) ?></td>
                <td><?= htmlspecialchars($p_data['status']) ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('למחוק את המוצר?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="p_id" value="<?= $p['p_id'] ?>">
                        <button type="submit" class="action-btn delete"><i class="fas fa-trash"></i> מחק</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <div style="text-align:center; margin-top:20px;">
        <a class="register-link" href="company_dashboard.php">⬅ חזרה לעמוד הראשי</a>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include __DIR__ . '/core/footer.php'; ?>
