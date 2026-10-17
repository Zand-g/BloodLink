<?php
// POST (or DELETE) /api/drive_cancel.php
// Body: { "drive_id": 2 }
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('POST', 'DELETE');
$in = read_json_body();
$errors = [];
$id = f_int($in, 'drive_id', $errors, true, 1, 4294967295);
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
$stmt = $db->prepare('SELECT status FROM drives WHERE drive_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    json_out(['ok' => false, 'message' => 'Drive not found'], 404);
}
if ($row['status'] === 'cancelled') {
    json_out(['ok' => true, 'drive_id' => $id, 'status' => 'cancelled', 'message' => 'Already cancelled']);
}
if ($row['status'] === 'completed') {
    json_out(['ok' => false, 'message' => 'A completed drive cannot be cancelled'], 422);
}

$stmt = $db->prepare("UPDATE drives SET status = 'cancelled' WHERE drive_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

json_out(['ok' => true, 'drive_id' => $id, 'status' => 'cancelled', 'message' => 'Cancelled']);
