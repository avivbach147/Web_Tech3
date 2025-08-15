<?php
/* scrum_manage.php, full file */

session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* Guard, Scrum Masters only, role = 2 */
if (!isset($_SESSION['uid']) || (int)($_SESSION['role'] ?? 0) !== 2) {
    header("Location: login.php");
    exit;
}

/* Helpers */
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function flash_add($type, $title, $table = null) { $_SESSION['flash'][] = ['type'=>$type,'title'=>$title,'table'=>$table]; }

/* Load products map, id => name */
$products = [];
$res = $conn->query("SELECT p_id, p_data FROM product ORDER BY p_id ASC");
while ($row = $res->fetch_assoc()) {
    $p = json_decode($row['p_data'], true) ?: [];
    $products[(int)$row['p_id']] = $p['name'] ?? ('Product #'.$row['p_id']);
}

/* Handle POST actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mid = isset($_POST['mid']) ? (int)$_POST['mid'] : 0;
    $pid = isset($_POST['p_id']) ? (int)$_POST['p_id'] : 0;

    $member_row = $conn->query("SELECT uid, name FROM user WHERE uid = {$mid}")->fetch_assoc();
    $member_name = $member_row['name'] ?? ('User #'.$mid);
    $product_name = $products[$pid] ?? ('Product #'.$pid);

    if ($action === 'assign' && $mid && $pid) {
        /* Server side duplicate guard */
        $exists = $conn->query("SELECT 1 FROM p_m WHERE m_id={$mid} AND p_id={$pid} LIMIT 1")->num_rows > 0;
        if ($exists) {
            flash_add('danger', 'המשתמש כבר משויך למוצר', [
                'headers' => ['מזהה משתמש','שם משתמש','מזהה מוצר','שם מוצר'],
                'rows'    => [[ $mid, esc($member_name), $pid, esc($product_name) ]]
            ]);
        } else {
            $stmt = $conn->prepare("INSERT INTO p_m (m_id, p_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $mid, $pid);
            $stmt->execute();
            $stmt->close();
            flash_add('success', 'בוצע שיוך למוצר', [
                'headers' => ['מזהה משתמש','שם משתמש','מזהה מוצר','שם מוצר'],
                'rows'    => [[ $mid, esc($member_name), $pid, esc($product_name) ]]
            ]);
        }
    }

    if ($action === 'unassign' && $mid && $pid) {
        $stmt = $conn->prepare("DELETE FROM p_m WHERE m_id = ? AND p_id = ?");
        $stmt->bind_param("ii", $mid, $pid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected > 0) {
            flash_add('success', 'הוסרה שייכות מהמוצר', [
                'headers' => ['מזהה משתמש','שם משתמש','מזהה מוצר','שם מוצר'],
                'rows'    => [[ $mid, esc($member_name), $pid, esc($product_name) ]]
            ]);
        } else {
            flash_add('danger', 'לא קיימת שייכות להסרה', [
                'headers' => ['מזהה משתמש','שם משתמש','מזהה מוצר','שם מוצר'],
                'rows'    => [[ $mid, esc($member_name), $pid, esc($product_name) ]]
            ]);
        }
    }

    if ($action === 'update_info' && $mid) {
        /* Read current JSON, keep s_id if exists */
        $res = $conn->query("SELECT m_data, s_id FROM member WHERE m_id = {$mid} LIMIT 1");
        $current = []; $sid = 1;
        if ($row = $res->fetch_assoc()) {
            $current = json_decode($row['m_data'], true) ?: [];
            if (!empty($row['s_id'])) { $sid = (int)$row['s_id']; }
        }

        /* Build new JSON from POST */
        $new = $current;
        $new['email'] = $_POST['email'] ?? ($current['email'] ?? '');
        $new['role']  = $_POST['team_role'] ?? ($current['role'] ?? '');
        $skills_raw   = trim($_POST['skills'] ?? '');
        $new['skills'] = $skills_raw !== '' ? array_values(array_filter(array_map('trim', explode(',', $skills_raw)), fn($x)=>$x!=='')) : ($current['skills'] ?? []);

        /* Prepare diff for feedback */
        $changes = [];
        $map = ['email'=>'אימייל','role'=>'תפקיד בצוות','skills'=>'מיומנויות'];
        foreach ($map as $k => $label) {
            $oldVal = $current[$k] ?? '';
            $newVal = $new[$k] ?? '';
            if ($k === 'skills') {
                $oldVal = is_array($oldVal) ? implode(', ', $oldVal) : (string)$oldVal;
                $newVal = is_array($newVal) ? implode(', ', $newVal) : (string)$newVal;
            }
            if ((string)$oldVal !== (string)$newVal) {
                $changes[] = [$label, esc($oldVal), esc($newVal)];
            }
        }

        $json = json_encode($new, JSON_UNESCAPED_UNICODE) ?: '{}';

        /* Upsert member row */
        $stmtUp = $conn->prepare("
            INSERT INTO member (m_id, s_id, m_data)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
              s_id = VALUES(s_id),
              m_data = VALUES(m_data)
        ");
        if (!$stmtUp) { die('Prepare failed: ' . $conn->error); }
        $stmtUp->bind_param("iis", $mid, $sid, $json);
        $stmtUp->execute();
        $stmtUp->close();

        if (!empty($changes)) {
            flash_add('success', 'פרטי המשתמש עודכנו', [
                'headers' => ['שדה','לפני','אחרי'],
                'rows'    => $changes
            ]);
        } else {
            flash_add('success', 'לא בוצעו שינויים בפרטי המשתמש');
        }
    }

    header("Location: scrum_manage.php");
    exit;
}

