<?php
// Bootstrap
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Page meta and layout
$pageTitle = "ניהול מוצרים";
include __DIR__ . '/core/header.php';

// DB connection
include __DIR__ . '/db.php';

// State flags
$product_added = false;
$added_product = null;
$deleted_product = null;
$deleted_assignments = [];
$edit_product = null;
$edit_pid = null;
$updated_product = null; // used for update summary

/* Preload owners list (role=2) for selects */
$owners_list = [];
$owners_res = $conn->query("SELECT uid, name FROM user WHERE role = 2");
if ($owners_res) {
    while ($r = $owners_res->fetch_assoc()) {
        $owners_list[] = ['uid' => (int)$r['uid'], 'name' => $r['name']];
    }
}

/* Add new product */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add") {
    $c_id    = (int)($_POST["company_id"] ?? 0);
    $uid     = (int)($_POST["owner_uid"] ?? 0);
    $name    = $_POST["name"] ?? "";
    $desc    = $_POST["description"] ?? "";
    $version = $_POST["version"] ?? "";
    $release = $_POST["release_date"] ?? "";
    $status  = $_POST["status"] ?? "";

    $product_data = [
        "uid"          => $uid,
        "name"         => $name,
        "description"  => $desc,
        "version"      => $version,
        "release_date" => $release,
        "status"       => $status,
        "kanban" => [
            "tasks"   => new stdClass(),
            "process" => new stdClass(),
            "log"     => [],
            "status"  => ["New", "To Do", "Doing", "Test", "Done"],
            "ver"     => "init"
        ]
    ];

    $json = json_encode($product_data, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO product (c_id, p_data) VALUES (?, ?)");
    $stmt->bind_param("is", $c_id, $json);
    $stmt->execute();
    $stmt->close();

    $product_added = true;
    $added_product = $product_data;
}

/* Delete product */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $pid = (int)($_POST["p_id"] ?? 0);

    // Read details before deletion (for summary)
    $stmt = $conn->prepare("SELECT p_data FROM product WHERE p_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows) {
        $row = $res->fetch_assoc();
        $deleted_product = json_decode($row["p_data"], true);
    }
    $stmt->close();

    // Collect assignments to show what was removed
    $stmt = $conn->prepare("SELECT m_id FROM p_m WHERE p_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $deleted_assignments[] = (int)$r["m_id"];
    }
    $stmt->close();

    // Delete assignments and product
    $stmt = $conn->prepare("DELETE FROM p_m WHERE p_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM product WHERE p_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $stmt->close();
}

/* Update product (inline row form) */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update") {
    $pid     = (int)($_POST["p_id"] ?? 0);
    $name    = $_POST["name"] ?? "";
    $desc    = $_POST["description"] ?? "";
    $version = $_POST["version"] ?? "";
    $release = $_POST["release_date"] ?? "";
    $status  = $_POST["status"] ?? "";

    $stmt = $conn->prepare("SELECT p_data FROM product WHERE p_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && ($row = $res->fetch_assoc())) {
        $p_data = json_decode($row["p_data"], true) ?: [];

        // Update fields from POST
        $p_data["name"]         = $name;
        $p_data["description"]  = $desc;
        $p_data["version"]      = $version;
        $p_data["release_date"] = $release;
        $p_data["status"]       = $status;

        // Update owner if provided by the inline form
        if (isset($_POST["owner_uid"])) {
            $p_data["uid"] = (int)$_POST["owner_uid"];
        }

        $json = json_encode($p_data, JSON_UNESCAPED_UNICODE);
        $stmt2 = $conn->prepare("UPDATE product SET p_data = ? WHERE p_id = ?");
        $stmt2->bind_param("si", $json, $pid);
        $stmt2->execute();
        $stmt2->close();

        $updated_product = $p_data; // for summary box
    }
    $stmt->close();
}

/* Load product for editing (legacy edit form remains available) */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "edit") {
    $edit_pid = (int)($_POST["p_id"] ?? 0);
    $stmt = $conn->prepare("SELECT p_data FROM product WHERE p_id = ?");
    $stmt->bind_param("i", $edit_pid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $edit_product = json_decode($row["p_data"], true);
    }
    $stmt->close();
}
?>

