<?php
// Bootstrap
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Page meta and layout
$pageTitle = "Product Tasks";
include __DIR__ . '/core/header.php';

// DB connection
include __DIR__ . '/db.php';

// Fetch product list
$products_result = $conn->query("SELECT p_id, p_data FROM product");
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $p_data = json_decode($row['p_data'], true);
    $products[] = [
        'p_id' => (int)$row['p_id'],
        'name' => $p_data['name'] ?? 'לא ידוע'
    ];
}

$selected_product_id = isset($_GET['p_id']) ? (int)$_GET['p_id'] : null;
?>

<style>
/* Inline styles kept for this page; move to a CSS file if preferred */
.kanban-board {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}
.kanban-column {
    flex: 1;
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 10px;
    background: #f9f9f9;
}
.kanban-column h3 {
    text-align: center;
}
.task-card {
    border: 1px solid #999;
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 10px;
    background: white;
}
</style>

<div class="container">
  <h2>📋 לוח קנבן עבור משימות מוצר</h2>

  <form method="GET">
      <label>בחר מוצר:</label>
      <select name="p_id" onchange="this.form.submit()">
          <option value="">-- בחר --</option>
          <?php foreach ($products as $product): ?>
              <option value="<?= (int)$product['p_id'] ?>" <?= $selected_product_id === (int)$product['p_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> (ID: <?= (int)$product['p_id'] ?>)
              </option>
          <?php endforeach; ?>
      </select>
  </form>

  <?php
  // If a product is selected, render its kanban
  if ($selected_product_id !== null) {
      $stmt = $conn->prepare("SELECT p_data FROM product WHERE p_id = ?");
      $stmt->bind_param("i", $selected_product_id);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($row = $result->fetch_assoc()) {
          $p_data = json_decode($row['p_data'], true) ?: [];
          $product_name = htmlspecialchars($p_data['name'] ?? 'לא ידוע', ENT_QUOTES, 'UTF-8');
          $tasks    = $p_data['kanban']['tasks']   ?? [];
          $statuses = $p_data['kanban']['status']  ?? [];
          $process  = $p_data['kanban']['process'] ?? [];

          // Build columns by status index
          $columns = [];
          foreach ($statuses as $index => $status_label) {
              $columns[$index] = [
                  'name' => $status_label,
                  'tasks' => []
              ];
          }

          // Distribute tasks into columns
          foreach ($tasks as $tid => $task_data) {
              $status_index = $process[$tid] ?? null;
              if ($status_index !== null && isset($columns[$status_index])) {
                  $columns[$status_index]['tasks'][] = [
                      'tid'   => $tid,
                      'title' => $task_data['data']['title'] ?? '',
                      'info'  => $task_data['data']['info']  ?? ''
                  ];
              }
          }
          ?>
          <h3>🔧 <?= $product_name ?> - לוח משימות</h3>
          <div class="kanban-board">
            <?php foreach ($columns as $col): ?>
              <div class="kanban-column">
                <h3><?= htmlspecialchars($col['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if (!empty($col['tasks'])): ?>
                  <?php foreach ($col['tasks'] as $task): ?>
                    <div class="task-card">
                      <strong><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                      <small><?= htmlspecialchars($task['info'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="task-card"><small>אין משימות בעמודה זו</small></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php
      } else {
          echo "<p>⚠️ מוצר לא נמצא</p>";
      }
      $stmt->close();
  }
  ?>
</div>

<?php include __DIR__ . '/core/footer.php'; ?>
