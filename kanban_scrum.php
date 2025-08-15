<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . "/db.php";

$uid = $_SESSION["uid"] ?? null;
if (!$uid) {
    header("Location: login.php");
    exit;
}

$pageTitle = "לוח קנבן";
include __DIR__ . "/core/header.php";

$selected_pid = $_GET["pid"] ?? null;
$owned_products = [];

// מוצרים שהמשתמש הבעלים שלהם
$sql1 = "SELECT p_id, p_data FROM product WHERE JSON_UNQUOTE(JSON_EXTRACT(p_data, '$.uid')) = ?";
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param('s', $uid);
$stmt1->execute();
$res1 = $stmt1->get_result();
while ($row = $res1->fetch_assoc()) {
    $pdata = json_decode($row["p_data"], true) ?: [];
    $owned_products[$row["p_id"]] = [
        "p_id"   => (int)$row["p_id"],
        "name"   => $pdata["name"] ?? "",
        "p_data" => $pdata
    ];
}
$stmt1->close();

// מוצרים ששותף אליהם
$sql2 = "
    SELECT DISTINCT p.p_id, p.p_data
    FROM product p
    JOIN p_m pm ON p.p_id = pm.p_id
    JOIN member m ON pm.m_id = m.m_id
    JOIN scrum_team st ON m.s_id = st.s_id
    WHERE JSON_UNQUOTE(JSON_EXTRACT(st.c_data, '$.uid')) = ?
";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param('s', $uid);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) {
    if (!isset($owned_products[$row["p_id"]])) {
        $pdata = json_decode($row["p_data"], true) ?: [];
        $owned_products[$row["p_id"]] = [
            "p_id"   => (int)$row["p_id"],
            "name"   => $pdata["name"] ?? "",
            "p_data" => $pdata
        ];
    }
}
$stmt2->close();

$owned_products = array_values($owned_products);

$selected_data = null;
foreach ($owned_products as $product) {
    if ((string)$product["p_id"] === (string)$selected_pid) {
        $selected_data = $product["p_data"];
        break;
    }
}

$maxNewId = 10000;
if ($selected_data) {
    $tasks = $selected_data["kanban"]["tasks"] ?? [];
    foreach (array_keys($tasks) as $tid) {
        if (preg_match('/^new-(\d+)$/', (string)$tid, $m)) {
            $num = (int)$m[1];
            if ($num >= $maxNewId) $maxNewId = $num + 1;
        }
    }
}
?>

