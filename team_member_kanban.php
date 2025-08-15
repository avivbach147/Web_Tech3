<?php
session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Guard: team member only (role = 4)
if (!isset($_SESSION["uid"]) || ($_SESSION["role"] ?? null) != 4) {
    header("Location: login.php");
    exit;
}

$uid = (int)($_SESSION["uid"] ?? 0);
$username = htmlspecialchars($_SESSION["name"] ?? "משתמש", ENT_QUOTES, 'UTF-8');

// Load member kanban
$res = $conn->query("SELECT m_id, m_data FROM member WHERE m_id = $uid");
if (!$res || $res->num_rows === 0) {
    $pageTitle = "לוח משימות אישי";
    include __DIR__ . '/core/header.php';
    echo '<div class="container"><h3>לא נמצא מידע עבור המשתמש.</h3></div>';
    include __DIR__ . '/core/footer.php';
    exit;
}

$row    = $res->fetch_assoc();
$m_id   = (int)$row["m_id"];
$m_data = json_decode($row["m_data"], true) ?: [];
$kanban   = $m_data["kanban"]  ?? [];
$tasks    = $kanban["tasks"]   ?? [];
$statuses = $kanban["status"]  ?? [];
$process  = $kanban["process"] ?? [];

// calculate next new-* id like in the example
$maxNewId = 10000;
foreach (array_keys($tasks) as $tid) {
    if (preg_match('/^new-(\d+)$/', (string)$tid, $m)) {
        $num = (int)$m[1];
        if ($num >= $maxNewId) $maxNewId = $num + 1;
    }
}

$pageTitle = "לוח משימות אישי";
include __DIR__ . '/core/header.php';
?>

<div class="container" id="kanban-page">
  <h2>שלום <?= $username ?> 👋</h2>
  <p><a class="register-link" href="update_member_info.php">עדכון פרטי משתמש</a></p>
</div>

<div id="kanban-container">
  <h3>לוח משימות אישי</h3>

  <form method="POST" action="save_member_kanban.php">
    <input type="hidden" name="mid" value="<?= (int)$m_id ?>">

    <div class="kanban-board">
      <?php foreach ($statuses as $status_index => $status_name): ?>
        <div class="kanban-column" data-status="<?= (int)$status_index ?>">
          <div class="kanban-column__header">
            <h4><?= htmlspecialchars((string)$status_name, ENT_QUOTES, 'UTF-8') ?></h4>
            <button type="button" class="btn btn-light btn-sm add-task" data-status="<?= (int)$status_index ?>">➕</button>
          </div>

          <?php foreach ($process as $tid => $s_index): ?>
            <?php if ((string)$s_index === (string)$status_index && isset($tasks[$tid])): ?>
              <?php $t = $tasks[$tid]["data"] ?? ["title" => "", "info" => ""]; ?>
              <div
                class="kanban-task"
                draggable="true"
                id="task-<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?>"
                data-id="<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?>"
              >
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
    <button type="submit" class="btn btn-primary" id="save-button" style="margin-top:20px;">💾 שמור</button>
  </form>
</div>

<!-- Modal (same structure/ids/classes as the example) -->
<div id="taskModal">
  <form id="modalForm" class="modal-card">
    <h3>➕ משימה חדשה</h3>
    <input type="hidden" id="modalStatusIndex">
    <label>כותרת:</label><br>
    <input type="text" id="modalTitle" required><br>
    <label>תיאור:</label><br>
    <textarea id="modalInfo" required></textarea><br><br>
    <div style="text-align:center;">
      <button type="submit" class="btn btn-primary" >שמור</button>
      <button type="button" class="btn btn-light" id="closeModal">ביטול</button>
    </div>
  </form>
</div>

<p><a href="scrum_dashboard.php" class="register-link" style="margin-top:30px;">⬅ חזרה לעמוד הראשי</a></p>

<?php include __DIR__ . '/core/footer.php'; ?>

<!-- JavaScript (aligned with the example’s behavior) -->
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
  // Drag & drop handlers identical to example
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

  // Add task (matches example: .add-task button opens #taskModal)
  document.querySelectorAll(".add-task").forEach(btn => {
    btn.addEventListener("click", () => {
      document.getElementById("modalStatusIndex").value = btn.dataset.status;
      document.getElementById("modalTitle").value = "";
      document.getElementById("modalInfo").value = "";
      document.getElementById("taskModal").style.display = "block";
      setTimeout(() => document.getElementById("modalTitle").focus(), 100);
    });
  });

  // Close modal
  document.getElementById("closeModal").addEventListener("click", () => {
    document.getElementById("taskModal").style.display = "none";
  });

  // Submit modal -> create task card
  document.getElementById("modalForm").addEventListener("submit", e => {
    e.preventDefault();
    const title = document.getElementById("modalTitle").value.trim();
    const info  = document.getElementById("modalInfo").value.trim();
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

  // Prepare payload on save (same ids/logic as example)
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
