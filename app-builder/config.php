<?php
/**
 * config.php
 *
 * – הגדרת חיבור למסד הנתונים
 * – פונקציות עזר בסיסיות: קבלת רשימת טבלאות, עמודות, ייצור PDO, וכו’.
 */

declare(strict_types=1);

// =============================
// 1) DATABASE CONFIGURATION
// =============================
define('DB_HOST',   'localhost');
define('DB_NAME',   'your_database_name');
define('DB_USER',   'your_username');
define('DB_PASS',   'your_password');
define('DB_CHARSET','utf8mb4');

// =============================
// 2) BOOTSTRAP 5 CDN LINKS
// =============================
define('BOOTSTRAP_CSS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
define('BOOTSTRAP_JS',  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js');

// =============================
// 3) PDO CONNECTION
// =============================
function getPDO(): \PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $opts = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new \PDO($dsn, DB_USER, DB_PASS, $opts);
    }
    return $pdo;
}

// =============================
// 4) GET ALL TABLE NAMES
// =============================
/**
 * @return string[]  – רשימת שמות הטבלאות במסד הנתונים
 */
function getTables(): array
{
    $pdo = getPDO();
    $stmt = $pdo->query("SHOW TABLES");
    $tables = [];
    while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    return $tables;
}

// =============================
// 5) GET COLUMNS FOR A TABLE
// =============================
/**
 * @param string $table
 * @return array<int,array{Field:string,Type:string,Null:string,Key:string,Default:mixed,Extra:string}>
 */
function getColumns(string $table): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table`");
    $stmt->execute();
    return $stmt->fetchAll();
}

// =============================
// 6) SANITIZE INPUT & BUILD WHERE CLAUSE
// =============================
/**
 * בונה מחרוזת WHERE ל־search across all text/char/varchar columns
 */
function buildSearchWhere(string $table, string $search, array &$params): string
{
    $cols = getColumns($table);
    $clauses = [];
    foreach ($cols as $col) {
        $type = strtolower($col['Type']);
        if (str_starts_with($type, 'varchar') || str_starts_with($type, 'text') || str_starts_with($type, 'char')) {
            $clauses[] = "`{$col['Field']}` LIKE :search";
        }
    }
    if (empty($clauses)) {
        return '';
    }
    $params['search'] = "%$search%";
    return 'WHERE ' . implode(' OR ', $clauses);
}

// =============================
// 7) GET PRIMARY KEY COLUMN (ASSUMES SINGLE-PK)
// =============================
/**
 * מחזיר את שם עמודת המפתח הראשי, אם קיימת
 */
function getPrimaryKey(string $table): ?string
{
    $cols = getColumns($table);
    foreach ($cols as $col) {
        if ($col['Key'] === 'PRI') {
            return $col['Field'];
        }
    }
    return null;
}

// =============================
// 8) ESCAPE HTML (TO PREVENT XSS)
// =============================
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