<div class="container">
  <h2>שלום <?= htmlspecialchars($_SESSION['name'] ?? 'אורח', ENT_QUOTES, 'UTF-8') ?> 👋</h2>

  <?php if ($edit_product): ?>
    <h2>✏️ עריכת מוצר</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="p_id" value="<?= (int)$edit_pid ?>">
      שם המוצר:
      <input type="text" name="name" value="<?= htmlspecialchars($edit_product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required><br>
      תיאור:
      <input type="text" name="description" value="<?= htmlspecialchars($edit_product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required><br>
      גרסה:
      <input type="text" name="version" value="<?= htmlspecialchars($edit_product['version'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required><br>
      תאריך שחרור:
      <input type="date" name="release_date" value="<?= htmlspecialchars($edit_product['release_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required><br>
      סטטוס:
      <select name="status" required>
        <?php foreach (["In Development", "Released", "Archived"] as $s): ?>
          <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= ($edit_product['status'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select><br>
      <input type="submit" value="💾 שמור שינויים">
      <a href="product_manager.php">ביטול</a>
    </form>
  <?php else: ?>
    <h2>➕ הוספת מוצר חדש</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      שם המוצר: <input type="text" name="name" required><br>
      תיאור: <input type="text" name="description" required><br>
      גרסה: <input type="text" name="version" required><br>
      תאריך שחרור: <input type="date" name="release_date" required><br>
      סטטוס:
      <select name="status" required>
        <option value="In Development">In Development</option>
        <option value="Released">Released</option>
        <option value="Archived">Archived</option>
      </select><br>

      <label>בחר חברה:</label>
      <select name="company_id" required>
        <?php
        $companies = $conn->query("SELECT c_id, c_data FROM company");
        while ($row = $companies->fetch_assoc()):
            $cdata = json_decode($row['c_data'], true);
            $company_name = $cdata['name'] ?? "Unknown";
        ?>
          <option value="<?= (int)$row['c_id'] ?>"><?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endwhile; ?>
      </select><br>

      בעל מוצר:
      <select name="owner_uid" required>
        <?php foreach ($owners_list as $o): ?>
          <option value="<?= (int)$o['uid'] ?>"><?= htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$o['uid'] ?>)</option>
        <?php endforeach; ?>
      </select><br>

      <input type="submit" value="הוסף מוצר" style="margin-bottom: 20px;">
    </form>
  <?php endif; ?>

  <?php if ($product_added && $added_product): ?>
    <div class="alert alert-success">✅ מוצר חדש נוסף</div>
    <table>
      <thead>
        <tr>
          <th>שם</th>
          <th>תיאור</th>
          <th>גרסה</th>
          <th>תאריך שחרור</th>
          <th>סטטוס</th>
          <th>בעל מוצר</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= htmlspecialchars($added_product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($added_product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($added_product['version'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($added_product['release_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($added_product['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int)($added_product['uid'] ?? 0) ?></td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($updated_product): ?>
    <div class="alert alert-success">🔄 המוצר עודכן</div>
    <table>
      <thead>
        <tr>
          <th>שם</th>
          <th>תיאור</th>
          <th>גרסה</th>
          <th>תאריך שחרור</th>
          <th>סטטוס</th>
          <th>בעל מוצר</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= htmlspecialchars($updated_product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($updated_product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($updated_product['version'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($updated_product['release_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($updated_product['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int)($updated_product['uid'] ?? 0) ?></td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($deleted_product): ?>
    <div class="alert alert-danger">🗑️ המוצר נמחק</div>
    <table>
      <thead>
        <tr>
          <th>שם</th>
          <th>תיאור</th>
          <th>גרסה</th>
          <th>תאריך שחרור</th>
          <th>סטטוס</th>
          <th>בעל מוצר</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= htmlspecialchars($deleted_product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($deleted_product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($deleted_product['version'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($deleted_product['release_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($deleted_product['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int)($deleted_product['uid'] ?? 0) ?></td>
        </tr>
      </tbody>
    </table>

    <?php if (!empty($deleted_assignments)): ?>
      <p>❌ נמחקו גם שיוכים עם אנשי צוות (m_id): <?= htmlspecialchars(implode(", ", $deleted_assignments), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <p>לא היו שיוכים של המוצר לאנשי צוות.</p>
    <?php endif; ?>
  <?php endif; ?>

  <hr>
  <h2 style="margin-top:50px;">📦 רשימת מוצרים </h2>
  <table><!-- use global container table styles -->
    <thead>
      <tr>
        <th>מזהה</th>
        <th>שם</th>
        <th>תיאור</th>
        <th>בעל מוצר</th>
        <th>סטטוס</th>
        <th>עדכון</th>
        <th>מחיקה</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $products = $conn->query("SELECT p_id, p_data FROM product");
      while ($p = $products->fetch_assoc()):
        $p_data = json_decode($p['p_data'], true) ?: [];
        $pid    = (int)$p['p_id'];
        $pname  = htmlspecialchars($p_data['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $pdesc  = htmlspecialchars($p_data['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $pver   = htmlspecialchars($p_data['version'] ?? '', ENT_QUOTES, 'UTF-8');
        $prls   = htmlspecialchars($p_data['release_date'] ?? '', ENT_QUOTES, 'UTF-8');
        $pstat  = htmlspecialchars($p_data['status'] ?? '', ENT_QUOTES, 'UTF-8');
        $powner = (int)($p_data['uid'] ?? 0);
      ?>
        <tr>
          <!-- Update form across the row (like users table) -->
          <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="p_id" value="<?= $pid ?>">
            <!-- keep version & release so they are not lost on inline update -->
            <input type="hidden" name="version" value="<?= $pver ?>">
            <input type="hidden" name="release_date" value="<?= $prls ?>">

            <td><?= $pid ?></td>
            <td><input type="text" name="name" value="<?= $pname ?>"></td>
            <td><input type="text" name="description" value="<?= $pdesc ?>"></td>
            <td>
              <select name="owner_uid">
                <?php foreach ($owners_list as $o): ?>
                  <option value="<?= (int)$o['uid'] ?>" <?= $powner===$o['uid'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$o['uid'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select name="status">
                <?php foreach (["In Development","Released","Archived"] as $s): ?>
                  <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= ($p_data['status'] ?? '') === $s ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="submit" value="עדכן"></td>
          </form>

          <!-- Separate delete form in its own cell -->
          <form method="POST" onsubmit="return confirm('למחוק את המוצר?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="p_id" value="<?= $pid ?>">
            <td><input type="submit" value="מחק"></td>
          </form>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <p><a href="product_dashboard.php" class="register-link">⬅ חזרה לעמוד הראשי</a></p>
</div>

<?php include __DIR__ . '/core/footer.php'; ?>