<div class="container" id="kanban-page">
  <h2>שלום <?= htmlspecialchars($_SESSION["name"] ?? "", ENT_QUOTES, 'UTF-8') ?> 👋</h2>

  <h2>בחר מוצר כדי לנהל את לוח הקאנבן שלו</h2>
  <form method="GET">
    <select name="pid" onchange="this.form.submit()">
      <option value="">-- בחר מוצר --</option>
      <?php foreach ($owned_products as $product): ?>
        <option value="<?= (int)$product['p_id'] ?>" <?= ((string)$product['p_id'] === (string)$selected_pid) ? 'selected' : '' ?>>
          <?= htmlspecialchars($product["name"] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($selected_data): ?>
  <?php
    $tasks    = $selected_data["kanban"]["tasks"]   ?? [];
    $statuses = $selected_data["kanban"]["status"]  ?? [];
    $process  = $selected_data["kanban"]["process"] ?? [];
  ?>

  <div id="kanban-container">
    <h3>לוח משימות: <?= htmlspecialchars($selected_data["name"] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>

    <form method="POST" action="save_kanban.php">
      <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$selected_pid, ENT_QUOTES, 'UTF-8') ?>">

      <div class="kanban-board">
        <?php foreach ($statuses as $status_index => $status_name): ?>
          <div class="kanban-column" data-status="<?= (int)$status_index ?>">
            <div class="kanban-column__header">
              <h4><?= htmlspecialchars((string)$status_name, ENT_QUOTES, 'UTF-8') ?></h4>
              <button type="button" class="btn btn-light btn-sm add-task" data-status="<?= (int)$status_index ?>">➕</button>
            </div>

            <?php foreach ($process as $tid => $s_index): ?>
              <?php if ((string)$s_index === (string)$status_index && isset($tasks[$tid])): ?>
                <?php $t = $tasks[$tid]["data"] ?? ["title"=>"","info"=>""]; ?>
                <div class="kanban-task" draggable="true" id="task-<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?>" data-id="<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?>">
                  <strong><?= htmlspecialchars((string)($t["title"] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><br>
                  <?= htmlspecialchars((string)($t["info"] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                  <br>
                  <button
                    type="button"
                    class="btn btn-danger btn-xs delete-task"
                    data-id="<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?>"
                    draggable="false"
                    onmousedown="event.preventDefault();event.stopPropagation();"
                    onclick="onDeleteTaskClick(event)"
                  >🗑️</button>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <input type="hidden" name="updated_process" id="updated-process">
      <input type="hidden" name="all_tasks" id="all-tasks">
      <button type="submit" class="btn btn-primary" id="save-button">💾 שמור</button>
    </form>
  </div>

  <!-- Modal -->
  <div id="taskModal">
    <form id="modalForm" class="modal-card">
      <h3>➕ משימה חדשה</h3>
      <input type="hidden" id="modalStatusIndex">
      <label>כותרת:</label><br>
      <input type="text" id="modalTitle" required><br>
      <label>תיאור:</label><br>
      <textarea id="modalInfo" required></textarea><br><br>
      <div style="text-align:center;">
        <button type="submit" class="btn btn-primary">שמור</button>
        <button type="button" class="btn btn-light" id="closeModal">ביטול</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<p><a href="scrum_dashboard.php" class="register-link" style="margin-top:30px;">⬅ חזרה לעמוד הראשי</a></p>

<?php include __DIR__ . "/core/footer.php"; ?>


<!-- JavaScript -->
<script>
let dragged = null;
let newTaskId = <?= (int)$maxNewId ?>;

function onDeleteTaskClick(e) {
  const btn = e.currentTarget;
  const tid = btn.getAttribute("data-id") || "";
  if (!tid) return;
  const task = document.getElementById("task-" + tid);
  if (task && confirm("האם למחוק משימה זו?")) {
    task.remove();
  }
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".kanban-column").forEach(col => {
    col.addEventListener("dragover", e => e.preventDefault());
    col.addEventListener("drop", e => {
      e.preventDefault();
      if (dragged) col.appendChild(dragged);
    });
  });

  document.querySelectorAll(".kanban-task").forEach(task => {
    task.addEventListener("dragstart", e => {
      dragged = e.currentTarget;
    });
  });

  document.querySelectorAll(".add-task").forEach(btn => {
    btn.addEventListener("click", () => {
      document.getElementById("modalStatusIndex").value = btn.dataset.status;
      document.getElementById("modalTitle").value = "";
      document.getElementById("modalInfo").value = "";
      document.getElementById("taskModal").style.display = "block";
      setTimeout(() => document.getElementById("modalTitle").focus(), 100);
    });
  });

  document.getElementById("closeModal").addEventListener("click", () => {
    document.getElementById("taskModal").style.display = "none";
  });

  document.getElementById("modalForm").addEventListener("submit", e => {
    e.preventDefault();
    const title = document.getElementById("modalTitle").value.trim();
    const info = document.getElementById("modalInfo").value.trim();
    const statusIndex = document.getElementById("modalStatusIndex").value;
    if (!title || !info) return;

    const tid = `new-${newTaskId++}`;
    const task = document.createElement("div");
    task.className = "kanban-task";
    task.draggable = true;
    task.id = "task-" + tid;
    task.dataset.id = tid;
    task.innerHTML = `
      <strong>${title}</strong><br>${info}
      <br>
      <button
        type="button"
        class="btn btn-danger btn-xs delete-task"
        data-id="${tid}"
        draggable="false"
        onmousedown="event.preventDefault();event.stopPropagation();"
        onclick="onDeleteTaskClick(event)"
      >🗑️</button>
    `;
    task.addEventListener("dragstart", e => { dragged = e.currentTarget; });

    const column = document.querySelector('.kanban-column[data-status="' + String(statusIndex) + '"]');
    if (column) column.appendChild(task);

    document.getElementById("taskModal").style.display = "none";
  });

  document.getElementById("save-button").addEventListener("click", () => {
    const updated = {};
    const tasks = {};

    document.querySelectorAll(".kanban-column").forEach(col => {
      const status = col.dataset.status;
      col.querySelectorAll(".kanban-task").forEach(task => {
        const tid = task.dataset.id;
        updated[tid] = status;

        const titleEl = task.querySelector("strong");
        const title = titleEl ? titleEl.innerText : "";

        const clone = task.cloneNode(true);
        const strong = clone.querySelector("strong");
        if (strong) strong.remove();
        clone.querySelectorAll("button").forEach(b => b.remove());
        const info = clone.textContent.trim();

        tasks[tid] = { data: { title, info } };
      });
    });

    document.getElementById("updated-process").value = JSON.stringify(updated);
    document.getElementById("all-tasks").value = JSON.stringify(tasks);
  });
});
</script>
