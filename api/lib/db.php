<?php
require_once __DIR__ . '/../config.php';

// One shared connection. Errors become exceptions (mysqli_sql_exception)
// so a failed query can never be silently ignored.
function db(): mysqli
{
    static $db = null;
    if ($db === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $db->set_charset('utf8mb4');
        $db->query("SET time_zone = '+08:00'");   // matches Asia/Manila
    }
    return $db;
}

// True if the query returns at least one row. Always use ? placeholders for $params.
function row_exists(mysqli $db, string $sql, string $types, ...$params): bool
{
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}
