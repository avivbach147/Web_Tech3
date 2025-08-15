<?php
// Bootstrap
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB
include __DIR__ . "/db.php";

// Auth guard (must run before sending any output)
$uid = $_SESSION["uid"] ?? null;
if (!$uid) {
    header("Location: login.php");
    exit;
}

// Page meta and layout
$pageTitle = "לוח קנבן";
include __DIR__ . "/core/header.php";

// Selected product
$selected_pid = $_GET["pid"] ?? null;

// Get only products the logged-in user owns
$owned_products = [];
$products_res = $conn->query("SELECT p_id, p_data FROM product");
while ($row = $products_res->fetch_assoc()) {
    $pdata = json_decode($row["p_data"], true);
    if (isset($pdata["uid"]) && (string)$pdata["uid"] === (string)$uid) {
        $owned_products[] = [
            "p_id"   => (int)$row["p_id"],
            "name"   => $pdata["name"] ?? "",
            "p_data" => $pdata
        ];
    }
}

// Check if selected product is owned by user
$selected_data = null;
foreach ($owned_products as $product) {
    if ((string)$product["p_id"] === (string)$selected_pid) {
        $selected_data = $product["p_data"];
        break;
    }
}

// Precompute next new-* id
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



<div class="container">
  <h2>שלום <?= htmlspecialchars($_SESSION["name"] ?? "", ENT_QUOTES, 'UTF-8') ?> 👋</h2>

  <h2>בחר מוצר</h2>
  <form method="GET">
    <select name="pid" onchange="this.form.submit()">
      <option value="">-- בחר מוצר --</option>
      <?php foreach ($owned_products as $product): ?>
        <option value="<?= (int)$product['p_id'] ?>" <?= ((string)$product['p_id'] === (string)$selected_pid) ? 'selected' : '' ?>>
          <?= htmlspecialchars($product["name"] ?? '', ENT_QUOTES, 'UTF-8') ?> (ID: <?= (int)$product["p_id"] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($selected_data): ?>
    <?php
      $tasks    = $selected_data["kanban"]["tasks"]   ?? [];
      $statuses = $selected_data["kanban"]["status"]  ?? [];
      $process  = $selected_data["kanban"]["process"] ?? [];
    ?>
    <h3>לוח משימות: <?= htmlspecialchars($selected_data["name"] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>

    <form method="POST" action="save_kanban.php">
      <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$selected_pid, ENT_QUOTES, 'UTF-8') ?>">
      <div class="kanban-board">
        <?php foreach ($statuses as $status_index => $status_name): ?>
          <div class="column" ondrop="drop(event)" ondragover="allowDrop(event)" data-status="<?= (int)$status_index ?>">
            <h4><?= htmlspecialchars((string)$status_name, ENT_QUOTES, 'UTF-8') ?></h4>
            <button type="button" onclick="openTaskModal(<?= (int)$status_index ?>)">➕</button>

            <?php foreach ($process as $tid => $s_index): ?>
              <?php if ((string)$s_index === (string)$status_index && isset($tasks[$tid])): ?>
                <?php $t = $tasks[$tid]["data"] ?? ["title"=>"","info"=>""]; ?>
                <div class="task" draggable="true" ondragstart="drag(event)" id="task-<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?>" data-id="<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?>">
                  <strong><?= htmlspecialchars((string)($t["title"] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><br>
                  <?= htmlspecialchars((string)($t["info"] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                  <br><button type="button" onclick="deleteTask('<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?>')">🗑️</button>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <br>
      <input type="hidden" name="updated_process" id="updated-process">
      <input type="hidden" name="all_tasks" id="all-tasks">
      <button type="submit" onclick="prepareSave()">💾 שמור</button>
    </form>

    <!-- Modal -->
    <div id="taskModal">
      <h3>➕ משימה חדשה</h3>
      <form onsubmit="submitNewTask(event)">
        <input type="hidden" id="modalStatusIndex">
        <label>כותרת:</label><br>
        <input type="text" id="modalTitle" required><br>
        <label>תיאור:</label><br>
        <textarea id="modalInfo" required></textarea><br><br>
        <button type="submit">שמור</button>
        <button type="button" onclick="closeTaskModal()">ביטול</button>
      </form>
    </div>

    <script>
    // Drag and drop + modal logic
    let dragged;
    let newTaskId = <?= (int)$maxNewId ?>;

    function allowDrop(ev) { ev.preventDefault(); }
    function drag(ev) { dragged = ev.target; }
    function drop(ev) {
      ev.preventDefault();
      const target = ev.currentTarget;
      if (dragged && target.classList.contains("column")) {
        target.appendChild(dragged);
      }
    }

    function deleteTask(tid) {
      const task = document.querySelector(`#task-${CSS.escape(tid)}`);
      if (task && confirm("האם למחוק משימה זו?")) task.remove();
    }

    function openTaskModal(statusIndex) {
      document.getElementById("modalStatusIndex").value = statusIndex;
      document.getElementById("modalTitle").value = "";
      document.getElementById("modalInfo").value = "";
      document.getElementById("taskModal").style.display = "block";
    }

    function closeTaskModal() {
      document.getElementById("taskModal").style.display = "none";
    }

    function submitNewTask(e) {
      e.preventDefault();
      const title = document.getElementById("modalTitle").value.trim();
      const info = document.getElementById("modalInfo").value.trim();
      const statusIndex = document.getElementById("modalStatusIndex").value;
      if (!title || !info) return;

      const tid = `new-${newTaskId++}`;
      const task = document.createElement("div");
      task.className = "task";
      task.draggable = true;
      task.id = "task-" + tid;
      task.dataset.id = tid;
      task.innerHTML = `
        <strong>${title}</strong><br>${info}
        <br><button type="button" onclick="deleteTask('${tid}')">🗑️</button>
      `;
      task.ondragstart = drag;

      const col = document.querySelector(`.column[data-status="${CSS.escape(statusIndex)}"]`);
      if (col) col.appendChild(task);
      closeTaskModal();
    }

    function prepareSave() {
      const updated = {};
      const tasks = {};
      document.querySelectorAll(".column").forEach(col => {
        const status = col.dataset.status;
        col.querySelectorAll(".task").forEach(task => {
          const tid = task.dataset.id;
          updated[tid] = status;
          const titleEl = task.querySelector("strong");
          const title = titleEl ? titleEl.innerText : "";
          // Extract info text (everything after the title)
          let info = "";
          if (titleEl) {
            const clone = task.cloneNode(true);
            const strong = clone.querySelector("strong");
            if (strong) strong.remove();
            const btn = clone.querySelector("button");
            if (btn) btn.remove();
            info = clone.textContent.trim();
          }
          tasks[tid] = { data: { title, info } };
        });
      });
      document.getElementById("updated-process").value = JSON.stringify(updated);
      document.getElementById("all-tasks").value = JSON.stringify(tasks);
    }
    </script>
  <?php endif; ?>

  <p><a href="product_dashboard.php">⬅ חזרה לעמוד הראשי</a></p>
</div>

<?php include __DIR__ . "/core/footer.php"; ?>