/* Load team members, role 4 only */
$teamMembers = [];
$res = $conn->query("SELECT uid, name FROM user WHERE role = 4 ORDER BY uid ASC");
while ($row = $res->fetch_assoc()) {
    $teamMembers[] = ['id'=>(int)$row['uid'], 'name'=>$row['name']];
}

/* Load assignments, map m_id => [p_id...] */
$assignedProducts = [];
$res = $conn->query("SELECT m_id, p_id FROM p_m ORDER BY m_id, p_id");
while ($row = $res->fetch_assoc()) {
    $assignedProducts[(int)$row['m_id']][] = (int)$row['p_id'];
}

/* Load member extra info */
$memberInfo = [];
$res = $conn->query("SELECT m_id, m_data FROM member");
while ($row = $res->fetch_assoc()) {
    $m = json_decode($row['m_data'], true) ?: [];
    $memberInfo[(int)$row['m_id']] = [
        'email'  => $m['email'] ?? '',
        'role'   => $m['role'] ?? '',
        'skills' => $m['skills'] ?? []
    ];
}

// Page meta and layout
$pageTitle = "ניהול צוות סקרם";

include __DIR__ . '/core/header.php';
$username = esc($_SESSION['name'] ?? 'משתמש');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<title>ניהול צוות סקרם</title>

