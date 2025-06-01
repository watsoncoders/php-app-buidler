<?php
/**
 * admin.php
 *
 * – ממשק ניהול CRUD אוטומטי ל־all tables in DB_NAME
 * – ניתן לבחור טבלה, לצפות בשורות, להוסיף/לשנות/למחוק.
 * – משתמש ב־Bootstrap 5 לממשק מודרני.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

// ───────────────────────────────────────────────────────────────
// 1) Process incoming POST/GET for CRUD operations
// ───────────────────────────────────────────────────────────────

$pdo = getPDO();
$tables = getTables();

$currentTable = $_GET['table'] ?? null;
$action       = $_GET['action'] ?? null;

// ביצוע הוספת רשומה (CREATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__crud_action']) && $_POST['__crud_action'] === 'create') {
    $tbl = $_POST['__table'];
    $cols = getColumns($tbl);
    $fields = [];
    $placeholders = [];
    $params = [];
    foreach ($cols as $col) {
        $field = $col['Field'];
        if ($col['Key'] === 'PRI' && $col['Extra'] === 'auto_increment') {
            continue;
        }
        if (array_key_exists($field, $_POST)) {
            $fields[] = "`$field`";
            $placeholders[] = ":$field";
            $params[$field] = $_POST[$field];
        }
    }
    $sql = "INSERT INTO `$tbl` (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    header("Location: ?table=$tbl");
    exit;
}

// ביצוע עריכת רשומה (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__crud_action']) && $_POST['__crud_action'] === 'update') {
    $tbl = $_POST['__table'];
    $pk  = getPrimaryKey($tbl);
    $id  = $_POST[$pk];
    $cols = getColumns($tbl);
    $sets = [];
    $params = [];
    foreach ($cols as $col) {
        $field = $col['Field'];
        if ($field === $pk) {
            continue;
        }
        if (array_key_exists($field, $_POST)) {
            $sets[] = "`$field` = :$field";
            $params[$field] = $_POST[$field];
        }
    }
    $params['pk'] = $id;
    $sql = "UPDATE `$tbl` SET " . implode(',', $sets) . " WHERE `$pk` = :pk";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    header("Location: ?table=$tbl");
    exit;
}

// ביצוע מחיקת רשומה (DELETE)
if (isset($_GET['action'], $_GET['table'], $_GET['id']) && $_GET['action'] === 'delete') {
    $tbl = $_GET['table'];
    $pk  = getPrimaryKey($tbl);
    $sql = "DELETE FROM `$tbl` WHERE `$pk` = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $_GET['id']]);
    header("Location: ?table=$tbl");
    exit;
}

// ───────────────────────────────────────────────────────────────
// 2) HEADER + BOOTSTRAP INCLUDES
// ───────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin → PHP App Builder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BOOTSTRAP_CSS; ?>">
</head>
<body class="bg-light">

<div class="container py-4">
    <h1 class="mb-4">Admin Panel / CRUD Generator</h1>

    <!-- 2.1) Navigation: List of Tables -->
    <nav class="mb-4">
        <ul class="nav nav-pills">
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

    <!-- 2.2) If no table selected → show instructions -->
    <?php if (!$currentTable): ?>
        <div class="alert alert-info">
            Select a table above to manage its records.
        </div>
    <?php else: ?>
        <!-- 2.3) Display CRUD interface for $currentTable -->
        <?php
        $cols = getColumns($currentTable);
        $pk   = getPrimaryKey($currentTable);
        // Pagination params (optional/very basic)
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        // (A) Count total records
        $stmtTotal = $pdo->query("SELECT COUNT(*) FROM `$currentTable`");
        $totalRecords = (int)$stmtTotal->fetchColumn();
        $totalPages = (int)ceil($totalRecords / $perPage);

        // (B) Fetch current page rows
        $stmtRows = $pdo->prepare("SELECT * FROM `$currentTable` LIMIT :offset, :limit");
        $stmtRows->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmtRows->bindValue('limit',  $perPage, \PDO::PARAM_INT);
        $stmtRows->execute();
        $rows = $stmtRows->fetchAll();
        ?>

        <!-- 2.3.1) “Add New” Button triggers a modal form -->
        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#createModal">
            + Add New Record
        </button>

        <!-- 2.3.2) Records Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($cols as $col): ?>
                            <th><?php echo h($col['Field']); ?></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($cols as $col): ?>
                                <td><?php echo h((string)$row[$col['Field']]); ?></td>
                            <?php endforeach; ?>
                            <td>
                                <!-- Edit button opens modal with record data -->
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#editModal-<?php echo h($row[$pk]); ?>">
                                    Edit
                                </button>
                                <!-- Delete link -->
                                <a class="btn btn-sm btn-danger"
                                   href="?table=<?php echo h($currentTable); ?>&action=delete&id=<?php echo h((string)$row[$pk]); ?>"
                                   onclick="return confirm('Are you sure you want to delete this record?');">
                                    Delete
                                </a>
                            </td>
                        </tr>

                        <!-- 2.3.3) Edit Modal for this row -->
                        <div class="modal fade" id="editModal-<?php echo h($row[$pk]); ?>" tabindex="-1">
                          <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                              <form method="POST" action="?table=<?php echo h($currentTable); ?>">
                                <div class="modal-header">
                                  <h5 class="modal-title">Edit Record #<?php echo h($row[$pk]); ?></h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                  <?php foreach ($cols as $col): 
                                    $field = $col['Field'];
                                    $val   = $row[$field];
                                    $readonly = ($field === $pk) ? 'readonly' : '';
                                  ?>
                                    <div class="mb-3">
                                      <label class="form-label"><?php echo h($field); ?></label>
                                      <input type="text"
                                             name="<?php echo h($field); ?>"
                                             value="<?php echo h((string)$val); ?>"
                                             class="form-control"
                                             <?php echo $readonly; ?>>
                                    </div>
                                  <?php endforeach; ?>
                                  <input type="hidden" name="__crud_action" value="update">
                                  <input type="hidden" name="__table" value="<?php echo h($currentTable); ?>">
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                  <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 2.3.4) Pagination Controls -->
        <?php if ($totalPages > 1): ?>
        <nav>
          <ul class="pagination">
            <?php for($p = 1; $p <= $totalPages; $p++): ?>
              <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                <a class="page-link"
                   href="?table=<?php echo h($currentTable); ?>&page=<?php echo $p; ?>">
                   <?php echo $p; ?>
                </a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
        <?php endif; ?>

        <!-- 2.3.5) Create Modal -->
        <div class="modal fade" id="createModal" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="POST" action="?table=<?php echo h($currentTable); ?>">
                <div class="modal-header">
                  <h5 class="modal-title">Add New Record to <?php echo h($currentTable); ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <?php foreach ($cols as $col): 
                    $field = $col['Field'];
                    // לא מציג עמודת PK אם היא auto-increment
                    if ($col['Key'] === 'PRI' && $col['Extra'] === 'auto_increment') {
                      continue;
                    }
                  ?>
                    <div class="mb-3">
                      <label class="form-label"><?php echo h($field); ?></label>
                      <input type="text"
                             name="<?php echo h($field); ?>"
                             class="form-control"
                             value="">
                    </div>
                  <?php endforeach; ?>
                  <input type="hidden" name="__crud_action" value="create">
                  <input type="hidden" name="__table"       value="<?php echo h($currentTable); ?>">
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-success">Create</button>
                </div>
              </form>
            </div>
          </div>
        </div>

    <?php endif; ?>
</div>

<!-- Include Bootstrap JS -->
<script src="<?php echo BOOTSTRAP_JS; ?>"></script>
</body>
</html>
