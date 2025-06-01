<?php
/**
 * frontend.php
 *
 * – ממשק Frontend דינמי להצגת רשומות הציבוריות.
 * – בחירת טבלה דרך GET param, הצגת רשומות ב־Bootstrap Card או טבלה עבור כל מבקר.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

// List of tables (allow public listing, ללא עריכה)
$tables = getTables();
$currentTable = $_GET['table'] ?? null;
$view = $_GET['view'] ?? null;  // אם נרצה להציג שורה בודדת

$pdo = getPDO();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Frontend → PHP App Builder</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?php echo BOOTSTRAP_CSS; ?>">
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="mb-4">Public Listing</h1>

  <!-- 1) Navigation: List of Tables -->
  <nav class="mb-4">
    <ul class="nav nav-tabs">
      <?php foreach ($tables as $tbl): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $tbl === $currentTable ? 'active' : ''; ?>"
             href="?table=<?php echo h($tbl); ?>">
             <?php echo h($tbl); ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <?php if (!$currentTable): ?>
    <div class="alert alert-info">Select a table above to browse its records.</div>
  <?php else: 
    $pk = getPrimaryKey($currentTable);
    if ($view !== null && $pk !== null) {
      // 1A) Show single record
      $stmt = $pdo->prepare("SELECT * FROM `$currentTable` WHERE `$pk` = :id LIMIT 1");
      $stmt->execute(['id' => $view]);
      $row = $stmt->fetch();
      if (!$row) {
        echo '<div class="alert alert-danger">Record not found.</div>';
      } else {
        echo '<div class="card">';
        echo '<div class="card-header">Record Details (#' . h((string)$view) . ')</div>';
        echo '<ul class="list-group list-group-flush">';
        foreach ($row as $col => $val) {
          echo '<li class="list-group-item"><strong>' . h($col) . ':</strong> ' . h((string)$val) . '</li>';
        }
        echo '</ul></div>';
        echo '<a href="?table=' . h($currentTable) . '" class="btn btn-primary mt-3">Back to List</a>';
      }
    } else {
      // 1B) List all records for $currentTable
      $stmtRows = $pdo->query("SELECT * FROM `$currentTable`");
      $allRows = $stmtRows->fetchAll();
      if (empty($allRows)) {
        echo '<div class="alert alert-warning">No records found in this table.</div>';
      } else {
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-hover">';
        echo '<thead><tr>';
        foreach (array_keys($allRows[0]) as $colName) {
          echo '<th>' . h($colName) . '</th>';
        }
        echo '<th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($allRows as $row) {
          echo '<tr>';
          foreach ($row as $val) {
            echo '<td>' . h((string)$val) . '</td>';
          }
          if ($pk !== null) {
            $id = h((string)$row[$pk]);
            echo '<td><a class="btn btn-sm btn-outline-primary" href="?table='
                 . h($currentTable) . '&view=' . $id . '">View</a></td>';
          } else {
            echo '<td><em>No PK</em></td>';
          }
          echo '</tr>';
        }
        echo '</tbody></table></div>';
      }
    }
  endif; ?>

</div>
<script src="<?php echo BOOTSTRAP_JS; ?>"></script>
</body>
</html>