<style>
/* Button fallback, low specificity */
.btn{padding:.5rem .8rem;border:1px solid var(--border-color,#d0d7e2);border-radius:10px;background:#fff;cursor:pointer}
.btn-sm{padding:.35rem .6rem;font-size:.95rem}
.btn-primary{background:var(--primary,#3a6ea5);border-color:var(--primary,#3a6ea5);color:#fff}
.btn-secondary{background:var(--light-bg,#f5f7fa)}
.btn-danger{background:var(--danger,#e74c3c);border-color:var(--danger,#e74c3c);color:#fff}

/* Modal styles */
.modal.hidden{display:none}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:1000}
.modal__dialog{background:#fff;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.12);width:min(720px,96vw);padding:16px 18px}
.modal__header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.modal__close{background:transparent;border:0;font-size:18px;cursor:pointer}
.modal__body{max-height:70vh;overflow:auto}
.modal__section{border:1px solid var(--border-color,#d0d7e2);border-radius:8px;padding:12px;margin-top:10px}
.form-row{display:grid;grid-template-columns:140px 1fr;gap:8px;align-items:center;margin-bottom:8px}

/* Table */
.table--responsive{width:100%;border-collapse:collapse;border-spacing:0}
.table--responsive th,.table--responsive td{border:1px solid var(--border-color,#d0d7e2);padding:10px;vertical-align:middle}
.table--compact th,.table--compact td{padding:8px}
.table--sticky thead th{position:sticky;top:0;z-index:3}

/* Visible header styling */
.table--responsive thead th{
  background: var(--primary,#3a6ea5);
  color: var(--white,#ffffff);
  font-weight:600;
  text-align:center;
  padding:12px 10px;
  border-bottom:1px solid var(--border-color,#d0d7e2);
}
.table--responsive thead{display:table-header-group}

/* Cells */
.table--responsive tbody td{
  background: var(--white,#ffffff);
  color: var(--dark-text,#2c3e50);
}
.nowrap{white-space:nowrap}
.hide-sm{display:table-cell}
@media(max-width:768px){.hide-sm{display:none}.form-row{grid-template-columns:1fr}}

/* Chips */
.chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{padding:4px 8px;border-radius:999px;background:var(--light-bg,#f5f7fa);border:1px solid var(--border-color,#d0d7e2);font-size:.9em}

/* Alerts */
.alert{padding:10px 12px;border-radius:8px;margin:8px 0}
.alert-success{background:#ecf9f0;border:1px solid #b8e6c6}
.alert-danger{background:#ffecec;border:1px solid #ffc7c7}
</style>
</head>
<body>

<div class="container">
  <h2>שלום <?= $username ?> 👋</h2>
  <h3>ניהול צוות סקרם</h3>

  <?php if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $msg): ?>
      <div class="alert <?= ($msg['type']==='danger' ? 'alert-danger' : 'alert-success') ?>"><?= esc($msg['title']) ?></div>
      <?php if (!empty($msg['table']['rows'])): ?>
        <table class="table--responsive table--compact">
          <?php if (!empty($msg['table']['headers'])): ?>
            <thead><tr>
              <?php foreach ($msg['table']['headers'] as $h): ?>
                <th scope="col"><?= esc($h) ?></th>
              <?php endforeach; ?>
            </tr></thead>
          <?php endif; ?>
          <tbody>
            <?php foreach ($msg['table']['rows'] as $r): ?>
              <tr><?php foreach ($r as $cell): ?><td><?= is_string($cell) ? $cell : esc($cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endforeach; unset($_SESSION['flash']); ?>
  <?php endif; ?>

  <table class="table--responsive table--sticky table--compact scrum-table">
    <thead>
      <tr>
        <th class="nowrap" scope="col">מזהה</th>
        <th scope="col">שם מלא</th>
        <th class="hide-sm" scope="col">אימייל</th>
        <th scope="col">תפקיד בצוות</th>
        <th class="hide-sm" scope="col">מיומנויות</th>
        <th scope="col">מוצרים מוקצים</th>
        <th scope="col">פעולות</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($teamMembers as $member):
        $mid = $member['id']; $name = $member['name'];
        $info = $memberInfo[$mid] ?? ['email'=>'','role'=>'','skills'=>[]];
        $email = $info['email'] ?? ''; $role = $info['role'] ?? '';
        $skills_arr = is_array($info['skills']) ? $info['skills'] : [];
        $skills_text = implode(', ', $skills_arr);
        $assigned = $assignedProducts[$mid] ?? [];
    ?>
      <tr class="member-row"
          data-mid="<?= $mid ?>"
          data-name="<?= esc($name) ?>"
          data-email="<?= esc($email) ?>"
          data-role="<?= esc($role) ?>"
          data-skills="<?= esc($skills_text) ?>"
          data-assigned='<?= esc(json_encode(array_values($assigned), JSON_UNESCAPED_UNICODE)) ?>'>
        <td class="nowrap"><?= $mid ?></td>
        <td><?= esc($name) ?></td>
        <td class="hide-sm"><?= esc($email) ?></td>
        <td><?= esc($role) ?></td>
        <td class="hide-sm"><?= esc($skills_text) ?></td>
        <td>
        <?php if ($assigned): ?>
            <div class="chips">
            <?php foreach ($assigned as $pid): ?>
                <div class="hide-sm"><?= esc($products[$pid] ?? ('Product #'.$pid)) ?> </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <em>אין הקצאות</em>
        <?php endif; ?>
        </td>

        <td><button type="button" class="btn btn-secondary btn-sm" onclick="openEditModal(this)">עריכה</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal hidden" aria-hidden="true">
  <div class="modal__dialog" role="dialog" aria-modal="true">
    <div class="modal__header">
      <h3 id="modalTitle">עריכת משתמש</h3>
      <button type="button" class="modal__close" onclick="closeEditModal()">✕</button>
    </div>

    <div class="modal__body">
      <!-- Update info -->
      <form id="formUpdate" method="POST" class="modal__section">
        <input type="hidden" name="action" value="update_info">
        <input type="hidden" name="mid" id="upd_mid" value="">
        <div class="form-row">
          <label>אימייל</label>
          <input type="email" name="email" id="upd_email" value="">
        </div>
        <div class="form-row">
          <label>תפקיד בצוות</label>
          <input type="text" name="team_role" id="upd_role" value="">
        </div>
        <div class="form-row">
          <label>מיומנויות</label>
          <input type="text" name="skills" id="upd_skills" placeholder="skill1, skill2">
        </div>
        <button type="submit" class="btn btn-primary">שמירת פרטים</button>
      </form>

      <!-- Assign product -->
      <form id="formAssign" method="POST" class="modal__section">
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="mid" id="asn_mid" value="">
        <div class="form-row">
          <label>שיוך למוצר</label>
          <select name="p_id" id="asn_pid">
            <option value="">בחר מוצר…</option>
            <?php foreach ($products as $pid => $pname): ?>
              <option value="<?= (int)$pid ?>"><?= esc($pname) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">שייך</button>
      </form>

      <!-- Unassign product -->
      <form id="formUnassign" method="POST" class="modal__section">
        <input type="hidden" name="action" value="unassign">
        <input type="hidden" name="mid" id="unasn_mid" value="">
        <div class="form-row">
          <label>הסרה ממוצר</label>
          <select name="p_id" id="unasn_pid">
            <option value="">בחר מוצר…</option>
          </select>
        </div>
        <button type="submit" class="btn btn-danger">הסר שיוך</button>
      </form>
    </div>
  </div>
</div>

<p><a href="scrum_dashboard.php" class="register-link" style="margin-top:30px;">⬅ חזרה לעמוד הראשי</a></p>

<script>
/* Expose product labels for unassign select */
window.__PRODUCTS_LABELS__ = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;

/* Open modal and populate fields */
function openEditModal(btn){
  const tr = btn.closest('tr.member-row'); if(!tr) return;
  const modal = document.getElementById('editModal');
  modal.classList.remove('hidden'); modal.classList.add('modal');

  const mid = tr.dataset.mid || '';
  const name = tr.dataset.name || '';
  const email = tr.dataset.email || '';
  const role = tr.dataset.role || '';
  const skills = tr.dataset.skills || '';
  let assigned = [];
  try { assigned = JSON.parse(tr.dataset.assigned || '[]'); } catch(e) { assigned = []; }

  document.getElementById('modalTitle').textContent = `עריכת משתמש: ${name}`;

  document.getElementById('upd_mid').value = mid;
  document.getElementById('upd_email').value = email;
  document.getElementById('upd_role').value = role;
  document.getElementById('upd_skills').value = skills;

  document.getElementById('asn_mid').value = mid;
  document.getElementById('asn_pid').value = '';

  document.getElementById('unasn_mid').value = mid;
  const unasnSel = document.getElementById('unasn_pid');
  while (unasnSel.options.length > 1) unasnSel.remove(1);
  assigned.forEach(function(pid){
    const opt = document.createElement('option');
    opt.value = String(pid);
    opt.text = window.__PRODUCTS_LABELS__?.[pid] || `Product #${pid}`;
    unasnSel.add(opt);
  });

  modal.dataset.assignedSet = JSON.stringify(assigned.reduce((acc, cur) => { acc[String(cur)] = true; return acc; }, {}));
}

/* Close modal */
function closeEditModal(){
  const modal = document.getElementById('editModal');
  modal.classList.add('hidden'); modal.classList.remove('modal');
}

/* Duplicate guard on assignment */
document.getElementById('formAssign').addEventListener('submit', function(ev){
  const modal = document.getElementById('editModal');
  let assignedSet = {};
  try { assignedSet = JSON.parse(modal.dataset.assignedSet || '{}'); } catch(e) { assignedSet = {}; }
  const sel = document.getElementById('asn_pid');
  const pid = sel.value;
  if (!pid) { ev.preventDefault(); alert('בחרי מוצר לשיוך'); return; }
  if (assignedSet[pid]) { ev.preventDefault(); alert('העובד כבר משויך למוצר הזה'); return; }
});

/* ESC key and click outside close */
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeEditModal(); });
document.getElementById('editModal').addEventListener('click', function(e){ if (e.target.id === 'editModal') closeEditModal(); });
</script>

<?php include __DIR__ . '/core/footer.php'; ?>
</body>
</html>
